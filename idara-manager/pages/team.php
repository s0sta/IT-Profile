<?php
declare(strict_types=1);

/**
 * Idara — my team: members with their live task load, the team workload bars
 * and a small card for my direct manager.
 */

$me   = Auth::current();
$team = Users::team((int) $me['id']);

// Executives / admins usually manage nobody directly: show the full roster.
if (!$team && in_array($me['role'], ['admin', 'executive'], true)) {
    $team = Users::all(true);
}

$workload = Tasks::teamWorkload($me);
$loadById = [];
foreach ($workload as $w) {
    $loadById[(int) $w['id']] = $w;
}
$maxLoad = 1;
foreach ($workload as $w) {
    $maxLoad = max($maxLoad, (int) $w['open_now']);
}

$manager = !empty($me['manager_id']) ? Users::find((int) $me['manager_id']) : null;

layout_header(t('team.title'), 'team');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('team.members')) ?> (<?= count($team) ?>)</h2>
    <span class="cell-muted"><?= e(t('team.total_members')) ?>: <?= count($team) ?></span>
  </div>

  <?php if (!$team): ?>
    <div class="empty"><?= e(t('team.no_members')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('common.name')) ?></th>
            <th><?= e(t('team.job_title')) ?></th>
            <th><?= e(t('common.email')) ?></th>
            <th><?= e(t('common.phone')) ?></th>
            <th><?= e(t('team.open_now')) ?></th>
            <th><?= e(t('team.overdue')) ?></th>
            <th><?= e(t('team.last_login')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($team as $m):
              $mid  = (int) $m['id'];
              $load = $loadById[$mid] ?? null;
              // teamWorkload() already covers the visible roster; fall back to a
              // direct count when a member sits outside its scope.
              $open = $load !== null ? (int) $load['open_now'] : Tasks::count(['assignee' => $mid], $me);
              $late = $load !== null ? (int) $load['overdue'] : Tasks::count(['assignee' => $mid, 'overdue' => 1], $me);
          ?>
            <tr>
              <td>
                <?= avatar_img($m, 34) ?>
                <?= e(user_name($m)) ?>
                <div class="cell-muted"><?= role_badge((string) $m['role']) ?></div>
              </td>
              <td><?= e(job_title_display($m['job_title'])) ?></td>
              <td><?= e($m['email']) ?></td>
              <td><?= e($m['phone'] !== '' ? $m['phone'] : '—') ?></td>
              <td><?= $open ?></td>
              <td class="<?= $late ? 'text-danger' : 'cell-muted' ?>"><?= $late ?></td>
              <td class="cell-muted"><?= e(time_ago($m['last_login_at'])) ?></td>
              <td class="cell-actions">
                <a class="btn btn-ghost btn-sm" href="<?= u('tasks&assignee=' . $mid) ?>"><?= e(t('team.view_tasks')) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('team.workload')) ?></h2>
  <?php if (!$workload): ?>
    <div class="empty"><?= e(t('team.no_members')) ?></div>
  <?php else: ?>
    <div class="hbar-list">
      <?php foreach ($workload as $w): ?>
        <div class="hbar">
          <span class="hbar-label"><?= e(user_name($w)) ?></span>
          <div class="hbar-track"><div class="hbar-fill hbar-load" style="width:<?= round((int) $w['open_now'] / $maxLoad * 100) ?>%"></div></div>
          <span class="hbar-value"><?= (int) $w['open_now'] ?><?= (int) $w['overdue'] ? ' ⚠' . (int) $w['overdue'] : '' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($manager): ?>
<section class="panel panel-muted">
  <h2 class="panel-title"><?= e(t('team.my_manager')) ?></h2>
  <div class="meta-grid">
    <div class="meta-item">
      <span class="meta-label"><?= e(t('common.name')) ?></span>
      <span class="meta-value"><?= e($manager['name']) ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label"><?= e(t('common.email')) ?></span>
      <span class="meta-value"><?= e($manager['email']) ?></span>
    </div>
  </div>
</section>
<?php endif; ?>

<?php layout_footer(); ?>
