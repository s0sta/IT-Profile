<?php
declare(strict_types=1);

$id     = (int) ($_GET['id'] ?? 0);
$ticket = Tickets::find($id);
if (!$ticket) {
    http_response_code(404);
    exit(t('e404.title'));
}
if (!Tickets::canView($ticket)) {
    http_response_code(403);
    exit(t('auth.denied'));
}

$staff = Auth::isStaff();

// ---------------------------------------------------------------- POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'reply') {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '') {
            flash('error', t('tv.msg_required'));
        } else {
            $internal = $staff && !empty($_POST['internal']);
            $replyId = Replies::add($id, Auth::id(), $body, $internal, !empty($_FILES['attachments']['name']));

            if ($staff && !$internal) {
                Tickets::markFirstResponse($id);
            }
            if (!$staff && in_array($ticket['status'], ['resolved', 'closed'], true)) {
                Tickets::reopen($id);
            }
            if ($internal) {
                if (!empty($ticket['assignee_id']) && (int) $ticket['assignee_id'] !== Auth::id()) {
                    notify((int) $ticket['assignee_id'], t('notif.msg_internal_note', ['ref' => $ticket['ref']]), u('ticket&id=' . $id));
                }
            } else {
                Tickets::notifyInvolved($ticket, t('notif.msg_new_reply', ['ref' => $ticket['ref']]));
            }
            flash('success', $internal ? t('tv.note_added') : t('tv.reply_sent'));
        }
    } elseif ($staff && $action === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        if (array_key_exists($status, statuses())) {
            Tickets::setStatus($id, $status);
            flash('success', t('tv.status_updated', ['status' => statuses()[$status]]));
        }
    } elseif ($staff && $action === 'assign') {
        $assignee = (int) ($_POST['assignee_id'] ?? 0) ?: null;
        Tickets::assign($id, $assignee);
        flash('success', $assignee ? t('tv.assigned') : t('tv.unassigned_flash'));
    } elseif ($staff && $action === 'priority') {
        $priority = (string) ($_POST['priority'] ?? '');
        if (array_key_exists($priority, priorities())) {
            Tickets::setPriority($id, $priority);
            flash('success', t('tv.priority_updated', ['priority' => priorities()[$priority]]));
        }
    } elseif ($staff && $action === 'category') {
        $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
        Tickets::setCategory($id, $categoryId);
        flash('success', t('tv.category_updated'));
    } elseif (!$staff && $action === 'close') {
        if ($ticket['status'] === 'resolved') {
            Tickets::setStatus($id, 'closed');
            flash('success', t('tv.closed_flash'));
        }
    }
    redirect('ticket&id=' . $id);
}

// Reload after any state change.
$ticket  = Tickets::find($id);
$replies = Replies::byTicket($id);
$agents  = Users::agents();
$cats    = Categories::all(true);
$sla     = sla_state($ticket);

layout_header($ticket['ref'] . ' — ' . $ticket['subject'], 'tickets');
?>

