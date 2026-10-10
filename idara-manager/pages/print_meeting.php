<?php
declare(strict_types=1);

/**
 * Idara — printable A4 view of one meeting's minutes: a chrome-less document
 * sheet (site name, meeting title, date, attendees, attendance register,
 * agenda, minutes and signature/stamp boxes). Reached through index.php
 * (?p=print-meeting); the toolbar is hidden in print media by
 * assets/css/print.css.
 *
 * Printing is allowed for the organizer, admins / executives, and every
 * attendee (mirroring the access rules of meeting_view.php, where managing
 * is limited to organizer + admin / executive but attending is not).
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

$id      = (int) ($_GET['id'] ?? 0);
$meeting = Meetings::find($id);
if (!$meeting) {
    http_response_code(404);
    layout_header(t('e404.title'), '');
    echo '<div class="panel"><h1 class="ticket-subject">404</h1><p class="muted-text">' . e(t('e404.text')) . '</p><a class="btn btn-primary" href="' . u('dashboard') . '">' . e(t('e404.back')) . '</a></div>';
    layout_footer();
    exit;
}

$attendees  = Meetings::attendees($id);
$isAttendee = in_array(
    (int) $me['id'],
    array_map(static fn (array $a): int => (int) $a['user_id'], $attendees),
    true
);
$canPrint = in_array($me['role'], ['admin', 'executive'], true)
    || (int) $meeting['organizer_id'] === (int) $me['id']
    || $isAttendee;

if (!$canPrint) {
    http_response_code(403);
    layout_header(t('auth.denied'), '');
    echo '<div class="panel panel-muted"><h1 class="ticket-subject">403</h1><p class="muted-text">' . e(t('auth.denied')) . '</p><a class="btn btn-primary" href="' . u('meeting&id=' . $id) . '">' . e(t('common.back')) . '</a></div>';
    layout_footer();
    exit;
}

$siteName = setting('site_name', t('app.name'));
$dateLine = both_dates($meeting['starts_at']);
$present  = array_values(array_filter($attendees, static fn (array $a): bool => (int) $a['attended'] === 1));
$absent   = array_values(array_filter($attendees, static fn (array $a): bool => (int) $a['attended'] === 0));
$toList   = $attendees ? implode(', ', array_map('user_name', $attendees)) : '—';
?>
<!doctype html>
<html lang="<?= e(daem_current_lang()) ?>" dir="<?= e(daem_lang_dir()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('print.title_minutes')) ?> · <?= e($meeting['title']) ?> · <?= e($siteName) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css?v=2">
<link rel="stylesheet" href="assets/css/print.css?v=1">
</head>
<body class="print-page">

  <div class="no-print print-toolbar">
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><?= e(t('common.print')) ?></button>
    <a class="btn btn-ghost btn-sm" href="<?= u('meeting&id=' . $id) ?>"><?= e(t('print.back')) ?></a>
  </div>

  <main class="print-sheet">
    <header class="print-head">
      <div class="print-site"><?= e($siteName) ?></div>
      <h1 class="print-title"><?= e(t('print.title_minutes')) ?></h1>
      <div class="print-subtitle"><?= e($meeting['title']) ?></div>
    </header>

    <div class="print-meta">
      <div class="print-meta-item">
        <span class="meta-label"><?= e(t('print.date')) ?></span>
        <span class="meta-value"><?= e($dateLine !== '' ? $dateLine : '—') ?></span>
      </div>
      <div class="print-meta-item">
        <span class="meta-label"><?= e(t('print.to')) ?></span>
        <span class="meta-value"><?= e($toList) ?></span>
      </div>
    </div>

    <div class="print-attendance">
      <div class="print-col">
        <h3 class="print-col-title"><?= e(t('print.attendees')) ?></h3>
        <ul>
          <?php if (!$present): ?>
            <li class="print-empty">—</li>
          <?php endif; ?>
          <?php foreach ($present as $a): ?>
            <li><?= e(user_name($a)) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="print-col">
        <h3 class="print-col-title"><?= e(t('print.absent')) ?></h3>
        <ul>
          <?php if (!$absent): ?>
            <li class="print-empty">—</li>
          <?php endif; ?>
          <?php foreach ($absent as $a): ?>
            <li><?= e(user_name($a)) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="print-body">
      <h2 class="print-section"><?= e(t('common.agenda')) ?></h2>
      <div><?= (string) $meeting['agenda'] !== '' ? render_body($meeting['agenda']) : '<span class="cell-muted">—</span>' ?></div>

      <h2 class="print-section"><?= e(t('common.minutes')) ?></h2>
      <div><?= (string) $meeting['minutes'] !== '' ? render_body($meeting['minutes']) : '<span class="cell-muted">—</span>' ?></div>
    </div>

    <div class="sig-row">
      <div class="sig-box"><span class="sig-label"><?= e(t('print.signature')) ?></span></div>
      <div class="sig-box"><span class="sig-label"><?= e(t('print.stamp')) ?></span></div>
    </div>
  </main>

</body>
</html>
