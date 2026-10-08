<?php
require_once __DIR__ . '/dbconfig.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $id = $_POST['id'] ?? '';
  $cost = (float)($_POST['original_price_mmk'] ?? 0);
  $sell = (float)($_POST['selling_price_mmk'] ?? 0);
  $costUsd = (float)($_POST['original_price_usd'] ?? 0);
  $sellUsd = (float)($_POST['selling_price_usd'] ?? 0);
  $nameMm = trim($_POST['name_mm'] ?? '');
  $nameEn = trim($_POST['name_en'] ?? '');
  $cat = in_array($_POST['category'] ?? '', ['Small', 'Normal', 'Large'], true) ? $_POST['category'] : '';
  if ($nameMm === '' || $nameEn === '' || $cat === '') {
    flash('Names and category are required.', 'error');
  } elseif ($cost <= 0 || $sell <= 0 || $costUsd < 0 || $sellUsd < 0) {
    flash('Prices must be positive numbers.', 'error');
  } else {
    $st = $pdo->prepare('UPDATE products SET name_mm=?, name_en=?, category=?, original_price_mmk=?, selling_price_mmk=?, original_price_usd=?, selling_price_usd=?,
            burn_time_mm=?, burn_time_en=?, description_mm=?, description_en=?, image_path=?, is_bestseller=?, is_available=? WHERE id=?');
    $st->execute([
      $nameMm,
      $nameEn,
      $cat,
      $cost,
      $sell,
      $costUsd,
      $sellUsd,
      trim($_POST['burn_time_mm'] ?? ''),
      trim($_POST['burn_time_en'] ?? ''),
      trim($_POST['description_mm'] ?? ''),
      trim($_POST['description_en'] ?? ''),
      trim($_POST['image_path'] ?? ''),
      isset($_POST['is_bestseller']) ? 1 : 0,
      isset($_POST['is_available']) ? 1 : 0,
      $id
    ]);
    flash('Product updated. Past orders keep their original price snapshots.');
  }
  redirect('products.php');
}

$products = $pdo->query("SELECT * FROM products ORDER BY FIELD(category,'Large','Normal','Small'), id")->fetchAll();
$avg = 0;
foreach ($products as $p) {
  $avg += $p['selling_price_mmk'] > 0 ? ($p['selling_price_mmk'] - $p['original_price_mmk']) / $p['selling_price_mmk'] * 100 : 0;
}
$avg = $products ? $avg / count($products) : 0;

$pageTitle = 'Products';
include __DIR__ . '/nav.php';
?>
<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div>
    <h1 class="text-2xl font-bold">Product Catalog</h1>
    <p class="text-sm text-sand"><?= count($products) ?> products · average margin <b class="text-copper"><?= number_format($avg, 1) ?>%</b></p>
  </div>

  <section class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($products as $p):
      $profit = $p['selling_price_mmk'] - $p['original_price_mmk'];
      $pct = $p['selling_price_mmk'] > 0 ? $profit / $p['selling_price_mmk'] * 100 : 0; ?>
      <article class="card p-4 flex gap-4 <?= $p['is_available'] ? '' : 'opacity-60' ?>">
        <img src="<?= e($p['image_path']) ?>" alt="<?= e($p['name_en']) ?>" onerror="this.style.visibility='hidden'" class="w-24 h-24 rounded-xl object-cover bg-rose-soft shrink-0">
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="text-xs text-sand"><?= e($p['id']) ?> · <?= e($p['category']) ?></p>
              <h3 class="font-bold leading-tight"><?= e($p['name_en']) ?></h3>
              <p class="text-xs text-sand"><?= e($p['name_mm']) ?> · <?= e($p['description_en']) ?> · <?= e($p['burn_time_en']) ?></p>
            </div>
            <button class="btn btn-ghost !px-2.5 !py-1.5 shrink-0" onclick='editProduct(<?= json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'><i class="fa-solid fa-pen"></i></button>
          </div>
          <div class="flex flex-wrap gap-1.5 my-2">
            <?php if ($p['is_bestseller']): ?><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gold/40 text-copper-dark border border-gold"><i class="fa-solid fa-star"></i> Bestseller</span><?php endif; ?>
            <?= $p['is_available'] ? '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">Available</span>' : '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-200">Unavailable</span>' ?>
          </div>
          <dl class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs">
            <dt class="text-sand">Cost</dt>
            <dd class="text-right font-semibold"><?= mmk($p['original_price_mmk']) ?></dd>
            <dt class="text-sand">Selling</dt>
            <dd class="text-right font-semibold"><?= mmk($p['selling_price_mmk']) ?></dd>
            <dt class="text-sand">Profit / unit</dt>
            <dd class="text-right font-semibold text-emerald-700"><?= mmk($profit) ?></dd>
            <dt class="text-sand">Margin</dt>
            <dd class="text-right font-bold text-copper"><?= number_format($pct, 1) ?>%</dd>
          </dl>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
