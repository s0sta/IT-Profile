<?php
declare(strict_types=1);

/**
 * Admin · Approval Types — create, inline edit, list and toggle active.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'update') {
        $data = [
            'name_ar' => trim((string) ($_POST['name_ar'] ?? '')),
            'name_en' => trim((string) ($_POST['name_en'] ?? '')),
        ];

        $excludeId = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
        if ($data['name_ar'] === '') {
            flash('error', t('at.err_name'));
        } elseif (ApprovalTypes::isDuplicate($data, $excludeId)) {
            flash('error', t('at.err_dup'));
        } elseif ($action === 'add') {
            ApprovalTypes::create($data);
            flash('success', t('at.created'));
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                ApprovalTypes::update($id, $data);
                flash('success', t('at.updated'));
            }
        }
        redirect('admin/types');
    }

    if ($action === 'toggle') {
        ApprovalTypes::toggleActive((int) ($_POST['id'] ?? 0));
        flash('success', t('at.toggled'));
        redirect('admin/types');
    }

    redirect('admin/types');
}

$types = ApprovalTypes::all();

layout_header(t('at.title'), 'admin/types');
?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('at.add')) ?></h2>
    <form method="post" action="<?= u('admin/types') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <label class="field-label"><?= e(t('at.name_ar')) ?></label>
      <input class="input" name="name_ar" required placeholder="<?= e(t('at.name_placeholder')) ?>">
      <label class="field-label"><?= e(t('at.name_en')) ?></label>
      <input class="input" name="name_en">
      <button class="btn btn-primary" type="submit"><?= e(t('common.add')) ?></button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('at.list')) ?> (<?= count($types) ?>)</h2>
    <?php if (!$types): ?>
      <div class="empty"><?= e(t('common.no_results')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th><?= e(t('common.name')) ?></th>
            <th><?= e(t('at.name_en')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr></thead>
          <tbody>
            <?php foreach ($types as $type): $typeId = (int) $type['id']; ?>
              <tr>
                <td>
                  <?= e(ApprovalTypes::label($type)) ?>
                  <form method="post" action="<?= u('admin/types') ?>" class="inline-form" style="margin-top:4px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $typeId ?>">
                    <input class="input input-sm" name="name_ar" value="<?= e((string) $type['name_ar']) ?>" placeholder="<?= e(t('at.name_placeholder')) ?>" required>
                    <input class="input input-sm" name="name_en" value="<?= e((string) $type['name_en']) ?>" placeholder="<?= e(t('at.name_en')) ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('common.save')) ?></button>
                  </form>
                </td>
                <td><?= e((string) $type['name_en'] !== '' ? (string) $type['name_en'] : '—') ?></td>
                <td><?= (int) $type['active'] === 1
                      ? '<span class="badge badge-active">' . e(t('common.active')) . '</span>'
                      : '<span class="badge badge-inactive">' . e(t('common.inactive')) . '</span>' ?></td>
                <td>
                  <form method="post" action="<?= u('admin/types') ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $typeId ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $type['active'] === 1 ? e(t('common.inactive')) : e(t('common.active')) ?></button>
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
