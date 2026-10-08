<?php
require_once __DIR__ . '/dbconfig.php';

$channels = ['Viber', 'Telegram', 'In-Store', 'Website'];

if (isset($_GET['detail'])) {
  $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
  $st->execute([(int)$_GET['detail']]);
  $order = $st->fetch();
  header('Content-Type: application/json; charset=utf-8');
  if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
  }
  $it = $pdo->prepare('SELECT oi.*, p.name_en, p.name_mm, p.description_en FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id');
  $it->execute([$order['id']]);
  $order['items'] = $it->fetchAll();
  $order['status_html'] = status_badge($order['status']);
  $order['channel_html'] = channel_badge($order['channel']);
  echo json_encode($order, JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'status') {
    $next = ['Pending' => 'Completed', 'Completed' => 'Cancelled', 'Cancelled' => 'Pending'];
    $st = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $st->execute([(int)$_POST['order_id']]);
    $cur = $st->fetchColumn();
    if ($cur) {
      $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$next[$cur], (int)$_POST['order_id']]);
      flash("Order status changed: $cur → {$next[$cur]}");
    }
    redirect('orders.php');
  }

  if ($action === 'create' || $action === 'update') {
    $isEdit = $action === 'update';
    try {
      $oid = $isEdit ? (int)($_POST['order_id'] ?? 0) : 0;
      $channel = in_array($_POST['channel'] ?? '', $channels, true) ? $_POST['channel'] : 'In-Store';
      $pids = $_POST['product_id'] ?? [];
      $qtys = $_POST['qty'] ?? [];

      $lines = [];
      foreach ($pids as $i => $pid) {
        $q = (int)($qtys[$i] ?? 0);
        if ($pid !== '' && $q > 0) {
          $lines[$pid] = ($lines[$pid] ?? 0) + $q;
        }
      }
      if (!$lines) {
        throw new RuntimeException('Add at least one product with quantity.');
      }

      $pdo->beginTransaction();

      $snap = [];
      $old = null;
      if ($isEdit) {
        $ex = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $ex->execute([$oid]);
        $old = $ex->fetch();
        if (!$old) {
          throw new RuntimeException('Order not found.');
        }
        $sn = $pdo->prepare('SELECT product_id, original_price_mmk, unit_price_mmk FROM order_items WHERE order_id = ?');
        $sn->execute([$oid]);
        foreach ($sn->fetchAll() as $r) {
          $snap[$r['product_id']] = $r;
        }
      }

      $cid = (int)($_POST['customer_id'] ?? 0);
      if ($cid > 0) {
        $c = $pdo->prepare('SELECT id, name, phone FROM customers WHERE id = ?');
        $c->execute([$cid]);
        $cust = $c->fetch();
        if (!$cust) {
          throw new RuntimeException('Selected customer not found.');
        }
      } else {
        $name = trim($_POST['new_name'] ?? '');
        $phone = trim($_POST['new_phone'] ?? '');
        if ($name === '' || $phone === '') {
          throw new RuntimeException('New customers need a name and phone number.');
        }
        $c = $pdo->prepare('SELECT id, name, phone FROM customers WHERE phone = ?');
        $c->execute([$phone]);
        $cust = $c->fetch();
        if (!$cust) {
          $pdo->prepare('INSERT INTO customers (name, phone, preferred_channel) VALUES (?,?,?)')->execute([$name, $phone, $channel]);
          $cust = ['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'phone' => $phone];
        }
      }

      $ph = implode(',', array_fill(0, count($lines), '?'));
      $ps = $pdo->prepare("SELECT id, original_price_mmk, selling_price_mmk, is_available FROM products WHERE id IN ($ph)");
      $ps->execute(array_keys($lines));
      $prods = [];
      foreach ($ps->fetchAll() as $p) {
        $prods[$p['id']] = $p;
      }
      foreach ($lines as $pid => $q) {
        if (!isset($prods[$pid])) {
          throw new RuntimeException('Unknown product selected.');
        }
        if (!$prods[$pid]['is_available'] && !isset($snap[$pid])) {
          throw new RuntimeException("Product $pid is currently unavailable.");
        }
      }

      if ($isEdit) {
        $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$oid]);
      } else {
        $pdo->prepare('INSERT INTO orders (order_number, customer_id, customer_name, customer_phone, channel, status) VALUES (?,?,?,?,?,?)')
          ->execute(['TMP' . bin2hex(random_bytes(6)), $cust['id'], $cust['name'], $cust['phone'], $channel, 'Pending']);
        $oid = (int)$pdo->lastInsertId();
      }

      $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, original_price_mmk, unit_price_mmk, subtotal_revenue_mmk, subtotal_cost_mmk, subtotal_profit_mmk) VALUES (?,?,?,?,?,?,?,?)');
      $rev = $cost = 0;
      foreach ($lines as $pid => $q) {
        $uc = (float)(isset($snap[$pid]) ? $snap[$pid]['original_price_mmk'] : $prods[$pid]['original_price_mmk']);
        $up = (float)(isset($snap[$pid]) ? $snap[$pid]['unit_price_mmk'] : $prods[$pid]['selling_price_mmk']);
        $sr = $up * $q;
        $sc = $uc * $q;
        $ins->execute([$oid, $pid, $q, $uc, $up, $sr, $sc, $sr - $sc]);
        $rev += $sr;
        $cost += $sc;
      }

      if ($isEdit) {
        $status = in_array($_POST['status'] ?? '', ['Pending', 'Completed', 'Cancelled'], true) ? $_POST['status'] : $old['status'];
        $ts = strtotime($_POST['order_date'] ?? '');
        $date = $ts ? date('Y-m-d H:i:s', $ts) : $old['order_date'];
        $pdo->prepare('UPDATE orders SET customer_id=?, customer_name=?, customer_phone=?, channel=?, status=?, order_date=?, total_revenue_mmk=?, total_cost_mmk=?, net_profit_mmk=? WHERE id=?')
          ->execute([$cust['id'], $cust['name'], $cust['phone'], $channel, $status, $date, $rev, $cost, $rev - $cost, $oid]);
        $orderNo = $old['order_number'];
      } else {
        $orderNo = sprintf('KKL-%s-%04d', date('ymd'), $oid);
        $pdo->prepare('UPDATE orders SET order_number=?, total_revenue_mmk=?, total_cost_mmk=?, net_profit_mmk=? WHERE id=?')
          ->execute([$orderNo, $rev, $cost, $rev - $cost, $oid]);
      }
      $pdo->commit();
      flash($isEdit ? "Order $orderNo updated." : "Order $orderNo created (" . mmk($rev) . ').');
    } catch (Throwable $ex) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      flash($ex instanceof RuntimeException ? $ex->getMessage() : 'Could not save the order. Please try again.', 'error');
      if (!($ex instanceof RuntimeException)) {
        error_log($ex->getMessage());
      }
    }
    redirect('orders.php');
  }
}

