<?php
declare(strict_types=1);

/**
 * Idara — قائمة المهام (route 'tasks').
 * Filters (GET): q, status, priority, category, assignee, department, mine, overdue, sort, page.
 */

$me = Auth::current();

$f = [
    'q'          => trim((string) ($_GET['q'] ?? '')),
    'status'     => (string) ($_GET['status'] ?? ''),
    'priority'   => (string) ($_GET['priority'] ?? ''),
    'category'   => (string) ($_GET['category'] ?? ''),
    'assignee'   => (string) ($_GET['assignee'] ?? ''),
    'department' => (string) ($_GET['department'] ?? ''),
    'mine'       => !empty($_GET['mine']) ? 1 : 0,
    'team'       => !empty($_GET['team']) ? 1 : 0,
    'overdue'    => !empty($_GET['overdue']) ? 1 : 0,
    'sort'       => (string) ($_GET['sort'] ?? 'due'),
];

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$total = Tasks::count($f, $me);
[$page, $pages] = paginate($total, $page, $perPage);
$rows = Tasks::list($f, $me, $perPage, ($page - 1) * $perPage);

$categories  = TaskCategories::all(true);
$users       = Users::all(true);
$departments = Departments::all(true);

$title = ($me['role'] ?? '') === 'member' ? t('tasks.my_title') : t('tasks.title');
layout_header($title, 'tasks');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e($title) ?></h2>
    <a class="btn btn-primary" href="<?= u('new-task') ?>"><?= icon('plus') ?> <?= e(t('tasks.new')) ?></a>
  </div>

  <div class="quick-actions" style="margin-bottom:12px">
    <a class="btn btn-sm <?= !$f['mine'] && !$f['team'] ? 'btn-primary' : 'btn-ghost' ?>" href="<?= u('tasks') ?>"><?= e(t('tasks.all_tab')) ?></a>
    <a class="btn btn-sm <?= $f['mine'] ? 'btn-primary' : 'btn-ghost' ?>" href="<?= u('tasks&mine=1') ?>"><?= e(t('tasks.my_tab')) ?></a>
    <?php if (($me['role'] ?? '') !== 'member'): ?>
      <a class="btn btn-sm <?= $f['team'] ? 'btn-primary' : 'btn-ghost' ?>" href="<?= u('tasks&team=1') ?>"><?= e(t('tasks.team_tab')) ?></a>
    <?php endif; ?>
  </div>

  <form class="filters" method="get" action="<?= u('tasks') ?>">
    <input type="hidden" name="p" value="tasks">
    <div class="filter-search">
      <?= icon('search') ?>
      <input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('tasks.search_placeholder')) ?>">
    </div>
    <select name="status" class="input input-select">
      <option value=""><?= e(t('tasks.all_statuses')) ?></option>
      <?php foreach (task_statuses() as $k => $label): ?>
        <option value="<?= e((string) $k) ?>" <?= $f['status'] === (string) $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="priority" class="input input-select">
      <option value=""><?= e(t('tasks.all_priorities')) ?></option>
      <?php foreach (priorities() as $k => $label): ?>
        <option value="<?= e((string) $k) ?>" <?= $f['priority'] === (string) $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="category" class="input input-select">
      <option value=""><?= e(t('tasks.all_categories')) ?></option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $f['category'] === (string) $c['id'] ? 'selected' : '' ?>><?= e(TaskCategories::label($c)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="assignee" class="input input-select">
      <option value=""><?= e(t('tasks.any_assignee')) ?></option>
      <?php foreach ($users as $u): ?>
        <option value="<?= (int) $u['id'] ?>" <?= $f['assignee'] === (string) $u['id'] ? 'selected' : '' ?>><?= e(Users::label($u)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="department" class="input input-select">
      <option value=""><?= e(t('tasks.all_departments')) ?></option>
      <?php foreach ($departments as $d): ?>
        <option value="<?= (int) $d['id'] ?>" <?= $f['department'] === (string) $d['id'] ? 'selected' : '' ?>><?= e(Departments::label($d)) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="check-label">
      <input type="checkbox" name="team" value="1" <?= $f['team'] ? 'checked' : '' ?>>
      <?= e(t('tasks.team_tab')) ?>
    </label>
    <label class="check-label">
      <input type="checkbox" name="overdue" value="1" <?= $f['overdue'] ? 'checked' : '' ?>>
      <?= e(t('tasks.only_overdue')) ?>
    </label>
    <select name="sort" class="input input-select">
      <option value="due" <?= $f['sort'] === 'due' ? 'selected' : '' ?>><?= e(t('tasks.sort_due')) ?></option>
      <option value="updated" <?= $f['sort'] === 'updated' ? 'selected' : '' ?>><?= e(t('tasks.sort_updated')) ?></option>
      <option value="created" <?= $f['sort'] === 'created' ? 'selected' : '' ?>><?= e(t('tasks.sort_created')) ?></option>
      <option value="priority" <?= $f['sort'] === 'priority' ? 'selected' : '' ?>><?= e(t('tasks.sort_priority')) ?></option>
      <option value="progress" <?= $f['sort'] === 'progress' ? 'selected' : '' ?>><?= e(t('tasks.sort_progress')) ?></option>
    </select>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= u('tasks') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="result-count"><?= e($total === 1 ? t('tasks.count_one') : t('tasks.count', ['n' => $total])) ?></div>
  <?php task_table($rows); ?>

  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a class="page-btn" href="<?= u('tasks') ?>&<?= e(keep_query()) ?>&page=<?= $page - 1 ?>">← <?= e(t('common.prev')) ?></a>
      <?php endif; ?>
      <span class="page-info"><?= e(t('common.page')) ?> <?= $page ?> <?= e(t('common.of')) ?> <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="page-btn" href="<?= u('tasks') ?>&<?= e(keep_query()) ?>&page=<?= $page + 1 ?>"><?= e(t('common.next')) ?> →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
