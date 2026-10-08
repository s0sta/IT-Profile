<?php
declare(strict_types=1);

/**
 * Admin · Departments — create, inline edit, list with usage counts and toggle.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'update') {
        $data = [
            'name_ar'    => trim((string) ($_POST['name_ar'] ?? '')),
            'name_en'    => trim((string) ($_POST['name_en'] ?? '')),
            'code'       => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'manager_id' => (int) ($_POST['manager_id'] ?? 0),
        ];

        $excludeId = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
        if ($data['name_ar'] === '') {
            flash('error', t('ad.err_name'));
        } elseif (Departments::isDuplicate($data, $excludeId)) {
            flash('error', t('ad.err_dup'));
        } elseif ($action === 'add') {
            Departments::create($data);
            flash('success', t('ad.created'));
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                Departments::update($id, $data);
                flash('success', t('ad.updated'));
            }
        }
        redirect('admin/departments');
    }

    if ($action === 'toggle') {
        Departments::toggleActive((int) ($_POST['id'] ?? 0));
        flash('success', t('ad.toggled'));
        redirect('admin/departments');
    }

    redirect('admin/departments');
}

$depts    = Departments::all();
$managers = Users::approvers();

$managerNames = [];
foreach (Users::all() as $row) {
    $managerNames[(int) $row['id']] = (string) $row['name'];
}

$userCounts = [];
$taskCounts = [];
foreach ($depts as $dept) {
    $userCounts[(int) $dept['id']] = (int) Database::value('SELECT COUNT(*) FROM users WHERE department_id = ?', [(int) $dept['id']]);
    $taskCounts[(int) $dept['id']] = (int) Database::value('SELECT COUNT(*) FROM tasks WHERE department_id = ?', [(int) $dept['id']]);
}

layout_header(t('ad.title'), 'admin/departments');
?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('ad.add')) ?></h2>
    <form method="post" action="<?= u('admin/departments') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <label class="field-label"><?= e(t('ad.name_ar')) ?></label>
      <input class="input" name="name_ar" required value="">
      <label class="field-label"><?= e(t('ad.name_en')) ?></label>
      <input class="input" name="name_en" value="">
      <label class="field-label"><?= e(t('ad.code')) ?></label>
      <input class="input" name="code" maxlength="12" value="">
      <label class="field-label"><?= e(t('ad.manager')) ?></label>
      <select class="input input-select" name="manager_id">
        <option value=""><?= e(t('common.select')) ?></option>
        <?php foreach ($managers as $mgr): ?>
          <option value="<?= (int) $mgr['id'] ?>"><?= e($mgr['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit"><?= e(t('common.add')) ?></button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('ad.list')) ?> (<?= count($depts) ?>)</h2>
    <?php if (!$depts): ?>
      <div class="empty"><?= e(t('common.no_results')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th><?= e(t('common.name')) ?></th>
            <th><?= e(t('ad.code')) ?></th>
            <th><?= e(t('ad.manager')) ?></th>
            <th><?= e(t('nav.users')) ?></th>
            <th><?= e(t('nav.tasks')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr></thead>
          <tbody>
            <?php foreach ($depts as $dept): $deptId = (int) $dept['id']; ?>
              <tr>
                <td>
                  <?= e(Departments::label($dept)) ?>
                  <form method="post" action="<?= u('admin/departments') ?>" class="inline-form" style="margin-top:4px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $deptId ?>">
                    <input class="input input-sm" name="name_ar" value="<?= e((string) $dept['name_ar']) ?>" placeholder="<?= e(t('ad.name_ar')) ?>" required>
                    <input class="input input-sm" name="name_en" value="<?= e((string) $dept['name_en']) ?>" placeholder="<?= e(t('ad.name_en')) ?>">
                    <input class="input input-sm" name="code" value="<?= e((string) $dept['code']) ?>" placeholder="<?= e(t('ad.code')) ?>" maxlength="12">
                    <select class="input input-select input-sm" name="manager_id">
                      <option value=""><?= e(t('common.select')) ?></option>
                      <?php foreach ($managers as $mgr): ?>
                        <option value="<?= (int) $mgr['id'] ?>" <?= (int) $dept['manager_id'] === (int) $mgr['id'] ? 'selected' : '' ?>><?= e($mgr['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('common.save')) ?></button>
                  </form>
                </td>
                <td><?= e((string) $dept['code'] !== '' ? (string) $dept['code'] : '—') ?></td>
                <td><?= e($managerNames[(int) $dept['manager_id']] ?? t('ad.no_manager')) ?></td>
                <td><?= (int) ($userCounts[$deptId] ?? 0) ?></td>
                <td><?= (int) ($taskCounts[$deptId] ?? 0) ?></td>
                <td><?= (int) $dept['active'] === 1
                      ? '<span class="badge badge-active">' . e(t('common.active')) . '</span>'
                      : '<span class="badge badge-inactive">' . e(t('common.inactive')) . '</span>' ?></td>
                <td>
                  <form method="post" action="<?= u('admin/departments') ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $deptId ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $dept['active'] === 1 ? e(t('common.inactive')) : e(t('common.active')) ?></button>
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
