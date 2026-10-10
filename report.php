<?php
require_once __DIR__ . '/finance_helpers.php';

$ranges = PERIOD_RANGES;
$range = period_from_request();
$self = 'report.php?range=' . $range;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);

  if ($action === 'income_delete') {
    if ($id > 0) {
      $pdo->prepare('DELETE FROM other_incomes WHERE id = ?')->execute([$id]);
      flash('Income entry deleted.');
    }
    redirect($self);
  }

  if ($action === 'income_add' || $action === 'income_edit') {
    $isEdit = $action === 'income_edit';
    $title = post_str('title');
    $source = post_str('source');
    $amount = parse_amount(post_str('amount_mmk'));
    $date = parse_datetime_input(post_str('income_date'));
    $notes = post_str('notes');

    if ($title === '' || mb_strlen($title) > 255) {
      flash('Title is required (max 255 characters).', 'error');
    } elseif (!in_array($source, INCOME_SOURCES, true)) {
      flash('Please choose a valid income source.', 'error');
    } elseif ($amount === null) {
      flash('Amount must be a positive number (up to 9,999,999,999.99, max 2 decimals).', 'error');
    } elseif ($date === null) {
      flash('The income date is not valid.', 'error');
    } elseif (mb_strlen($notes) > 2000) {
      flash('Notes are limited to 2,000 characters.', 'error');
    } else {
      try {
        $exists = true;
        if ($isEdit) {
          $chk = $pdo->prepare('SELECT COUNT(*) FROM other_incomes WHERE id = ?');
          $chk->execute([$id]);
          $exists = (bool)$chk->fetchColumn();
        }
        if (!$exists) {
          flash('That income entry no longer exists.', 'error');
        } elseif ($isEdit) {
          $pdo->prepare('UPDATE other_incomes SET income_date=?, source=?, title=?, amount_mmk=?, notes=? WHERE id=?')
            ->execute([$date, $source, $title, $amount, $notes !== '' ? $notes : null, $id]);
          flash("Income '$title' updated (" . mmk($amount) . ').');
        } else {
          $pdo->prepare('INSERT INTO other_incomes (income_date, source, title, amount_mmk, notes) VALUES (?,?,?,?,?)')
            ->execute([$date, $source, $title, $amount, $notes !== '' ? $notes : null]);
          flash("Income '$title' added (" . mmk($amount) . ').');
        }
      } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('Could not save the income entry. Please try again.', 'error');
      }
    }
  }
  redirect($self);
}
$condO = period_condition($range, 'o.order_date');
$condE = period_condition($range, 'expense_date');
$condI = period_condition($range, 'income_date');

$k = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(total_revenue_mmk),0) AS rev, COALESCE(SUM(total_cost_mmk),0) AS cogs, COALESCE(SUM(net_profit_mmk),0) AS profit
                  FROM orders o WHERE o.status = 'Completed' AND $condO")->fetch();
$margin = $k['rev'] > 0 ? $k['profit'] / $k['rev'] * 100 : 0;

$oi = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(amount_mmk),0) AS total FROM other_incomes WHERE $condI")->fetch();
$ex = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(amount_mmk),0) AS total FROM expenses WHERE $condE")->fetch();

$revenue        = money_round((float)$k['rev']);
$cogs           = money_round((float)$k['cogs']);
$orderProfit    = money_round((float)$k['profit']);
$otherIncome    = money_round((float)$oi['total']);
$totalIncome    = money_round($orderProfit + $otherIncome);
$totalExpenses  = money_round((float)$ex['total']);
$netResult      = money_round($totalIncome - $totalExpenses);
$operatingResult = money_round($orderProfit - $totalExpenses);
$pct = fn(float $v) => $revenue > 0 ? number_format($v / $revenue * 100, 1) . '%' : '—';
$tone = fn(float $v) => $v < 0 ? 'text-rose-700' : 'text-emerald-700';

