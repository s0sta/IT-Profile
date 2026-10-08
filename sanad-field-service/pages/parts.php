<?php
declare(strict_types=1);

$q = trim((string) ($_GET['q'] ?? ''));
$showLow = (string) ($_GET['low'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Auth::requireOffice();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $sku  = trim((string) ($_POST['sku'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($sku === '') {
            flash('error', t('part.err_sku'));
        } elseif ($name === '') {
            flash('error', t('part.err_name'));
        } elseif (Database::value('SELECT COUNT(*) FROM parts WHERE sku = ?', [strtoupper($sku)])) {
            flash('error', t('part.err_sku') . ' — ' . t('au.err_duplicate'));
        } else {
            Parts::create($_POST);
            flash('success', t('parts.created'));
        }
    } elseif ($action === 'update') {
        Parts::update((int) ($_POST['id'] ?? 0), $_POST);
        flash('success', t('parts.saved'));
    } elseif ($action === 'receive') {
        $qty = (float) ($_POST['qty'] ?? 0);
        if ($qty <= 0) {
            flash('error', t('part.err_qty'));
        } else {
            Parts::adjustStock((int) ($_POST['id'] ?? 0), $qty, 'goods received');
            flash('success', t('parts.received'));
        }
    }
    redirect('parts');
}

$rows = $showLow ? Parts::lowStock() : Parts::all(false, $q);

layout_header(t('parts.title'), 'parts');
?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('parts') ?>">
    <div class="filter-search"><?= icon('search') ?><input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('parts.search_placeholder')) ?>"></div>
    <label class="filter-date"><input type="checkbox" name="low" value="1" <?= $showLow ? 'checked' : '' ?>> <?= e(t('parts.low_stock')) ?></label>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= u('parts') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="panel-head">
    <span class="result-count"><?= e(t('parts.count', ['n' => count($rows)])) ?> · <?= e(t('parts.stock_value', ['currency' => (string) setting('currency', 'AED')])) ?>: <?= e(money(Parts::stockValue())) ?></span>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><?= e($showLow ? t('parts.no_low_stock') : t('parts.empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('part.sku')) ?></th>
            <th><?= e(t('part.name')) ?></th>
            <th><?= e(t('part.stock')) ?></th>
            <th><?= e(t('part.reorder_level')) ?></th>
            <th><?= e(t('part.cost')) ?></th>
            <th><?= e(t('part.price')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $p): $low = (float) $p['stock_qty'] <= (float) $p['reorder_level']; ?>
            <tr class="<?= $low ? 'row-warn' : '' ?>">
              <td>
                <form method="post" action="<?= u('parts') ?>" class="inline-form" id="part-form-<?= (int) $p['id'] ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <input class="input input-sm" name="sku" value="<?= e($p['sku']) ?>" required>
              </td>
              <td>
                  <input class="input input-sm" name="name" value="<?= e($p['name']) ?>" required>
                  <select class="input input-select input-sm" name="unit">
                    <?php foreach (['pc', 'box', 'm', 'l', 'kg', 'set', 'hour'] as $unit): ?>
                      <option value="<?= e($unit) ?>" <?= $p['unit'] === $unit ? 'selected' : '' ?>><?= e($unit) ?></option>
                    <?php endforeach; ?>
                  </select>
              </td>
              <td>
                <span class="badge <?= $low ? 'badge-pri-urgent' : 'badge-cat' ?>"><?= e((string) (float) $p['stock_qty']) ?> <?= e($p['unit']) ?></span>
                <?php if ($low): ?><div class="cell-muted"><?= e(t('parts.low_badge')) ?></div><?php endif; ?>
              </td>
              <td><input class="input input-sm" style="width:80px" type="number" step="0.01" min="0" name="reorder_level" value="<?= e((string) (float) $p['reorder_level']) ?>"></td>
              <td><input class="input input-sm" style="width:90px" type="number" step="0.01" min="0" name="cost_price" value="<?= e((string) (float) $p['cost_price']) ?>"></td>
              <td><input class="input input-sm" style="width:90px" type="number" step="0.01" min="0" name="sell_price" value="<?= e((string) (float) $p['sell_price']) ?>"></td>
              <td class="cell-actions">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('common.save')) ?></button>
                </form>
                <form method="post" action="<?= u('parts') ?>" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="receive">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <input class="input input-sm" style="width:80px" type="number" step="0.01" min="0.01" name="qty" placeholder="+0" required>
                  <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('parts.receive')) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('parts.add')) ?></h2>
  <form method="post" action="<?= u('parts') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="form-col">
        <label class="field-label"><?= e(t('part.sku')) ?> *</label>
        <input class="input" name="sku" required placeholder="<?= e(t('part.sku_ph')) ?>">
        <label class="field-label"><?= e(t('part.name')) ?> *</label>
        <input class="input" name="name" required placeholder="<?= e(t('part.name_ph')) ?>">
      </div>
      <div class="form-col">
        <div class="form-row">
          <div><label class="field-label"><?= e(t('part.unit')) ?></label>
            <select class="input input-select" name="unit">
              <?php foreach (['pc', 'box', 'm', 'l', 'kg', 'set', 'hour'] as $unit): ?>
                <option value="<?= e($unit) ?>"><?= e($unit) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label class="field-label"><?= e(t('part.stock')) ?></label><input class="input" type="number" step="0.01" min="0" name="stock_qty" value="0"></div>
        </div>
        <div class="form-row">
          <div><label class="field-label"><?= e(t('part.reorder_level')) ?></label><input class="input" type="number" step="0.01" min="0" name="reorder_level" value="0"></div>
          <div><label class="field-label"><?= e(t('part.cost')) ?></label><input class="input" type="number" step="0.01" min="0" name="cost_price" value="0"></div>
        </div>
        <label class="field-label"><?= e(t('part.price')) ?></label>
        <input class="input" type="number" step="0.01" min="0" name="sell_price" value="0">
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= e(t('common.add')) ?></button>
    </div>
  </form>
</section>

<?php layout_footer(); ?>
