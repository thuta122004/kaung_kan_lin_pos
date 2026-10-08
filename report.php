<?php
require_once __DIR__ . '/dbconfig.php';

$ranges = ['all' => 'All Time', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month'];
$range = isset($ranges[$_GET['range'] ?? '']) ? $_GET['range'] : 'all';
$cond = [
    'all'   => '1=1',
    'today' => 'DATE(o.order_date) = CURDATE()',
    'week'  => 'YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1)',
    'month' => 'YEAR(o.order_date) = YEAR(CURDATE()) AND MONTH(o.order_date) = MONTH(CURDATE())',
][$range];

$k = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(total_revenue_mmk),0) AS rev, COALESCE(SUM(total_cost_mmk),0) AS cogs, COALESCE(SUM(net_profit_mmk),0) AS profit
                  FROM orders o WHERE o.status = 'Completed' AND $cond")->fetch();
$margin = $k['rev'] > 0 ? $k['profit'] / $k['rev'] * 100 : 0;

$cats = $pdo->query("SELECT p.category, SUM(oi.quantity) AS units, SUM(oi.subtotal_revenue_mmk) AS rev, SUM(oi.subtotal_cost_mmk) AS cost, SUM(oi.subtotal_profit_mmk) AS profit
                     FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
                     WHERE o.status = 'Completed' AND $cond GROUP BY p.category ORDER BY FIELD(p.category,'Small','Normal','Large')")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
$catRows = [];
foreach (['Small', 'Normal', 'Large'] as $c) {
    $r = $cats[$c] ?? ['units' => 0, 'rev' => 0, 'cost' => 0, 'profit' => 0];
    $catRows[$c] = $r;
}

$top = $pdo->query("SELECT p.id, p.name_en, p.description_en, p.category, SUM(oi.quantity) AS units, SUM(oi.subtotal_revenue_mmk) AS rev, SUM(oi.subtotal_profit_mmk) AS profit
                    FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
                    WHERE o.status = 'Completed' AND $cond GROUP BY p.id, p.name_en, p.description_en, p.category
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

$pageTitle = 'Reports & Analytics';
include __DIR__ . '/nav.php';
?>
<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold">Reports &amp; Analytics</h1>
      <p class="text-sm text-sand"><?= (int)$k['n'] ?> completed orders · <?= e($ranges[$range]) ?></p>
    </div>
    <div class="flex gap-1.5 bg-white border border-rose-soft rounded-full p-1">
      <?php foreach ($ranges as $key => $label): ?>
        <a href="?range=<?= $key ?>" class="px-3.5 py-1.5 rounded-full text-sm font-semibold transition <?= $range === $key ? 'bg-copper text-white' : 'text-sand hover:bg-rose-soft hover:text-copper' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php foreach ([
      ['Total Revenue', mmk($k['rev']), 'fa-coins', 'text-copper'],
      ['Cost of Goods Sold', mmk($k['cogs']), 'fa-boxes-stacked', 'text-charcoal'],
      ['Net Profit', mmk($k['profit']), 'fa-sack-dollar', 'text-emerald-700'],
      ['Profit Margin', number_format($margin, 1) . '%', 'fa-percent', 'text-copper'],
    ] as [$t, $v, $ic, $cl]): ?>
      <div class="card p-4">
        <div class="flex items-center justify-between"><p class="text-xs font-semibold text-sand"><?= $t ?></p><i class="fa-solid <?= $ic ?> text-gold"></i></div>
        <p class="text-xl font-bold mt-1 <?= $cl ?>"><?= e($v) ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card overflow-hidden">
    <h2 class="font-bold px-4 pt-4 pb-3 text-copper"><i class="fa-solid fa-layer-group mr-2"></i>Category Profitability</h2>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead><tr><th>Category</th><th>Units Sold</th><th>Gross Revenue</th><th>Cost</th><th>Net Profit</th><th>Margin</th></tr></thead>
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
        <thead><tr><th>#</th><th>Product</th><th>Category</th><th>Units Sold</th><th>Revenue</th><th>Net Profit</th></tr></thead>
        <tbody>
        <?php foreach ($top as $i => $t): ?>
          <tr>
            <td class="font-bold text-copper"><?= $i + 1 ?></td>
            <td><p class="font-semibold"><?= e($t['name_en']) ?> <span class="text-sand font-normal">(<?= e($t['description_en']) ?>)</span></p><p class="text-xs text-sand"><?= e($t['id']) ?></p></td>
            <td><?= e($t['category']) ?></td>
            <td><?= number_format((int)$t['units']) ?></td>
            <td class="whitespace-nowrap"><?= mmk($t['rev']) ?></td>
            <td class="whitespace-nowrap font-semibold text-emerald-700"><?= mmk($t['profit']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$top): ?><tr><td colspan="6" class="text-center text-sand py-8">No completed sales in this period.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card p-4">
    <h2 class="font-bold mb-3 text-copper"><i class="fa-solid fa-chart-column mr-2"></i>Monthly Performance — Revenue vs Profit (last 12 months)</h2>
    <div class="relative h-72"><canvas id="monthly"></canvas></div>
  </section>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('monthly'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      { label: 'Revenue', data: <?= json_encode($rev) ?>, backgroundColor: '#E8B4B8', borderRadius: 6 },
      { label: 'Net Profit', data: <?= json_encode($prof) ?>, backgroundColor: '#C86D7C', borderRadius: 6 }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + Math.round(c.parsed.y).toLocaleString() + ' MMK' } } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() }, grid: { color: '#FBE8EC' } }, x: { grid: { display: false } } }
  }
});
</script>
</body></html>