$expRows = $pdo->query("SELECT id, expense_date, category, title, amount_mmk, notes FROM expenses WHERE $condE ORDER BY expense_date DESC, id DESC")->fetchAll();
$incRows = $pdo->query("SELECT id, income_date, source, title, amount_mmk, notes FROM other_incomes WHERE $condI ORDER BY income_date DESC, id DESC")->fetchAll();

$cats = $pdo->query("SELECT p.category, SUM(oi.quantity) AS units, SUM(oi.subtotal_revenue_mmk) AS rev, SUM(oi.subtotal_cost_mmk) AS cost, SUM(oi.subtotal_profit_mmk) AS profit
                     FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
                     WHERE o.status = 'Completed' AND $condO GROUP BY p.category ORDER BY FIELD(p.category,'Small','Normal','Large')")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
$catRows = [];
foreach (['Small', 'Normal', 'Large'] as $c) {
  $r = $cats[$c] ?? ['units' => 0, 'rev' => 0, 'cost' => 0, 'profit' => 0];
  $catRows[$c] = $r;
}

$top = $pdo->query("SELECT p.id, p.name_en, p.description_en, p.category, SUM(oi.quantity) AS units, SUM(oi.subtotal_revenue_mmk) AS rev, SUM(oi.subtotal_profit_mmk) AS profit
                    FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
                    WHERE o.status = 'Completed' AND $condO GROUP BY p.id, p.name_en, p.description_en, p.category
                    ORDER BY profit DESC LIMIT 5")->fetchAll();

$mrows = $pdo->query("SELECT DATE_FORMAT(order_date,'%Y-%m') AS m, SUM(total_revenue_mmk) AS rev, SUM(net_profit_mmk) AS profit
                      FROM orders WHERE status = 'Completed' AND order_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                      GROUP BY m ORDER BY m")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
$labels = $rev = $prof = [];
for ($i = 11; $i >= 0; $i--) {
  $key = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
  $labels[] = date('M Y', strtotime($key . '-01'));
  $rev[] = (float)($mrows[$key]['rev'] ?? 0);
  $prof[] = (float)($mrows[$key]['profit'] ?? 0);
}

$todayD = new DateTimeImmutable('today');
$monthly = false;
switch ($range) {
  case 'month':
    $start = $todayD->modify('first day of this month');
    $end = $start->modify('+1 month');
    $flowTitle = 'Daily — ' . $todayD->format('F Y');
    break;
  case 'week':
    $start = $todayD->modify('monday this week');
    $end = $start->modify('+7 days');
    $flowTitle = 'Daily — this week';
    break;
  case 'today':
    $start = $todayD->modify('-6 days');
    $end = $todayD->modify('+1 day');
    $flowTitle = 'Daily — last 7 days, ending today';
    break;
  default:
    $monthly = true;
    $start = $todayD->modify('first day of this month')->modify('-11 months');
    $end = $todayD->modify('first day of this month')->modify('+1 month');
    $flowTitle = 'Monthly — last 12 months';
}
$keyFmt = $monthly ? 'Y-m' : 'Y-m-d';
$lblFmt = $monthly ? 'M Y' : 'j M';
$sqlFmt = $monthly ? '%Y-%m' : '%Y-%m-%d';
$from = $start->format('Y-m-d 00:00:00');
$to = $end->format('Y-m-d 00:00:00');

$keys = $flowLabels = [];
for ($d = $start; $d < $end; $d = $monthly ? $d->modify('+1 month') : $d->modify('+1 day')) {
  $keys[] = $d->format($keyFmt);
  $flowLabels[] = $d->format($lblFmt);
}

$series = function (string $table, string $dateCol, string $valCol, string $where = '1=1') use ($pdo, $sqlFmt, $from, $to): array {
  $st = $pdo->prepare("SELECT DATE_FORMAT($dateCol, '$sqlFmt') AS b, SUM($valCol) AS v FROM $table WHERE $where AND $dateCol >= ? AND $dateCol < ? GROUP BY b");
  $st->execute([$from, $to]);
  return $st->fetchAll(PDO::FETCH_KEY_PAIR);
};
$sOrders = $series('orders', 'order_date', 'net_profit_mmk', "status = 'Completed'");
$sIncome = $series('other_incomes', 'income_date', 'amount_mmk');
$sExpense = $series('expenses', 'expense_date', 'amount_mmk');

$flowIncome = $flowExpense = $flowNet = [];
foreach ($keys as $key) {
  $inc = (float)($sOrders[$key] ?? 0) + (float)($sIncome[$key] ?? 0);
  $exp = (float)($sExpense[$key] ?? 0);
  $flowIncome[] = money_round($inc);
  $flowExpense[] = money_round($exp);
  $flowNet[] = money_round($inc - $exp);
}

$plLines = [
  ['Revenue from completed orders', $revenue, '', 'line', ''],
  ['Cost of goods sold (COGS)', $cogs, '−', 'line', ''],
  ['Order profit', $orderProfit, '', 'sub', 'Revenue − COGS'],
  ['Other incomes', $otherIncome, '+', 'line', ''],
  ['Total income', $totalIncome, '', 'sub', 'Order profit + Other incomes'],
  ['Total expenses', $totalExpenses, '−', 'line', ''],
  ['Net result', $netResult, '', 'total', 'Total income − Total expenses'],
];

$pageTitle = 'Reports & Analytics';
include __DIR__ . '/nav.php';
?>
<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold">Reports &amp; Analytics</h1>
      <p class="text-sm text-sand">Profit &amp; Loss · <?= (int)$k['n'] ?> completed orders · <?= (int)$ex['n'] ?> expenses · <?= (int)$oi['n'] ?> other incomes · <?= e($ranges[$range]) ?></p>
    </div>
    <div class="flex gap-1.5 bg-white border border-rose-soft rounded-full p-1">
      <?php foreach ($ranges as $key => $label): ?>
        <a href="?range=<?= e($key) ?>" class="px-3.5 py-1.5 rounded-full text-sm font-semibold transition <?= $range === $key ? 'bg-copper text-white' : 'text-sand hover:bg-rose-soft hover:text-copper' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php foreach (
      [
        ['Total Revenue', mmk($k['rev']), 'fa-coins', 'text-copper'],
        ['Cost of Goods Sold', mmk($k['cogs']), 'fa-boxes-stacked', 'text-charcoal'],
        ['Order Profit', mmk($k['profit']), 'fa-sack-dollar', 'text-emerald-700'],
        ['Profit Margin', number_format($margin, 1) . '%', 'fa-percent', 'text-copper'],
      ] as [$t, $v, $ic, $cl]
    ): ?>
      <div class="card p-4">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold text-sand"><?= e($t) ?></p><i class="fa-solid <?= $ic ?> text-gold"></i>
        </div>
        <p class="text-xl font-bold mt-1 <?= $cl ?>"><?= e($v) ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php foreach (
      [
        ['Other Incomes', mmk($otherIncome), 'fa-hand-holding-dollar', 'text-copper', 'Investments, capital, grants'],
        ['Total Income', mmk($totalIncome), 'fa-arrow-down-to-line', 'text-emerald-700', 'Order profit + other incomes'],
        ['Total Expenses', mmk($totalExpenses), 'fa-wallet', 'text-rose-700', 'Raw materials, packaging, operational costs'],
        ['Net Result', mmk($netResult), 'fa-scale-balanced', $tone($netResult), 'Total income - total expenses'],
      ] as [$t, $v, $ic, $cl, $sub]
    ): ?>
      <div class="card p-4">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold text-sand"><?= e($t) ?></p><i class="fa-solid <?= $ic ?> text-gold"></i>
        </div>
        <p class="text-xl font-bold mt-1 <?= $cl ?>"><?= e($v) ?></p>
        <?php if ($sub !== ''): ?><p class="text-xs text-sand mt-0.5"><?= e($sub) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card overflow-hidden">
    <h2 class="font-bold px-4 pt-4 pb-3 text-copper"><i class="fa-solid fa-file-invoice-dollar mr-2"></i>Profit &amp; Loss Statement — <?= e($ranges[$range]) ?></h2>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th>Line</th>
            <th>How it's calculated</th>
            <th class="!text-right">Amount</th>
            <th class="!text-right">% of Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($plLines as [$label, $amt, $sign, $style, $hint]):
            $isTotal = $style === 'total';
            $amtCls = $style === 'line' ? '' : ($isTotal ? $tone($amt) : 'text-charcoal'); ?>
            <tr class="<?= $style === 'line' ? '' : 'bg-rose-soft/40' ?>">
              <td class="<?= $style === 'line' ? 'pl-8' : 'font-bold' ?> <?= $isTotal ? 'text-base' : '' ?>"><?= e($label) ?></td>
              <td class="text-xs text-sand"><?= e($hint) ?></td>
              <td class="text-right whitespace-nowrap <?= $style === 'line' ? '' : 'font-bold' ?> <?= $amtCls ?>"><?= $sign !== '' && $amt > 0 ? e($sign) . ' ' : '' ?><?= mmk($amt) ?></td>
              <td class="text-right text-sand"><?= $pct($amt) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="px-4 py-3 text-xs text-sand border-t border-rose-soft">
      Counts Completed orders only. Candle costs (COGS) are already deducted from order profit, while other incomes boost cash flow without affecting trading profit.
    </p>
  </section>

  <section class="card p-4">
    <h2 class="font-bold mb-1 text-copper"><i class="fa-solid fa-chart-column mr-2"></i>Income vs Expenses vs Net Result</h2>
    <p class="text-xs text-sand mb-3"><?= e($flowTitle) ?>. Income = order profit + other incomes.</p>
    <div class="relative h-72"><canvas id="flow"></canvas></div>
  </section>

  <section class="card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-4 pb-3">
      <h2 class="font-bold text-copper"><i class="fa-solid fa-wallet mr-2"></i>Expenses (<?= count($expRows) ?>)</h2>
      <a class="btn btn-ghost !py-1.5" href="expenses.php?range=<?= e($range) ?>"><i class="fa-solid fa-pen-to-square"></i> Manage expenses</a>
    </div>
    <div class="overflow-x-auto max-h-96 overflow-y-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th>Date</th>
            <th>Title</th>
            <th>Category</th>
            <th class="!text-right">Amount</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expRows as $r): ?>
            <tr>
              <td class="whitespace-nowrap"><?= e(date('d M Y', strtotime($r['expense_date']))) ?></td>
              <td class="font-semibold"><?= e($r['title']) ?></td>
              <td><?= expense_badge($r['category']) ?></td>
              <td class="text-right whitespace-nowrap font-semibold text-rose-700"><?= mmk($r['amount_mmk']) ?></td>
              <td class="text-sand max-w-xs truncate" title="<?= e($r['notes'] ?? '') ?>"><?= e($r['notes'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$expRows): ?><tr>
              <td colspan="5" class="text-center text-sand py-8">No expenses in this period.</td>
            </tr><?php endif; ?>
        </tbody>
        <?php if ($expRows): ?>
          <tfoot>
            <tr class="bg-rose-soft/40">
              <td colspan="3" class="font-bold">Total expenses</td>
              <td class="text-right font-bold text-rose-700 whitespace-nowrap"><?= mmk($totalExpenses) ?></td>
              <td></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </section>

  <section class="card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-4 pb-3">
      <h2 class="font-bold text-copper"><i class="fa-solid fa-hand-holding-dollar mr-2"></i>Other Incomes (<?= count($incRows) ?>)</h2>
      <button class="btn" onclick="openIncomeAdd()"><i class="fa-solid fa-plus"></i> Add Income</button>
    </div>
    <div class="overflow-x-auto max-h-96 overflow-y-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th>Date</th>
            <th>Title</th>
            <th>Source</th>
            <th class="!text-right">Amount</th>
            <th>Notes</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($incRows as $r): ?>
            <tr>
              <td class="whitespace-nowrap"><?= e(date('d M Y', strtotime($r['income_date']))) ?></td>
              <td class="font-semibold"><?= e($r['title']) ?></td>
              <td><?= source_badge($r['source']) ?></td>
              <td class="text-right whitespace-nowrap font-semibold text-emerald-700"><?= mmk($r['amount_mmk']) ?></td>
              <td class="text-sand max-w-xs truncate" title="<?= e($r['notes'] ?? '') ?>"><?= e($r['notes'] ?? '') ?></td>
              <td class="whitespace-nowrap">
                <button class="btn btn-ghost !px-3 !py-1.5" title="Edit income" onclick='editIncome(<?= json_encode(['id' => (int)$r['id'], 'title' => $r['title'], 'source' => $r['source'], 'amount_mmk' => $r['amount_mmk'], 'income_date' => $r['income_date'], 'notes' => $r['notes'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'><i class="fa-solid fa-pen"></i></button>
                <form method="post" class="inline" onsubmit="return confirm('Delete this income entry? This cannot be undone.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="income_delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="range" value="<?= e($range) ?>">
                  <button class="btn btn-ghost !px-3 !py-1.5 !text-rose-700" title="Delete income"><i class="fa-solid fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$incRows): ?><tr>
              <td colspan="6" class="text-center text-sand py-8">No other incomes in this period.</td>
            </tr><?php endif; ?>
        </tbody>
        <?php if ($incRows): ?>
          <tfoot>
            <tr class="bg-rose-soft/40">
              <td colspan="3" class="font-bold">Total other incomes</td>
              <td class="text-right font-bold text-emerald-700 whitespace-nowrap"><?= mmk($otherIncome) ?></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </section>

  <section class="card overflow-hidden">
    <h2 class="font-bold px-4 pt-4 pb-3 text-copper"><i class="fa-solid fa-layer-group mr-2"></i>Category Profitability</h2>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th>Category</th>
            <th>Units Sold</th>
            <th>Gross Revenue</th>
            <th>Cost</th>
            <th>Net Profit</th>
            <th>Margin</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($catRows as $name => $r): $m = $r['rev'] > 0 ? $r['profit'] / $r['rev'] * 100 : 0; ?>
            <tr>
              <td class="font-semibold"><?= e($name) ?></td>
              <td><?= number_format((int)$r['units']) ?></td>
              <td class="whitespace-nowrap"><?= mmk($r['rev']) ?></td>
              <td class="whitespace-nowrap"><?= mmk($r['cost']) ?></td>
              <td class="whitespace-nowrap font-semibold text-emerald-700"><?= mmk($r['profit']) ?></td>
              <td><?= number_format($m, 1) ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card overflow-hidden">
    <h2 class="font-bold px-4 pt-4 pb-3 text-copper"><i class="fa-solid fa-trophy mr-2"></i>Top 5 Profit-Generating Products</h2>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th>#</th>
            <th>Product</th>
            <th>Category</th>
            <th>Units Sold</th>
            <th>Revenue</th>
            <th>Net Profit</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top as $i => $t): ?>
            <tr>
              <td class="font-bold text-copper"><?= $i + 1 ?></td>
              <td>
                <p class="font-semibold"><?= e($t['name_en']) ?> <span class="text-sand font-normal">(<?= e($t['description_en']) ?>)</span></p>
                <p class="text-xs text-sand"><?= e($t['id']) ?></p>
              </td>
              <td><?= e($t['category']) ?></td>
              <td><?= number_format((int)$t['units']) ?></td>
              <td class="whitespace-nowrap"><?= mmk($t['rev']) ?></td>
              <td class="whitespace-nowrap font-semibold text-emerald-700"><?= mmk($t['profit']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$top): ?><tr>
              <td colspan="6" class="text-center text-sand py-8">No completed sales in this period.</td>
            </tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card p-4">
    <h2 class="font-bold mb-3 text-copper"><i class="fa-solid fa-chart-column mr-2"></i>Monthly Performance — Revenue vs Profit (last 12 months)</h2>
    <div class="relative h-72"><canvas id="monthly"></canvas></div>
  </section>
</main>

<div id="incModal" class="modal-bg">
  <form method="post" id="incForm" class="modal-box max-w-md p-6 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="iAction" value="income_add">
    <input type="hidden" name="id" id="iId" value="">
    <input type="hidden" name="range" value="<?= e($range) ?>">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-bold text-copper"><i class="fa-solid fa-hand-holding-dollar mr-2"></i><span id="iTitle">New Income</span></h2>
      <button type="button" onclick="closeModal('incModal')" class="text-sand hover:text-charcoal"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div><label class="label">Title</label><input name="title" id="iName" class="input" required maxlength="255" placeholder="e.g. Partner capital top-up"></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">Source</label>
        <select name="source" id="iSrc" class="input"><?php foreach (INCOME_SOURCES as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select>
      </div>
      <div><label class="label">Amount (MMK)</label><input type="number" name="amount_mmk" id="iAmt" class="input" required min="0.01" step="0.01" placeholder="0"></div>
    </div>
    <div><label class="label">Date &amp; time</label><input type="datetime-local" name="income_date" id="iDate" class="input" required></div>
    <div><label class="label">Notes (optional)</label><textarea name="notes" id="iNotes" class="input" rows="3" maxlength="2000"></textarea></div>
    <div class="flex justify-end gap-2">
      <button type="button" class="btn btn-ghost" onclick="closeModal('incModal')">Cancel</button>
      <button class="btn"><i class="fa-solid fa-check"></i> Save</button>
    </div>
  </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
  const nowLocal = () => {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
  };

  function openIncomeAdd() {
    document.getElementById('incForm').reset();
    iAction.value = 'income_add';
    iId.value = '';
    iTitle.textContent = 'New Income';
    iDate.value = nowLocal();
    openModal('incModal');
  }

  function editIncome(x) {
    document.getElementById('incForm').reset();
    iAction.value = 'income_edit';
    iId.value = x.id;
    iTitle.textContent = 'Edit Income';
    iName.value = x.title;
    iSrc.value = x.source;
    iAmt.value = x.amount_mmk;
    iDate.value = x.income_date.replace(' ', 'T').slice(0, 16);
    iNotes.value = x.notes || '';
    openModal('incModal');
  }

  const tip = {
    callbacks: {
      label: c => c.dataset.label + ': ' + Math.round(c.parsed.y).toLocaleString() + ' MMK'
    }
  };

  new Chart(document.getElementById('flow'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($flowLabels) ?>,
      datasets: [{
          type: 'bar',
          label: 'Total Income',
          data: <?= json_encode($flowIncome) ?>,
          backgroundColor: '#E8B4B8',
          borderRadius: 6,
          order: 2
        },
        {
          type: 'bar',
          label: 'Total Expenses',
          data: <?= json_encode($flowExpense) ?>,
          backgroundColor: '#6F6164',
          borderRadius: 6,
          order: 2
        },
        {
          type: 'line',
          label: 'Net Result',
          data: <?= json_encode($flowNet) ?>,
          borderColor: '#047857',
          backgroundColor: '#047857',
          tension: .25,
          pointRadius: 3,
          order: 1
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        tooltip: tip
      },
      scales: {
        y: {
          ticks: {
            callback: v => v.toLocaleString()
          },
          grid: {
            color: '#FBE8EC'
          }
        },
        x: {
          grid: {
            display: false
          }
        }
      }
    }
  });

  new Chart(document.getElementById('monthly'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labels) ?>,
      datasets: [{
          label: 'Revenue',
          data: <?= json_encode($rev) ?>,
          backgroundColor: '#E8B4B8',
          borderRadius: 6
        },
        {
          label: 'Net Profit',
          data: <?= json_encode($prof) ?>,
          backgroundColor: '#C86D7C',
          borderRadius: 6
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        tooltip: tip
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: v => v.toLocaleString()
          },
          grid: {
            color: '#FBE8EC'
          }
        },
        x: {
          grid: {
            display: false
          }
        }
      }
    }
  });
</script>
</body>

</html>