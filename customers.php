<?php
require_once __DIR__ . '/dbconfig.php';

$channels = ['Viber', 'Telegram', 'In-Store', 'Website'];

if (isset($_GET['detail'])) {
  $st = $pdo->prepare('SELECT id, name, phone, preferred_channel, created_at FROM customers WHERE id = ?');
  $st->execute([(int)$_GET['detail']]);
  $c = $st->fetch();
  header('Content-Type: application/json; charset=utf-8');
  if (!$c) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
  }
  $os = $pdo->prepare('SELECT id, order_number, channel, status, total_revenue_mmk, net_profit_mmk, order_date FROM orders WHERE customer_id = ? ORDER BY order_date DESC');
  $os->execute([$c['id']]);
  $c['orders'] = array_map(function ($o) {
    $o['status_html'] = status_badge($o['status']);
    return $o;
  }, $os->fetchAll());
  $c['channel_html'] = channel_badge($c['preferred_channel']);
  echo json_encode($c, JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $isEdit = ($_POST['action'] ?? '') === 'edit';
  $cid = (int)($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $ch = in_array($_POST['preferred_channel'] ?? '', $channels, true) ? $_POST['preferred_channel'] : 'In-Store';
  if ($name === '' || $phone === '') {
    flash('Name and phone are required.', 'error');
  } else {
    try {
      if ($isEdit) {
        $pdo->beginTransaction();
        $up = $pdo->prepare('UPDATE customers SET name=?, phone=?, preferred_channel=? WHERE id=?');
        $up->execute([$name, $phone, $ch, $cid]);
        $pdo->prepare('UPDATE orders SET customer_name=?, customer_phone=? WHERE customer_id=?')->execute([$name, $phone, $cid]);
        $pdo->commit();
        flash("Customer '$name' updated.");
      } else {
        $pdo->prepare('INSERT INTO customers (name, phone, preferred_channel) VALUES (?,?,?)')->execute([$name, $phone, $ch]);
        flash("Customer '$name' added.");
      }
    } catch (PDOException $ex) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      flash($ex->getCode() === '23000' ? 'A customer with that phone number already exists.' : 'Could not save the customer.', 'error');
    }
  }
  redirect('customers.php');
}

$rows = $pdo->query("SELECT c.*,
        COUNT(o.id) AS orders_count,
        COALESCE(SUM(CASE WHEN o.status='Completed' THEN o.total_revenue_mmk END),0) AS revenue,
        COALESCE(SUM(CASE WHEN o.status='Completed' THEN o.net_profit_mmk END),0) AS profit,
        MAX(o.order_date) AS last_order
    FROM customers c LEFT JOIN orders o ON o.customer_id = c.id AND o.status <> 'Cancelled'
    GROUP BY c.id ORDER BY revenue DESC, c.name")->fetchAll();

$total = count($rows);
$repeat = count(array_filter($rows, fn($r) => $r['orders_count'] >= 2));
$k = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(total_revenue_mmk),0) rev FROM orders WHERE status='Completed'")->fetch();
$aov = $k['n'] ? $k['rev'] / $k['n'] : 0;
$topCh = $pdo->query('SELECT preferred_channel, COUNT(*) n FROM customers GROUP BY preferred_channel ORDER BY n DESC LIMIT 1')->fetch();