$sum = $pdo->query("SELECT
    SUM(status='Pending') AS pending,
    SUM(status='Completed') AS completed,
    COALESCE(SUM(CASE WHEN status='Completed' THEN total_revenue_mmk END),0) AS revenue,
    COALESCE(SUM(CASE WHEN status='Completed' THEN net_profit_mmk END),0) AS profit
    FROM orders")->fetch();
$orders = $pdo->query('SELECT * FROM orders ORDER BY order_date DESC, id DESC')->fetchAll();
$customers = $pdo->query('SELECT id, name, phone FROM customers ORDER BY name')->fetchAll();
$products = $pdo->query('SELECT id, name_en, description_en, original_price_mmk, selling_price_mmk, is_available FROM products ORDER BY id')->fetchAll();

$pageTitle = 'Orders';
include __DIR__ . '/nav.php';
?>
<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-charcoal">Order Management</h1>
      <p class="text-sm text-sand">Revenue and profit count Completed orders only.</p>
    </div>
    <button class="btn" onclick="openCreate()"><i class="fa-solid fa-plus"></i> New Order</button>
  </div>

  <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $cards = [
      ['Pending Orders', (int)$sum['pending'], 'fa-hourglass-half', 'text-amber-700 bg-amber-100'],
      ['Completed Orders', (int)$sum['completed'], 'fa-circle-check', 'text-emerald-700 bg-emerald-100'],
      ['Total Revenue', mmk($sum['revenue']), 'fa-coins', 'text-copper bg-rose-soft'],
      ['Total Profit', mmk($sum['profit']), 'fa-sack-dollar', 'text-copper bg-rose-soft'],
    ];
    foreach ($cards as [$t, $v, $ic, $cl]): ?>
      <div class="card p-4 flex items-center gap-3">
        <span class="w-11 h-11 rounded-full flex items-center justify-center <?= $cl ?>"><i class="fa-solid <?= $ic ?>"></i></span>
        <div>
          <p class="text-xs font-semibold text-sand"><?= $t ?></p>
          <p class="text-lg font-bold"><?= e($v) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Channel</th>
            <th>Status</th>
            <th>Revenue</th>
            <th>Net Profit</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td class="font-semibold text-copper"><?= e($o['order_number']) ?></td>
              <td>
                <p class="font-semibold"><?= e($o['customer_name']) ?></p>
                <p class="text-xs text-sand"><?= e($o['customer_phone']) ?></p>
              </td>
              <td><?= channel_badge($o['channel']) ?></td>
              <td><?= status_badge($o['status']) ?></td>
              <td class="whitespace-nowrap"><?= mmk($o['total_revenue_mmk']) ?></td>
              <td class="whitespace-nowrap font-semibold <?= $o['net_profit_mmk'] >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>"><?= mmk($o['net_profit_mmk']) ?></td>
              <td class="whitespace-nowrap text-sand"><?= e(date('d M Y, H:i', strtotime($o['order_date']))) ?></td>
              <td>
                <div class="flex gap-2">
                  <button class="btn btn-ghost !px-3 !py-1.5" onclick="showOrder(<?= (int)$o['id'] ?>)" title="View details"><i class="fa-solid fa-eye"></i></button>
                  <button class="btn btn-ghost !px-3 !py-1.5" onclick="editOrder(<?= (int)$o['id'] ?>)" title="Edit order"><i class="fa-solid fa-pen"></i></button>
                  <a class="btn btn-ghost !px-3 !py-1.5" href="receipt.php?id=<?= (int)$o['id'] ?>" target="_blank" rel="noopener" title="Receipt"><i class="fa-solid fa-file-invoice"></i></a>
                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                    <button class="btn !px-3 !py-1.5" title="Next status"><i class="fa-solid fa-rotate"></i> Status</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$orders): ?><tr>
              <td colspan="8" class="text-center text-sand py-10">No orders yet. Click “New Order” to add the first one.</td>
            </tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<div id="createModal" class="modal-bg">
  <form method="post" id="orderForm" class="modal-box max-w-2xl p-6 space-y-4" onsubmit="return validateOrder()">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="fAction" value="create">
    <input type="hidden" name="order_id" id="fOrderId" value="">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-bold text-copper"><i class="fa-solid fa-cart-plus mr-2"></i><span id="modalTitle">Create Order</span></h2>
      <button type="button" onclick="closeModal('createModal')" class="text-sand hover:text-charcoal"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
      <div>
        <label class="label">Customer</label>
        <select name="customer_id" id="custSel" class="input" onchange="toggleNewCust()">
          <option value="0">+ New customer (by phone)</option>
          <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?> — <?= e($c['phone']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label">Channel</label>
        <select name="channel" class="input"><?php foreach ($channels as $ch): ?><option><?= e($ch) ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <div id="editOnly" class="hidden">
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">Status</label>
          <select name="status" id="fStatus" class="input">
            <option>Pending</option>
            <option>Completed</option>
            <option>Cancelled</option>
          </select>
        </div>
        <div><label class="label">Order date</label><input type="datetime-local" name="order_date" id="fDate" class="input"></div>
      </div>
    </div>
    <div id="newCust" class="grid sm:grid-cols-2 gap-3 p-3 rounded-xl bg-rose-soft/60 border border-rose-soft">
      <div><label class="label">New customer name</label><input name="new_name" class="input" placeholder="Name"></div>
      <div><label class="label">Phone (existing phone is reused)</label><input name="new_phone" class="input" placeholder="09..."></div>
    </div>

    <div>
      <div class="flex items-center justify-between mb-2">
        <label class="label !mb-0">Items</label>
        <button type="button" class="btn btn-ghost !py-1" onclick="addRow()"><i class="fa-solid fa-plus"></i> Add item</button>
      </div>
      <div id="rows" class="space-y-2"></div>
    </div>

    <div class="rounded-xl bg-rose-soft/60 border border-rose-soft p-3 grid grid-cols-3 gap-2 text-center text-sm">
      <div>
        <p class="text-xs text-sand">Revenue</p>
        <p id="tRev" class="font-bold">0 MMK</p>
      </div>
      <div>
        <p class="text-xs text-sand">Cost</p>
        <p id="tCost" class="font-bold">0 MMK</p>
      </div>
      <div>
        <p class="text-xs text-sand">Net Profit</p>
        <p id="tProfit" class="font-bold text-emerald-700">0 MMK</p>
      </div>
    </div>
    <div class="flex justify-end gap-2">
      <button type="button" class="btn btn-ghost" onclick="closeModal('createModal')">Cancel</button>
      <button class="btn"><i class="fa-solid fa-check"></i> <span id="saveLabel">Save Order</span></button>
    </div>
  </form>
</div>

<div id="detailModal" class="modal-bg">
  <div class="modal-box max-w-3xl p-6" id="detailBody"></div>
</div>

<script>
  const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  let snap = {};
  const optHtml = sel => PRODUCTS.filter(p => p.is_available == 1 || p.id === sel).map(p =>
    `<option value="${esc(p.id)}" data-cost="${p.original_price_mmk}" data-price="${p.selling_price_mmk}" ${p.id === sel ? 'selected' : ''}>${esc(p.id)} — ${esc(p.name_en)} (${esc(p.description_en)}) · ${Math.round(p.selling_price_mmk).toLocaleString()}</option>`).join('');

  function toggleNewCust() {
    document.getElementById('newCust').style.display = document.getElementById('custSel').value === '0' ? 'grid' : 'none';
  }

  function addRow(pid = '', qty = 1) {
    const d = document.createElement('div');
    d.className = 'row grid grid-cols-[1fr_5rem_auto] gap-2 items-center';
    d.innerHTML = `<select name="product_id[]" class="input" onchange="recalc()"><option value="">Select product…</option>${optHtml(pid)}</select>
    <input type="number" name="qty[]" min="1" value="${qty}" class="input" oninput="recalc()">
    <button type="button" class="text-sand hover:text-rose-700 px-2" onclick="this.parentNode.remove();recalc()"><i class="fa-solid fa-trash"></i></button>`;
    document.getElementById('rows').appendChild(d);
  }

  function recalc() {
    let r = 0,
      c = 0;
    document.querySelectorAll('#rows .row').forEach(row => {
      const sel = row.querySelector('select');
      const o = sel.selectedOptions[0];
      const q = parseInt(row.querySelector('input').value) || 0;
      if (!o || !sel.value) return;
      const s = snap[sel.value];
      r += (s ? s.price : o.dataset.price) * q;
      c += (s ? s.cost : o.dataset.cost) * q;
    });
    tRev.textContent = fmt(r);
    tCost.textContent = fmt(c);
    tProfit.textContent = fmt(r - c);
  }

  function validateOrder() {
    const ok = [...document.querySelectorAll('#rows .row')].some(r => r.querySelector('select').value && parseInt(r.querySelector('input').value) > 0);
    if (!ok) {
      alert('Please add at least one item.');
      return false;
    }
    return true;
  }

  function setMode(edit) {
    fAction.value = edit ? 'update' : 'create';
    modalTitle.textContent = edit ? 'Edit Order' : 'Create Order';
    saveLabel.textContent = edit ? 'Save Changes' : 'Save Order';
    document.getElementById('editOnly').classList.toggle('hidden', !edit);
  }

  function openCreate() {
    document.getElementById('orderForm').reset();
    snap = {};
    fOrderId.value = '';
    document.getElementById('rows').innerHTML = '';
    setMode(false);
    toggleNewCust();
    addRow();
    recalc();
    openModal('createModal');
  }
  async function editOrder(id) {
    try {
      const o = await (await fetch('orders.php?detail=' + id)).json();
      if (o.error) throw new Error(o.error);
      document.getElementById('orderForm').reset();
      fOrderId.value = o.id;
      snap = {};
      o.items.forEach(i => snap[i.product_id] = {
        cost: +i.original_price_mmk,
        price: +i.unit_price_mmk
      });
      setMode(true);
      const sel = document.getElementById('custSel');
      sel.value = o.customer_id && [...sel.options].some(x => x.value == o.customer_id) ? o.customer_id : '0';
      if (sel.value === '0') {
        document.querySelector('[name=new_name]').value = o.customer_name;
        document.querySelector('[name=new_phone]').value = o.customer_phone;
      }
      document.querySelector('[name=channel]').value = o.channel;
      fStatus.value = o.status;
      fDate.value = o.order_date.replace(' ', 'T').slice(0, 16);
      document.getElementById('rows').innerHTML = '';
      o.items.forEach(i => addRow(i.product_id, i.quantity));
      toggleNewCust();
      recalc();
      openModal('createModal');
    } catch (err) {
      alert('Could not load the order for editing.');
    }
  }
  async function showOrder(id) {
    const box = document.getElementById('detailBody');
    box.innerHTML = '<p class="text-sand text-center py-8"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</p>';
    openModal('detailModal');
    try {
      const o = await (await fetch('orders.php?detail=' + id)).json();
      if (o.error) throw new Error(o.error);
      box.innerHTML = `
      <div class="flex items-start justify-between mb-4">
        <div><h2 class="text-lg font-bold text-copper">${esc(o.order_number)}</h2>
        <p class="text-xs text-sand">${esc(o.order_date)}</p></div>
        <div class="flex items-center gap-3">
          <a class="btn btn-ghost !py-1.5" href="receipt.php?id=${o.id}" target="_blank" rel="noopener"><i class="fa-solid fa-file-invoice"></i> Receipt</a>
          <button class="btn btn-ghost !py-1.5" onclick="closeModal('detailModal');editOrder(${o.id})"><i class="fa-solid fa-pen"></i> Edit</button>
          <button onclick="closeModal('detailModal')" class="text-sand hover:text-charcoal"><i class="fa-solid fa-xmark"></i></button>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 items-center mb-4">${o.status_html} ${o.channel_html}
        <span class="text-sm ml-2"><b>${esc(o.customer_name)}</b> · ${esc(o.customer_phone)}</span></div>
      <div class="overflow-x-auto rounded-xl border border-rose-soft"><table class="w-full">
        <thead><tr><th>Item</th><th>Qty</th><th>Unit Cost</th><th>Unit Price</th><th>Subtotal</th><th>Profit</th></tr></thead>
        <tbody>${o.items.map(i => `<tr>
          <td><p class="font-semibold">${esc(i.name_en)}</p><p class="text-xs text-sand">${esc(i.description_en)} · ${esc(i.product_id)}</p></td>
          <td>${i.quantity}</td><td>${fmt(i.original_price_mmk)}</td><td>${fmt(i.unit_price_mmk)}</td>
          <td>${fmt(i.subtotal_revenue_mmk)}</td><td class="font-semibold text-emerald-700">${fmt(i.subtotal_profit_mmk)}</td></tr>`).join('')}</tbody></table></div>
      <div class="mt-4 grid grid-cols-3 gap-2 text-center rounded-xl bg-rose-soft/60 border border-rose-soft p-3">
        <div><p class="text-xs text-sand">Revenue</p><p class="font-bold">${fmt(o.total_revenue_mmk)}</p></div>
        <div><p class="text-xs text-sand">Cost</p><p class="font-bold">${fmt(o.total_cost_mmk)}</p></div>
        <div><p class="text-xs text-sand">Net Profit</p><p class="font-bold text-emerald-700">${fmt(o.net_profit_mmk)}</p></div>
      </div>`;
    } catch (err) {
      box.innerHTML = '<p class="text-rose-700 text-center py-8">Could not load order details.</p>';
    }
  }
  toggleNewCust();
  addRow();
</script>
</body>

</html>