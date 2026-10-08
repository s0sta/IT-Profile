<?php
declare(strict_types=1);

/**
 * Idara — register a new letter (incoming or outgoing).
 *
 * Attachments are intentionally not offered here: the attachments table has no
 * correspondence column, so files could never be retrieved again.
 */

$me = Auth::current();

// Input kept across the PRG redirect when validation fails.
$old = $_SESSION['old_letter'] ?? [];
unset($_SESSION['old_letter']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $d = [
        'direction'     => (string) ($_POST['direction'] ?? 'incoming'),
        'subject'       => trim((string) ($_POST['subject'] ?? '')),
        'summary'       => trim((string) ($_POST['summary'] ?? '')),
        'party'         => trim((string) ($_POST['party'] ?? '')),
        'reference_no'  => trim((string) ($_POST['reference_no'] ?? '')),
        'priority'      => (string) ($_POST['priority'] ?? 'medium'),
        'assignee_id'   => (int) ($_POST['assignee_id'] ?? 0) ?: null,
        'department_id' => (int) ($_POST['department_id'] ?? 0) ?: null,
        'received_at'   => trim((string) ($_POST['received_at'] ?? '')) ?: null,
        'due_date'      => trim((string) ($_POST['due_date'] ?? '')) ?: null,
    ];

    $errors = [];
    if ($d['subject'] === '') {
        $errors[] = t('corr.err_subject');
    }
    if ($d['party'] === '') {
        $errors[] = t('corr.err_party');
    }
    if (!array_key_exists($d['direction'], directions())) {
        $d['direction'] = 'incoming';
    }
    if (!array_key_exists($d['priority'], priorities())) {
        $d['priority'] = 'medium';
    }

    if (!$errors) {
        $id = Correspondence::create($d, $me);
        flash('success', t('corr.created_ok'));
        redirect('letter&id=' . $id);
    }

    foreach ($errors as $err) {
        flash('error', $err);
    }
    $_SESSION['old_letter'] = $d;
    redirect('new-letter');
}

$users = Users::all(true);
$depts = Departments::all(true);

$vDirection  = (string) ($old['direction'] ?? 'incoming');
$vPriority   = (string) ($old['priority'] ?? 'medium');
$vReceivedAt = array_key_exists('received_at', $old) ? (string) ($old['received_at'] ?? '') : date('Y-m-d');

layout_header(t('corr.new'), 'correspondence');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('corr.new')) ?></h2>
    <a class="link" href="<?= u('correspondence') ?>">← <?= e(t('common.back')) ?></a>
  </div>

  <form method="post" action="<?= u('new-letter') ?>">
    <?= csrf_field() ?>

    <div class="grid-2">
      <div>
        <label class="field-label" for="direction"><?= e(t('corr.col_direction')) ?></label>
        <select class="input input-select" id="direction" name="direction">
          <?php foreach (directions() as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $vDirection === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="field-label" for="subject"><?= e(t('common.subject')) ?></label>
        <input class="input" id="subject" name="subject" type="text" maxlength="250" required
               value="<?= e((string) ($old['subject'] ?? '')) ?>">

        <label class="field-label" for="party"><?= e(t('corr.party')) ?></label>
        <input class="input" id="party" name="party" type="text" maxlength="180" required
               value="<?= e((string) ($old['party'] ?? '')) ?>">

        <label class="field-label" for="reference_no"><?= e(t('corr.reference_no')) ?> <span class="cell-muted">(<?= e(t('common.optional')) ?>)</span></label>
        <input class="input" id="reference_no" name="reference_no" type="text" maxlength="120"
               value="<?= e((string) ($old['reference_no'] ?? '')) ?>">

        <label class="field-label" for="summary"><?= e(t('common.summary')) ?></label>
        <textarea class="input" id="summary" name="summary" rows="4"><?= e((string) ($old['summary'] ?? '')) ?></textarea>
      </div>

      <div>
        <label class="field-label" for="priority"><?= e(t('common.priority')) ?></label>
        <select class="input input-select" id="priority" name="priority">
          <?php foreach (priorities() as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $vPriority === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="field-label" for="assignee_id"><?= e(t('common.assignee')) ?></label>
        <select class="input input-select" id="assignee_id" name="assignee_id">
          <option value="0"><?= e(t('common.unassigned')) ?></option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= (int) ($old['assignee_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="field-label" for="department_id"><?= e(t('common.department')) ?></label>
        <select class="input input-select" id="department_id" name="department_id">
          <option value="0"><?= e(t('common.select')) ?></option>
          <?php foreach ($depts as $dep): ?>
            <option value="<?= (int) $dep['id'] ?>" <?= (int) ($old['department_id'] ?? 0) === (int) $dep['id'] ? 'selected' : '' ?>><?= e(bilingual($dep, 'name')) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="field-label" for="received_at"><?= e(t('corr.received_at')) ?></label>
        <input class="input" id="received_at" name="received_at" type="date" value="<?= e($vReceivedAt) ?>">

        <label class="field-label" for="due_date"><?= e(t('corr.due_date')) ?></label>
        <input class="input" id="due_date" name="due_date" type="date" value="<?= e((string) ($old['due_date'] ?? '')) ?>">
      </div>
    </div>

    <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
  </form>
</section>

<?php layout_footer(); ?>
