<?php
declare(strict_types=1);

$error = null;
$blocked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $login    = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if ($login === '' || $password === '') {
        $error = t('auth.enter_both');
    } elseif (Auth::throttled($login, $ip)) {
        $blocked = true;
    } else {
        $user = Auth::attempt($login, $password);
        if ($user) {
            // A one-time password from an administrator must be replaced first.
            if (!empty($user['must_change_password'])) {
                flash('warning', t('profile.must_change'));
                redirect('profile&force=1');
            }
            redirect('dashboard');
        }
        $error = t('auth.invalid') . (Auth::throttled($login, $ip) ? ' ' . t('auth.too_many') : '');
    }
}

$site = setting('site_name', 'Daem');
$ipBlocked = Auth::throttled('', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
?>
<!doctype html>
<html lang="<?= e(daem_current_lang()) ?>" dir="<?= e(daem_lang_dir()) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('auth.title')) ?> · <?= e($site) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css?v=2">
<script>
(function(){try{var t=localStorage.getItem('daem-theme');
if(!t){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}
document.documentElement.setAttribute('data-theme',t);}catch(err){}})();
</script>
</head>
<body class="standalone">
  <div class="login-lang"><?= daem_lang_switcher() ?></div>
  <div class="login-wrap">
    <div class="login-card">
      <div class="login-brand"><span class="brand-logo">◈</span> <?= e($site) ?> <span class="brand-sub"><?= e(t('brand.tagline')) ?></span></div>

      <?php if ($ipBlocked): ?><div class="flash flash-error"><?= e(t('auth.locked')) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
      <?php if ($blocked): ?><div class="flash flash-error"><?= e(t('auth.too_many')) ?></div><?php endif; ?>

      <form method="post" action="<?= u('login') ?>" autocomplete="off">
        <?= csrf_field() ?>
        <label class="field-label" for="login"><?= e(t('auth.username_or_email')) ?></label>
        <input class="input" id="login" name="login" type="text" required autofocus value="<?= e($_POST['login'] ?? '') ?>">

        <label class="field-label" for="password"><?= e(t('auth.password')) ?></label>
        <input class="input" id="password" name="password" type="password" required>

        <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.sign_in')) ?></button>
      </form>

      <p class="login-foot"><?= e(t('auth.provisioned')) ?></p>
      <p class="login-foot"><a href="<?= u('doc') ?>"><?= e(t('nav.doc')) ?> →</a></p>
    </div>
  </div>
<script src="assets/js/app.js?v=2"></script>
</body>
</html>
