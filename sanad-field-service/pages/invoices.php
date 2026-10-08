<?php
declare(strict_types=1);

$f = [
    'q'        => trim((string) ($_GET['q'] ?? '')),
    'status'   => (string) ($_GET['status'] ?? ''),
    'customer' => (string) ($_GET['customer'] ?? ''),
];
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$total = Invoices::count($f);
[$page, $pages] = paginate($total, $page, $perPage);
$rows = Invoices::all($f, $perPage, ($page - 1) * $perPage);
$customers = Customers::all(true);
$aging = Invoices::aging();

layout_header(t('invoices.title'), 'invoices');
?>

<section class="stats-grid stats-grid-sm">
  <div class="stat-card"><span class="stat-value"><?= e(money(Invoices::invoicedThisMonth(), false)) ?></span><span class="stat-label"><?= e(t('dash.invoiced_month', ['currency' => (string) setting('currency', 'AED')])) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= e(money(Invoices::collectedThisMonth(), false)) ?></span><span class="stat-label"><?= e(t('dash.collected_month', ['currency' => (string) setting('currency', 'AED')])) ?></span></div>
  <div class="stat-card <?= Invoices::outstandingTotal() > 0 ? 'stat-warn' : '' ?>"><span class="stat-value"><?= e(money(Invoices::outstandingTotal(), false)) ?></span><span class="stat-label"><?= e(t('dash.outstanding', ['currency' => (string) setting('currency', 'AED')])) ?></span></div>
  <div class="stat-card <?= Invoices::overdueCount() ? 'stat-warn' : '' ?>"><span class="stat-value"><?= Invoices::overdueCount() ?></span><span class="stat-label"><?= e(t('dash.overdue_invoices')) ?></span></div>
</section>

<section class="panel">
  <form class="filters" method="get" action="<?= u('invoices') ?>">
    <div class="filter-search"><?= icon('search') ?><input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('invoices.search_placeholder')) ?>"></div>
    <select name="status" class="input input-select">
      <option value=""><?= e(t('invoices.all_statuses')) ?></option>
      <?php foreach (invoice_statuses() as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="customer" class="input input-select">
      <option value=""><?= e(t('invoices.any_customer')) ?></option>
      <?php foreach ($customers as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $f['customer'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= u('invoices') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="panel-head">
    <span class="result-count"><?= e(t('invoices.count', ['n' => $total])) ?></span>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><?= e(t('invoices.empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('inv.number')) ?></th>
            <th><?= e(t('inv.customer')) ?></th>
            <th><?= e(t('inv.job')) ?></th>
            <th><?= e(t('inv.issued')) ?></th>
            <th><?= e(t('inv.due')) ?></th>
            <th><?= e(t('inv.total')) ?></th>
            <th><?= e(t('inv.open')) ?></th>
            <th><?= e(t('common.status')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $inv): $open = (float) $inv['total'] - (float) $inv['paid_amount']; ?>
            <tr>
              <td><a class="ref-link" href="<?= u('invoice&id=' . (int) $inv['id']) ?>"><?= e($inv['number']) ?></a></td>
              <td><?= e($inv['customer_name'] ?? '—') ?></td>
              <td><?php if ($inv['job_number']): ?><a class="ref-link" href="<?= u('job&id=' . (int) $inv['job_id']) ?>"><?= e($inv['job_number']) ?></a><?php else: ?><span class="cell-muted">—</span><?php endif; ?></td>
              <td class="cell-muted"><?= e(fmt_date($inv['issued_at'])) ?></td>
              <td><?= due_chip($inv['due_at']) ?></td>
              <td><?= e(money((float) $inv['total'])) ?></td>
              <td><?= e(money($open)) ?></td>
              <td><?= invoice_status_badge($inv['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?><a class="page-btn" href="<?= u('invoices') ?>&<?= e(keep_query()) ?>&page=<?= $page - 1 ?>">← <?= e(t('common.prev')) ?></a><?php endif; ?>
        <span class="page-info"><?= e(t('common.page')) ?> <?= $page ?> <?= e(t('common.of')) ?> <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="page-btn" href="<?= u('invoices') ?>&<?= e(keep_query()) ?>&page=<?= $page + 1 ?>"><?= e(t('common.next')) ?> →</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('dash.aging')) ?></h2>
  <div class="hbar-list">
    <?php
    $rows2 = [
        ['label' => t('inv.age_current'), 'value' => $aging['current']],
        ['label' => t('inv.age_30'), 'value' => $aging['d30']],
        ['label' => t('inv.age_60'), 'value' => $aging['d60']],
        ['label' => t('inv.age_90'), 'value' => $aging['d90']],
    ];
    $max2 = max(1.0, ...array_map(static fn (array $r): float => (float) $r['value'], $rows2));
    foreach ($rows2 as $row): ?>
      <div class="hbar">
        <span class="hbar-label"><?= e($row['label']) ?></span>
        <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width:<?= round((float) $row['value'] / $max2 * 100) ?>%"></div></div>
        <span class="hbar-value"><?= e(money((float) $row['value'], false)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php layout_footer(); ?>