</main>

<div id="editModal" class="modal-bg">
  <form method="post" class="modal-box max-w-xl p-6 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="f_id">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-bold text-copper"><i class="fa-solid fa-pen mr-2"></i><span id="f_title">Edit Product</span></h2>
      <button type="button" onclick="closeModal('editModal')" class="text-sand hover:text-charcoal"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">Name (Myanmar)</label><input name="name_mm" id="f_nmm" class="input" required maxlength="255"></div>
      <div><label class="label">Name (English)</label><input name="name_en" id="f_nen" class="input" required maxlength="255"></div>
      <div><label class="label">Category</label>
        <select name="category" id="f_cat" class="input">
          <option>Small</option>
          <option>Normal</option>
          <option>Large</option>
        </select>
      </div>
      <div><label class="label">Image path</label><input name="image_path" id="f_img" class="input" maxlength="255"></div>
      <div><label class="label">Burn time (Myanmar)</label><input name="burn_time_mm" id="f_bmm" class="input" maxlength="50"></div>
      <div><label class="label">Burn time (English)</label><input name="burn_time_en" id="f_ben" class="input" maxlength="50"></div>
      <div><label class="label">Description (Myanmar)</label><input name="description_mm" id="f_dmm" class="input"></div>
      <div><label class="label">Description (English)</label><input name="description_en" id="f_den" class="input"></div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">Cost price (MMK)</label><input type="number" step="1" min="1" name="original_price_mmk" id="f_cost" class="input" required oninput="preview()"></div>
      <div><label class="label">Selling price (MMK)</label><input type="number" step="1" min="1" name="selling_price_mmk" id="f_sell" class="input" required oninput="preview()"></div>
      <div><label class="label">Cost price (USD)</label><input type="number" step="0.01" min="0" name="original_price_usd" id="f_costu" class="input" required></div>
      <div><label class="label">Selling price (USD)</label><input type="number" step="0.01" min="0" name="selling_price_usd" id="f_sellu" class="input" required></div>
    </div>
    <p id="f_prev" class="text-sm rounded-xl bg-rose-soft/60 border border-rose-soft p-3 text-center"></p>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_bestseller" id="f_best" class="accent-[#C86D7C] w-4 h-4"> Bestseller</label>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_available" id="f_avail" class="accent-[#C86D7C] w-4 h-4"> Available for new orders</label>
    <div class="flex justify-end gap-2">
      <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
      <button class="btn"><i class="fa-solid fa-check"></i> Save</button>
    </div>
  </form>
</div>
<script>
  function preview() {
    const c = +f_cost.value,
      s = +f_sell.value,
      p = s - c;
    f_prev.innerHTML = s > 0 ? `Profit per unit: <b class="${p >= 0 ? 'text-emerald-700' : 'text-rose-700'}">${fmt(p)}</b> · Margin: <b class="text-copper">${(p / s * 100).toFixed(1)}%</b>` : '';
  }

  function editProduct(p) {
    f_id.value = p.id;
    f_title.textContent = p.id + ' — ' + p.name_en + ' (' + p.description_en + ')';
    f_cost.value = Math.round(p.original_price_mmk);
    f_sell.value = Math.round(p.selling_price_mmk);
    f_nmm.value = p.name_mm;
    f_nen.value = p.name_en;
    f_cat.value = p.category;
    f_img.value = p.image_path || '';
    f_bmm.value = p.burn_time_mm || '';
    f_ben.value = p.burn_time_en || '';
    f_dmm.value = p.description_mm || '';
    f_den.value = p.description_en || '';
    f_costu.value = p.original_price_usd;
    f_sellu.value = p.selling_price_usd;
    f_best.checked = p.is_bestseller == 1;
    f_avail.checked = p.is_available == 1;
    preview();
    openModal('editModal');
  }
</script>
</body>

</html>