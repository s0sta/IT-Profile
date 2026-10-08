<?php
declare(strict_types=1);

$me = Auth::current();
$isManager  = $me['role'] === 'manager';
$isOverseer = in_array($me['role'], ['admin', 'executive'], true);

$myStats    = Tasks::statsFor(['id' => $me['id'], 'role' => 'member', 'department_id' => $me['department_id']]);
$scopeStats = Tasks::statsFor($me);
$inboxCount = Approvals::inboxCount($me);
$inboxOver  = Approvals::overdueInboxCount($me);
$dueSoon    = Tasks::dueSoon($me, 7, 6);
$inbox      = Approvals::inboxFor($me, 5);
$byPriority = Tasks::byPriority($me);
$apprStatus = Approvals::byStatus($me);
$team       = $isManager ? Users::team((int) $me['id']) : [];
$workload   = ($isManager || $isOverseer) ? array_slice(Tasks::teamWorkload($me), 0, 6) : [];
$meetings   = Meetings::nextForUser($me, 4);
$letters    = Correspondence::openCount($me);
$lettersLate = Correspondence::overdueCount($me);
$lastTasks  = Tasks::list(['sort' => 'updated'], $me, 6, 0);
$activeDelegations = Delegations::activeForDelegate((int) $me['id']);
$activity   = AuditLog::recent(6);

$maxPrio = max(1, ...array_values($byPriority));
$apprTotal = max(1, array_sum($apprStatus));
$maxLoad = 1;
foreach ($workload as $w) {
    $maxLoad = max($maxLoad, (int) $w['open_now']);
}

$title = $isManager ? t('dash.title_manager') : ($isOverseer ? t('dash.title_admin') : t('dash.title_member'));
layout_header($title, 'dashboard');
?>

<?php if ($activeDelegations): ?>
  <div class="flash flash-info">
    <?= e(t('deleg.for_me')) ?>:
    <?php $names = []; foreach ($activeDelegations as $d) { $names[] = $d['delegator_name'] . ' (' . fmt_date($d['starts_at']) . ' → ' . fmt_date($d['ends_at']) . ')'; } ?>
    <strong><?= e(implode(' · ', $names)) ?></strong>
  </div>
<?php endif; ?>

<section class="stats-grid">
  <a class="stat-card" href="<?= u('tasks&mine=1') ?>">
    <span class="stat-value"><?= (int) $myStats['open'] ?></span>
    <span class="stat-label"><?= e(t('dash.my_open')) ?></span>
  </a>
  <a class="stat-card <?= $inboxCount ? 'stat-accent' : '' ?>" href="<?= u('approvals&inbox=1') ?>">
    <span class="stat-value"><?= $inboxCount ?></span>
    <span class="stat-label"><?= e(t('dash.awaiting_me')) ?><?php if ($inboxOver): ?> · <?= (int) $inboxOver ?> <?= e(t('dash.overdue_approvals')) ?><?php endif; ?></span>
  </a>
  <a class="stat-card <?= $myStats['overdue'] ? 'stat-warn' : '' ?>" href="<?= u('tasks&mine=1&overdue=1') ?>">
    <span class="stat-value"><?= (int) $myStats['overdue'] ?></span>
    <span class="stat-label"><?= e(t('dash.overdue_tasks')) ?></span>
  </a>
  <a class="stat-card" href="<?= u('calendar') ?>">
    <span class="stat-value"><?= count($dueSoon) ?></span>
    <span class="stat-label"><?= e(t('dash.due_week')) ?></span>
  </a>
  <a class="stat-card" href="<?= u('correspondence') ?>">
    <span class="stat-value"><?= $letters ?><?= $lettersLate ? ' ⚠' : '' ?></span>
    <span class="stat-label"><?= e(t('dash.open_letters')) ?></span>
  </a>
  <a class="stat-card" href="<?= u('meetings') ?>">
    <span class="stat-value"><?= Meetings::upcomingCount($me) ?></span>
    <span class="stat-label"><?= e(t('dash.upcoming_meetings')) ?></span>
  </a>
  <?php if ($isManager): ?>
  <a class="stat-card" href="<?= u('team') ?>">
    <span class="stat-value"><?= count($team) ?></span>
    <span class="stat-label"><?= e(t('dash.team_members')) ?></span>
  </a>
  <a class="stat-card <?= $scopeStats['overdue'] ? 'stat-warn' : '' ?>" href="<?= u('tasks&team=1&overdue=1') ?>">
    <span class="stat-value"><?= (int) $scopeStats['overdue'] ?></span>
    <span class="stat-label"><?= e(t('dash.team_overdue')) ?></span>
  </a>
  <?php endif; ?>
</section>

<?php if ($inboxCount): ?>
<section class="panel panel-accent">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('dash.inbox_title')) ?></h2>
    <a class="link" href="<?= u('approvals&inbox=1') ?>"><?= e(t('common.view_all')) ?> →</a>
  </div>
  <?php approval_table($inbox); ?>
