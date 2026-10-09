<?php
declare(strict_types=1);

$staff = Auth::isStaff();

$since30 = date('Y-m-d H:i:s', strtotime('-30 days'));
$today   = date('Y-m-d H:i:s', strtotime('today'));

$openTotal = 0;
foreach (Tickets::openByStatus(!$staff) as $row) {
    $openTotal += (int) $row['c'];
}

$overdue    = Tickets::overdueCount();
$unassigned = $staff ? Tickets::unassignedCount() : null;
$createdT   = Tickets::createdSince($today);
$resolved30 = Tickets::resolvedSince($since30);
$avgRes     = Tickets::avgResolutionHours($since30);

$series = Tickets::series14();
$maxDay = 1;
foreach ($series as $d) {
    $maxDay = max($maxDay, $d['count']);
}

$byPriority = Tickets::openByPriority();
$prioMap = [];
foreach ($byPriority as $r) {
    $prioMap[$r['priority']] = (int) $r['c'];
}
$maxPrio = max(1, ...array_values(array_merge($prioMap, ['low' => 0, 'medium' => 0, 'high' => 0, 'urgent' => 0])));

$recent = Tickets::list([], 6, 0);

layout_header($staff ? t('dash.title_staff') : t('dash.title_requester'), 'dashboard');
?>

<section class="stats-grid">
  <a class="stat-card stat-card-link" href="<?= u('tickets') ?>">
    <span class="stat-value"><?= $openTotal ?></span>
    <span class="stat-label"><?= e(t('dash.open')) ?></span>
  </a>
  <a class="stat-card stat-card-link <?= $overdue ? 'stat-warn' : '' ?>" href="<?= u('tickets&sort=due') ?>">
    <span class="stat-value"><?= $overdue ?></span>
    <span class="stat-label"><?= e(t('dash.overdue')) ?></span>
  </a>
  <?php if ($staff): ?>
  <a class="stat-card stat-card-link" href="<?= u('tickets&assignee=none') ?>">
    <span class="stat-value"><?= $unassigned ?></span>
    <span class="stat-label"><?= e(t('dash.unassigned')) ?></span>
  </a>
  <?php endif; ?>
  <a class="stat-card stat-card-link" href="<?= u('tickets&sort=created') ?>">
    <span class="stat-value"><?= $createdT ?></span>
    <span class="stat-label"><?= e(t('dash.created_today')) ?></span>
  </a>
  <a class="stat-card stat-card-link" href="<?= u('tickets&status=resolved') ?>">
    <span class="stat-value"><?= $resolved30 ?></span>
    <span class="stat-label"><?= e(t('dash.resolved_30')) ?></span>
  </a>
  <a class="stat-card stat-card-link" href="<?= u(Auth::isAdmin() ? 'admin/reports' : 'tickets') ?>">
    <span class="stat-value"><?= $avgRes === null ? '—' : $avgRes . 'h' ?></span>
    <span class="stat-label"><?= e(t('dash.avg_resolution')) ?></span>
  </a>
</section>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('dash.chart_title')) ?></h2>
    <div class="bars" role="img" aria-label="<?= e(t('dash.chart_title')) ?>">
      <?php foreach ($series as $d): $h = $d['count'] ? max(6, round($d['count'] / $maxDay * 100)) : 0; ?>
        <div class="bar-col" title="<?= e($d['day']) ?>: <?= $d['count'] ?>">
          <span class="bar-value"><?= $d['count'] ?></span>
          <div class="bar-track"><div class="bar-fill" style="height:<?= $h ?>%"></div></div>
          <span class="bar-label"><?= e($d['day']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('dash.by_priority')) ?></h2>
    <div class="hbar-list">
      <?php foreach (['urgent', 'high', 'medium', 'low'] as $p): $c = $prioMap[$p] ?? 0; ?>
        <div class="hbar">
          <span class="hbar-label"><?= priority_badge($p) ?></span>
          <div class="hbar-track"><div class="hbar-fill hbar-<?= e($p) ?>" style="width:<?= $maxPrio ? round($c / $maxPrio * 100) : 0 ?>%"></div></div>
          <span class="hbar-value"><?= $c ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($staff): ?>
<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('dash.workload')) ?></h2>
    <?php $wl = Tickets::agentWorkload(); ?>
    <?php if (!$wl): ?><div class="empty"><?= e(t('dash.no_assigned')) ?></div><?php else: ?>
      <div class="hbar-list">
        <?php $maxW = max(1, (int) $wl[0]['c']); foreach ($wl as $w): ?>
          <div class="hbar">
            <span class="hbar-label"><?= e($w['name']) ?></span>
            <div class="hbar-track"><div class="hbar-fill hbar-agent" style="width:<?= round($w['c'] / $maxW * 100) ?>%"></div></div>
            <span class="hbar-value"><?= (int) $w['c'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
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
  </div>
</section>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e($staff ? t('dash.recent_staff') : t('dash.recent_user')) ?></h2>
    <a class="link" href="<?= u('tickets') ?>"><?= e(t('common.view_all')) ?> →</a>
  </div>
  <?php ticket_table($recent); ?>
</section>

<?php
if (!$staff) {
    $latest = Kb::articles(null, '', 4, 0, true);
    ?>
    <section class="panel">
      <div class="panel-head">
        <h2 class="panel-title"><?= e(t('dash.kb_latest')) ?></h2>
        <a class="link" href="<?= u('kb') ?>"><?= e(t('dash.open_kb')) ?> →</a>
      </div>
      <?php if (!$latest): ?><div class="empty"><?= e(t('dash.no_articles')) ?></div><?php else: ?>
        <ul class="kb-mini">
          <?php foreach ($latest as $a): ?>
            <li><a href="<?= u('article&slug=' . e($a['slug'])) ?>"><?= e($a['title']) ?></a><span class="cell-muted"><?= e($a['category_name'] ?? t('common.general')) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
<?php } ?>

<?php layout_footer(); ?>
