<?php
declare(strict_types=1);

/**
 * Idara — one letter: full details, status change and edit form.
 */

$me = Auth::current();

$id     = (int) ($_GET['id'] ?? 0);
$letter = Correspondence::find($id);
if (!$letter) {
    http_response_code(404);
    layout_header(t('e404.title'), '');
    echo '<div class="panel"><h1 class="ticket-subject">404</h1><p class="muted-text">' . e(t('e404.text')) . '</p><a class="btn btn-primary" href="' . u('dashboard') . '">' . e(t('e404.back')) . '</a></div>';
    layout_footer();
    exit;
}

// Admin / executive, the assignee, or the assignee's manager may edit.
// (correspondence has no creator column, so the assignee's manager is the
// closest schema-consistent reading of "the creator's manager".)
$canEdit = in_array($me['role'], ['admin', 'executive'], true)
    || (int) $letter['assignee_id'] === (int) $me['id']
    || ($me['role'] === 'manager' && in_array((int) $letter['assignee_id'], Users::scopeIds($me), true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!$canEdit) {
        flash('error', t('corr.no_permission'));
        redirect('letter&id=' . $id);
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        if (array_key_exists($status, correspondence_statuses())) {
            Correspondence::setStatus($id, $status);
            flash('success', t('corr.status_changed'));
        }
    } elseif ($action === 'update') {
        $d = [
            'subject'       => trim((string) ($_POST['subject'] ?? '')),
            'summary'       => trim((string) ($_POST['summary'] ?? '')),
            'party'         => trim((string) ($_POST['party'] ?? '')),
            'reference_no'  => trim((string) ($_POST['reference_no'] ?? '')),
            'priority'      => (string) ($_POST['priority'] ?? $letter['priority']),
            'assignee_id'   => (int) ($_POST['assignee_id'] ?? 0) ?: null,
            'department_id' => (int) ($_POST['department_id'] ?? 0) ?: null,
            'due_date'      => trim((string) ($_POST['due_date'] ?? '')) ?: null,
        ];
        if (!array_key_exists($d['priority'], priorities())) {
            $d['priority'] = (string) $letter['priority'];
        }

        if ($d['subject'] === '') {
            flash('error', t('corr.err_subject'));
        } elseif ($d['party'] === '') {
            flash('error', t('corr.err_party'));
        } else {
            Correspondence::update($id, $d);
            flash('success', t('corr.updated_ok'));
        }
    }

    redirect('letter&id=' . $id);
}

$users = $canEdit ? Users::all(true) : [];
$depts = $canEdit ? Departments::all(true) : [];
$open  = in_array((string) $letter['status'], ['new', 'under_review'], true);
$dept  = bilingual($letter, 'dept_name');

layout_header(t('corr.details'), 'correspondence');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title">
      <span class="ref-pill"><?= e($letter['ref']) ?></span>
      <?= e($letter['subject']) ?>
    </h2>
    <div>
      <a class="btn btn-ghost btn-sm" href="<?= u('print-letter&id=' . $id) ?>" target="_blank" rel="noopener"><?= e(t('common.print')) ?></a>
      <a class="link" href="<?= u('correspondence') ?>">← <?= e(t('common.back')) ?></a>
    </div>
  </div>

  <div class="ticket-badges">
    <?= direction_badge((string) $letter['direction']) ?>
    <?= correspondence_status_badge((string) $letter['status']) ?>
    <?= priority_badge((string) $letter['priority']) ?>
  </div>

  <div class="meta-grid">
    <div class="meta-item">
      <span class="meta-label"><?= e(t('corr.col_party')) ?></span>
      <span class="meta-value"><?= e((string) $letter['party'] !== '' ? $letter['party'] : '—') ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('corr.reference_no')) ?></span>
      <span class="meta-value"><?= e((string) $letter['reference_no'] !== '' ? $letter['reference_no'] : '—') ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('corr.received_at')) ?></span>
      <span class="meta-value"><?= e(fmt_date($letter['received_at'])) ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('corr.due_date')) ?></span>
      <span class="meta-value"><?= $open ? due_chip($letter['due_date'], 'new') : due_chip($letter['due_date'], 'completed', $letter['status'] === 'archived' ? (correspondence_statuses()['archived'] ?? t('tstatus.completed')) : (correspondence_statuses()[$letter['status']] ?? t('tstatus.completed'))) ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('common.assignee')) ?></span>
      <span class="meta-value"><?= e($letter['assignee_name'] ?? t('common.unassigned')) ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('common.department')) ?></span>
      <span class="meta-value"><?= e($dept !== '' ? $dept : '—') ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('common.created')) ?></span>
      <span class="meta-value"><?= e(fmt_dt($letter['created_at'])) ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('common.updated')) ?></span>
      <span class="meta-value"><?= e(time_ago($letter['updated_at'])) ?></span>
    </div>
  </div>

  <h3 class="mini-title"><?= e(t('common.summary')) ?></h3>
  <div class="post-text"><?= $letter['summary'] !== '' ? render_body($letter['summary']) : '<span class="cell-muted">—</span>' ?></div>
