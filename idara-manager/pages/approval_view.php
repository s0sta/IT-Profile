<?php
declare(strict_types=1);

$me  = Auth::current();
$id  = (int) ($_GET['id'] ?? 0);
$approval = Approvals::find($id);

if (!$approval) {
    http_response_code(404);
    exit(t('e404.title'));
}
if (!Approvals::canView($approval, $me)) {
    http_response_code(403);
    exit(t('auth.denied'));
}

$canDecide = Approvals::canDecide($approval, (int) $me['id']);
$isMine    = (int) $approval['requester_id'] === (int) $me['id'];
$current   = Approvals::currentStep($id);
$delegated = false;
if ($canDecide && $current && (int) $current['approver_id'] !== (int) $me['id']) {
    $delegated = true;
}

// ---------------------------------------------------------------- POST: decision
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'decide' && $canDecide) {
        $decision = (string) ($_POST['decision'] ?? '');
        $note     = trim((string) ($_POST['note'] ?? ''));

        if (!in_array($decision, ['approved', 'rejected', 'returned'], true)) {
            flash('error', t('approvals.err_type'));
        } elseif ($decision !== 'approved' && $note === '') {
            flash('error', t('approvals.err_note'));
        } else {
            Approvals::decide($id, $decision, $note);
            handle_uploads(null, null, $id, (int) $me['id']);
            $key = $decision === 'approved' ? 'approvals.approved_msg' : ($decision === 'rejected' ? 'approvals.rejected_msg' : 'approvals.returned_msg');
            flash('success', t($key));
        }
        redirect('approval&id=' . $id);
    }
}

$approval = Approvals::find($id);
$steps    = Approvals::steps($id);
$current  = Approvals::currentStep($id);
$files    = attachmentsFor(null, null, $id);

layout_header($approval['ref'] . ' — ' . $approval['title'], 'approvals');
?>

<div class="panel ticket-head-panel">
  <div class="ticket-head">
    <div class="ticket-title">
      <span class="ref-pill"><?= e($approval['ref']) ?></span>
      <h2 class="ticket-subject"><?= e($approval['title']) ?></h2>
      <div class="ticket-badges">
        <?= approval_status_badge((string) $approval['status']) ?>
        <?= priority_badge((string) $approval['priority']) ?>
        <?php if ($approval['type_name_ar'] || $approval['type_name_en']): ?>
          <span class="badge badge-cat"><?= e(bilingual($approval, 'type_name')) ?></span>
        <?php endif; ?>
        <?php if ($approval['dept_name_ar'] || $approval['dept_name_en']): ?>
          <span class="badge badge-cat"><?= e(bilingual($approval, 'dept_name')) ?></span>
        <?php endif; ?>
        <?php if ($canDecide): ?><span class="badge badge-actionable"><?= e(t('approvals.you_decide')) ?></span><?php endif; ?>
      </div>
    </div>
    <?php if ($files): ?>
      <div class="post-attachments">
        <?php foreach ($files as $a): ?>
          <a class="att" href="<?= u('download&id=' . (int) $a['id']) ?>">📎 <?= e($a['original_name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="meta-grid">
    <div class="meta-item"><span class="meta-label"><?= e(t('approvals.requested_by')) ?></span><span class="meta-value"><?= e($approval['requester_name'] ?? '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('approvals.submitted_at')) ?></span><span class="meta-value"><?= e(fmt_dt($approval['created_at'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('approvals.col_due')) ?></span><span class="meta-value"><?= due_chip($approval['due_date'], $approval['status'] === 'pending' ? 'new' : 'completed') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('approvals.current')) ?></span><span class="meta-value"><?= e(t('approvals.step_of', ['a' => (int) $approval['current_step'], 'b' => (int) $approval['steps_total']])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.status')) ?></span><span class="meta-value"><?= approval_status_badge((string) $approval['status']) ?></span></div>
    <?php if ($approval['closed_at']): ?>
      <div class="meta-item"><span class="meta-label"><?= e(t('approvals.decided_at')) ?></span><span class="meta-value"><?= e(fmt_dt($approval['closed_at'])) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($approval['related_task_id'])): ?>
      <div class="meta-item"><span class="meta-label"><?= e(t('approvals.related_task')) ?></span>
        <span class="meta-value"><a class="row-link" href="<?= u('task&id=' . (int) $approval['related_task_id']) ?>"><?= e(Tasks::makeRef((int) $approval['related_task_id'])) ?></a></span>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (trim((string) $approval['description']) !== ''): ?>
<section class="panel">
  <h2 class="panel-title"><?= e(t('approvals.request_desc')) ?></h2>
  <div class="post-text"><?= render_body($approval['description']) ?></div>
</section>
<?php endif; ?>

<?php if ($canDecide): ?>
<section class="panel panel-accent">
  <h2 class="panel-title"><?= e(t('approvals.decide')) ?></h2>
  <?php if ($delegated): ?>
    <p class="muted-text"><?= e(t('approvals.delegated_note')) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= u('approval&id=' . $id) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="decide">
    <label class="field-label"><?= e(t('approvals.decision_note')) ?></label>
    <textarea class="input" name="note" rows="3" placeholder="<?= e(t('approvals.note_placeholder')) ?>"></textarea>
    <div class="reply-bar">
      <div class="reply-left">
        <label class="file-label">
          <input type="file" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip">
          📎 <?= e(t('common.attach')) ?>
        </label>
      </div>
      <div class="decision-buttons">
        <button class="btn btn-approve" type="submit" name="decision" value="approved"><?= icon('check') ?> <?= e(t('approvals.approve')) ?></button>
        <button class="btn btn-return" type="submit" name="decision" value="returned"><?= e(t('approvals.return')) ?></button>
        <button class="btn btn-reject" type="submit" name="decision" value="rejected"><?= icon('x') ?> <?= e(t('approvals.reject')) ?></button>
      </div>
    </div>
  </form>
</section>
<?php elseif ($approval['status'] === 'pending'): ?>
  <div class="panel panel-muted">
    <p class="muted-text"><?= e(t('approvals.waiting_others')) ?><?= $current ? ': ' . e($current['approver_name'] ?? '') : '' ?></p>
  </div>
<?php else: ?>
  <div class="panel panel-muted">
    <p class="muted-text"><?= e(t('approvals.closed')) ?></p>
  </div>
<?php endif; ?>

<section class="panel">
  <h2 class="panel-title"><?= e(t('approvals.history')) ?></h2>
  <ol class="approval-chain">
    <?php foreach ($steps as $i => $step): ?>
      <li class="chain-step chain-<?= e((string) $step['status']) ?>">
        <span class="chain-index"><?= (int) $step['step_order'] ?></span>
        <div class="chain-body">
          <div class="chain-head">
            <strong><?= e($step['approver_name'] ?? '—') ?></strong>
            <?= role_badge((string) ($step['approver_role'] ?? 'manager')) ?>
            <?= step_status_badge((string) $step['status']) ?>
            <?php if ((int) $step['step_order'] === (int) $approval['current_step'] && $approval['status'] === 'pending'): ?>
              <span class="badge badge-actionable"><?= e(t('approvals.current')) ?></span>
            <?php endif; ?>
          </div>
          <?php if (trim((string) $step['note']) !== ''): ?>
            <div class="chain-note"><?= render_body($step['note']) ?></div>
          <?php endif; ?>
          <?php if ($step['decided_at']): ?>
            <div class="cell-muted"><?= e(fmt_dt($step['decided_at'])) ?></div>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</section>

<?php layout_footer(); ?>
