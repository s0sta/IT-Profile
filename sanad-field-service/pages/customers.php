<?php
declare(strict_types=1);

$q         = trim((string) ($_GET['q'] ?? ''));
$activeOnly = (string) ($_GET['active'] ?? '1') === '1';
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 25;

$all = Customers::all($activeOnly, $q);
$total = count($all);
[$page, $pages] = paginate($total, $page, $perPage);
$rows = array_slice($all, ($page - 1) * $perPage, $perPage);

layout_header(t('customers.title'), 'customers');
?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('customers') ?>">
    <div class="filter-search"><?= icon('search') ?><input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('customers.search_placeholder')) ?>"></div>
    <label class="filter-date"><input type="checkbox" name="active" value="1" <?= $activeOnly ? 'checked' : '' ?>> <?= e(t('common.active')) ?></label>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= u('customers') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="panel-head">
    <span class="result-count"><?= e($total === 1 ? t('customers.count_one') : t('customers.count', ['n' => $total])) ?></span>
    <a class="btn btn-primary btn-sm" href="<?= u('customer_new') ?>"><?= icon('plus') ?> <?= e(t('customers.add')) ?></a>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><?= e(t('customers.empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('customers.col_code')) ?></th>
            <th><?= e(t('common.name')) ?></th>
            <th><?= e(t('common.phone')) ?></th>
            <th><?= e(t('common.city')) ?></th>
            <th><?= e(t('customers.col_sites')) ?></th>
            <th><?= e(t('customers.col_jobs')) ?></th>
            <th><?= e(t('customers.col_contracts')) ?></th>
            <th><?= e(t('common.status')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $c): ?>
            <tr>
              <td class="cell-muted"><?= e($c['code'] ?? '—') ?></td>
              <td><a class="ref-link" href="<?= u('customer&id=' . (int) $c['id']) ?>"><?= e($c['name']) ?></a>
                <div class="cell-muted"><?= e(t('ctype.' . ($c['type'] ?? 'company'))) ?></div></td>
              <td><?= e($c['phone'] ?: '—') ?></td>
              <td><?= e($c['city'] ?: '—') ?></td>
              <td><span class="badge badge-cat"><?= (int) $c['site_count'] ?></span></td>
              <td><span class="badge badge-cat"><?= (int) $c['job_count'] ?></span></td>
              <td><span class="badge badge-cat"><?= (int) $c['contract_count'] ?></span></td>
              <td><?= $c['active'] ? '<span class="badge badge-active">' . e(t('common.active')) . '</span>' : '<span class="badge badge-inactive">' . e(t('common.inactive')) . '</span>' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?><a class="page-btn" href="<?= u('customers') ?>&<?= e(keep_query()) ?>&page=<?= $page - 1 ?>">← <?= e(t('common.prev')) ?></a><?php endif; ?>
        <span class="page-info"><?= e(t('common.page')) ?> <?= $page ?> <?= e(t('common.of')) ?> <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="page-btn" href="<?= u('customers') ?>&<?= e(keep_query()) ?>&page=<?= $page + 1 ?>"><?= e(t('common.next')) ?> →</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
