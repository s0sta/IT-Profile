<?php
declare(strict_types=1);

$user  = Auth::current();
$today = date('Y-m-d');

if (Auth::isOffice() || Auth::isAdmin()) {
    Alerts::generateIfDue((int) setting('alert_days', '30'));
}

$byStatus  = Jobs::countByStatus();
$open      = Jobs::openCount();
$unassigned = Jobs::unassigned();
$overdue   = Jobs::overdue();
$todayJobs = Jobs::today();
$visits    = Auth::isOffice() ? Contracts::dueVisits((int) setting('alert_days', '30')) : [];
$lowStock  = Auth::isOffice() ? Parts::lowStock() : [];
$workload  = Auth::isOffice() ? Users::workload() : [];

layout_header(t('dash.title'), 'dashboard');
?>

<?php if (Auth::isTechnician()): ?>
  <?php
  $myOpen = Jobs::list(['technician' => Auth::id(), 'sort' => 'scheduled'], 100, 0);
  $myOpen = array_values(array_filter($myOpen, static fn (array $j): bool => is_open_job($j['status'])));
  $myToday = array_values(array_filter($myOpen, static fn (array $j): bool => $j['scheduled_date'] === $today));
  ?>
  <section class="stats-grid stats-grid-sm">
    <div class="stat-card"><span class="stat-value"><?= count($myToday) ?></span><span class="stat-label"><?= e(t('dash.my_today')) ?></span></div>
    <div class="stat-card"><span class="stat-value"><?= count($myOpen) ?></span><span class="stat-label"><?= e(t('dash.my_open')) ?></span></div>
    <div class="stat-card <?= count(array_filter($myOpen, static fn (array $j): bool => $j['scheduled_date'] && $j['scheduled_date'] < $today)) ? 'stat-warn' : '' ?>">
      <span class="stat-value"><?= count(array_filter($myOpen, static fn (array $j): bool => $j['scheduled_date'] && $j['scheduled_date'] < $today)) ?></span>
      <span class="stat-label"><?= e(t('dash.my_late')) ?></span>
    </div>
    <div class="stat-card"><span class="stat-value"><?= count(array_filter($myOpen, static fn (array $j): bool => $j['status'] === 'in_progress')) ?></span><span class="stat-label"><?= e(t('jstatus.in_progress')) ?></span></div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e(t('dash.today_work')) ?></h2>
      <a class="link" href="<?= u('jobs') ?>"><?= e(t('common.view_all')) ?> →</a>
    </div>
    <?php job_table($myToday); ?>
  </section>

  <section class="panel">
    <h2 class="panel-title"><?= e(t('dash.my_queue')) ?></h2>
    <?php job_table(array_slice($myOpen, 0, 12)); ?>
  </section>

