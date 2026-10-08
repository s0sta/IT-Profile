<?php
declare(strict_types=1);

$staff = Auth::isStaff();

$f = [
    'q'        => trim((string) ($_GET['q'] ?? '')),
    'status'   => (string) ($_GET['status'] ?? ''),
    'priority' => (string) ($_GET['priority'] ?? ''),
    'category' => (string) ($_GET['category'] ?? ''),
    'assignee' => (string) ($_GET['assignee'] ?? ''),
    'sort'     => (string) ($_GET['sort'] ?? 'updated'),
];
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$total   = Tickets::count($f);
[$page, $pages] = paginate($total, $page, $perPage);
$rows    = Tickets::list($f, $perPage, ($page - 1) * $perPage);
$cats    = Categories::all(true);
$agents  = Users::agents();

layout_header($staff ? t('tickets.title') : t('tickets.my_title'), 'tickets');
?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('tickets') ?>">
    <input type="hidden" name="p" value="tickets">
    <div class="filter-search">
      <?= icon('search') ?>
      <input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('tickets.search_placeholder')) ?>">
    </div>
    <select name="status" class="input input-select">
      <option value=""><?= e(t('tickets.all_statuses')) ?></option>
      <?php foreach (statuses() as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="priority" class="input input-select">
      <option value=""><?= e(t('tickets.all_priorities')) ?></option>
      <?php foreach (priorities() as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $f['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="category" class="input input-select">
      <option value=""><?= e(t('tickets.all_categories')) ?></option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $f['category'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($staff): ?>
    <select name="assignee" class="input input-select">
      <option value=""><?= e(t('tickets.any_assignee')) ?></option>
      <option value="none" <?= $f['assignee'] === 'none' ? 'selected' : '' ?>><?= e(t('common.unassigned')) ?></option>
      <?php foreach ($agents as $a): ?>
        <option value="<?= (int) $a['id'] ?>" <?= $f['assignee'] === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <select name="sort" class="input input-select">
      <option value="updated" <?= $f['sort'] === 'updated' ? 'selected' : '' ?>><?= e(t('tickets.sort_updated')) ?></option>
      <option value="created" <?= $f['sort'] === 'created' ? 'selected' : '' ?>><?= e(t('tickets.sort_created')) ?></option>
      <option value="due" <?= $f['sort'] === 'due' ? 'selected' : '' ?>><?= e(t('tickets.sort_due')) ?></option>
      <option value="priority" <?= $f['sort'] === 'priority' ? 'selected' : '' ?>><?= e(t('tickets.sort_priority')) ?></option>
    </select>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= u('tickets') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="result-count"><?= e($total === 1 ? t('tickets.count_one') : t('tickets.count', ['n' => $total])) ?></div>
  <?php ticket_table($rows); ?>

  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a class="page-btn" href="<?= u('tickets') ?>&<?= e(keep_query()) ?>&page=<?= $page - 1 ?>">← <?= e(t('common.prev')) ?></a>
      <?php endif; ?>
      <span class="page-info"><?= e(t('common.page')) ?> <?= $page ?> <?= e(t('common.of')) ?> <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="page-btn" href="<?= u('tickets') ?>&<?= e(keep_query()) ?>&page=<?= $page + 1 ?>"><?= e(t('common.next')) ?> →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
