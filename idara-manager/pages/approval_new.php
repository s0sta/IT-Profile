<?php
declare(strict_types=1);

/**
 * Idara — طلب اعتماد جديد (route 'new-approval').
 * POST: title, description, type_id, priority, due_date, related_task_id,
 *       approver_ids[] (ordered chain) and optional attachments[].
 * Approvals::create($data, $chain, $me) → handle_uploads(…, $approvalId, …) → redirect('approval&id=…').
 */

$me = Auth::current();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

$types        = ApprovalTypes::all(true);
$approvers    = Users::approvers();
$relatedTasks = Tasks::list(['sort' => 'updated'], $me, 100, 0);

// Whitelist of ids that may appear in the chain (same list the chooser renders).
$approverIds = [];
foreach ($approvers as $a) {
    if ((int) $a['id'] === (int) $me['id']) {
        continue; // a requester can never approve their own request
    }
    $approverIds[(int) $a['id']] = true;
}

$errors = [];

if ($isPost) {
    csrf_check();

    $title         = trim((string) ($_POST['title'] ?? ''));
    $description   = trim((string) ($_POST['description'] ?? ''));
    $typeId        = (int) ($_POST['type_id'] ?? 0) ?: null;
    $priority      = (string) ($_POST['priority'] ?? '');
    $dueDate       = trim((string) ($_POST['due_date'] ?? ''));
    $relatedTaskId = (int) ($_POST['related_task_id'] ?? 0) ?: null;

    if ($title === '' || mb_strlen($title) > 250) {
        $errors[] = t('approvals.err_title');
    }
    if ($typeId === null || !ApprovalTypes::find($typeId)) {
        $errors[] = t('approvals.err_type');
    }
    if (!array_key_exists($priority, priorities())) {
        $errors[] = t('tasks.err_priority');
    }

    // Ordered chain: keep the submitted order, drop blanks/duplicates/unknown ids.
    $chain = [];
    foreach ((array) ($_POST['approver_ids'] ?? []) as $candidate) {
        $candidate = (int) $candidate;
        if ($candidate > 0 && isset($approverIds[$candidate]) && !in_array($candidate, $chain, true)) {
            $chain[] = $candidate;
        }
    }
    if (!$chain) {
        $submitted = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['approver_ids'] ?? [])), static fn (int $v): bool => $v > 0)));
        $errors[] = count($submitted) === 1 && $submitted[0] === (int) $me['id']
            ? t('approvals.err_self')
            : t('approvals.err_approvers');
    }

    if ($relatedTaskId !== null) {
        $related = Tasks::find($relatedTaskId);
        if (!$related || !Tasks::canView($related, $me)) {
            $relatedTaskId = null;
        }
    }
    if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        $dueDate = '';
    }

    if (!$errors) {
        $id = Approvals::create([
            'title'           => $title,
            'description'     => $description,
            'type_id'         => $typeId,
            'department_id'   => $me['department_id'] ?: null,
            'priority'        => $priority,
            'due_date'        => $dueDate,
            'related_task_id' => $relatedTaskId,
        ], $chain, $me);

        $uploads = handle_uploads(null, null, $id, Auth::id());

        flash('success', t('approvals.created_ok'));
        foreach ($uploads['errors'] as $uploadError) {
            flash('warning', (string) $uploadError);
        }
        redirect('approval&id=' . $id);
    }
}

// Form state — POST wins so a validation error keeps what the user typed.
$vTitle       = $isPost ? trim((string) ($_POST['title'] ?? '')) : '';
$vDescription = $isPost ? trim((string) ($_POST['description'] ?? '')) : '';
$vType        = $isPost ? (int) ($_POST['type_id'] ?? 0) : 0;
$vPriority    = $isPost ? (string) ($_POST['priority'] ?? '') : 'medium';
$vDueDate     = $isPost ? trim((string) ($_POST['due_date'] ?? '')) : '';
$vRelatedTask = $isPost ? (int) ($_POST['related_task_id'] ?? 0) : 0;
$vApprovers   = array_map('intval', (array) ($_POST['approver_ids'] ?? []));