$pageTitle = 'Customers';
include __DIR__ . '/nav.php';
?>
<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold">Customer CRM</h1>
      <p class="text-sm text-sand">Lifetime revenue and profit count Completed orders only.</p>
    </div>
    <button class="btn" onclick="openAdd()"><i class="fa-solid fa-user-plus"></i> Add Customer</button>
  </div>

  <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php foreach (
      [
        ['Total Customers', $total, 'fa-users'],
        ['Repeat Customers', $repeat, 'fa-repeat'],
        ['Avg Order Value', mmk($aov), 'fa-basket-shopping'],
        ['Top Preferred Channel', $topCh ? $topCh['preferred_channel'] : '—', 'fa-star'],
      ] as [$t, $v, $ic]
    ): ?>
      <div class="card p-4 flex items-center gap-3">
        <span class="w-11 h-11 rounded-full bg-rose-soft text-copper flex items-center justify-center"><i class="fa-solid <?= $ic ?>"></i></span>
        <div>
          <p class="text-xs font-semibold text-sand"><?= $t ?></p>
          <p class="text-lg font-bold"><?= e($v) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card overflow-hidden">
    <div class="p-3 border-b border-rose-soft"><input id="q" class="input max-w-xs" placeholder="Search name or phone…" oninput="filterRows()"></div>
    <div class="overflow-x-auto">
      <table class="w-full" id="tbl">
        <thead>
          <tr>
            <th>Customer</th>
            <th>Phone</th>
            <th>Preferred Channel</th>
            <th>Orders</th>
            <th>Lifetime Revenue</th>
            <th>Lifetime Profit</th>
            <th>Last Order</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="font-semibold"><?= e($r['name']) ?></td>
              <td><?= e($r['phone']) ?></td>
              <td><?= channel_badge($r['preferred_channel']) ?></td>
              <td><?= (int)$r['orders_count'] ?></td>
              <td class="whitespace-nowrap"><?= mmk($r['revenue']) ?></td>
              <td class="whitespace-nowrap font-semibold text-emerald-700"><?= mmk($r['profit']) ?></td>
              <td class="whitespace-nowrap text-sand"><?= $r['last_order'] ? e(date('d M Y', strtotime($r['last_order']))) : '—' ?></td>
              <td class="whitespace-nowrap">
                <button class="btn btn-ghost !px-3 !py-1.5" onclick="showCustomer(<?= (int)$r['id'] ?>)"><i class="fa-solid fa-clock-rotate-left"></i> History</button>
                <button class="btn btn-ghost !px-3 !py-1.5" title="Edit customer" onclick='editCustomer(<?= json_encode(['id' => (int)$r['id'], 'name' => $r['name'], 'phone' => $r['phone'], 'preferred_channel' => $r['preferred_channel']], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'><i class="fa-solid fa-pen"></i></button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr>
              <td colspan="8" class="text-center text-sand py-10">No customers yet.</td>
            </tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<div id="addModal" class="modal-bg">
  <form method="post" id="custForm" class="modal-box max-w-md p-6 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="cAction" value="add">
    <input type="hidden" name="id" id="cId" value="">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-bold text-copper"><i class="fa-solid fa-user-plus mr-2"></i><span id="cTitle">New Customer</span></h2>
      <button type="button" onclick="closeModal('addModal')" class="text-sand hover:text-charcoal"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div><label class="label">Name</label><input name="name" id="cName" class="input" required maxlength="100"></div>
    <div><label class="label">Phone</label><input name="phone" id="cPhone" class="input" required maxlength="50" placeholder="09..."></div>
    <div><label class="label">Preferred Channel</label>
      <select name="preferred_channel" id="cChannel" class="input" style="padding: 10px 12px; font-size: 16px; height: 44px;"><?php foreach ($channels as $ch): ?><option><?= e($ch) ?></option><?php endforeach; ?></select>
    </div>
    <div class="flex justify-end gap-2">
      <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
      <button class="btn"><i class="fa-solid fa-check"></i> Save</button>
    </div>
  </form>
</div>

<div id="detailModal" class="modal-bg">
  <div class="modal-box max-w-2xl p-6" id="detailBody"></div>
</div>

<script>
  function openAdd() {
    document.getElementById('custForm').reset();
    cAction.value = 'add';
    cId.value = '';
    cTitle.textContent = 'New Customer';
    openModal('addModal');
  }

  function editCustomer(c) {
    cAction.value = 'edit';
    cId.value = c.id;
    cTitle.textContent = 'Edit Customer';
    cName.value = c.name;
    cPhone.value = c.phone;
    cChannel.value = c.preferred_channel;
    openModal('addModal');
  }

  function filterRows() {
    const q = document.getElementById('q').value.toLowerCase();
    document.querySelectorAll('#tbl tbody tr').forEach(tr => tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none');
  }
  async function showCustomer(id) {
    const box = document.getElementById('detailBody');
    box.innerHTML = '<p class="text-sand text-center py-8"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</p>';
    openModal('detailModal');
    try {
      const c = await (await fetch('customers.php?detail=' + id)).json();
      if (c.error) throw new Error(c.error);
      window._cust = c;
      box.innerHTML = `
      <div class="flex items-start justify-between mb-3">
        <div><h2 class="text-lg font-bold text-copper">${esc(c.name)}</h2><p class="text-sm text-sand">${esc(c.phone)} · Customer since ${esc(c.created_at.slice(0,10))}</p></div>
        <div class="flex items-center gap-3">
          <button class="btn btn-ghost !py-1.5" onclick="closeModal('detailModal');editCustomer(window._cust)"><i class="fa-solid fa-pen"></i> Edit</button>
          <button onclick="closeModal('detailModal')" class="text-sand hover:text-charcoal"><i class="fa-solid fa-xmark"></i></button>
        </div>
      </div>
      <div class="mb-4">${c.channel_html}</div>
      <div class="overflow-x-auto rounded-xl border border-rose-soft"><table class="w-full">
        <thead><tr><th>Order #</th><th>Date</th><th>Channel</th><th>Status</th><th>Value</th><th></th></tr></thead>
        <tbody>${c.orders.length ? c.orders.map(o => `<tr><td class="font-semibold text-copper">${esc(o.order_number)}</td>
          <td class="whitespace-nowrap">${esc(o.order_date.slice(0,10))}</td><td>${esc(o.channel)}</td><td>${o.status_html}</td>
          <td class="whitespace-nowrap">${fmt(o.total_revenue_mmk)}</td>
          <td><a class="btn btn-ghost !px-3 !py-1.5" href="receipt.php?id=${Number(o.id)}" target="_blank" rel="noopener" title="Receipt"><i class="fa-solid fa-file-invoice"></i></a></td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-sand py-6">No orders yet.</td></tr>'}</tbody></table></div>`;
    } catch (err) {
      box.innerHTML = '<p class="text-rose-700 text-center py-8">Could not load customer.</p>';
    }
  }
</script>
</body>

</html>