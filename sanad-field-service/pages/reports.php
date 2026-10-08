<?php
declare(strict_types=1);

/**
 * Sanad — Reports (finance).
 * Period / technician / customer filters, key numbers, breakdowns, CSV export.
 * Access is enforced by the front controller (finance-only route).
 */

$today      = date('Y-m-d');
$monthStart = date('Y-m-01');

// ---------------------------------------------------------------- filters (GET)

$rawFrom = (string) ($_GET['from'] ?? '');
$rawTo   = (string) ($_GET['to'] ?? '');

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom) === 1 ? $rawFrom : $monthStart;
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo) === 1 ? $rawTo : $today;

$technicianId = (int) ($_GET['technician'] ?? 0);
$customerId   = (int) ($_GET['customer'] ?? 0);
$wantsCsv     = (string) ($_GET['export'] ?? '') === 'csv';

// ---------------------------------------------------------------- fail-safe query helper
/**
 * Reporting must never take the whole page down because one query disagrees
 * with a hosting MySQL/MariaDB build. Each block is wrapped: on failure it
 * degrades to an empty result and the reason is shown to administrators.
 */
$degraded = [];
$safe = static function (string $label, callable $run, $fallback) use (&$degraded) {
    try {
        return $run();
    } catch (Throwable $e) {
        $degraded[] = $label . ': ' . get_class($e) . ' — ' . $e->getMessage();
        @error_log('[Sanad reports] ' . $label . ': ' . $e->getMessage());
        return $fallback;
    }
};

// ---------------------------------------------------------------- shared SQL

$where  = ['j.scheduled_date >= ?', 'j.scheduled_date <= ?'];
$params = [$from, $to];