layout_header(t('approvals.new'), 'approvals');
?>

<?php if ($errors): ?>
  <div class="flash flash-error"><ul style="margin:0;padding-inline-start:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="panel">
  <h2 class="panel-title"><?= e(t('approvals.new')) ?></h2>
  <form method="post" action="<?= u('new-approval') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <label class="field-label" for="title"><?= e(t('approvals.request_title')) ?></label>
    <input class="input" id="title" name="title" type="text" maxlength="250" required
           value="<?= e($vTitle) ?>">

    <label class="field-label" for="description"><?= e(t('approvals.request_desc')) ?></label>
    <textarea class="input" id="description" name="description" rows="6"><?= e($vDescription) ?></textarea>

    <label class="field-label" for="type_id"><?= e(t('approvals.type')) ?></label>
    <select class="input input-select" id="type_id" name="type_id" required>
      <option value=""><?= e(t('common.select')) ?></option>
      <?php foreach ($types as $ty): ?>
        <option value="<?= (int) $ty['id'] ?>" <?= $vType === (int) $ty['id'] ? 'selected' : '' ?>><?= e(ApprovalTypes::label($ty)) ?></option>
      <?php endforeach; ?>
    </select>

    <label class="field-label" for="priority"><?= e(t('common.priority')) ?></label>
    <select class="input input-select" id="priority" name="priority">
      <?php foreach (priorities() as $k => $label): ?>
        <option value="<?= e((string) $k) ?>" <?= $vPriority === (string) $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>

    <label class="field-label" for="due_date"><?= e(t('tasks.due_date')) ?></label>
    <input class="input" id="due_date" name="due_date" type="date" value="<?= e($vDueDate) ?>">

    <label class="field-label" for="related_task_id"><?= e(t('approvals.related_task')) ?> <span class="cell-muted">· <?= e(t('common.optional')) ?></span></label>
    <select class="input input-select" id="related_task_id" name="related_task_id">
      <option value=""><?= e(t('common.select')) ?></option>
      <?php foreach ($relatedTasks as $rt): ?>
        <option value="<?= (int) $rt['id'] ?>" <?= $vRelatedTask === (int) $rt['id'] ? 'selected' : '' ?>><?= e($rt['ref'] . ' — ' . $rt['title']) ?></option>
      <?php endforeach; ?>
    </select>

    <label class="field-label" for="approver_1"><?= e(t('approvals.chain')) ?></label>
    <p class="muted-text"><?= e(t('approvals.chain_hint')) ?></p>
    <select class="input input-select" id="approver_1" name="approver_ids[]" required>
      <option value=""><?= e(t('common.select')) ?></option>
      <?php foreach ($approvers as $a): ?><?php if ((int) $a['id'] === (int) $me['id']) { continue; } ?>
        <option value="<?= (int) $a['id'] ?>" <?= (int) ($vApprovers[0] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name'] . ' — ' . (roles()[$a['role']] ?? $a['role'])) ?></option>
      <?php endforeach; ?>
    </select>

    <p class="mini-title"><?= e(t('approvals.add_approver')) ?></p>
    <?php for ($i = 1; $i <= 2; $i++): ?>
      <select class="input input-select" name="approver_ids[]">
        <option value=""><?= e(t('common.none')) ?></option>
        <?php foreach ($approvers as $a): ?><?php if ((int) $a['id'] === (int) $me['id']) { continue; } ?>
          <option value="<?= (int) $a['id'] ?>" <?= (int) ($vApprovers[$i] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name'] . ' — ' . (roles()[$a['role']] ?? $a['role'])) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endfor; ?>

    <label class="field-label" for="attachments"><?= e(t('common.attach')) ?></label>
    <input class="input" id="attachments" name="attachments[]" type="file" multiple>
    <p class="muted-text"><?= e(t('common.max_size')) ?></p>

    <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= e(t('common.submit')) ?></button>
  </form>
</section>

<?php layout_footer(); ?>
