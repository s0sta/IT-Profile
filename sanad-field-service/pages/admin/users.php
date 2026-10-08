<?php
declare(strict_types=1);

/**
 * Admin — users: create accounts, edit them, reset passwords and
 * activate/deactivate staff. Only administrators reach this page.
 */

Auth::requireAdmin();

$roles = roles();

$editId   = (int) ($_GET['edit'] ?? 0);
$keepForm = false;
$form     = [
    'id'       => 0,
    'name'     => '',
    'username' => '',
    'email'    => '',
    'role'     => 'technician',
    'phone'    => '',
    'colour'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'add' || $action === 'update') {
        $form = [
            'id'       => $id,
            'name'     => trim((string) ($_POST['name'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
            'role'     => (string) ($_POST['role'] ?? ''),
            'phone'    => trim((string) ($_POST['phone'] ?? '')),
            'colour'   => trim((string) ($_POST['colour'] ?? '')),
        ];
        $keepForm = true;
        $password = (string) ($_POST['password'] ?? '');
        $error    = '';

        if ($form['name'] === '') {
            $error = t('au.err_name');
        } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $form['username'])) {
            $error = t('au.err_username');
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $error = t('au.err_email');
        } elseif (!array_key_exists($form['role'], $roles)) {
            $error = t('au.err_role');
        } elseif ($action === 'add' && strlen($password) < 8) {
            $error = t('au.err_password');
        } else {
            $duplicate = (int) Database::value(
                'SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id <> ?',
                [$form['username'], $form['email'], $id]
            );
            if ($duplicate > 0) {
                $error = t('au.err_duplicate');
            }
        }

        if ($error !== '') {
            flash('error', $error);
            $editId = $action === 'update' ? $id : 0;
        } elseif ($action === 'add') {
            Users::create([
                'name'     => $form['name'],
                'username' => $form['username'],
                'email'    => $form['email'],
                'password' => $password,
                'role'     => $form['role'],
                'phone'    => $form['phone'],
                'colour'   => $form['colour'],
            ]);
            flash('success', t('au.created'));
            redirect('admin/users');
        } else {
            Users::update($id, [
                'name'     => $form['name'],
                'username' => $form['username'],
                'email'    => $form['email'],
                'role'     => $form['role'],
                'phone'    => $form['phone'],
                'colour'   => $form['colour'],
            ]);
            flash('success', t('au.updated'));
            redirect('admin/users');
        }
    } elseif ($action === 'reset_password') {
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            flash('error', t('au.err_password'));
        } else {
            Users::setPassword($id, $password);
            flash('success', t('au.reset_done'));
            redirect('admin/users');
        }
    } elseif ($action === 'toggle') {
        Users::toggleActive($id);
        flash('success', t('au.toggled'));
        redirect('admin/users');
    }
}

// ?edit=<id> fills the form on the left; a failed POST keeps what was typed.
if (!$keepForm && $editId > 0) {
    $edit = Users::find($editId);
    if ($edit) {
        $form = [
            'id'       => (int) $edit['id'],
            'name'     => (string) $edit['name'],
            'username' => (string) $edit['username'],
            'email'    => (string) $edit['email'],
            'role'     => (string) $edit['role'],
            'phone'    => (string) $edit['phone'],
            'colour'   => (string) $edit['colour'],
        ];
    } else {
        $editId = 0;
    }
}

$colour = preg_match('/^#[0-9a-fA-F]{6}$/', $form['colour']) ? $form['colour'] : '#4f46e5';
$users  = Users::all();

layout_header(t('au.title'), 'admin/users');
?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e($editId > 0 ? t('au.edit') : t('au.add')) ?></h2>
    <form method="post" action="<?= u('admin/users') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editId > 0 ? 'update' : 'add' ?>">
      <?php if ($editId > 0): ?>
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
      <?php endif; ?>

      <label class="field-label" for="name"><?= e(t('au.full_name')) ?></label>
      <input class="input" id="name" name="name" type="text" value="<?= e($form['name']) ?>" required>

      <label class="field-label" for="username"><?= e(t('common.username')) ?></label>
      <input class="input" id="username" name="username" type="text" value="<?= e($form['username']) ?>" required>

      <label class="field-label" for="email"><?= e(t('common.email')) ?></label>
      <input class="input" id="email" name="email" type="email" value="<?= e($form['email']) ?>" required>

      <label class="field-label" for="role"><?= e(t('common.role')) ?></label>
      <select class="input input-select" id="role" name="role" required>
        <?php foreach ($roles as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $form['role'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="phone"><?= e(t('common.phone')) ?></label>
      <input class="input" id="phone" name="phone" type="text" value="<?= e($form['phone']) ?>">

      <?php if ($editId === 0): ?>
        <label class="field-label" for="password"><?= e(t('au.password')) ?></label>
        <input class="input" id="password" name="password" type="password" minlength="8" required>
      <?php endif; ?>

      <label class="field-label" for="colour"><?= e(t('au.colour')) ?></label>
      <input class="input" id="colour" name="colour" type="color" value="<?= e($colour) ?>">

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= e($editId > 0 ? t('common.save') : t('au.create')) ?></button>
        <?php if ($editId > 0): ?>
          <a class="btn btn-ghost" href="<?= u('admin/users') ?>"><?= e(t('common.cancel')) ?></a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e(t('au.all', ['n' => count($users)])) ?></h2>
    </div>

    <?php if (!$users): ?>
      <div class="empty"><?= e(t('common.none')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e(t('au.full_name')) ?></th>
              <th><?= e(t('common.role')) ?></th>
              <th><?= e(t('common.phone')) ?></th>
              <th><?= e(t('common.active')) ?></th>
              <th><?= e(t('au.last_sign_in')) ?></th>
              <th><?= e(t('common.actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $usr): ?>
              <?php $phone = trim((string) $usr['phone']); ?>
              <tr>
                <td class="cell-subject"><?= e($usr['name']) ?>
                  <div class="cell-muted"><?= e($usr['email']) ?> · @<?= e($usr['username']) ?></div></td>
                <td><?= role_badge((string) $usr['role']) ?></td>
                <td><?= e($phone !== '' ? $phone : '—') ?></td>
                <td>
                  <?php if ($usr['active']): ?>
                    <span class="badge badge-active"><?= e(t('common.active')) ?></span>
                  <?php else: ?>
                    <span class="badge badge-inactive"><?= e(t('common.inactive')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="cell-muted"><?= e($usr['last_login_at'] ? time_ago((string) $usr['last_login_at']) : t('profile.never')) ?></td>
                <td class="cell-actions">
                  <a class="btn btn-ghost btn-sm" href="<?= u('admin/users&edit=' . (int) $usr['id']) ?>"><?= e(t('common.edit')) ?></a>
                  <?php if ($usr['role'] !== 'admin'): ?>
                    <form class="inline-form" method="post" action="<?= u('admin/users') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int) $usr['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= e($usr['active'] ? t('common.deactivate') : t('common.activate')) ?></button>
                    </form>
                  <?php endif; ?>
                  <form class="inline-form" method="post" action="<?= u('admin/users') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" value="<?= (int) $usr['id'] ?>">
                    <input class="input input-sm" type="password" name="password" minlength="8" placeholder="<?= e(t('au.password')) ?>" required>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('au.reset')) ?></button>
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