if ($technicianId > 0) {
    $where[]  = 'j.assigned_to = ?';
    $params[] = $technicianId;
}
if ($customerId > 0) {
    $where[]  = 'j.customer_id = ?';
    $params[] = $customerId;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$jobSelect =
    'SELECT j.*, c.name AS customer_name, s.name AS site_name, sv.name AS service_name, sv.price AS service_price,
            u.name AS technician_name, u.colour AS technician_colour,
            (SELECT COALESCE(SUM(jp.qty * jp.unit_price), 0) FROM job_parts jp WHERE jp.job_id = j.id) AS parts_total
     FROM jobs j
     LEFT JOIN customers c ON c.id = j.customer_id
     LEFT JOIN sites s ON s.id = j.site_id
     LEFT JOIN services sv ON sv.id = j.service_id
     LEFT JOIN users u ON u.id = j.assigned_to ';

$jobOrder = ' ORDER BY j.scheduled_date ASC, j.window_start ASC';

// ---------------------------------------------------------------- CSV export

if ($wantsCsv) {
    $rows = Database::all($jobSelect . $whereSql . $jobOrder, $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sanad-jobs-' . $from . '-' . $to . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['number', 'date', 'customer', 'site', 'service', 'technician', 'priority', 'status', 'value'], ',', '"', '');
    foreach ($rows as $row) {
        $value = (float) ($row['service_price'] ?? 0) + (float) ($row['parts_total'] ?? 0);
        fputcsv($out, [
            (string) ($row['number'] ?? ''),
            (string) ($row['scheduled_date'] ?? ''),
            (string) ($row['customer_name'] ?? ''),
            (string) ($row['site_name'] ?? ''),
            (string) ($row['service_name'] ?? ''),
            (string) ($row['technician_name'] ?? ''),
            (string) ($row['priority'] ?? ''),
            (string) ($row['status'] ?? ''),
            number_format(round($value, 2), 2, '.', ''),
        ], ',', '"', '');
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------- pickers

$technicians = $safe('technicians', static fn () => Users::technicians(), []);
$customers   = $safe('customers', static fn () => Customers::all(true), []);

// ---------------------------------------------------------------- key numbers

$totalJobs = (int) $safe('total jobs', static fn () => Database::value('SELECT COUNT(*) FROM jobs j ' . $whereSql, $params), 0);
$doneJobs  = (int) $safe('done jobs', static fn () => Database::value(
    'SELECT COUNT(*) FROM jobs j ' . $whereSql . ' AND j.status = ?',
    array_merge($params, ['done'])
), 0);
$openJobs  = (int) $safe('open jobs', static fn () => Database::value(
    "SELECT COUNT(*) FROM jobs j " . $whereSql . " AND j.status IN ('new','scheduled','in_progress','on_hold')",
    $params
), 0);
$lateJobs  = (int) $safe('late jobs', static fn () => Database::value(
    "SELECT COUNT(*) FROM jobs j " . $whereSql
    . " AND j.status IN ('new','scheduled','in_progress','on_hold') AND j.scheduled_date < ?",
    array_merge($params, [$today])
), 0);

$finished  = $safe('durations', static fn () => Database::all(
    'SELECT j.created_at, j.completed_at FROM jobs j ' . $whereSql . ' AND j.status = ? AND j.completed_at IS NOT NULL',
    array_merge($params, ['done'])
), []);
$durations = [];
foreach ($finished as $row) {
    $start = strtotime((string) $row['created_at']);
    $end   = strtotime((string) $row['completed_at']);
    if ($start !== false && $end !== false && $end >= $start) {
        $durations[] = ($end - $start) / 86400;
    }
}
$avgDays = $durations ? round(array_sum($durations) / count($durations), 1) : 0.0;

// Money is period-based: invoices issued and payments received inside the range.
$billed      = (float) $safe('billed', static fn () => Database::value(
    'SELECT COALESCE(SUM(total), 0) FROM invoices WHERE issued_at >= ? AND issued_at < ?',
    [$from, date('Y-m-d', strtotime($to . ' +1 day'))]
), 0);
$collected   = (float) $safe('collected', static fn () => Database::value(
    'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE paid_at >= ? AND paid_at < ?',
    [$from, date('Y-m-d', strtotime($to . ' +1 day'))]
), 0);
$outstanding = (float) $safe('outstanding', static fn () => Invoices::outstandingTotal(), 0);

// ---------------------------------------------------------------- breakdowns

$byStatus = $safe('by status', static fn () => Database::all(
    'SELECT j.status AS k, COUNT(*) AS cnt FROM jobs j ' . $whereSql . ' GROUP BY j.status ORDER BY cnt DESC, j.status ASC',
    $params
), []);
$byTech = $safe('by technician', static fn () => Database::all(
    'SELECT u.name AS k, COUNT(*) AS cnt FROM jobs j LEFT JOIN users u ON u.id = j.assigned_to ' . $whereSql
    . ' GROUP BY u.id, u.name ORDER BY cnt DESC, u.name ASC',
    $params
), []);
$byService = $safe('by services', static fn () => Database::all(
    'SELECT sv.name AS k, COUNT(*) AS cnt FROM jobs j LEFT JOIN services sv ON sv.id = j.service_id ' . $whereSql
    . ' GROUP BY sv.id, sv.name ORDER BY cnt DESC, sv.name ASC LIMIT 8',
    $params
), []);
$byCustomer = $safe('by customers', static fn () => Database::all(
    'SELECT c.name AS k, COUNT(*) AS cnt FROM jobs j LEFT JOIN customers c ON c.id = j.customer_id ' . $whereSql
    . ' GROUP BY c.id, c.name ORDER BY cnt DESC, c.name ASC LIMIT 8',
    $params
), []);
$partsUsed = $safe('parts used', static fn () => Database::all(
    'SELECT p.name AS k, SUM(jp.qty) AS cnt FROM job_parts jp JOIN parts p ON p.id = jp.part_id JOIN jobs j ON j.id = jp.job_id '
    . $whereSql . ' GROUP BY p.id, p.name ORDER BY cnt DESC, p.name ASC LIMIT 8',
    $params
), []);
$aging = $safe('aging', static fn () => Invoices::aging(), ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0]);

$jobRows = $safe('job list', static fn () => Database::all($jobSelect . $whereSql . $jobOrder . ' LIMIT 100', $params), []);

/** Label for a LEFT JOIN key that may be missing. */
$nameOf = static function (?string $name): string {
    $name = trim((string) $name);
    return $name !== '' ? $name : t('common.none');
};

/** Quantities: 3 · 2.5 (no trailing zeroes). */
$qtyText = static function (float $qty): string {
    return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
};

/** CSV download carrying the filters currently in the URL. */
$exportHref = rtrim(u('reports') . '&' . keep_query(), '&') . '&export=csv';

layout_header(t('reports.title'), 'reports');
?>

<?php if ($degraded && Auth::isAdmin()): ?>
<section class="panel" style="border-color:var(--warning)">
  <h2 class="panel-title">⚠️ <?= e(t('reports.degraded')) ?></h2>
  <ul class="mini-list">
    <?php foreach ($degraded as $problem): ?>
      <li><span class="cell-muted"><?= e($problem) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('reports') ?>">
    <input type="hidden" name="p" value="reports">
    <label class="filter-date"><?= e(t('common.from')) ?> <input class="input input-sm" type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="filter-date"><?= e(t('common.to')) ?> <input class="input input-sm" type="date" name="to" value="<?= e($to) ?>"></label>
    <select name="technician" class="input input-select">
      <option value=""><?= e(t('jobs.any_technician')) ?></option>
      <?php foreach ($technicians as $tech): ?>
        <option value="<?= (int) $tech['id'] ?>" <?= $technicianId === (int) $tech['id'] ? 'selected' : '' ?>><?= e((string) $tech['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="customer" class="input input-select">
      <option value=""><?= e(t('jobs.any_customer')) ?></option>
      <?php foreach ($customers as $cust): ?>
        <option value="<?= (int) $cust['id'] ?>" <?= $customerId === (int) $cust['id'] ? 'selected' : '' ?>><?= e((string) $cust['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= e(t('reports.run')) ?></button>
    <a class="btn btn-ghost" href="<?= u('reports') ?>"><?= e(t('common.reset')) ?></a>
    <a class="btn btn-ghost btn-sm" href="<?= e($exportHref) ?>"><?= icon('chart') ?> <?= e(t('common.export_csv')) ?></a>
  </form>
</section>

<section class="stats-grid stats-grid-sm">
  <div class="stat-card"><span class="stat-value"><?= e((string) $totalJobs) ?></span><span class="stat-label"><?= e(t('reports.jobs_period')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= e((string) $doneJobs) ?></span><span class="stat-label"><?= e(t('reports.jobs_done')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= e((string) $openJobs) ?></span><span class="stat-label"><?= e(t('reports.jobs_open')) ?></span></div>
  <div class="stat-card <?= $lateJobs ? 'stat-warn' : '' ?>"><span class="stat-value"><?= e((string) $lateJobs) ?></span><span class="stat-label"><?= e(t('reports.jobs_late')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= e(number_format($avgDays, 1)) ?></span><span class="stat-label"><?= e(t('reports.avg_duration')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= e(money($billed)) ?></span><span class="stat-label"><?= e(t('reports.revenue')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= e(money($collected)) ?></span><span class="stat-label"><?= e(t('reports.collected')) ?></span></div>
  <div class="stat-card <?= $outstanding > 0 ? 'stat-warn' : '' ?>"><span class="stat-value"><?= e(money($outstanding)) ?></span><span class="stat-label"><?= e(t('reports.outstanding')) ?></span></div>
</section>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('reports.by_status')) ?></h2>
    <?php if (!$byStatus): ?>
      <div class="empty"><?= e(t('reports.empty')) ?></div>
    <?php else: ?>
      <?php $max = max(1, ...array_map(static fn (array $r): int => (int) $r['cnt'], $byStatus)); ?>
      <div class="hbar-list">
        <?php foreach ($byStatus as $r): ?>
          <div class="hbar">
            <span class="hbar-label"><?= job_status_badge((string) $r['k']) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width: <?= (int) round((int) $r['cnt'] / $max * 100) ?>%"></div></div>
            <span class="hbar-value"><?= e((string) (int) $r['cnt']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('reports.by_technician')) ?></h2>
    <?php if (!$byTech): ?>
      <div class="empty"><?= e(t('reports.empty')) ?></div>
    <?php else: ?>
      <?php $max = max(1, ...array_map(static fn (array $r): int => (int) $r['cnt'], $byTech)); ?>
      <div class="hbar-list">
        <?php foreach ($byTech as $r): ?>
          <div class="hbar">
            <span class="hbar-label"><?= e($nameOf($r['k'] ?? null)) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width: <?= (int) round((int) $r['cnt'] / $max * 100) ?>%"></div></div>
            <span class="hbar-value"><?= e((string) (int) $r['cnt']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('reports.by_service')) ?></h2>
    <?php if (!$byService): ?>
      <div class="empty"><?= e(t('reports.empty')) ?></div>
    <?php else: ?>
      <?php $max = max(1, ...array_map(static fn (array $r): int => (int) $r['cnt'], $byService)); ?>
      <div class="hbar-list">
        <?php foreach ($byService as $r): ?>
          <div class="hbar">
            <span class="hbar-label"><?= e($nameOf($r['k'] ?? null)) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width: <?= (int) round((int) $r['cnt'] / $max * 100) ?>%"></div></div>
            <span class="hbar-value"><?= e((string) (int) $r['cnt']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('reports.by_customer')) ?></h2>
    <?php if (!$byCustomer): ?>
      <div class="empty"><?= e(t('reports.empty')) ?></div>
    <?php else: ?>
      <?php $max = max(1, ...array_map(static fn (array $r): int => (int) $r['cnt'], $byCustomer)); ?>
      <div class="hbar-list">
        <?php foreach ($byCustomer as $r): ?>
          <div class="hbar">
            <span class="hbar-label"><?= e($nameOf($r['k'] ?? null)) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width: <?= (int) round((int) $r['cnt'] / $max * 100) ?>%"></div></div>
            <span class="hbar-value"><?= e((string) (int) $r['cnt']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('reports.parts_used')) ?></h2>
    <?php if (!$partsUsed): ?>
      <div class="empty"><?= e(t('reports.empty')) ?></div>
    <?php else: ?>
      <?php $max = max(1.0, ...array_map(static fn (array $r): float => (float) $r['cnt'], $partsUsed)); ?>
      <div class="hbar-list">
        <?php foreach ($partsUsed as $r): ?>
          <div class="hbar">
            <span class="hbar-label"><?= e($nameOf($r['k'] ?? null)) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width: <?= (int) round((float) $r['cnt'] / $max * 100) ?>%"></div></div>
            <span class="hbar-value"><?= e($qtyText((float) $r['cnt'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('reports.aging')) ?></h2>
    <?php
    $agingRows = [
        ['label' => t('inv.age_current'), 'value' => (float) $aging['current']],
        ['label' => t('inv.age_30'),      'value' => (float) $aging['d30']],
        ['label' => t('inv.age_60'),      'value' => (float) $aging['d60']],
        ['label' => t('inv.age_90'),      'value' => (float) $aging['d90']],
    ];
    $agingTotal = (float) array_sum(array_column($agingRows, 'value'));
    $agingMax   = max(1.0, ...array_map(static fn (array $r): float => (float) $r['value'], $agingRows));
    ?>
    <?php if ($agingTotal <= 0.0): ?>
      <div class="empty"><?= e(t('reports.empty')) ?></div>
    <?php else: ?>
      <div class="hbar-list">
        <?php foreach ($agingRows as $row): ?>
          <div class="hbar">
            <span class="hbar-label"><?= e((string) $row['label']) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width: <?= (int) round((float) $row['value'] / $agingMax * 100) ?>%"></div></div>
            <span class="hbar-value"><?= e(money((float) $row['value'], false)) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('reports.detail', ['n' => $totalJobs])) ?></h2>
    <span class="result-count"><?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?></span>
  </div>
  <?php job_table($jobRows); ?>
</section>

<?php layout_footer(); ?>
