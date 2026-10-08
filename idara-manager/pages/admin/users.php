<?php
declare(strict_types=1);

/**
 * Admin · Users — list, create, edit, reset password and activate/deactivate.
 * Guards: an admin account and the current user can never be deactivated.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'update') {
        $data = [
            'name'          => trim((string) ($_POST['name'] ?? '')),
            'name_en'       => trim((string) ($_POST['name_en'] ?? '')),
            'username'      => trim((string) ($_POST['username'] ?? '')),
            'email'         => trim((string) ($_POST['email'] ?? '')),
            'role'          => (string) ($_POST['role'] ?? 'member'),
            'department_id' => (int) ($_POST['department_id'] ?? 0),
            'job_title'     => trim((string) ($_POST['job_title'] ?? '')),
            'phone'         => trim((string) ($_POST['phone'] ?? '')),
            'manager_id'    => (int) ($_POST['manager_id'] ?? 0),
        ];
        $id     = (int) ($_POST['id'] ?? 0);
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = t('au.err_name');
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $data['username'])) {
            $errors[] = t('au.err_username');
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('au.err_email');
        }
        if (!array_key_exists($data['role'], roles())) {
            $errors[] = t('au.err_role');
        }
        $dup = Database::value(
            'SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id <> ?',
            [$data['username'], $data['email'], $id]
        );
        if ($dup) {
            $errors[] = t('au.err_duplicate');
        }
        if ($action === 'add') {
            $pw = (string) ($_POST['password'] ?? '');
            if (strlen($pw) < 8) {
                $errors[] = t('au.err_password');
            }
        }

        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            redirect($action === 'update' && $id ? 'admin/users&edit=' . $id : 'admin/users');
        }

        if ($action === 'add') {
            Users::create($data + ['password' => (string) ($_POST['password'] ?? '')]);
            flash('success', t('au.created'));
        } else {
            if ($id) {
                Users::update($id, $data);
                flash('success', t('au.updated'));
            }
        }
        redirect('admin/users');
    }

    if ($action === 'reset_password') {
        $id = (int) ($_POST['id'] ?? 0);
        $pw = (string) ($_POST['password'] ?? '');
        if (strlen($pw) < 8) {
            flash('error', t('au.err_password'));
        } elseif ($id) {
            Users::setPassword($id, $pw);
            flash('success', t('au.reset_done'));
        }
        redirect('admin/users');
    }

    if ($action === 'toggle') {
        $id     = (int) ($_POST['id'] ?? 0);
        $target = $id ? Users::find($id) : null;
        if (!$target || $id === Auth::id() || $target['role'] === 'admin') {
            flash('error', t('auth.denied'));
        } else {
            Users::toggleActive($id);
            flash('success', t('au.toggled'));
        }
        redirect('admin/users');
    }

    redirect('admin/users');
}

$users    = Users::all();
$editId   = (int) ($_GET['edit'] ?? 0);
$editUser = $editId ? Users::find($editId) : null;
$isEdit   = $editUser !== null;
$depts    = Departments::all(true);
$managers = Users::approvers();

layout_header(t('au.title'), 'admin/users');
?>

<section class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= $isEdit ? e(t('au.edit')) : e(t('au.add')) ?></h2>
      <?php if ($isEdit): ?><a class="link" href="<?= u('admin/users') ?>"><?= e(t('common.cancel')) ?></a><?php endif; ?>
    </div>
    <form method="post" action="<?= u('admin/users') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'add' ?>">
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>"><?php endif; ?>

      <label class="field-label"><?= e(t('au.full_name')) ?></label>
      <input class="input" name="name" required value="<?= e($editUser['name'] ?? '') ?>">
      <label class="field-label"><?= e(t('au.name_en')) ?></label>
      <input class="input" name="name_en" value="<?= e($editUser['name_en'] ?? '') ?>">
      <label class="field-label"><?= e(t('common.username')) ?></label>
      <input class="input" name="username" required value="<?= e($editUser['username'] ?? '') ?>">
      <label class="field-label"><?= e(t('common.email')) ?></label>
      <input class="input" type="email" name="email" required value="<?= e($editUser['email'] ?? '') ?>">
      <label class="field-label"><?= e(t('common.role')) ?></label>
      <select class="input input-select" name="role">
        <?php foreach (roles() as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= ($editUser['role'] ?? 'member') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="field-label"><?= e(t('common.department')) ?></label>
      <select class="input input-select" name="department_id">
        <option value=""><?= e(t('common.select')) ?></option>
        <?php foreach ($depts as $dept): ?>
          <option value="<?= (int) $dept['id'] ?>" <?= (int) ($editUser['department_id'] ?? 0) === (int) $dept['id'] ? 'selected' : '' ?>><?= e(Departments::label($dept)) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="field-label"><?= e(t('common.job_title')) ?></label>
      <input class="input" name="job_title" value="<?= e($editUser['job_title'] ?? '') ?>">
      <label class="field-label"><?= e(t('common.phone')) ?></label>
      <input class="input" name="phone" value="<?= e($editUser['phone'] ?? '') ?>">
      <label class="field-label"><?= e(t('common.manager')) ?></label>
      <select class="input input-select" name="manager_id">
        <option value=""><?= e(t('common.select')) ?></option>
        <?php foreach ($managers as $mgr): ?>
          <option value="<?= (int) $mgr['id'] ?>" <?= (int) ($editUser['manager_id'] ?? 0) === (int) $mgr['id'] ? 'selected' : '' ?>><?= e($mgr['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!$isEdit): ?>
        <label class="field-label"><?= e(t('au.password')) ?></label>
        <input class="input" type="password" name="password" required>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= $isEdit ? e(t('au.save')) : e(t('au.create')) ?></button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('au.all')) ?> (<?= count($users) ?>)</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr>
          <th><?= e(t('common.name')) ?></th>
          <th><?= e(t('common.username')) ?></th>
          <th><?= e(t('common.role')) ?></th>
          <th><?= e(t('common.department')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <th><?= e(t('profile.last_sign_in')) ?></th>
          <th><?= e(t('common.actions')) ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($users as $u): $deptLabel = bilingual($u, 'dept_name'); ?>
            <tr>
              <td><?= e($u['name']) ?><div class="cell-muted"><?= e($u['email']) ?></div></td>
              <td><?= e($u['username']) ?></td>
              <td><?= role_badge((string) $u['role']) ?></td>
              <td><?= e($deptLabel !== '' ? $deptLabel : '—') ?></td>
              <td><?= (int) $u['active'] === 1
                    ? '<span class="badge badge-active">' . e(t('common.active')) . '</span>'
                    : '<span class="badge badge-inactive">' . e(t('common.inactive')) . '</span>' ?></td>
              <td class="cell-muted"><?= e($u['last_login_at'] ? time_ago((string) $u['last_login_at']) : t('profile.never')) ?></td>
              <td class="cell-actions">
                <a class="btn btn-ghost btn-sm" href="<?= u('admin/users&edit=' . (int) $u['id']) ?>"><?= e(t('common.edit')) ?></a>
                <form method="post" action="<?= u('admin/users') ?>" class="inline-form" data-confirm="<?= e(t('au.confirm_reset')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="password" value="Temp12345">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('au.reset')) ?></button>
                </form>
                <?php if ((int) $u['id'] !== Auth::id() && $u['role'] !== 'admin'): ?>
                  <form method="post" action="<?= u('admin/users') ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-ghost btn-sm <?= (int) $u['active'] === 1 ? 'text-danger' : '' ?>" type="submit"><?= (int) $u['active'] === 1 ? e(t('au.deactivate')) : e(t('au.activate')) ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php layout_footer(); ?>
