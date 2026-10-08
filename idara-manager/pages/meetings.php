<?php
declare(strict_types=1);

/**
 * Idara — meetings: schedule a new one and browse upcoming / past meetings.
 */

$me = Auth::current();

// Input kept across the PRG redirect when validation fails.
$old          = $_SESSION['old_meeting'] ?? [];
$oldAttendees = array_map('intval', (array) ($_SESSION['old_meeting_attendees'] ?? []));
unset($_SESSION['old_meeting'], $_SESSION['old_meeting_attendees']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $title    = trim((string) ($_POST['title'] ?? ''));
    $agenda   = trim((string) ($_POST['agenda'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $startsRaw = trim((string) ($_POST['starts_at'] ?? ''));
    $endsRaw   = trim((string) ($_POST['ends_at'] ?? ''));

    $attendeeIds = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['attendees'] ?? [])),
        static fn (int $v): bool => $v > 0
    )));

    $errors = [];
    if ($title === '') {
        $errors[] = t('meetings.err_title');
    }

    $starts = ($startsRaw !== '' && strtotime($startsRaw) !== false) ? date('Y-m-d H:i:s', strtotime($startsRaw)) : null;
    $ends   = ($endsRaw !== '' && strtotime($endsRaw) !== false) ? date('Y-m-d H:i:s', strtotime($endsRaw)) : null;
    if ($starts === null) {
        $errors[] = t('meetings.err_start');
    }

    if (!$attendeeIds) {
        $errors[] = t('meetings.err_attendees');
    }

    if (!$errors) {
        $id = Meetings::create([
            'title'     => $title,
            'agenda'    => $agenda,
            'location'  => $location,
            'starts_at' => (string) $starts,
            'ends_at'   => $ends,
        ], $attendeeIds);

        flash('success', t('meetings.created_ok'));
        redirect('meeting&id=' . $id);
    }

    foreach ($errors as $err) {
        flash('error', $err);
    }
    $_SESSION['old_meeting'] = [
        'title'     => $title,
        'agenda'    => $agenda,
        'location'  => $location,
        'starts_at' => $startsRaw,
        'ends_at'   => $endsRaw,
    ];
    $_SESSION['old_meeting_attendees'] = $attendeeIds;
    redirect('meetings');
}

$users = Users::all(true);

$upcoming = Meetings::listForUser($me, 50, true);
// listForUser(..., false) has no upper bound, so keep only what already started
// to avoid duplicating the upcoming list.
$todayStart = date('Y-m-d 00:00:00');
$past = array_slice(array_values(array_filter(
    Meetings::listForUser($me, 20, false),
    static fn (array $m): bool => (string) $m['starts_at'] < $todayStart
)), 0, 20);

/** Shared table for the upcoming and past lists. */
$meeting_table = static function (array $rows): void {
    if (!$rows) {
        echo '<div class="empty">' . e(t('meetings.no_meetings')) . '</div>';
        return;
    }
    ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('common.subject')) ?></th>
            <th><?= e(t('meetings.starts_at')) ?></th>
            <th><?= e(t('common.location')) ?></th>
            <th><?= e(t('meetings.organizer')) ?></th>
            <th><?= e(t('meetings.attendees')) ?></th>
            <th><?= e(t('common.status')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $m): ?>
            <tr>
              <td class="cell-subject">
                <a class="row-link" href="<?= u('meeting&id=' . (int) $m['id']) ?>"><?= e($m['title']) ?></a>
              </td>
              <td><?= e(fmt_dt($m['starts_at'])) ?></td>
              <td><?= e((string) $m['location'] !== '' ? $m['location'] : '—') ?></td>
              <td><?= e(bilingual($m, 'organizer_name')) ?></td>
              <td><?= (int) $m['attendees_count'] === 1 ? e(t('meetings.attendees_one')) : e(t('meetings.attendees_count', ['n' => (int) $m['attendees_count']])) ?></td>
              <td><span class="badge"><?= e(meeting_statuses()[(string) $m['status']] ?? (string) $m['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
};

layout_header(t('meetings.title'), 'meetings');
?>

<section class="panel">
  <h2 class="panel-title"><?= e(t('meetings.new')) ?></h2>
  <form method="post" action="<?= u('meetings') ?>">
    <?= csrf_field() ?>

    <div class="grid-2">
      <div>
        <label class="field-label" for="title"><?= e(t('common.subject')) ?></label>
        <input class="input" id="title" name="title" type="text" maxlength="250" required value="<?= e((string) ($old['title'] ?? '')) ?>">

        <label class="field-label" for="agenda"><?= e(t('common.agenda')) ?></label>
        <textarea class="input" id="agenda" name="agenda" rows="4"><?= e((string) ($old['agenda'] ?? '')) ?></textarea>

        <label class="field-label" for="location"><?= e(t('common.location')) ?></label>
        <input class="input" id="location" name="location" type="text" maxlength="180" value="<?= e((string) ($old['location'] ?? '')) ?>">
      </div>

      <div>
        <label class="field-label" for="starts_at"><?= e(t('meetings.starts_at')) ?></label>
        <input class="input" id="starts_at" name="starts_at" type="datetime-local" required value="<?= e((string) ($old['starts_at'] ?? '')) ?>">

        <label class="field-label" for="ends_at"><?= e(t('meetings.ends_at')) ?></label>
        <input class="input" id="ends_at" name="ends_at" type="datetime-local" value="<?= e((string) ($old['ends_at'] ?? '')) ?>">

        <label class="field-label" for="attendees"><?= e(t('meetings.attendees')) ?></label>
        <select class="input" id="attendees" name="attendees[]" multiple size="6" required>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= in_array((int) $u['id'], $oldAttendees, true) || (empty($oldAttendees) && (int) $u['id'] === (int) $me['id']) ? 'selected' : '' ?>>
              <?= e($u['name']) ?><?= $u['job_title'] !== '' ? ' — ' . e($u['job_title']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="muted-text"><?= e(t('meetings.hint_attendees')) ?></p>
      </div>
    </div>

    <button class="btn btn-primary" type="submit"><?= e(t('common.create')) ?></button>
  </form>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('meetings.upcoming')) ?></h2>
  <?php $meeting_table($upcoming); ?>
</section>

<section class="panel panel-muted">
  <details>
    <summary class="panel-title"><?= e(t('meetings.past')) ?> (<?= count($past) ?>)</summary>
    <?php $meeting_table($past); ?>
  </details>
</section>

<?php layout_footer(); ?>
