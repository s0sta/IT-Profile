<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="<?= e(sanad_current_lang()) ?>" dir="<?= e(sanad_lang_dir()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('e404.title')) ?> · <?= e(setting('site_name', 'Sanad')) ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body class="standalone">
  <div class="error-card">
    <h1>404</h1>
    <p><?= e(t('e404.text')) ?></p>
    <a class="btn btn-primary" href="<?= u('dashboard') ?>"><?= e(t('e404.back')) ?></a>
  </div>
</body>
</html>
