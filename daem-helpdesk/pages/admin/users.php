<?php
declare(strict_types=1);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'update') {
        $data = [
            'name'       => trim((string) ($_POST['name'] ?? '')),
            'username'   => trim((string) ($_POST['username'] ?? '')),
            'email'      => trim((string) ($_POST['email'] ?? '')),
            'role'       => (string) ($_POST['role'] ?? 'user'),
            'department' => trim((string) ($_POST['department'] ?? '')),
            'phone'      => trim((string) ($_POST['phone'] ?? '')),
        ];
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
            [$data['username'], $data['email'], (int) ($_POST['id'] ?? 0)]
        );
        if ($dup) {
            $errors[] = t('au.err_duplicate');
        }

        if ($action === 'add') {
            $pw = (string) ($_POST['password'] ?? '');
            if (strlen($pw) < 8) {
                $errors[] = t('au.err_password');
            }
            if (!$errors) {
                Users::create($data + ['password' => $pw]);
                flash('success', t('au.created'));
                redirect('admin/users');
            }
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if (!$errors && $id) {
                Users::update($id, $data);
                flash('success', t('au.updated'));
                redirect('admin/users');
            }
        }
    } elseif ($action === 'reset_password') {
        $id   = (int) ($_POST['id'] ?? 0);
        $user = $id ? Users::find($id) : null;
        if (!$user) {
            flash('error', t('au.err_name'));
        } else {
            // One-time password, shown once to the administrator; the user must change it at next sign-in.
            $temp = bin2hex(random_bytes(4));
            Users::setPassword($id, $temp, true);
            flash('success', t('au.reset_generated', ['name' => $user['name'], 'password' => $temp]));
        }
        redirect('admin/users');
    } elseif ($action === 'toggle') {
        Users::toggleActive((int) ($_POST['id'] ?? 0));
        flash('success', t('au.toggled'));
        redirect('admin/users');
    }
    if ($errors) {
        $editUser = ['id' => (int) ($_POST['id'] ?? 0)] + ($_POST ?? []);
    }
}

$users = Users::all();
$editId = (int) ($_GET['edit'] ?? 0);
$editUser = $editUser ?? ($editId ? Users::find($editId) : null);

layout_header(t('au.title'), 'admin/users');
?>

<?php if ($errors): ?>
  <div class="flash flash-error"><ul style="margin:0;padding-left:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= $editUser ? e(t('au.edit')) : e(t('au.add')) ?></h2>
      <?php if ($editUser): ?><a class="link" href="<?= u('admin/users') ?>"><?= e(t('au.cancel')) ?></a><?php endif; ?>
    </div>
    <form method="post" action="<?= u('admin/users') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'add' ?>">
      <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>"><?php endif; ?>

      <label class="field-label"><?= e(t('au.full_name')) ?></label>
      <input class="input" name="name" required value="<?= e($editUser['name'] ?? '') ?>">
      <label class="field-label"><?= e(t('common.username')) ?></label>
      <input class="input" name="username" required value="<?= e($editUser['username'] ?? '') ?>">
      <label class="field-label"><?= e(t('common.email')) ?></label>
      <input class="input" type="email" name="email" required value="<?= e($editUser['email'] ?? '') ?>">
      <label class="field-label"><?= e(t('common.role')) ?></label>
      <select class="input input-select" name="role">
        <?php foreach (roles() as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= ($editUser['role'] ?? 'user') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="field-label"><?= e(t('common.department')) ?></label>
      <input class="input" name="department" value="<?= e($editUser['department'] ?? '') ?>">
      <label class="field-label"><?= e(t('common.phone')) ?></label>
      <input class="input" name="phone" value="<?= e($editUser['phone'] ?? '') ?>">
      <?php if (!$editUser): ?>
        <label class="field-label"><?= e(t('au.password')) ?></label>
        <input class="input" type="password" name="password" required>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= $editUser ? e(t('au.save')) : e(t('au.create')) ?></button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('au.all')) ?> (<?= count($users) ?>)</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('common.name')) ?></th><th><?= e(t('common.username')) ?></th><th><?= e(t('common.role')) ?></th><th><?= e(t('common.department')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('au.last_sign_in')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= e($u['name']) ?><div class="cell-muted"><?= e($u['email']) ?></div></td>
              <td><?= e($u['username']) ?></td>
              <td><?= role_badge($u['role']) ?></td>
              <td><?= e($u['department'] ?: '—') ?></td>
              <td><?= $u['active'] ? '<span class="badge badge-active">' . e(t('common.active')) . '</span>' : '<span class="badge badge-inactive">' . e(t('common.inactive')) . '</span>' ?></td>
              <td class="cell-muted"><?= e($u['last_login_at'] ? time_ago($u['last_login_at']) : t('profile.never')) ?></td>
              <td class="cell-actions">
                <a class="btn btn-ghost btn-sm" href="<?= u('admin/users&edit=' . (int) $u['id']) ?>"><?= e(t('common.edit')) ?></a>
                <form method="post" action="<?= u('admin/users') ?>" class="inline-form" data-confirm="<?= e(t('au.confirm_reset')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('au.reset')) ?></button>
                </form>
                <?php if ((int) $u['id'] !== Auth::id() && $u['role'] !== 'admin'): ?>
                <form method="post" action="<?= u('admin/users') ?>" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="btn btn-ghost btn-sm <?= $u['active'] ? 'text-danger' : '' ?>" type="submit"><?= $u['active'] ? e(t('au.deactivate')) : e(t('au.activate')) ?></button>
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
