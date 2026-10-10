<?php
declare(strict_types=1);

/**
 * Idara — printable A4 view of one official letter: a chrome-less document
 * sheet (site name, reference, both dates, from/to, subject, summary and
 * signature/stamp boxes). Reached through index.php (?p=print-letter); the
 * toolbar is hidden in print media by assets/css/print.css.
 */

if (!function_exists('t')) {
    require_once dirname(__DIR__) . '/includes/bootstrap.php';
}
if (!function_exists('layout_header')) {
    require_once dirname(__DIR__) . '/includes/layout.php';
}

$me = Auth::current();
if (!$me) {
    redirect('login');
}

$id     = (int) ($_GET['id'] ?? 0);
$letter = Correspondence::find($id);
if (!$letter) {
    http_response_code(404);
    layout_header(t('e404.title'), '');
    echo '<div class="panel"><h1 class="ticket-subject">404</h1><p class="muted-text">' . e(t('e404.text')) . '</p><a class="btn btn-primary" href="' . u('dashboard') . '">' . e(t('e404.back')) . '</a></div>';
    layout_footer();
    exit;
}

// letter_view.php applies no view-level guard beyond being signed in, so
// printing mirrors it: any signed-in user may print any letter. (There is no
// Correspondence::canView — the only canView methods in models.php belong to
// Tasks and Approvals.)

$siteName = setting('site_name', t('app.name'));
$dateLine = both_dates($letter['created_at']);
$from     = trim((string) $letter['party']);
$to       = trim((string) ($letter['assignee_name'] ?? ''));
?>
<!doctype html>
<html lang="<?= e(daem_current_lang()) ?>" dir="<?= e(daem_lang_dir()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('print.title_letter')) ?> · <?= e($letter['ref']) ?> · <?= e($siteName) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css?v=2">
<link rel="stylesheet" href="assets/css/print.css?v=1">
</head>
<body class="print-page">

  <div class="no-print print-toolbar">
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><?= e(t('common.print')) ?></button>
    <a class="btn btn-ghost btn-sm" href="<?= u('letter&id=' . $id) ?>"><?= e(t('print.back')) ?></a>
  </div>

  <main class="print-sheet">
    <header class="print-head">
      <div class="print-site"><?= e($siteName) ?></div>
      <h1 class="print-title"><?= e(t('print.title_letter')) ?></h1>
    </header>

    <div class="print-meta">
      <div class="print-meta-item">
        <span class="meta-label"><?= e(t('print.ref')) ?></span>
        <span class="meta-value"><?= e($letter['ref']) ?></span>
      </div>
      <div class="print-meta-item">
        <span class="meta-label"><?= e(t('print.date')) ?> · <?= e(t('print.hijri_date')) ?></span>
        <span class="meta-value"><?= e($dateLine !== '' ? $dateLine : '—') ?></span>
      </div>
      <div class="print-meta-item">
        <span class="meta-label"><?= e(t('print.from')) ?></span>
        <span class="meta-value"><?= e($from !== '' ? $from : '—') ?></span>
      </div>
      <div class="print-meta-item">
        <span class="meta-label"><?= e(t('print.to')) ?></span>
        <span class="meta-value"><?= e($to !== '' ? $to : '—') ?></span>
      </div>
    </div>

    <div class="print-body">
      <h2 class="print-subject"><?= e($letter['subject']) ?></h2>
      <div><?= (string) $letter['summary'] !== '' ? render_body($letter['summary']) : '<span class="cell-muted">—</span>' ?></div>
    </div>

    <div class="sig-row">
      <div class="sig-box"><span class="sig-label"><?= e(t('print.signature')) ?></span></div>
      <div class="sig-box"><span class="sig-label"><?= e(t('print.stamp')) ?></span></div>
    </div>
  </main>

</body>
</html>
