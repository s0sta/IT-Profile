<?php
declare(strict_types=1);

/**
 * Idara — one meeting: agenda, attendance register, minutes and quick status
 * changes.
 */

$me = Auth::current();

$id      = (int) ($_GET['id'] ?? 0);
$meeting = Meetings::find($id);
if (!$meeting) {
    http_response_code(404);
    exit(t('e404.title'));
}

// Only the organizer (or an admin / executive) may change the register,
// the minutes or the meeting status.
$canManage = in_array($me['role'], ['admin', 'executive'], true)
    || (int) $meeting['organizer_id'] === (int) $me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = (string) ($_POST['action'] ?? '');

    /** Persist status + minutes without dropping the meeting's other fields. */
    $save = static function (array $meeting, string $status, string $minutes) use ($id): void {
        Meetings::update($id, [
            'title'     => (string) $meeting['title'],
            'agenda'    => (string) $meeting['agenda'],
            'location'  => (string) $meeting['location'],
            'starts_at' => (string) $meeting['starts_at'],
            'ends_at'   => (string) ($meeting['ends_at'] ?? ''),
            'status'    => $status,
            'minutes'   => $minutes,
        ]);
    };

    if (!$canManage) {
        flash('error', t('auth.denied'));
        redirect('meeting&id=' . $id);
    }

    if ($action === 'attendance') {
        $userId   = (int) ($_POST['user_id'] ?? 0);
        $attended = (string) ($_POST['attended'] ?? '0') === '1';
        if ($userId > 0) {
            Meetings::setAttended($id, $userId, $attended);
            flash('success', t('meetings.updated_ok'));
        }
        redirect('meeting&id=' . $id);
    }

    if ($action === 'minutes' || $action === 'status') {
        $status = (string) ($_POST['status'] ?? $meeting['status']);
        if (!array_key_exists($status, meeting_statuses())) {
            $status = (string) $meeting['status'];
        }
        $minutes = $action === 'minutes'
            ? trim((string) ($_POST['minutes'] ?? ''))
            : (string) $meeting['minutes'];

        $save($meeting, $status, $minutes);
        flash('success', t('meetings.updated_ok'));
        redirect('meeting&id=' . $id);
    }

    redirect('meeting&id=' . $id);
}

$attendees = Meetings::attendees($id);
$present   = 0;
foreach ($attendees as $a) {
    if ((int) $a['attended'] === 1) {
        $present++;
    }
}

layout_header(t('meetings.details'), 'meetings');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e($meeting['title']) ?></h2>
    <div>
      <a class="link" href="<?= u('meetings') ?>">← <?= e(t('common.back')) ?></a>
    </div>
  </div>

  <div class="ticket-badges">
    <span class="badge"><?= e(meeting_statuses()[(string) $meeting['status']] ?? (string) $meeting['status']) ?></span>
    <span class="badge"><?= e(t('meetings.attendees_count', ['n' => count($attendees)])) ?></span>
    <span class="badge"><?= e(t('meetings.present')) ?>: <?= $present ?></span>
  </div>

  <?php if ($canManage): ?>
    <div class="ticket-actions">
      <?php if ((string) $meeting['status'] !== 'done'): ?>
        <form method="post" action="<?= u('meeting&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status" value="done">
          <button class="btn btn-primary btn-sm" type="submit"><?= icon('check') ?> <?= e(t('meetings.mark_done')) ?></button>
        </form>
      <?php endif; ?>
      <?php if ((string) $meeting['status'] !== 'cancelled'): ?>
        <form method="post" action="<?= u('meeting&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status" value="cancelled">
          <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= icon('x') ?> <?= e(t('meetings.mark_cancelled')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="meta-grid">
    <div class="meta-item">
      <span class="meta-label"><?= e(t('meetings.organizer')) ?></span>
      <span class="meta-value"><?= e($meeting['organizer_name'] ?? '—') ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('meetings.starts_at')) ?></span>
      <span class="meta-value"><?= e(fmt_dt($meeting['starts_at'])) ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('meetings.ends_at')) ?></span>
      <span class="meta-value"><?= e(fmt_dt($meeting['ends_at'])) ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('common.location')) ?></span>
      <span class="meta-value"><?= e((string) $meeting['location'] !== '' ? $meeting['location'] : '—') ?></span>
    </div>
  </div>

  <h3 class="mini-title"><?= e(t('common.agenda')) ?></h3>
  <div class="post-text"><?= $meeting['agenda'] !== '' ? render_body($meeting['agenda']) : '<span class="cell-muted">—</span>' ?></div>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('meetings.attendees')) ?> (<?= count($attendees) ?>)</h2>
  <?php if (!$attendees): ?>
    <div class="empty"><?= e(t('common.no_results')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('common.name')) ?></th>
            <th><?= e(t('common.role')) ?></th>
            <th><?= e(t('team.job_title')) ?></th>
            <th><?= e(t('meetings.attendance')) ?></th>
            <?php if ($canManage): ?><th><?= e(t('common.actions')) ?></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attendees as $a): ?>
            <tr>
              <td>
                <span class="avatar"><?= e(initials($a['name'])) ?></span>
                <?= e($a['name']) ?>
              </td>
              <td><?= role_badge((string) $a['role']) ?></td>
              <td><?= e((string) $a['job_title'] !== '' ? $a['job_title'] : '—') ?></td>
              <td>
                <?php if ((int) $a['attended'] === 1): ?>
                  <span class="badge badge-active"><?= e(t('meetings.present')) ?></span>
                <?php else: ?>
                  <span class="badge badge-inactive"><?= e(t('meetings.absent')) ?></span>
                <?php endif; ?>
              </td>
              <?php if ($canManage): ?>
                <td class="cell-actions">
                  <form method="post" action="<?= u('meeting&id=' . $id) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="attendance">
                    <input type="hidden" name="user_id" value="<?= (int) $a['user_id'] ?>">
                    <select class="input input-select auto-submit" name="attended" aria-label="<?= e(t('meetings.attendance')) ?>">
                      <option value="1" <?= (int) $a['attended'] === 1 ? 'selected' : '' ?>><?= e(t('meetings.present')) ?></option>
                      <option value="0" <?= (int) $a['attended'] === 0 ? 'selected' : '' ?>><?= e(t('meetings.absent')) ?></option>
                    </select>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('common.save')) ?></button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('meetings.minutes')) ?></h2>
  <?php if ($canManage): ?>
    <form method="post" action="<?= u('meeting&id=' . $id) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="minutes">

      <label class="field-label" for="minutes"><?= e(t('common.minutes')) ?></label>
      <textarea class="input" id="minutes" name="minutes" rows="6" placeholder="<?= e(t('meetings.minutes_placeholder')) ?>"><?= e((string) $meeting['minutes']) ?></textarea>

      <label class="field-label" for="status"><?= e(t('common.status')) ?></label>
      <select class="input input-select" id="status" name="status">
        <?php foreach (meeting_statuses() as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= (string) $meeting['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
    </form>
  <?php else: ?>
    <div class="post-text"><?= $meeting['minutes'] !== '' ? render_body($meeting['minutes']) : '<span class="cell-muted">—</span>' ?></div>
    <p class="muted-text"><?= e(t('auth.denied')) ?></p>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
