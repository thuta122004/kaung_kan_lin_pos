<?php
require_once __DIR__ . '/finance_helpers.php';

$range = period_from_request();
$self = 'expenses.php?range=' . $range;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        if ($id > 0) {
            $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
            flash('Expense deleted.');
        }
        redirect($self);
    }

    if ($action === 'add' || $action === 'edit') {
        $isEdit = $action === 'edit';
        $title = post_str('title');
        $cat = post_str('category');
        $amount = parse_amount(post_str('amount_mmk'));
        $date = parse_datetime_input(post_str('expense_date'));
        $notes = post_str('notes');

        if ($title === '' || mb_strlen($title) > 255) {
            flash('Title is required (max 255 characters).', 'error');
        } elseif (!in_array($cat, EXPENSE_CATEGORIES, true)) {
            flash('Please choose a valid category.', 'error');
        } elseif ($amount === null) {
            flash('Amount must be a positive number (up to 9,999,999,999.99, max 2 decimals).', 'error');
        } elseif ($date === null) {
            flash('The expense date is not valid.', 'error');
        } elseif (mb_strlen($notes) > 2000) {
            flash('Notes are limited to 2,000 characters.', 'error');
        } else {
            try {
                $exists = true;
                if ($isEdit) {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE id = ?');
                    $chk->execute([$id]);
                    $exists = (bool)$chk->fetchColumn();
                }
                if (!$exists) {
                    flash('That expense no longer exists.', 'error');
                } elseif ($isEdit) {
                    $pdo->prepare('UPDATE expenses SET expense_date=?, category=?, title=?, amount_mmk=?, notes=? WHERE id=?')
                        ->execute([$date, $cat, $title, $amount, $notes !== '' ? $notes : null, $id]);
                    flash("Expense '$title' updated (" . mmk($amount) . ').');
                } else {
                    $pdo->prepare('INSERT INTO expenses (expense_date, category, title, amount_mmk, notes) VALUES (?,?,?,?,?)')
                        ->execute([$date, $cat, $title, $amount, $notes !== '' ? $notes : null]);
                    flash("Expense '$title' added (" . mmk($amount) . ').');
                }
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                flash('Could not save the expense. Please try again.', 'error');
            }
        }
    }
    redirect($self);
}

$cond = period_condition($range, 'expense_date');

$rows = $pdo->query("SELECT id, expense_date, category, title, amount_mmk, notes FROM expenses WHERE $cond ORDER BY expense_date DESC, id DESC")->fetchAll();
$k = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(amount_mmk),0) AS total, COALESCE(MAX(amount_mmk),0) AS biggest FROM expenses WHERE $cond")->fetch();
$byCat = $pdo->query("SELECT category, COUNT(*) AS n, SUM(amount_mmk) AS total FROM expenses WHERE $cond GROUP BY category ORDER BY total DESC, category")->fetchAll();

$total = (float)$k['total'];
$top = $byCat[0] ?? null;
$topShare = ($top && $total > 0) ? (float)$top['total'] / $total * 100 : 0;

