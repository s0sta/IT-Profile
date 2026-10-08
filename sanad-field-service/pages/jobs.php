<?php
declare(strict_types=1);

$f = [
    'q'          => trim((string) ($_GET['q'] ?? '')),
    'status'     => (string) ($_GET['status'] ?? ''),
    'priority'   => (string) ($_GET['priority'] ?? ''),
    'type'       => (string) ($_GET['type'] ?? ''),
    'technician' => (string) ($_GET['technician'] ?? ''),
    'customer'   => (string) ($_GET['customer'] ?? ''),
    'from'       => (string) ($_GET['from'] ?? ''),
    'to'         => (string) ($_GET['to'] ?? ''),
    'sort'       => (string) ($_GET['sort'] ?? 'scheduled'),
];
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$total = Jobs::count($f);
[$page, $pages] = paginate($total, $page, $perPage);
$rows = Jobs::list($f, $perPage, ($page - 1) * $perPage);
$technicians = Users::technicians();
$customers = Auth::isTechnician() ? [] : Customers::all(true);

layout_header(Auth::isTechnician() ? t('jobs.my_title') : t('jobs.title'), 'jobs');
?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('jobs') ?>">
    <div class="filter-search"><?= icon('search') ?><input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('jobs.search_placeholder')) ?>"></div>
    <select name="status" class="input input-select">
      <option value=""><?= e(t('jobs.all_statuses')) ?></option>
      <?php foreach (job_statuses() as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="priority" class="input input-select">
      <option value=""><?= e(t('jobs.all_priorities')) ?></option>
      <?php foreach (job_priorities() as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $f['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type" class="input input-select">
      <option value=""><?= e(t('jobs.all_types')) ?></option>
      <?php foreach (job_types() as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!Auth::isTechnician()): ?>
      <select name="technician" class="input input-select">
        <option value=""><?= e(t('jobs.any_technician')) ?></option>
        <option value="none" <?= $f['technician'] === 'none' ? 'selected' : '' ?>><?= e(t('job.unassigned')) ?></option>
        <?php foreach ($technicians as $tech): ?>
          <option value="<?= (int) $tech['id'] ?>" <?= $f['technician'] === (string) $tech['id'] ? 'selected' : '' ?>><?= e($tech['name']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <?php if ($customers): ?>
      <select name="customer" class="input input-select">
        <option value=""><?= e(t('jobs.any_customer')) ?></option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $f['customer'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <label class="filter-date"><?= e(t('common.from')) ?> <input class="input input-sm" type="date" name="from" value="<?= e($f['from']) ?>"></label>
    <label class="filter-date"><?= e(t('common.to')) ?> <input class="input input-sm" type="date" name="to" value="<?= e($f['to']) ?>"></label>
    <select name="sort" class="input input-select">
      <option value="scheduled" <?= $f['sort'] === 'scheduled' ? 'selected' : '' ?>><?= e(t('jobs.sort_scheduled')) ?></option>
      <option value="created" <?= $f['sort'] === 'created' ? 'selected' : '' ?>><?= e(t('jobs.sort_created')) ?></option>
      <option value="priority" <?= $f['sort'] === 'priority' ? 'selected' : '' ?>><?= e(t('jobs.sort_priority')) ?></option>
      <option value="number" <?= $f['sort'] === 'number' ? 'selected' : '' ?>><?= e(t('jobs.sort_number')) ?></option>
    </select>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= u('jobs') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="panel-head">
    <span class="result-count"><?= e($total === 1 ? t('jobs.count_one') : t('jobs.count', ['n' => $total])) ?></span>
    <?php if (Auth::isOffice()): ?>
      <a class="btn btn-primary btn-sm" href="<?= u('job_new') ?>"><?= icon('plus') ?> <?= e(t('nav.new_job')) ?></a>
    <?php endif; ?>
  </div>

  <?php job_table($rows); ?>

  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a class="page-btn" href="<?= u('jobs') ?>&<?= e(keep_query()) ?>&page=<?= $page - 1 ?>">← <?= e(t('common.prev')) ?></a>
      <?php endif; ?>
      <span class="page-info"><?= e(t('common.page')) ?> <?= $page ?> <?= e(t('common.of')) ?> <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="page-btn" href="<?= u('jobs') ?>&<?= e(keep_query()) ?>&page=<?= $page + 1 ?>"><?= e(t('common.next')) ?> →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
