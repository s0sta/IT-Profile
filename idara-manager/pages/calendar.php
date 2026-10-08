<?php
declare(strict_types=1);

/**
 * Idara — month calendar of due items (no JavaScript).
 * ?p=calendar&month=YYYY-MM  (default: current month)
 *
 * The models only expose list() with limits, so the month's tasks, approvals and
 * meetings are loaded and filtered in PHP by their date column. Visibility is
 * enforced by the models themselves (Tasks::list / Approvals::list / Meetings::listForUser).
 */

$me = Auth::current();
if (!$me) {
    redirect('login');
}

// ---------------------------------------------------------------- month window
$monthParam = (string) ($_GET['month'] ?? '');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
    $monthParam = date('Y-m');
}

$monthStart   = new DateTimeImmutable($monthParam . '-01');
$year         = (int) $monthStart->format('Y');
$month        = (int) $monthStart->format('n');
$daysInMonth  = (int) $monthStart->format('t');
$firstWeekday = (int) $monthStart->format('w'); // 0 = Sunday (Gulf/MENA week start)
$today        = date('Y-m-d');

/** Month link that keeps every other query argument. */
$monthLink = static function (string $m): string {
    $qs = $_GET;
    unset($qs['p'], $qs['month'], $qs['lang']);
    return u('calendar&month=' . $m . ($qs ? '&' . http_build_query($qs) : ''));
};

// ---------------------------------------------------------------- collect items
/** @var array<int, array<int, array{type:string,label:string,href:string}>> $items */
$items = [];

foreach (Tasks::list(['sort' => 'due'], $me, 500, 0) as $t) {
    if (in_array((string) $t['status'], ['completed', 'cancelled'], true)) {
        continue; // finished tasks no longer count as due
    }
    $due = substr((string) $t['due_date'], 0, 10);
    if ($due === '' || substr($due, 0, 7) !== $monthParam) {
        continue;
    }
    $items[(int) substr($due, 8, 2)][] = [
        'type'  => 'task',
        'label' => (string) $t['title'],
        'href'  => u('task&id=' . (int) $t['id']),
    ];
}

foreach (Approvals::list(['sort' => 'due'], $me, 500, 0) as $ap) {
    if ((string) $ap['status'] !== 'pending') {
        continue; // only requests still waiting count as due
    }
    $due = substr((string) $ap['due_date'], 0, 10);
    if ($due === '' || substr($due, 0, 7) !== $monthParam) {
        continue;
    }
    $items[(int) substr($due, 8, 2)][] = [
        'type'  => 'approval',
        'label' => (string) $ap['title'],
        'href'  => u('approval&id=' . (int) $ap['id']),
    ];
}

foreach (Meetings::listForUser($me, 200, false) as $m) {
    if ((string) $m['status'] === 'cancelled') {
        continue;
    }
    $day = substr((string) $m['starts_at'], 0, 10);
    if ($day === '' || substr($day, 0, 7) !== $monthParam) {
        continue;
    }
    $items[(int) substr($day, 8, 2)][] = [
        'type'  => 'meeting',
        'label' => (string) $m['title'],
        'href'  => u('meeting&id=' . (int) $m['id']),
    ];
}

foreach (Correspondence::forMonth($me, $monthParam) as $c) {
    $due = substr((string) $c['due_date'], 0, 10);
    if ($due === '' || substr($due, 0, 7) !== $monthParam) {
        continue;
    }
    $items[(int) substr($due, 8, 2)][] = [
        'type'  => 'letter',
        'label' => (string) $c['subject'],
        'href'  => u('letter&id=' . (int) $c['id']),
    ];
}

// Weekday short labels: Sunday-first, language-aware (not part of the lang table).
$weekdays = daem_current_lang() === 'ar'
    ? ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س']
    : ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

$monthNames = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
$monthTitle = daem_current_lang() === 'ar'
    ? $monthNames[$month - 1] . ' ' . $year
    : $monthStart->format('F Y');

layout_header(t('cal.title'), 'calendar');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e($monthTitle) ?></h2>
    <div class="quick-actions">
      <a class="btn btn-sm" href="<?= e($monthLink($monthStart->modify('-1 month')->format('Y-m'))) ?>" rel="prev">‹ <?= e(t('common.prev')) ?></a>
      <a class="btn btn-sm btn-ghost" href="<?= u('calendar') ?>"><?= e(t('cal.today')) ?></a>
      <a class="btn btn-sm" href="<?= e($monthLink($monthStart->modify('+1 month')->format('Y-m'))) ?>" rel="next"><?= e(t('common.next')) ?> ›</a>
    </div>
  </div>

  <div class="cal-grid">
    <?php foreach ($weekdays as $wd): ?>
      <div class="cal-head"><?= e($wd) ?></div>
    <?php endforeach; ?>

    <?php for ($i = 0; $i < $firstWeekday; $i++): ?>
      <div class="cal-cell cal-empty" aria-hidden="true"></div>
    <?php endfor; ?>

    <?php for ($day = 1; $day <= $daysInMonth; $day++):
        $date    = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $isToday = ($date === $today);
        $dayItems = $items[$day] ?? [];
        ?>
      <div class="cal-cell<?= $isToday ? ' cal-today' : '' ?>">
        <span class="cal-day"<?= $isToday ? ' title="' . e(t('cal.today')) . '"' : '' ?>><?= $day ?></span>
        <?php if (!$dayItems): ?>
          <span class="cell-muted" title="<?= e(t('cal.no_items')) ?>">—</span>
        <?php else: ?>
          <?php foreach ($dayItems as $it):
              $cls = $it['type'] === 'task' ? 'cal-task' : ($it['type'] === 'approval' ? 'cal-approval' : ($it['type'] === 'letter' ? 'cal-letter' : 'cal-meeting'));
              $kind = $it['type'] === 'task' ? t('cal.task_due') : ($it['type'] === 'approval' ? t('cal.approval_due') : ($it['type'] === 'letter' ? t('cal.letter_due') : t('cal.meeting')));
              ?>
            <a class="cal-item <?= $cls ?>" href="<?= e($it['href']) ?>" title="<?= e($kind . ': ' . $it['label']) ?>"><?= e($it['label']) ?></a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>

  <div class="cal-legend">
    <span><?= e(t('cal.legend')) ?>:</span>
    <span><span class="dot cal-task"></span><?= e(t('cal.task_due')) ?></span>
    <span><span class="dot cal-approval"></span><?= e(t('cal.approval_due')) ?></span>
    <span><span class="dot cal-meeting"></span><?= e(t('cal.meeting')) ?></span>
    <span><span class="dot cal-letter"></span><?= e(t('cal.letters')) ?></span>
  </div>
</section>

<?php layout_footer(); ?>
