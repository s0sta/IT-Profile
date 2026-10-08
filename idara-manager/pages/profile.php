<?php
declare(strict_types=1);

/**
 * Idara — personal profile: account overview + password change.
 * ?p=profile   (name / email / role / department are managed by the administrator)
 */

$me = Auth::current();
if (!$me) {
    redirect('login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'password');

    if ($action === 'profile') {
        // Self-service profile: photo (built-in or upload), bio, birthdate.
        $avatar = trim((string) ($_POST['avatar'] ?? ''));
        $bio    = trim((string) ($_POST['bio'] ?? ''));
        $birth  = trim((string) ($_POST['birthdate'] ?? ''));
        $errors = [];

        if ($avatar !== '' && !preg_match('/^builtin:(0[1-9]|1[0-9]|20)\.svg$/', $avatar)) {
            $avatar = (string) ($me['avatar'] ?? ''); // keep the current photo on bad input
        }
        $photo = $_FILES['photo'] ?? null;
        if (is_array($photo) && ($photo['error'] ?? 0) === UPLOAD_ERR_OK && (int) ($photo['size'] ?? 0) > 0) {
            $ext = strtolower(pathinfo((string) ($photo['name'] ?? ''), PATHINFO_EXTENSION));
            if ((int) $photo['size'] > 2 * 1024 * 1024) {
                $errors[] = t('profile.err_avatar_size');
            } elseif (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $errors[] = t('profile.err_avatar_type');
            } else {
                @mkdir(APP_ROOT . '/storage/avatars', 0750, true);
                $newName = 'u' . (int) $me['id'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file((string) $photo['tmp_name'], APP_ROOT . '/storage/avatars/' . $newName)) {
                    if (str_starts_with((string) ($me['avatar'] ?? ''), 'up:')) {
                        $oldFile = APP_ROOT . '/storage/avatars/' . basename(substr((string) $me['avatar'], 3));
                        if (is_file($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                    $avatar = 'up:' . $newName;
                } else {
                    $errors[] = t('profile.err_avatar_type');
                }
            }
        }
        if ($birth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
            $birth = '';
        }

        if (!$errors) {
            Users::updateProfile((int) $me['id'], [
                'avatar'    => $avatar,
                'bio'       => mb_substr($bio, 0, 1000, 'UTF-8'),
                'birthdate' => $birth,
            ]);
            flash('success', t('profile.profile_updated'));
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
        redirect('profile');
    }

    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!password_verify($current, (string) $me['password_hash'])) {
        flash('error', t('profile.err_current'));
    } elseif (mb_strlen($new, 'UTF-8') < 8) {
        flash('error', t('profile.err_short'));
    } elseif ($new !== $confirm) {
        flash('error', t('profile.err_match'));
    } else {
        Users::setPassword((int) $me['id'], $new);
        Auth::clearMustChange();
        flash('success', t('profile.changed'));
    }
    redirect('profile');
}

$department = Departments::label(Departments::find((int) ($me['department_id'] ?? 0)));

layout_header(t('profile.title'), 'profile');
?>

<section class="panel">
  <h2 class="panel-title"><?= e(t('profile.avatar')) ?></h2>
  <form method="post" action="<?= u('profile') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">

    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
      <?= avatar_img($me, 72) ?>
      <span class="muted-text"><?= e(user_name($me)) ?> · <?= e(t('common.role')) ?>: <?= e(roles()[$me['role']] ?? $me['role']) ?></span>
    </div>

    <label class="field-label"><?= e(t('profile.avatar_pick')) ?></label>
    <div class="avatar-picker">
      <?php for ($i = 1; $i <= 20; $i++): $av = sprintf('builtin:%02d.svg', $i); ?>
        <label>
          <input type="radio" name="avatar" value="<?= e($av) ?>" <?= (string) $me['avatar'] === $av ? 'checked' : '' ?>>
          <img src="assets/avatars/<?= sprintf('%02d', $i) ?>.svg" alt="<?= $i ?>" width="52" height="52">
        </label>
      <?php endfor; ?>
      <label title="<?= e(t('profile.no_avatar')) ?>">
        <input type="radio" name="avatar" value="" <?= (string) ($me['avatar'] ?? '') === '' ? 'checked' : '' ?>>
        <span class="avatar-no">×</span>
      </label>
    </div>

    <label class="field-label"><?= e(t('profile.avatar_upload')) ?></label>
    <input class="input" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">

    <label class="field-label"><?= e(t('profile.bio')) ?></label>
    <textarea class="input" name="bio" rows="3" maxlength="1000" placeholder="<?= e(t('profile.bio_placeholder')) ?>"><?= e((string) $me['bio']) ?></textarea>

    <label class="field-label"><?= e(t('profile.birthdate')) ?></label>
    <input class="input" type="date" name="birthdate" value="<?= e((string) ($me['birthdate'] ?? '')) ?>">

    <p><button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button></p>
  </form>
</section>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('profile.account')) ?></h2>
    <div class="meta-grid">
      <div class="meta-item"><span class="meta-label"><?= e(t('common.name')) ?></span><span class="meta-value"><?= e($me['name']) ?></span></div>
      <div class="meta-item"><span class="meta-label"><?= e(t('au.name_en')) ?></span><span class="meta-value"><?= e($me['name_en'] !== '' && $me['name_en'] !== null ? (string) $me['name_en'] : '—') ?></span></div>
      <div class="meta-item"><span class="meta-label"><?= e(t('common.username')) ?></span><span class="meta-value"><?= e($me['username']) ?></span></div>
      <div class="meta-item"><span class="meta-label"><?= e(t('common.email')) ?></span><span class="meta-value"><?= e($me['email']) ?></span></div>
      <div class="meta-item"><span class="meta-label"><?= e(t('common.role')) ?></span><span class="meta-value"><?= role_badge((string) $me['role']) ?></span></div>
      <div class="meta-item"><span class="meta-label"><?= e(t('common.department')) ?></span><span class="meta-value"><?= e($department) ?></span></div>
      <div class="meta-item"><span class="meta-label"><?= e(t('common.job_title')) ?></span><span class="meta-value"><?= $me['job_title'] ? e((string) $me['job_title']) : '—' ?></span></div>
      <div class="meta-item"><span class="meta-label"><?= e(t('common.phone')) ?></span><span class="meta-value"><?= $me['phone'] ? e((string) $me['phone']) : '—' ?></span></div>
      <div class="meta-item"><span class="meta-label"><?= e(t('profile.member_since')) ?></span><span class="meta-value"><?= e(fmt_date($me['created_at'])) ?></span></div>
      <div class="meta-item"><span class="meta-label"><?= e(t('profile.last_sign_in')) ?></span><span class="meta-value"><?= $me['last_login_at'] ? e(fmt_dt((string) $me['last_login_at'])) : e(t('profile.never')) ?></span></div>
    </div>
    <p class="muted-text" style="margin-top:14px"><?= e(t('profile.note')) ?></p>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('profile.change_password')) ?></h2>
    <form method="post" action="<?= u('profile') ?>">
      <?= csrf_field() ?>
      <label class="field-label" for="current_password"><?= e(t('profile.current')) ?></label>
      <input class="input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
      <label class="field-label" for="new_password"><?= e(t('profile.new')) ?></label>
      <input class="input" id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password">
      <label class="field-label" for="confirm_password"><?= e(t('profile.confirm')) ?></label>
      <input class="input" id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
      <p><button class="btn btn-primary" type="submit"><?= e(t('profile.update')) ?></button></p>
    </form>
  </div>
</section>

<?php layout_footer(); ?>
