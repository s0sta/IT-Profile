<?php
declare(strict_types=1);

$f = [
    'from'     => (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))),
    'to'       => (string) ($_GET['to'] ?? date('Y-m-d')),
    'status'   => (string) ($_GET['status'] ?? ''),
    'priority' => (string) ($_GET['priority'] ?? ''),
    'category' => (string) ($_GET['category'] ?? ''),
];
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

$rows    = Tickets::reportRows($f);
$summary = Tickets::reportSummary($f);

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="daem-tickets-' . $f['from'] . '-to-' . $f['to'] . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [t('common.ref'), t('common.subject'), t('common.requester'), t('common.assignee'), t('common.category'), t('common.priority'), t('common.status'), t('common.created'), t('tv.first_response'), t('status.resolved'), t('status.closed')], ',', '"', '');
    foreach ($rows as $t) {
        fputcsv($out, [
            $t['ref'], $t['subject'], $t['requester_name'] ?? '', $t['assignee_name'] ?? '',
            $t['category_name'] ?? '', $t['priority'], $t['status'],
            $t['created_at'], $t['first_response_at'] ?? '', $t['resolved_at'] ?? '', $t['closed_at'] ?? '',
        ], ',', '"', '');
    }
    fclose($out);
    audit('report_exported', 'report', null, 'from=' . $f['from'] . ' to=' . $f['to']);
    exit;
}

$byStatus = [];
$byPriority = [];
foreach ($rows as $t) {
    $byStatus[$t['status']] = ($byStatus[$t['status']] ?? 0) + 1;
    $byPriority[$t['priority']] = ($byPriority[$t['priority']] ?? 0) + 1;
}
ksort($byStatus);

layout_header(t('ar.title'), 'admin/reports');
?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('admin/reports') ?>">
    <input type="hidden" name="p" value="admin/reports">
    <label class="filter-date"><?= e(t('ar.from')) ?> <input class="input input-sm" type="date" name="from" value="<?= e($f['from']) ?>"></label>
    <label class="filter-date"><?= e(t('ar.to')) ?> <input class="input input-sm" type="date" name="to" value="<?= e($f['to']) ?>"></label>
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
    <button class="btn" type="submit"><?= e(t('ar.run')) ?></button>
    <a class="btn btn-ghost" href="<?= u('admin/reports') ?>"><?= e(t('common.reset')) ?></a>
    <a class="btn btn-soft" href="<?= u('admin/reports') ?>&<?= e(keep_query()) ?>&export=csv">⬇ <?= e(t('ar.export')) ?></a>
  </form>
</section>

<section class="stats-grid">
  <div class="stat-card"><span class="stat-value"><?= $summary['total'] ?></span><span class="stat-label"><?= e(t('ar.total')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= $summary['resolved'] ?></span><span class="stat-label"><?= e(t('ar.resolved')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= $summary['open_now'] ?></span><span class="stat-label"><?= e(t('ar.open')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= $summary['avg_hours'] === null ? '—' : $summary['avg_hours'] . 'h' ?></span><span class="stat-label"><?= e(t('ar.avg')) ?></span></div>
  <div class="stat-card <?= ($summary['sla_compliance'] !== null && $summary['sla_compliance'] < 80) ? 'stat-warn' : '' ?>">
    <span class="stat-value"><?= $summary['sla_compliance'] === null ? '—' : $summary['sla_compliance'] . '%' ?></span>
    <span class="stat-label"><?= e(t('ar.compliance')) ?></span>
  </div>
</section>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('ar.by_status')) ?></h2>
    <?php if (!$byStatus): ?><div class="empty"><?= e(t('ar.empty')) ?></div><?php else: ?>
      <div class="hbar-list">
        <?php $max = max(1, ...array_values($byStatus)); foreach ($byStatus as $s => $c): ?>
          <div class="hbar">
            <span class="hbar-label"><?= status_badge($s) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width:<?= round($c / $max * 100) ?>%"></div></div>
            <span class="hbar-value"><?= $c ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('ar.by_priority')) ?></h2>
    <?php if (!$byPriority): ?><div class="empty"><?= e(t('ar.empty')) ?></div><?php else: ?>
      <div class="hbar-list">
        <?php $max = max(1, ...array_values($byPriority)); foreach (priorities() as $p => $label): $c = $byPriority[$p] ?? 0; ?>
          <div class="hbar">
            <span class="hbar-label"><?= priority_badge($p) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-<?= e($p) ?>" style="width:<?= $max ? round($c / $max * 100) : 0 ?>%"></div></div>
            <span class="hbar-value"><?= $c ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('ar.list', ['n' => count($rows)])) ?></h2>
  <?php ticket_table(array_slice(array_reverse($rows), 0, 100)); ?>
</section>

<?php layout_footer(); ?>
