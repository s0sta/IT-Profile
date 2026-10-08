<?php
declare(strict_types=1);

/**
 * Admin · Task Categories — create, inline edit, list and toggle active.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'update') {
        $data = [
            'name_ar' => trim((string) ($_POST['name_ar'] ?? '')),
            'name_en' => trim((string) ($_POST['name_en'] ?? '')),
        ];

        if ($data['name_ar'] === '') {
            flash('error', t('ac.err_name'));
        } elseif ($action === 'add') {
            TaskCategories::create($data);
            flash('success', t('ac.created'));
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                TaskCategories::update($id, $data);
                flash('success', t('ac.updated'));
            }
        }
        redirect('admin/categories');
    }

    if ($action === 'toggle') {
        TaskCategories::toggleActive((int) ($_POST['id'] ?? 0));
        flash('success', t('ac.toggled'));
        redirect('admin/categories');
    }

    redirect('admin/categories');
}

$cats = TaskCategories::all();

layout_header(t('ac.title'), 'admin/categories');
?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('ac.add')) ?></h2>
    <form method="post" action="<?= u('admin/categories') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <label class="field-label"><?= e(t('ac.name_ar')) ?></label>
      <input class="input" name="name_ar" required placeholder="<?= e(t('ac.name_placeholder')) ?>">
      <label class="field-label"><?= e(t('ac.name_en')) ?></label>
      <input class="input" name="name_en">
      <button class="btn btn-primary" type="submit"><?= e(t('common.add')) ?></button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('ac.list')) ?> (<?= count($cats) ?>)</h2>
    <?php if (!$cats): ?>
      <div class="empty"><?= e(t('common.no_results')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th><?= e(t('common.name')) ?></th>
            <th><?= e(t('ac.name_en')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr></thead>
          <tbody>
            <?php foreach ($cats as $cat): $catId = (int) $cat['id']; ?>
              <tr>
                <td>
                  <?= e(TaskCategories::label($cat)) ?>
                  <form method="post" action="<?= u('admin/categories') ?>" class="inline-form" style="margin-top:4px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $catId ?>">
                    <input class="input input-sm" name="name_ar" value="<?= e((string) $cat['name_ar']) ?>" placeholder="<?= e(t('ac.name_placeholder')) ?>" required>
                    <input class="input input-sm" name="name_en" value="<?= e((string) $cat['name_en']) ?>" placeholder="<?= e(t('ac.name_en')) ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('common.save')) ?></button>
                  </form>
                </td>
                <td><?= e((string) $cat['name_en'] !== '' ? (string) $cat['name_en'] : '—') ?></td>
                <td><?= (int) $cat['active'] === 1
                      ? '<span class="badge badge-active">' . e(t('common.active')) . '</span>'
                      : '<span class="badge badge-inactive">' . e(t('common.inactive')) . '</span>' ?></td>
                <td>
                  <form method="post" action="<?= u('admin/categories') ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $catId ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $cat['active'] === 1 ? e(t('common.inactive')) : e(t('common.active')) ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php layout_footer(); ?>