</section>
<?php endif; ?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('dash.my_tasks')) ?></h2>
    <div class="hbar-list">
      <?php foreach (task_statuses() as $key => $label): $c = 0; ?>
        <?php if ($key === 'in_progress') { $c = (int) $myStats['in_progress']; } elseif ($key === 'waiting') { $c = (int) $myStats['waiting']; } elseif ($key === 'blocked') { $c = (int) $myStats['blocked']; } elseif ($key === 'completed') { $c = (int) $myStats['completed']; } elseif ($key === 'new') { $c = max(0, (int) $myStats['open'] - (int) $myStats['in_progress'] - (int) $myStats['waiting'] - (int) $myStats['blocked']); } ?>
        <div class="hbar">
          <span class="hbar-label"><?= task_status_badge((string) $key) ?></span>
          <div class="hbar-track"><div class="hbar-fill hbar-<?= e((string) $key) ?>" style="width:<?= $myStats['total'] ? round($c / max(1, (int) $myStats['total']) * 100) : 0 ?>%"></div></div>
          <span class="hbar-value"><?= $c ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('dash.approvals_by_status')) ?></h2>
    <div class="hbar-list">
      <?php foreach (approval_statuses() as $key => $label): $c = (int) ($apprStatus[$key] ?? 0); ?>
        <div class="hbar">
          <span class="hbar-label"><?= approval_status_badge((string) $key) ?></span>
          <div class="hbar-track"><div class="hbar-fill hbar-appr-<?= e((string) $key) ?>" style="width:<?= round($c / $apprTotal * 100) ?>%"></div></div>
          <span class="hbar-value"><?= $c ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($workload): ?>
<section class="panel">
  <h2 class="panel-title"><?= e(t('dash.team_workload')) ?></h2>
  <div class="hbar-list">
    <?php foreach ($workload as $w): ?>
      <div class="hbar">
        <span class="hbar-label"><?= e(user_name($w)) ?></span>
        <div class="hbar-track"><div class="hbar-fill hbar-load" style="width:<?= round((int) $w['open_now'] / $maxLoad * 100) ?>%"></div></div>
        <span class="hbar-value"><?= (int) $w['open_now'] ?><?= (int) $w['overdue'] ? ' ⚠' . (int) $w['overdue'] : '' ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e(t('dash.my_latest')) ?></h2>
      <a class="link" href="<?= u('tasks') ?>"><?= e(t('common.view_all')) ?> →</a>
    </div>
    <?php task_table($lastTasks); ?>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('dash.next_meetings')) ?></h2>
    <?php if (!$meetings): ?>
      <div class="empty"><?= e(t('dash.no_meetings')) ?></div>
    <?php else: ?>
      <ul class="timeline">
        <?php foreach ($meetings as $m): ?>
          <li>
            <span class="timeline-date"><?= e(fmt_dt($m['starts_at'])) ?></span>
            <a class="row-link" href="<?= u('meeting&id=' . (int) $m['id']) ?>"><?= e($m['title']) ?></a>
            <?php if ($m['location']): ?><span class="cell-muted">· <?= e($m['location']) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <hr>
    <h2 class="panel-title"><?= e(t('dash.recent_activity')) ?></h2>
    <?php if (!$activity): ?>
      <div class="empty"><?= e(t('dash.no_activity')) ?></div>
    <?php else: ?>
      <ul class="activity">
        <?php foreach ($activity as $a): ?>
          <li>
            <span class="activity-dot"></span>
            <span class="activity-text">
              <strong><?= e($a['username']) ?></strong>
              <?= e(str_replace('_', ' ', (string) $a['action'])) ?>
              <span class="cell-muted">· <?= e(time_ago($a['created_at'])) ?></span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('dash.due_week')) ?></h2>
  <?php task_table($dueSoon); ?>
</section>

<section class="panel panel-muted">
  <h2 class="panel-title"><?= e(t('dash.quick_actions')) ?></h2>
  <div class="quick-actions">
    <a class="btn btn-primary" href="<?= u('new-task') ?>"><?= icon('plus') ?> <?= e(t('nav.new_task')) ?></a>
    <a class="btn" href="<?= u('new-approval') ?>"><?= icon('approve') ?> <?= e(t('approvals.new')) ?></a>
    <a class="btn" href="<?= u('new-letter') ?>"><?= icon('mail') ?> <?= e(t('corr.new')) ?></a>
    <a class="btn" href="<?= u('meetings') ?>"><?= icon('calendar') ?> <?= e(t('meetings.title')) ?></a>
    <a class="btn btn-ghost" href="<?= u('calendar') ?>"><?= icon('clock') ?> <?= e(t('cal.title')) ?></a>
    <a class="btn btn-ghost" href="<?= u('doc') ?>"><?= icon('help') ?> <?= e(t('nav.doc')) ?></a>
  </div>
</section>

<?php layout_footer(); ?>
