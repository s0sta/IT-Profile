<?php
declare(strict_types=1);

/**
 * Idara — 404 page. Reached through index.php (unknown ?p= route) or directly.
 * Self-bootstraps when the front controller did not load the framework.
 */

if (!function_exists('t')) {
    require_once dirname(__DIR__) . '/includes/bootstrap.php';
}
?>
<!doctype html>
<html lang="<?= e(daem_current_lang()) ?>" dir="<?= e(daem_lang_dir()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('e404.title')) ?> · <?= e(setting('site_name', t('app.name'))) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body class="standalone">
  <div class="error-card">
    <h1>404</h1>
    <p><?= e(t('e404.text')) ?></p>
    <a class="btn btn-primary" href="<?= u('dashboard') ?>"><?= e(t('e404.back')) ?></a>
  </div>
</body>
</html>
