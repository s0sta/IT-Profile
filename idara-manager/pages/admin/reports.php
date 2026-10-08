<?php
declare(strict_types=1);

/**
 * Admin · Reports — date-range report over the scoped task set, with optional
 * status / department filters, summary cards, breakdowns and CSV export.
 */

$me = Auth::current();

$from = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
$to   = (string) ($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$status     = (string) ($_GET['status'] ?? '');
$department = (string) ($_GET['department'] ?? '');
if ($status !== '' && !array_key_exists($status, task_statuses())) {
    $status = '';
}

$f = [
    'from'       => $from,
    'to'         => $to,
    'status'     => $status,
    'department' => $department,
];

$rows   = Tasks::reportRows($f, $me);
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

// ---------------------------------------------------------------- CSV export
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="idara-tasks-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        t('common.ref'),
        t('tasks.col_task'),
        t('common.assignee'),
        t('common.department'),
        t('common.priority'),
        t('common.status'),
        t('tasks.col_progress'),
        t('common.created'),
        t('tasks.col_due'),
    ], ',', '"', '');
    foreach ($rows as $row) {
        $deptCell = '';
        if ((string) ($row['dept_name_ar'] ?? '') !== '' || (string) ($row['dept_name_en'] ?? '') !== '') {
            $deptCell = bilingual($row, 'dept_name');
        }
        fputcsv($out, [
            (string) $row['ref'],
            (string) $row['title'],
            (string) ($row['assignee_name'] ?? ''),
            $deptCell,
            priorities()[(string) $row['priority']] ?? (string) $row['priority'],
            task_statuses()[(string) $row['status']] ?? (string) $row['status'],
            (int) $row['progress'] . '%',
            (string) $row['created_at'],
            (string) ($row['due_date'] ?? ''),
        ], ',', '"', '');
    }
    fclose($out);
    audit('report_exported', 'report', null, 'from=' . $from . ' to=' . $to);
    exit;
}

// ---------------------------------------------------------------- summary
$total     = count($rows);
$completed = 0;
$open      = 0;
$overdue   = 0;
$daysSum   = 0.0;
$daysCount = 0;

foreach ($rows as $row) {
    $rowStatus = (string) $row['status'];
    if ($rowStatus === 'completed') {
        $completed++;
        if (!empty($row['completed_at'])) {
            $daysSum += (strtotime((string) $row['completed_at']) - strtotime((string) $row['created_at'])) / 86400;
            $daysCount++;
        }
    }
    if (task_is_open($rowStatus)) {
        $open++;
    }
    if (is_overdue($row['due_date'] ?? null, $rowStatus)) {
        $overdue++;
    }
}

$completion = $total > 0 ? (int) round($completed / $total * 100) : 0;
$avgDays    = $daysCount > 0 ? round($daysSum / $daysCount, 1) : null;

$apprStatus  = Approvals::byStatus($me);
$apprPending = (int) ($apprStatus['pending'] ?? 0);
$apprAvg     = Approvals::avgDecisionDays($me);

// ---------------------------------------------------------------- breakdowns
$byStatus = [];
foreach (task_statuses() as $key => $label) {
    $byStatus[$key] = 0;
}
foreach ($rows as $row) {
    $key = (string) $row['status'];
    $byStatus[$key] = ($byStatus[$key] ?? 0) + 1;
}

$byDept = [];
foreach ($rows as $row) {
    $key = (int) ($row['department_id'] ?? 0);
    $byDept[$key] = ($byDept[$key] ?? 0) + 1;
}

$byMember = [];
foreach ($rows as $row) {
    $memberName = trim((string) ($row['assignee_name'] ?? ''));
    if ($memberName === '') {
        $memberName = t('common.unassigned');
    }
    $byMember[$memberName] = ($byMember[$memberName] ?? 0) + 1;
}
arsort($byMember);

$byType = Approvals::byType($me);
$depts  = Departments::all();

