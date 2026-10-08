<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', t('ac.err_name'));
        } else {
            Categories::create($name, trim((string) ($_POST['description'] ?? '')));
            flash('success', t('ac.created'));
        }
    } elseif ($action === 'update') {
        Categories::update((int) ($_POST['id'] ?? 0), trim((string) ($_POST['name'] ?? '')), trim((string) ($_POST['description'] ?? '')));
        flash('success', t('ac.updated'));
    } elseif ($action === 'toggle') {
        Categories::toggleActive((int) ($_POST['id'] ?? 0));
        flash('success', t('ac.toggled'));
    }
    redirect('admin/categories');
}

$cats = Categories::all();
layout_header(t('ac.title'), 'admin/categories');
?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('ac.add')) ?></h2>
    <form method="post" action="<?= u('admin/categories') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <label class="field-label"><?= e(t('ac.name')) ?></label>
      <input class="input" name="name" required placeholder="<?= e(t('ac.name_placeholder')) ?>">
      <label class="field-label"><?= e(t('ac.description')) ?></label>
      <input class="input" name="description" placeholder="<?= e(t('ac.description_placeholder')) ?>">
      <button class="btn btn-primary" type="submit"><?= e(t('ac.add_button')) ?></button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('ac.list')) ?> (<?= count($cats) ?>)</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('ac.name')) ?></th><th><?= e(t('ac.description')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($cats as $c): ?>
            <tr>
              <td>
                <?= e($c['name']) ?>
                <form method="post" action="<?= u('admin/categories') ?>" class="inline-form" style="margin-top:4px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <input class="input input-sm" name="name" value="<?= e($c['name']) ?>" required>
                  <input class="input input-sm" name="description" value="<?= e($c['description']) ?>" placeholder="<?= e(t('ac.description')) ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('ac.save')) ?></button>
                </form>
              </td>
              <td><?= e($c['description'] ?: '—') ?></td>
              <td><?= $c['active'] ? '<span class="badge badge-active">' . e(t('ac.active')) . '</span>' : '<span class="badge badge-inactive">' . e(t('ac.hidden')) . '</span>' ?></td>
              <td>
                <form method="post" action="<?= u('admin/categories') ?>" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= $c['active'] ? e(t('ac.hide')) : e(t('ac.show')) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php layout_footer(); ?>
