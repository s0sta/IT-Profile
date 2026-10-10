<?php
declare(strict_types=1);

/**
 * Admin · Audit Log — searchable, paginated record of every action.
 * Stored action codes are shown raw (underscores → spaces); they are data, not
 * translation keys, so they are never passed through t().
 */

$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

$total = AuditLog::count($q);
[$page, $pages] = paginate($total, $page, $perPage);
$rows = AuditLog::list($q, $perPage, ($page - 1) * $perPage);

layout_header(t('al.title'), 'admin/audit');
?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('admin/audit') ?>">
    <input type="hidden" name="p" value="admin/audit">
    <div class="filter-search"><?= icon('search') ?><input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('al.search')) ?>"></div>
    <button class="btn" type="submit"><?= e(t('common.search')) ?></button>
    <a class="btn btn-ghost" href="<?= u('admin/audit') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="result-count"><?= e(t('al.count', ['n' => $total])) ?></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th><?= e(t('al.time')) ?></th><th><?= e(t('al.user')) ?></th><th><?= e(t('al.action')) ?></th><th><?= e(t('al.entity')) ?></th><th><?= e(t('al.details')) ?></th><th><?= e(t('al.ip')) ?></th></tr></thead>
      <tbody>
        <?php
        $nameMap = [];
        foreach (Users::all() as $uu) {
            $nameMap[(int) $uu['id']] = user_name($uu);
        }
        foreach ($rows as $a): ?>
          <tr>
            <td class="cell-muted"><?= e(fmt_dt($a['created_at'])) ?></td>
            <td><?= e($a['username']) ?></td>
            <td><span class="badge badge-audit"><?= e(str_replace('_', ' ', $a['action'])) ?></span></td>
            <td><?php
                if (($a['entity'] ?? '') === 'user') {
                    $uid   = (int) $a['entity_id'];
                    $label = $nameMap[$uid] ?? null;
                    if ($label === null && preg_match('/name=([^;]+)/', (string) ($a['details'] ?? ''), $m)) {
                        $label = trim($m[1]);
                    }
                    echo $label !== null ? e($label) : 'user #' . $uid;
                } else {
                    echo e($a['entity'] ?? '—');
                    echo $a['entity_id'] ? ' #' . e((string) $a['entity_id']) : '';
                }
            ?></td>
            <td class="cell-muted"><?= e($a['details'] ?? '') ?></td>
            <td class="cell-muted"><?= e($a['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?><a class="page-btn" href="<?= u('admin/audit') ?>&<?= e(keep_query()) ?>&page=<?= $page - 1 ?>">← <?= e(t('common.prev')) ?></a><?php endif; ?>
      <span class="page-info"><?= e(t('common.page')) ?> <?= $page ?> <?= e(t('common.of')) ?> <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="page-btn" href="<?= u('admin/audit') ?>&<?= e(keep_query()) ?>&page=<?= $page + 1 ?>"><?= e(t('common.next')) ?> →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
