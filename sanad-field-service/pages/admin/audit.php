<?php
declare(strict_types=1);

/**
 * Admin — audit log: read-only history of every change in Sanad,
 * with a text search and pagination. Only administrators reach this page.
 */

Auth::requireAdmin();

$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

$total = AuditLog::count($q);
[$page, $pages] = paginate($total, $page, $perPage);
$rows = AuditLog::list($q, $perPage, ($page - 1) * $perPage);
$qs   = keep_query();

layout_header(t('al.title'), 'admin/audit');
?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('admin/audit') ?>">
    <div class="filter-search"><?= icon('search') ?><input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('al.search')) ?>"></div>
    <button class="btn" type="submit"><?= e(t('common.search')) ?></button>
    <a class="btn btn-ghost" href="<?= u('admin/audit') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="panel-head">
    <span class="result-count"><?= e(t('al.count', ['n' => $total])) ?></span>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><?= e(t('common.none')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('al.time')) ?></th>
            <th><?= e(t('al.user')) ?></th>
            <th><?= e(t('al.action')) ?></th>
            <th><?= e(t('al.entity')) ?></th>
            <th><?= e(t('al.details')) ?></th>
            <th><?= e(t('al.ip')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
            $entity = (string) ($row['entity'] ?? '');
            if ($entity !== '' && ($row['entity_id'] ?? '') !== null && (string) $row['entity_id'] !== '') {
                $entity .= ' #' . (string) $row['entity_id'];
            }
            ?>
            <tr>
              <td class="cell-muted"><?= e(fmt_dt((string) $row['created_at'])) ?></td>
              <td><?= e((string) ($row['username'] ?? '')) ?></td>
              <td><span class="badge badge-audit"><?= e(str_replace('_', ' ', (string) $row['action'])) ?></span></td>
              <td><?= e($entity) ?></td>
              <td class="cell-muted"><?= e((string) ($row['details'] ?? '')) ?></td>
              <td class="cell-muted"><?= e((string) ($row['ip'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a class="page-btn" href="<?= u('admin/audit') ?><?= $qs !== '' ? '&amp;' . e($qs) : '' ?>&amp;page=<?= $page - 1 ?>">← <?= e(t('common.prev')) ?></a>
      <?php endif; ?>
      <span class="page-info"><?= e(t('common.page')) ?> <?= $page ?> <?= e(t('common.of')) ?> <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="page-btn" href="<?= u('admin/audit') ?><?= $qs !== '' ? '&amp;' . e($qs) : '' ?>&amp;page=<?= $page + 1 ?>"><?= e(t('common.next')) ?> →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