<div class="panel ticket-head-panel">
  <div class="ticket-head">
    <div class="ticket-title">
      <span class="ref-pill"><?= e($ticket['ref']) ?></span>
      <h2 class="ticket-subject"><?= e($ticket['subject']) ?></h2>
      <div class="ticket-badges">
        <?= status_badge($ticket['status']) ?>
        <?= priority_badge($ticket['priority']) ?>
        <?php if ($ticket['category_name']): ?><span class="badge badge-cat"><?= e($ticket['category_name']) ?></span><?php endif; ?>
      </div>
    </div>

    <?php if ($staff): ?>
      <div class="ticket-actions">
        <form method="post" action="<?= u('ticket&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="status">
          <select name="status" class="input input-select auto-submit" aria-label="<?= e(t('common.status')) ?>">
            <?php foreach (statuses() as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $ticket['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <form method="post" action="<?= u('ticket&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="assign">
          <select name="assignee_id" class="input input-select auto-submit" aria-label="<?= e(t('common.assignee')) ?>">
            <option value="0"><?= e(t('common.unassigned')) ?></option>
            <?php foreach ($agents as $a): ?>
              <option value="<?= (int) $a['id'] ?>" <?= (int) ($ticket['assignee_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <form method="post" action="<?= u('ticket&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="priority">
          <select name="priority" class="input input-select auto-submit" aria-label="<?= e(t('common.priority')) ?>">
            <?php foreach (priorities() as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $ticket['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <form method="post" action="<?= u('ticket&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="category">
          <select name="category_id" class="input input-select auto-submit" aria-label="<?= e(t('common.category')) ?>">
            <option value="0"><?= e(t('common.no_category')) ?></option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) ($ticket['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    <?php elseif ($ticket['status'] === 'resolved'): ?>
      <form method="post" action="<?= u('ticket&id=' . $id) ?>" class="inline-form"
            data-confirm="<?= e(t('tv.confirm_close')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="close">
        <button class="btn" type="submit"><?= e(t('tv.close_ticket')) ?></button>
      </form>
    <?php endif; ?>
  </div>

  <div class="meta-grid">
    <div class="meta-item"><span class="meta-label"><?= e(t('common.requester')) ?></span><span class="meta-value"><?= e($ticket['requester_name']) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.assignee')) ?></span><span class="meta-value"><?= e($ticket['assignee_name'] ?? t('common.unassigned')) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.category')) ?></span><span class="meta-value"><?= e($ticket['category_name'] ?? '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.created')) ?></span><span class="meta-value"><?= e(fmt_dt($ticket['created_at'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.updated')) ?></span><span class="meta-value"><?= e(time_ago($ticket['updated_at'])) ?></span></div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('tv.sla_due')) ?></span>
      <span class="meta-value">
        <?= e(fmt_dt($ticket['sla_due'])) ?>
        <?php if ($sla === 'breached'): ?><span class="sla-chip sla-breach"><?= e(t('tv.sla_breach')) ?></span>
        <?php elseif (is_open_status($ticket['status'])): ?><span class="sla-chip sla-ok"><?= e(t('tv.sla_ok')) ?></span><?php endif; ?>
      </span>
    </div>
    <div class="meta-item"><span class="meta-label"><?= e(t('tv.first_response')) ?></span><span class="meta-value"><?= e($ticket['first_response_at'] ? fmt_dt($ticket['first_response_at']) : '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('tv.resolved_at')) ?></span><span class="meta-value"><?= e($ticket['resolved_at'] ? fmt_dt($ticket['resolved_at']) : '—') ?></span></div>
  </div>
</div>

<section class="thread">
  <div class="post">
    <span class="avatar"><?= e(initials($ticket['requester_name'])) ?></span>
    <div class="post-body">
      <div class="post-head">
        <strong><?= e($ticket['requester_name']) ?></strong>
        <span class="badge badge-role badge-role-user"><?= e(t('tv.badge_requester')) ?></span>
        <span class="post-time"><?= e(fmt_dt($ticket['created_at'])) ?></span>
      </div>
      <div class="post-text"><?= render_body($ticket['description']) ?></div>
    </div>
  </div>

  <?php foreach ($replies as $r):
      if (!$staff && $r['is_internal']) {
          continue;
      }
      $atts = Replies::attachments((int) $r['id']);
  ?>
    <div class="post <?= $r['is_internal'] ? 'post-internal' : '' ?>">
      <span class="avatar"><?= e(initials($r['author_name'])) ?></span>
      <div class="post-body">
        <div class="post-head">
          <strong><?= e($r['author_name']) ?></strong>
          <?= role_badge($r['author_role']) ?>
          <?php if ($r['is_internal']): ?><span class="badge badge-internal"><?= e(t('tv.internal_note')) ?></span><?php endif; ?>
          <span class="post-time"><?= e(fmt_dt($r['created_at'])) ?></span>
        </div>
        <div class="post-text"><?= render_body($r['body']) ?></div>
        <?php if ($atts): ?>
          <div class="post-attachments">
            <?php foreach ($atts as $a): ?>
              <a class="att" href="<?= u('download&id=' . (int) $a['id']) ?>">📎 <?= e($a['original_name']) ?>
                <span class="cell-muted">(<?= round($a['size'] / 1024, 1) ?> KB)</span></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<?php if ($staff ? is_open_status($ticket['status']) : true): ?>
<section class="panel">
  <h2 class="panel-title"><?= e($staff ? t('tv.reply') : t('tv.message')) ?></h2>
  <form method="post" action="<?= u('ticket&id=' . $id) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reply">
    <textarea class="input" name="body" rows="5" required placeholder="<?= e(t('tv.message')) ?>…"></textarea>
    <div class="reply-bar">
      <div class="reply-left">
        <label class="file-label">
          <input type="file" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip">
          📎 <?= e(t('tv.attach')) ?> <span class="cell-muted">(<?= e(t('tv.attachments_hint')) ?>)</span>
        </label>
        <?php if ($staff): ?>
          <label class="check-label"><input type="checkbox" name="internal" value="1"> <?= e(t('tv.internal')) ?></label>
        <?php endif; ?>
      </div>
      <button class="btn btn-primary" type="submit"><?= e(t('tv.send')) ?></button>
    </div>
  </form>
</section>
<?php else: ?>
  <div class="panel panel-muted">
    <p class="muted-text"><?= e($staff
        ? t('tv.closed_note_staff', ['status' => statuses()[$ticket['status']]])
        : t('tv.closed_note_user', ['status' => statuses()[$ticket['status']]])) ?></p>
  </div>
<?php endif; ?>

<?php layout_footer(); ?>