layout_header(t('ar.title'), 'admin/reports');
?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('admin/reports') ?>">
    <input type="hidden" name="p" value="admin/reports">
    <label class="filter-date"><?= e(t('ar.from')) ?> <input class="input input-sm" type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="filter-date"><?= e(t('ar.to')) ?> <input class="input input-sm" type="date" name="to" value="<?= e($to) ?>"></label>
    <select name="status" class="input input-select">
      <option value=""><?= e(t('tasks.all_statuses')) ?></option>
      <?php foreach (task_statuses() as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="department" class="input input-select">
      <option value=""><?= e(t('tasks.all_departments')) ?></option>
      <?php foreach ($depts as $dept): ?>
        <option value="<?= (int) $dept['id'] ?>" <?= (string) $dept['id'] === $department ? 'selected' : '' ?>><?= e(Departments::label($dept)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= e(t('ar.run')) ?></button>
    <a class="btn btn-ghost" href="<?= u('admin/reports') ?>"><?= e(t('common.reset')) ?></a>
    <a class="btn btn-soft" href="<?= u('admin/reports') ?>&<?= e(keep_query()) ?>&export=csv"><?= icon('chart') ?> <?= e(t('ar.export')) ?></a>
  </form>
</section>

<section class="stats-grid">
  <div class="stat-card"><span class="stat-value"><?= $total ?></span><span class="stat-label"><?= e(t('ar.total')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= $completed ?></span><span class="stat-label"><?= e(t('ar.completed')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= $open ?></span><span class="stat-label"><?= e(t('ar.open')) ?></span></div>
  <div class="stat-card <?= $overdue ? 'stat-warn' : '' ?>"><span class="stat-value"><?= $overdue ?></span><span class="stat-label"><?= e(t('ar.overdue')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= $completion ?>%</span><span class="stat-label"><?= e(t('ar.completion')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= $avgDays === null ? '—' : e((string) $avgDays) ?></span><span class="stat-label"><?= e(t('ar.avg_days')) ?></span></div>
  <div class="stat-card <?= $apprPending ? 'stat-accent' : '' ?>"><span class="stat-value"><?= $apprPending ?></span><span class="stat-label"><?= e(t('ar.appr_pending')) ?></span></div>
  <div class="stat-card"><span class="stat-value"><?= $apprAvg === null ? '—' : e((string) $apprAvg) ?></span><span class="stat-label"><?= e(t('ar.appr_avg')) ?></span></div>
</section>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('ar.by_status')) ?></h2>
    <?php if ($total === 0): ?>
      <div class="empty"><?= e(t('ar.empty')) ?></div>
    <?php else: ?>
      <?php $maxStatus = max(1, ...array_values($byStatus)); ?>
      <div class="hbar-list">
        <?php foreach (task_statuses() as $key => $label): $count = (int) ($byStatus[$key] ?? 0); ?>
          <div class="hbar">
            <span class="hbar-label"><?= task_status_badge((string) $key) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-<?= e((string) $key) ?>" style="width:<?= round($count / $maxStatus * 100) ?>%"></div></div>
            <span class="hbar-value"><?= $count ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('ar.by_department')) ?></h2>
    <?php if (!$depts): ?>
      <div class="empty"><?= e(t('ar.empty')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e(t('ar.department')) ?></th><th><?= e(t('nav.tasks')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($depts as $dept): ?>
              <tr>
                <td><?= e(Departments::label($dept)) ?></td>
                <td><?= (int) ($byDept[(int) $dept['id']] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('ar.by_member')) ?></h2>
    <?php if (!$byMember): ?>
      <div class="empty"><?= e(t('ar.empty')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e(t('ar.member')) ?></th><th><?= e(t('nav.tasks')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($byMember as $memberName => $count): ?>
              <tr>
                <td><?= e((string) $memberName) ?></td>
                <td><?= (int) $count ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('ar.by_type')) ?></h2>
    <?php if (!$byType): ?>
      <div class="empty"><?= e(t('ar.empty')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e(t('approvals.type')) ?></th><th><?= e(t('nav.approvals')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($byType as $typeRow): $typeLabel = bilingual($typeRow, 'name'); ?>
              <tr>
                <td><?= e($typeLabel !== '' ? $typeLabel : t('common.none')) ?></td>
                <td><?= (int) $typeRow['c'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('ar.list', ['n' => count($rows)])) ?></h2>
  <?php task_table(array_slice(array_reverse($rows), 0, 100)); ?>
</section>

<?php layout_footer(); ?>
