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
        flash('success', t('profile.changed'));
    }
    redirect('profile');
}

$department = Departments::label(Departments::find((int) ($me['department_id'] ?? 0)));

layout_header(t('profile.title'), 'profile');
?>

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
