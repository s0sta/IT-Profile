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

        if ($avatar !== '' && !preg_match('/^builtin:(0[1-9]|[12][0-9]|3[0-2])\.webp$/', $avatar)) {
            $avatar = (string) ($me['avatar'] ?? ''); // keep the current photo on bad input
        }
        $photo = $_FILES['photo'] ?? null;
        if (is_array($photo) && ($photo['error'] ?? 0) === UPLOAD_ERR_OK && (int) ($photo['size'] ?? 0) > 0) {
            $ext = strtolower(pathinfo((string) ($photo['name'] ?? ''), PATHINFO_EXTENSION));
            $size = (int) $photo['size'];
            $info = @getimagesize((string) $photo['tmp_name']);
            $validType = is_array($info)
                && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
                && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
            if ($size > 2 * 1024 * 1024) {
                $errors[] = t('profile.err_avatar_size');
            } elseif (!$validType) {
                // extension, or the actual bytes, are not a real image
                $errors[] = t('profile.err_avatar_invalid');
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
                    $avatar = 'up:' . $newName; // an uploaded photo always wins over the picker
                } else {
                    $errors[] = t('profile.err_avatar_save');
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

    if ($action === 'mfa_enable') {
        // Generate a fresh secret and store it (enabled stays 0 until verified).
        $secret = totp_secret();
        Database::exec('UPDATE users SET mfa_secret = ?, mfa_enabled = 0 WHERE id = ?', [$secret, (int) $me['id']]);
        audit('mfa_secret_created', 'user', (int) $me['id']);
        flash('info', t('profile.mfa_secret_hint'));
        redirect('profile');
    }

    if ($action === 'mfa_verify') {
        $code = trim((string) ($_POST['code'] ?? ''));
        if (totp_verify((string) $me['mfa_secret'], $code)) {
            Database::exec('UPDATE users SET mfa_enabled = 1 WHERE id = ?', [(int) $me['id']]);
            audit('mfa_enabled', 'user', (int) $me['id']);
            flash('success', t('profile.mfa_enabled_ok'));
        } else {
            flash('error', t('profile.mfa_bad_code'));
        }
        redirect('profile');
    }

    if ($action === 'mfa_disable') {
        $pw = (string) ($_POST['password'] ?? '');
        if (!password_verify($pw, (string) $me['password_hash'])) {
            flash('error', t('profile.mfa_bad_password'));
        } else {
            Database::exec("UPDATE users SET mfa_secret = '', mfa_enabled = 0 WHERE id = ?", [(int) $me['id']]);
            audit('mfa_disabled', 'user', (int) $me['id']);
            flash('success', t('profile.mfa_disabled_ok'));
        }
        redirect('profile');
    }

    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!password_verify($current, (string) $me['password_hash'])) {
        flash('error', t('profile.err_current'));
    } elseif (!valid_password($new)) {
        flash('error', t('profile.err_policy'));
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
      <?php for ($i = 1; $i <= 32; $i++): $av = sprintf('builtin:%02d.webp', $i); ?>
        <label>
          <input type="radio" name="avatar" value="<?= e($av) ?>" <?= (string) $me['avatar'] === $av ? 'checked' : '' ?>>
          <img src="assets/avatars/<?= sprintf('%02d', $i) ?>.webp" alt="<?= $i ?>" width="52" height="52">
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

<section class="panel">
  <h2 class="panel-title"><?= e(t('profile.mfa')) ?></h2>
  <?php $mfaOn = (int) ($me['mfa_enabled'] ?? 0) === 1; ?>
  <p class="muted-text">
    <?= e(t('common.status')) ?>:
    <strong><?= $mfaOn ? e(t('profile.mfa_status_on')) : e(t('profile.mfa_status_off')) ?></strong>
  </p>

  <?php if ($mfaOn): ?>
    <form method="post" action="<?= u('profile') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mfa_disable">
      <label class="field-label" for="mfa-pw"><?= e(t('profile.mfa_confirm_disable')) ?></label>
      <input class="input" id="mfa-pw" name="password" type="password" required autocomplete="current-password">
      <button class="btn btn-ghost text-danger" type="submit"><?= e(t('profile.mfa_disable')) ?></button>
    </form>
  <?php elseif (trim((string) ($me['mfa_secret'] ?? '')) !== ''): ?>
    <p class="muted-text"><?= e(t('profile.mfa_secret_hint')) ?></p>
    <div class="ref-pill"><?= e($me['mfa_secret']) ?></div>
    <form method="post" action="<?= u('profile') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mfa_verify">
      <label class="field-label" for="mfa-code"><?= e(t('profile.mfa_code_hint')) ?></label>
      <input class="input" id="mfa-code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" required>
      <button class="btn btn-primary" type="submit"><?= e(t('common.verify')) ?></button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= u('profile') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mfa_enable">
      <button class="btn btn-primary" type="submit"><?= e(t('profile.mfa_enable')) ?></button>
    </form>
  <?php endif; ?>
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
