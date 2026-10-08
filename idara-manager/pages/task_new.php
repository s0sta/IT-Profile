<?php
declare(strict_types=1);

/**
 * Idara — إنشاء مهمة جديدة (route 'new-task').
 * POST: title, description, category_id, priority, assignee_id, department_id,
 *       start_date, due_date  →  Tasks::create($data, $me)  →  redirect('task&id=…').
 */

$me = Auth::current();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

$categories  = TaskCategories::all(true);
$users       = Users::all(true);
$departments = Departments::all(true);

$errors = [];

if ($isPost) {
    csrf_check();

    $title        = trim((string) ($_POST['title'] ?? ''));
    $description  = trim((string) ($_POST['description'] ?? ''));
    $categoryId   = (int) ($_POST['category_id'] ?? 0) ?: null;
    $priority     = (string) ($_POST['priority'] ?? '');
    $assigneeId   = (int) ($_POST['assignee_id'] ?? 0) ?: null;
    $departmentId = (int) ($_POST['department_id'] ?? 0) ?: null;
    $startDate    = trim((string) ($_POST['start_date'] ?? ''));
    $dueDate      = trim((string) ($_POST['due_date'] ?? ''));

    if ($title === '' || mb_strlen($title) > 250) {
        $errors[] = t('tasks.err_title');
    }
    if (!array_key_exists($priority, priorities())) {
        $errors[] = t('tasks.err_priority');
    }

    // Drop references that do not exist (keeps the form forgiving, never invents rows).
    if ($categoryId !== null && !TaskCategories::find($categoryId)) {
        $categoryId = null;
    }
    if ($assigneeId !== null && !Users::find($assigneeId)) {
        $assigneeId = null;
    }
    if ($departmentId !== null && !Departments::find($departmentId)) {
        $departmentId = null;
    }
    if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $startDate = '';
    }
    if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        $dueDate = '';
    }

    if (!$errors) {
        $id = Tasks::create([
            'title'         => $title,
            'description'   => $description,
            'category_id'   => $categoryId,
            'priority'      => $priority,
            'assignee_id'   => $assigneeId,
            'department_id' => $departmentId,
            'start_date'    => $startDate,
            'due_date'      => $dueDate,
        ], $me);

        flash('success', t('tasks.created_ok'));
        redirect('task&id=' . $id);
    }
}

// Form state — POST wins so a validation error keeps what the user typed.
$vTitle        = $isPost ? trim((string) ($_POST['title'] ?? '')) : '';
$vDescription  = $isPost ? trim((string) ($_POST['description'] ?? '')) : '';
$vCategory     = $isPost ? (int) ($_POST['category_id'] ?? 0) : 0;
$vPriority     = $isPost ? (string) ($_POST['priority'] ?? '') : 'medium';
$vAssignee     = $isPost ? (int) ($_POST['assignee_id'] ?? 0) : (int) $me['id'];
$vDepartment   = $isPost ? (int) ($_POST['department_id'] ?? 0) : (int) ($me['department_id'] ?? 0);
$vStartDate    = $isPost ? trim((string) ($_POST['start_date'] ?? '')) : '';
$vDueDate      = $isPost ? trim((string) ($_POST['due_date'] ?? '')) : '';

layout_header(t('tasks.new'), 'tasks');
?>

<?php if ($errors): ?>
  <div class="flash flash-error"><ul style="margin:0;padding-inline-start:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('tasks.new')) ?></h2>
    <form method="post" action="<?= u('new-task') ?>">
      <?= csrf_field() ?>

      <label class="field-label" for="title"><?= e(t('tasks.col_task')) ?></label>
      <input class="input" id="title" name="title" type="text" maxlength="250" required
             value="<?= e($vTitle) ?>">

      <label class="field-label" for="description"><?= e(t('common.description')) ?></label>
      <textarea class="input" id="description" name="description" rows="7"><?= e($vDescription) ?></textarea>

      <label class="field-label" for="category_id"><?= e(t('common.category')) ?></label>
      <select class="input input-select" id="category_id" name="category_id">
        <option value=""><?= e(t('common.select')) ?></option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $vCategory === (int) $c['id'] ? 'selected' : '' ?>><?= e(TaskCategories::label($c)) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="priority"><?= e(t('common.priority')) ?></label>
      <select class="input input-select" id="priority" name="priority">
        <?php foreach (priorities() as $k => $label): ?>
          <option value="<?= e((string) $k) ?>" <?= $vPriority === (string) $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="assignee_id"><?= e(t('common.assignee')) ?></label>
      <select class="input input-select" id="assignee_id" name="assignee_id">
        <option value=""><?= e(t('common.unassigned')) ?></option>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= $vAssignee === (int) $u['id'] ? 'selected' : '' ?>><?= e(Users::label($u)) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="department_id"><?= e(t('common.department')) ?></label>
      <select class="input input-select" id="department_id" name="department_id">
        <option value=""><?= e(t('common.none')) ?></option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= $vDepartment === (int) $d['id'] ? 'selected' : '' ?>><?= e(Departments::label($d)) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="start_date"><?= e(t('tasks.start_date')) ?></label>
      <input class="input" id="start_date" name="start_date" type="date" value="<?= e($vStartDate) ?>">

      <label class="field-label" for="due_date"><?= e(t('tasks.due_date')) ?></label>
      <input class="input" id="due_date" name="due_date" type="date" value="<?= e($vDueDate) ?>">

      <button class="btn btn-primary" type="submit"><?= icon('plus') ?> <?= e(t('common.create')) ?></button>
    </form>
  </div>

  <aside class="panel panel-muted">
    <h2 class="panel-title"><?= e(t('tasks.details')) ?></h2>
    <ul class="steps">
      <li><?= e(t('tasks.checklist')) ?></li>
      <li><?= e(t('tasks.updates')) ?></li>
      <li><?= e(t('common.attachments')) ?></li>
    </ul>
    <a class="btn btn-ghost" href="<?= u('tasks') ?>"><?= e(t('tasks.title')) ?> →</a>
  </aside>
</section>

<?php layout_footer(); ?>