$pageTitle = 'Expenses';
include __DIR__ . '/nav.php';
?>
<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Expense Tracking</h1>
            <p class="text-sm text-sand"><?= (int)$k['n'] ?> expenses · <?= e(PERIOD_RANGES[$range]) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex gap-1.5 bg-white border border-rose-soft rounded-full p-1">
                <?php foreach (PERIOD_RANGES as $key => $label): ?>
                    <a href="?range=<?= e($key) ?>" class="px-3.5 py-1.5 rounded-full text-sm font-semibold transition <?= $range === $key ? 'bg-copper text-white' : 'text-sand hover:bg-rose-soft hover:text-copper' ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
            <button class="btn" onclick="openAdd()"><i class="fa-solid fa-plus"></i> Add Expense</button>
        </div>
    </div>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach (
            [
                ['Total Expenses', mmk($total), 'fa-wallet', ''],
                ['Entries', number_format((int)$k['n']), 'fa-list-check', ''],
                ['Top Category', $top ? $top['category'] : '—', 'fa-star', $top ? mmk($top['total']) . ' · ' . number_format($topShare, 1) . '%' : ''],
                ['Largest Expense', mmk($k['biggest']), 'fa-arrow-trend-up', ''],
            ] as [$t, $v, $ic, $sub]
        ): ?>
            <div class="card p-4 flex items-center gap-3">
                <span class="w-11 h-11 rounded-full bg-rose-soft text-copper flex items-center justify-center shrink-0"><i class="fa-solid <?= $ic ?>"></i></span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-sand"><?= e($t) ?></p>
                    <p class="text-lg font-bold"><?= e($v) ?></p>
                    <?php if ($sub !== ''): ?><p class="text-xs text-sand"><?= e($sub) ?></p><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <?php if ($byCat): ?>
        <section class="card p-4">
            <h2 class="font-bold mb-3 text-copper"><i class="fa-solid fa-chart-pie mr-2"></i>Spending by Category</h2>
            <div class="space-y-3">
                <?php foreach ($byCat as $c):
                    $share = $total > 0 ? (float)$c['total'] / $total * 100 : 0; ?>
                    <div class="grid grid-cols-[9rem_1fr_auto] items-center gap-3">
                        <div><?= expense_badge($c['category']) ?></div>
                        <div class="h-2 rounded-full bg-rose-soft overflow-hidden">
                            <div class="h-2 rounded-full bg-copper" style="width: <?= number_format($share, 1, '.', '') ?>%"></div>
                        </div>
                        <p class="text-sm text-right whitespace-nowrap"><b><?= mmk($c['total']) ?></b> <span class="text-sand">· <?= number_format($share, 1) ?>% · <?= (int)$c['n'] ?>×</span></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card overflow-hidden">
        <div class="p-3 border-b border-rose-soft flex flex-wrap items-center justify-between gap-2">
            <input id="q" class="input max-w-xs" placeholder="Search title or category…" oninput="filterRows()">
            <p id="shown" class="text-sm text-sand"></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full" id="tbl">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Amount (MMK)</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r):
                        $ts = strtotime($r['expense_date']); ?>
                        <tr data-search="<?= e(mb_strtolower($r['title'] . ' ' . $r['category'])) ?>" data-amount="<?= e($r['amount_mmk']) ?>">
                            <td class="whitespace-nowrap"><?= e(date('d M Y', $ts)) ?><p class="text-xs text-sand"><?= e(date('H:i', $ts)) ?></p>
                            </td>
                            <td class="font-semibold"><?= e($r['title']) ?></td>
                            <td><?= expense_badge($r['category']) ?></td>
                            <td class="whitespace-nowrap font-semibold text-rose-700"><?= mmk($r['amount_mmk']) ?></td>
                            <td class="text-sand max-w-xs truncate" title="<?= e($r['notes'] ?? '') ?>"><?= e($r['notes'] ?? '') ?></td>
                            <td class="whitespace-nowrap">
                                <button class="btn btn-ghost !px-3 !py-1.5" title="Edit expense" onclick='editExpense(<?= json_encode(['id' => (int)$r['id'], 'title' => $r['title'], 'category' => $r['category'], 'amount_mmk' => $r['amount_mmk'], 'expense_date' => $r['expense_date'], 'notes' => $r['notes'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'><i class="fa-solid fa-pen"></i></button>
                                <form method="post" class="inline" onsubmit="return confirm('Delete this expense? This cannot be undone.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="range" value="<?= e($range) ?>">
                                    <button class="btn btn-ghost !px-3 !py-1.5 !text-rose-700" title="Delete expense"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr>
                            <td colspan="6" class="text-center text-sand py-10">No expenses recorded for this period.</td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div id="expModal" class="modal-bg">
    <form method="post" id="expForm" class="modal-box max-w-md p-6 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="xAction" value="add">
        <input type="hidden" name="id" id="xId" value="">
        <input type="hidden" name="range" value="<?= e($range) ?>">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-copper"><i class="fa-solid fa-wallet mr-2"></i><span id="xTitle">New Expense</span></h2>
            <button type="button" onclick="closeModal('expModal')" class="text-sand hover:text-charcoal"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div><label class="label">Title</label><input name="title" id="xName" class="input" required maxlength="255" placeholder="e.g. Paraffin wax 50 kg"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label">Category</label>
                <select name="category" id="xCat" class="input"><?php foreach (EXPENSE_CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="label">Amount (MMK)</label><input type="number" name="amount_mmk" id="xAmt" class="input" required min="0.01" step="0.01" placeholder="0"></div>
        </div>
        <div><label class="label">Date &amp; time</label><input type="datetime-local" name="expense_date" id="xDate" class="input" required></div>
        <div><label class="label">Notes (optional)</label><textarea name="notes" id="xNotes" class="input" rows="3" maxlength="2000"></textarea></div>
        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-ghost" onclick="closeModal('expModal')">Cancel</button>
            <button class="btn"><i class="fa-solid fa-check"></i> Save</button>
        </div>
    </form>
</div>

<script>
    const nowLocal = () => {
        const d = new Date();
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    };

    function openAdd() {
        document.getElementById('expForm').reset();
        xAction.value = 'add';
        xId.value = '';
        xTitle.textContent = 'New Expense';
        xDate.value = nowLocal();
        openModal('expModal');
    }

    function editExpense(x) {
        document.getElementById('expForm').reset();
        xAction.value = 'edit';
        xId.value = x.id;
        xTitle.textContent = 'Edit Expense';
        xName.value = x.title;
        xCat.value = x.category;
        xAmt.value = x.amount_mmk;
        xDate.value = x.expense_date.replace(' ', 'T').slice(0, 16);
        xNotes.value = x.notes || '';
        openModal('expModal');
    }

    function filterRows() {
        const q = document.getElementById('q').value.trim().toLowerCase();
        let n = 0,
            sum = 0;
        document.querySelectorAll('#tbl tbody tr[data-search]').forEach(tr => {
            const show = tr.dataset.search.includes(q);
            tr.style.display = show ? '' : 'none';
            if (show) {
                n++;
                sum += parseFloat(tr.dataset.amount) || 0;
            }
        });
        document.getElementById('shown').textContent = n + ' shown · ' + fmt(sum);
    }
    filterRows();
</script>
</body>

</html>