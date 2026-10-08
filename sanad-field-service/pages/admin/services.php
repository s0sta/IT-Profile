<?php
declare(strict_types=1);

/**
 * Admin — service catalog: add services, edit them in place and
 * (de)activate them. Only administrators reach this page.
 */

Auth::requireAdmin();

$add = [
    'code'         => '',
    'name'         => '',
    'description'  => '',
    'price'        => '',
    'duration_min' => '60',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'add' || $action === 'update') {
        $data = [
            'code'         => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'name'         => trim((string) ($_POST['name'] ?? '')),
            'description'  => trim((string) ($_POST['description'] ?? '')),
            'price'        => (float) ($_POST['price'] ?? 0),
            'duration_min' => (int) ($_POST['duration_min'] ?? 60),
        ];
        if ($action === 'add') {
            $add = [
                'code'         => $data['code'],
                'name'         => $data['name'],
                'description'  => $data['description'],
                'price'        => (string) ($_POST['price'] ?? ''),
                'duration_min' => (string) ($_POST['duration_min'] ?? '60'),
            ];
        }

        $duplicate = Database::value('SELECT COUNT(*) FROM services WHERE code = ? AND id <> ?', [$data['code'], $id]);
        if ($data['code'] === '') {
            flash('error', t('sv.err_code'));
        } elseif ($data['name'] === '') {
            flash('error', t('sv.err_name'));
        } elseif ($duplicate) {
            flash('error', t('sv.err_code') . ' — ' . t('au.err_duplicate'));
        } else {
            if ($action === 'add') {
                Services::create($data);
                flash('success', t('sv.created'));
            } else {
                Services::update($id, $data);
                flash('success', t('sv.saved'));
            }
            redirect('admin/services');
        }
    } elseif ($action === 'toggle') {
        Services::toggleActive($id);
        flash('success', t('sv.toggled'));
        redirect('admin/services');
    }
}

$currency = (string) setting('currency', 'AED');
$services = Services::all(true);
$inactive = array_values(array_filter(
    Services::all(),
    static fn (array $s): bool => !$s['active']
));

layout_header(t('sv.title'), 'admin/services');
?>

<section class="panel">
  <h2 class="panel-title"><?= e(t('sv.add')) ?></h2>
  <form method="post" action="<?= u('admin/services') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="form-col">
        <label class="field-label" for="code"><?= e(t('sv.code')) ?> *</label>
        <input class="input" id="code" name="code" type="text" value="<?= e($add['code']) ?>" required>

        <label class="field-label" for="name"><?= e(t('sv.name')) ?> *</label>
        <input class="input" id="name" name="name" type="text" value="<?= e($add['name']) ?>" placeholder="<?= e(t('sv.name_ph')) ?>" required>

        <label class="field-label" for="description"><?= e(t('sv.description')) ?></label>
        <textarea class="input" id="description" name="description" rows="3"><?= e($add['description']) ?></textarea>
      </div>
      <div class="form-col">
        <label class="field-label" for="price"><?= e(t('sv.price', ['currency' => $currency])) ?></label>
        <input class="input" id="price" name="price" type="number" step="0.01" min="0" value="<?= e($add['price']) ?>">

        <label class="field-label" for="duration_min"><?= e(t('sv.duration')) ?></label>
        <input class="input" id="duration_min" name="duration_min" type="number" step="1" min="0" value="<?= e($add['duration_min']) ?>">
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= e(t('sv.add')) ?></button>
    </div>
  </form>
</section>

<section class="panel">
  <div class="panel-head">
    <span class="result-count"><?= e(t('sv.list', ['n' => count($services)])) ?></span>
  </div>

  <?php if (!$services): ?>
    <div class="empty"><?= e(t('common.none')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('sv.code')) ?></th>
            <th><?= e(t('sv.name')) ?></th>
            <th><?= e(t('sv.description')) ?></th>
            <th><?= e(t('sv.price', ['currency' => $currency])) ?></th>
            <th><?= e(t('sv.duration')) ?></th>
            <th><?= e(t('common.active')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($services as $svc): $formId = 'svc-' . (int) $svc['id']; ?>
            <tr>
              <td>
                <form id="<?= e($formId) ?>" method="post" action="<?= u('admin/services') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int) $svc['id'] ?>">
                </form>
                <input class="input input-sm" form="<?= e($formId) ?>" type="text" name="code" value="<?= e($svc['code']) ?>" required>
              </td>
              <td><input class="input" form="<?= e($formId) ?>" type="text" name="name" value="<?= e($svc['name']) ?>" required></td>
              <td><input class="input" form="<?= e($formId) ?>" type="text" name="description" value="<?= e($svc['description']) ?>"></td>
              <td>
                <input class="input input-sm" form="<?= e($formId) ?>" type="number" step="0.01" min="0" name="price" value="<?= e((string) $svc['price']) ?>">
                <div class="cell-muted"><?= e(money((float) $svc['price'])) ?></div>
              </td>
              <td><input class="input input-sm" form="<?= e($formId) ?>" type="number" step="1" min="0" name="duration_min" value="<?= (int) $svc['duration_min'] ?>"></td>
              <td>
                <?php if ($svc['active']): ?>
                  <span class="badge badge-active"><?= e(t('common.active')) ?></span>
                <?php else: ?>
                  <span class="badge badge-inactive"><?= e(t('common.inactive')) ?></span>
                <?php endif; ?>
              </td>
              <td class="cell-actions">
                <button class="btn btn-primary btn-sm" form="<?= e($formId) ?>" type="submit"><?= e(t('common.save')) ?></button>
                <form class="inline-form" method="post" action="<?= u('admin/services') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $svc['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= e($svc['active'] ? t('common.deactivate') : t('common.activate')) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if ($inactive): ?>
<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('common.inactive')) ?></h2>
    <span class="result-count"><?= e(t('sv.list', ['n' => count($inactive)])) ?></span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e(t('sv.code')) ?></th>
          <th><?= e(t('sv.name')) ?></th>
          <th><?= e(t('sv.price', ['currency' => $currency])) ?></th>
          <th><?= e(t('common.active')) ?></th>
          <th><?= e(t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($inactive as $svc): ?>
          <tr>
            <td><?= e($svc['code']) ?></td>
            <td class="cell-subject"><?= e($svc['name']) ?></td>
            <td><?= e(money((float) $svc['price'])) ?></td>
            <td><span class="badge badge-inactive"><?= e(t('common.inactive')) ?></span></td>
            <td class="cell-actions">
              <form class="inline-form" method="post" action="<?= u('admin/services') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $svc['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('common.activate')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php layout_footer(); ?>