</section>

<?php if ($canEdit): ?>
<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('tasks.change_status')) ?></h2>
    <form method="post" action="<?= u('letter&id=' . $id) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <label class="field-label" for="status"><?= e(t('common.status')) ?></label>
      <select class="input input-select" id="status" name="status">
        <?php foreach (correspondence_statuses() as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= (string) $letter['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('common.edit')) ?></h2>
    <form method="post" action="<?= u('letter&id=' . $id) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">

      <label class="field-label" for="e_subject"><?= e(t('common.subject')) ?></label>
      <input class="input" id="e_subject" name="subject" type="text" maxlength="250" required value="<?= e((string) $letter['subject']) ?>">

      <label class="field-label" for="e_party"><?= e(t('corr.party')) ?></label>
      <input class="input" id="e_party" name="party" type="text" maxlength="180" required value="<?= e((string) $letter['party']) ?>">

      <label class="field-label" for="e_reference_no"><?= e(t('corr.reference_no')) ?></label>
      <input class="input" id="e_reference_no" name="reference_no" type="text" maxlength="120" value="<?= e((string) $letter['reference_no']) ?>">

      <label class="field-label" for="e_summary"><?= e(t('common.summary')) ?></label>
      <textarea class="input" id="e_summary" name="summary" rows="4"><?= e((string) $letter['summary']) ?></textarea>

      <label class="field-label" for="e_priority"><?= e(t('common.priority')) ?></label>
      <select class="input input-select" id="e_priority" name="priority">
        <?php foreach (priorities() as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= (string) $letter['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="e_assignee_id"><?= e(t('common.assignee')) ?></label>
      <select class="input input-select" id="e_assignee_id" name="assignee_id">
        <option value="0"><?= e(t('common.unassigned')) ?></option>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= (int) $letter['assignee_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="e_department_id"><?= e(t('common.department')) ?></label>
      <select class="input input-select" id="e_department_id" name="department_id">
        <option value="0"><?= e(t('common.select')) ?></option>
        <?php foreach ($depts as $dep): ?>
          <option value="<?= (int) $dep['id'] ?>" <?= (int) $letter['department_id'] === (int) $dep['id'] ? 'selected' : '' ?>><?= e(bilingual($dep, 'name')) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="e_due_date"><?= e(t('corr.due_date')) ?></label>
      <input class="input" id="e_due_date" name="due_date" type="date" value="<?= e($letter['due_date'] ? substr((string) $letter['due_date'], 0, 10) : '') ?>">

      <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
    </form>
  </div>
</section>
<?php else: ?>
<div class="panel panel-muted">
  <p class="muted-text"><?= e(t('corr.no_permission')) ?></p>
</div>
<?php endif; ?>

<?php layout_footer(); ?>