<?php elseif (Auth::isAccountant()): ?>
  <?php $aging = Invoices::aging(); ?>
  <section class="stats-grid stats-grid-sm">
    <div class="stat-card"><span class="stat-value"><?= e(money(Invoices::invoicedThisMonth(), false)) ?></span><span class="stat-label"><?= e(t('dash.invoiced_month', ['currency' => (string) setting('currency', 'AED')])) ?></span></div>
    <div class="stat-card"><span class="stat-value"><?= e(money(Invoices::collectedThisMonth(), false)) ?></span><span class="stat-label"><?= e(t('dash.collected_month', ['currency' => (string) setting('currency', 'AED')])) ?></span></div>
    <div class="stat-card <?= Invoices::outstandingTotal() > 0 ? 'stat-warn' : '' ?>"><span class="stat-value"><?= e(money(Invoices::outstandingTotal(), false)) ?></span><span class="stat-label"><?= e(t('dash.outstanding', ['currency' => (string) setting('currency', 'AED')])) ?></span></div>
    <div class="stat-card <?= Invoices::overdueCount() ? 'stat-warn' : '' ?>"><span class="stat-value"><?= Invoices::overdueCount() ?></span><span class="stat-label"><?= e(t('dash.overdue_invoices')) ?></span></div>
  </section>

  <section class="grid-2">
    <div class="panel">
      <h2 class="panel-title"><?= e(t('dash.aging')) ?></h2>
      <div class="hbar-list">
        <?php
        $agingRows = [
            ['label' => t('inv.age_current'), 'value' => $aging['current']],
            ['label' => t('inv.age_30'), 'value' => $aging['d30']],
            ['label' => t('inv.age_60'), 'value' => $aging['d60']],
            ['label' => t('inv.age_90'), 'value' => $aging['d90']],
        ];
        $maxAging = max(1.0, ...array_map(static fn (array $r): float => (float) $r['value'], $agingRows));
        foreach ($agingRows as $row): ?>
          <div class="hbar">
            <span class="hbar-label"><?= e($row['label']) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width:<?= round((float) $row['value'] / $maxAging * 100) ?>%"></div></div>
            <span class="hbar-value"><?= e(money((float) $row['value'], false)) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="panel">
      <div class="panel-head">
        <h2 class="panel-title"><?= e(t('dash.recent_invoices')) ?></h2>
        <a class="link" href="<?= u('invoices') ?>"><?= e(t('common.view_all')) ?> →</a>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e(t('inv.number')) ?></th><th><?= e(t('inv.customer')) ?></th><th><?= e(t('inv.total')) ?></th><th><?= e(t('common.status')) ?></th></tr></thead>
          <tbody>
            <?php foreach (Invoices::recent(6) as $inv): ?>
              <tr>
                <td><a class="ref-link" href="<?= u('invoice&id=' . (int) $inv['id']) ?>"><?= e($inv['number']) ?></a></td>
                <td><?= e($inv['customer_name'] ?? '—') ?></td>
                <td><?= e(money((float) $inv['total'])) ?></td>
                <td><?= invoice_status_badge($inv['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

<?php else: ?>

  <section class="stats-grid stats-grid-sm">
    <div class="stat-card"><span class="stat-value"><?= count($todayJobs) ?></span><span class="stat-label"><?= e(t('dash.today_jobs')) ?></span></div>
    <div class="stat-card"><span class="stat-value"><?= $open ?></span><span class="stat-label"><?= e(t('dash.open_jobs')) ?></span></div>
    <div class="stat-card <?= $unassigned ? 'stat-warn' : '' ?>"><span class="stat-value"><?= $unassigned ?></span><span class="stat-label"><?= e(t('dash.unassigned')) ?></span></div>
    <div class="stat-card <?= count($overdue) ? 'stat-warn' : '' ?>"><span class="stat-value"><?= count($overdue) ?></span><span class="stat-label"><?= e(t('dash.overdue_jobs')) ?></span></div>
    <div class="stat-card"><span class="stat-value"><?= count($visits) ?></span><span class="stat-label"><?= e(t('dash.due_visits', ['days' => (int) setting('alert_days', '30')])) ?></span></div>
    <div class="stat-card <?= count($lowStock) ? 'stat-warn' : '' ?>"><span class="stat-value"><?= count($lowStock) ?></span><span class="stat-label"><?= e(t('dash.low_stock')) ?></span></div>
  </section>

  <section class="grid-2">
    <div class="panel">
      <div class="panel-head">
        <h2 class="panel-title"><?= e(t('dash.board_today')) ?></h2>
        <a class="link" href="<?= u('board') ?>"><?= e(t('nav.board')) ?> →</a>
      </div>
      <?php if (!$todayJobs): ?>
        <div class="empty"><?= e(t('board.no_jobs')) ?></div>
      <?php else: ?>
        <div class="hbar-list">
          <?php
          $perTech = [];
          foreach ($todayJobs as $job) {
              $key = $job['technician_name'] ?? t('job.unassigned');
              $perTech[$key] = ($perTech[$key] ?? 0) + 1;
          }
          $maxTech = max($perTech);
          foreach ($perTech as $name => $count): ?>
            <div class="hbar">
              <span class="hbar-label"><?= e((string) $name) ?></span>
              <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width:<?= round($count / $maxTech * 100) ?>%"></div></div>
              <span class="hbar-value"><?= $count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2 class="panel-title"><?= e(t('dash.by_status')) ?></h2>
      <div class="hbar-list">
        <?php $maxStatus = max(1, ...array_values(array_merge($byStatus, array_fill_keys(array_keys(job_statuses()), 0))));
        foreach (job_statuses() as $key => $label): $c = $byStatus[$key] ?? 0; ?>
          <a class="hbar hbar-link" href="<?= u('jobs&status=' . e($key)) ?>">
            <span class="hbar-label"><?= job_status_badge($key) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-<?= e($key) ?>" style="width:<?= round($c / $maxStatus * 100) ?>%"></div></div>
            <span class="hbar-value"><?= $c ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="grid-2">
    <div class="panel">
      <div class="panel-head">
        <h2 class="panel-title"><?= e(t('dash.contract_visits')) ?></h2>
        <a class="link" href="<?= u('contracts') ?>"><?= e(t('nav.contracts')) ?> →</a>
      </div>
      <?php if (!$visits): ?><div class="empty"><?= e(t('dash.no_visits')) ?></div><?php else: ?>
        <ul class="mini-list">
          <?php foreach (array_slice($visits, 0, 6) as $visit): ?>
            <li>
              <span><?= e($visit['customer_name']) ?></span>
              <span class="cell-muted"><?= e((string) $visit['contract_number']) ?><?= $visit['site_name'] ? ' · ' . e($visit['site_name']) : '' ?></span>
              <span class="mini-right"><?= due_chip($visit['due_date']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2 class="panel-title"><?= e(t('dash.workload')) ?></h2>
      <?php if (!$workload): ?><div class="empty"><?= e(t('dash.no_technicians')) ?></div><?php else: ?>
        <div class="hbar-list">
          <?php $maxWork = max(1, ...array_map(static fn (array $w): int => (int) $w['open_jobs'], $workload));
          foreach ($workload as $w): ?>
            <div class="hbar">
              <span class="hbar-label"><?= tech_chip($w['name'], $w['colour']) ?></span>
              <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width:<?= round((int) $w['open_jobs'] / $maxWork * 100) ?>%"></div></div>
              <span class="hbar-value"><?= (int) $w['open_jobs'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($overdue): ?>
  <section class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e(t('dash.overdue_jobs')) ?></h2>
      <a class="link" href="<?= u('jobs&sort=scheduled') ?>"><?= e(t('common.view_all')) ?> →</a>
    </div>
    <?php job_table(array_slice($overdue, 0, 8)); ?>
  </section>
  <?php endif; ?>

  <section class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e(t('dash.recent_jobs')) ?></h2>
      <a class="link" href="<?= u('jobs') ?>"><?= e(t('common.view_all')) ?> →</a>
    </div>
    <?php job_table(Jobs::recent(8)); ?>
  </section>

  <?php if (Auth::isAdmin()): ?>
  <section class="panel">
    <h2 class="panel-title"><?= e(t('dash.activity')) ?></h2>
    <?php $act = AuditLog::list('', 8, 0); ?>
    <?php if (!$act): ?><div class="empty"><?= e(t('dash.no_activity')) ?></div><?php else: ?>
      <ul class="activity">
        <?php foreach ($act as $a): ?>
          <li>
            <span class="activity-dot"></span>
            <span class="activity-text">
              <strong><?= e($a['username']) ?></strong>
              <?= e(str_replace('_', ' ', $a['action'])) ?>
              <?php if ($a['entity']): ?><span class="cell-muted">(<?= e($a['entity']) ?><?= $a['entity_id'] ? ' #' . e($a['entity_id']) : '' ?>)</span><?php endif; ?>
              <span class="cell-muted">· <?= e(time_ago($a['created_at'])) ?></span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <?php endif; ?>

<?php endif; ?>

<?php layout_footer(); ?>
