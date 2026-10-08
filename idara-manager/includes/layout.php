<?php
declare(strict_types=1);

/**
 * Idara — shared layout: sidebar navigation (manager workspace), topbar with
 * language switcher / theme / notifications / user menu, badges, progress bars
 * and the task & approval tables used by several pages.
 */

function icon(string $name): string
{
    static $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'tasks'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
        'approve'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>',
        'team'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.5c3 .3 5.5 2.5 5.5 5.5"/></svg>',
        'mail'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/></svg>',
        'calendar'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>',
        'clock'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
        'share'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.2 10.8l7.6-3.6M8.2 13.2l7.6 3.6"/></svg>',
        'users'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.5c3 .3 5.5 2.5 5.5 5.5"/></svg>',
        'building'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16"/><path d="M14 9h4a2 2 0 0 1 2 2v10"/><path d="M7 7h3M7 11h3M7 15h3M17 13h1M17 17h1M3 21h18"/></svg>',
        'tags'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11V4a1 1 0 0 1 1-1h7l10 10-7 7L3 11z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>',
        'stamp'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 21h14M7 17h10v-2a5 5 0 0 0-3-4.6V8a2 2 0 1 0-4 0v2.4A5 5 0 0 0 7 15v2z"/></svg>',
        'sliders'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h13M20 18h1"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
        'list'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/></svg>',
        'chart'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
        'plus'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>',
        'bell'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>',
        'search'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>',
        'logout'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>',
        'user'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>',
        'moon'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>',
        'sun'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
        'menu'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
        'help'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.4 2.3c-.6.3-.9.8-.9 1.4v.6"/><circle cx="12" cy="17" r="1"/></svg>',
        'check'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4.5 4.5L19 6.5"/></svg>',
        'x'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        'back'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 6l6 6-6 6"/></svg>',
        'flag'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 21V4h9l-1 3h6v9h-8l-1-3H5"/></svg>',
    ];
    return $icons[$name] ?? '';
}

// ---------------------------------------------------------------- badges

function task_status_badge(string $status): string
{
    return '<span class="badge badge-task badge-task-' . e($status) . '">' . e(task_statuses()[$status] ?? $status) . '</span>';
}

function approval_status_badge(string $status): string
{
    return '<span class="badge badge-appr badge-appr-' . e($status) . '">' . e(approval_statuses()[$status] ?? $status) . '</span>';
}

function step_status_badge(string $status): string
{
    return '<span class="badge badge-step badge-step-' . e($status) . '">' . e(step_statuses()[$status] ?? $status) . '</span>';
}

function correspondence_status_badge(string $status): string
{
    return '<span class="badge badge-corr badge-corr-' . e($status) . '">' . e(correspondence_statuses()[$status] ?? $status) . '</span>';
}

function priority_badge(string $priority): string
{
    return '<span class="badge badge-priority badge-p-' . e($priority) . '">' . e(priorities()[$priority] ?? $priority) . '</span>';
}

function role_badge(string $role): string
{
    return '<span class="badge badge-role badge-role-' . e($role) . '">' . e(roles()[$role] ?? $role) . '</span>';
}

function direction_badge(string $direction): string
{
    $class = $direction === 'incoming' ? 'dir-in' : 'dir-out';
    return '<span class="badge ' . $class . '">' . e(directions()[$direction] ?? $direction) . '</span>';
}

/** Progress bar + percentage label. */
function progress_bar(int $progress): string
{
    $progress = max(0, min(100, $progress));
    return '<span class="progress" title="' . $progress . '%">'
        . '<span class="progress-track"><span class="progress-fill band-' . progress_band($progress) . '" style="width:' . $progress . '%"></span></span>'
        . '<span class="progress-num">' . $progress . '%</span></span>';
}

/** Coloured due-date chip (red when overdue, amber when near). */
function due_chip(?string $date, ?string $status = null): string
{
    if (!$date) {
        return '<span class="cell-muted">—</span>';
    }
    $days = days_left($date);
    $open = $status === null || task_is_open($status);
    $class = 'due-chip';
    if ($open && $days !== null && $days < 0) {
        $class .= ' due-over';
    } elseif ($open && $days !== null && $days <= 2) {
        $class .= ' due-soon';
    }
    return '<span class="' . $class . '">' . e(due_label($date, $status)) . '</span>';
}

// ---------------------------------------------------------------- tables

function task_table(array $rows, bool $showAssignee = true): void
{
    if (!$rows) {
        echo '<div class="empty">' . e(t('tasks.empty')) . '</div>';
        return;
    }
    ?>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e(t('tasks.col_ref')) ?></th>
          <th><?= e(t('tasks.col_task')) ?></th>
          <th><?= e(t('common.priority')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <th><?= e(t('tasks.col_progress')) ?></th>
          <?php if ($showAssignee): ?><th><?= e(t('tasks.col_assignee')) ?></th><?php endif; ?>
          <th><?= e(t('tasks.col_due')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $task): ?>
        <tr>
          <td><a class="ref-link" href="<?= u('task&id=' . (int) $task['id']) ?>"><?= e($task['ref']) ?></a></td>
          <td class="cell-subject">
            <a class="row-link" href="<?= u('task&id=' . (int) $task['id']) ?>"><?= e($task['title']) ?></a>
            <?php if (!empty($task['cat_name_ar']) || !empty($task['cat_name_en'])): ?>
              <div class="cell-muted"><?= e(bilingual($task, 'cat_name')) ?></div>
            <?php endif; ?>
          </td>
          <td><?= priority_badge((string) $task['priority']) ?></td>
          <td><?= task_status_badge((string) $task['status']) ?></td>
          <td><?= progress_bar((int) $task['progress']) ?></td>
          <?php if ($showAssignee): ?><td><?= e($task['assignee_name'] ?? t('common.unassigned')) ?></td><?php endif; ?>
          <td><?= due_chip($task['due_date'], $task['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php
}

function approval_table(array $rows): void
{
    if (!$rows) {
        echo '<div class="empty">' . e(t('approvals.empty')) . '</div>';
        return;
    }
    ?>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e(t('approvals.col_ref')) ?></th>
          <th><?= e(t('approvals.col_request')) ?></th>
          <th><?= e(t('approvals.col_type')) ?></th>
          <th><?= e(t('common.priority')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <th><?= e(t('approvals.col_requester')) ?></th>
          <th><?= e(t('approvals.col_due')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $ap): ?>
        <tr>
          <td><a class="ref-link" href="<?= u('approval&id=' . (int) $ap['id']) ?>"><?= e($ap['ref']) ?></a></td>
          <td class="cell-subject">
            <a class="row-link" href="<?= u('approval&id=' . (int) $ap['id']) ?>"><?= e($ap['title']) ?></a>
            <div class="cell-muted"><?= e(t('approvals.step_of', ['a' => (int) $ap['current_step'], 'b' => (int) $ap['steps_total']])) ?></div>
          </td>
          <td><?= e(bilingual($ap, 'type_name')) ?></td>
          <td><?= priority_badge((string) $ap['priority']) ?></td>
          <td><?= approval_status_badge((string) $ap['status']) ?></td>
          <td><?= e($ap['requester_name'] ?? '—') ?></td>
          <td><?= due_chip($ap['due_date'], $ap['status'] === 'pending' ? 'new' : 'completed') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php
}

// ---------------------------------------------------------------- layout

function layout_header(string $title, string $active = ''): void
{
    $user   = Auth::current();
    $unread = Notifications::unreadCount(Auth::id());
    $site   = setting('site_name', t('app.name'));
    $inbox  = Approvals::inboxCount($user ?? []);

    $nav = [
        ['dashboard', t('nav.dashboard'), 'dashboard'],
        ['tasks', t('nav.tasks'), 'tasks'],
        ['approvals', t('nav.approvals') . ($inbox ? ' (' . $inbox . ')' : ''), 'approve'],
        ['team', t('nav.team'), 'team'],
        ['correspondence', t('nav.correspondence'), 'mail'],
        ['meetings', t('nav.meetings'), 'calendar'],
        ['calendar', t('nav.calendar'), 'clock'],
        ['delegations', t('nav.delegations'), 'share'],
        ['doc', t('nav.doc'), 'help'],
    ];
    $adminNav = [];
    if (Auth::isAdmin()) {
        $adminNav = [
            ['admin/users', t('nav.users'), 'users'],
            ['admin/departments', t('nav.departments'), 'building'],
            ['admin/categories', t('nav.categories'), 'tags'],
            ['admin/types', t('nav.types'), 'stamp'],
            ['admin/settings', t('nav.settings'), 'sliders'],
            ['admin/audit', t('nav.audit'), 'list'],
            ['admin/reports', t('nav.reports'), 'chart'],
        ];
    }
    ?>
<!doctype html>
<html lang="<?= e(daem_current_lang()) ?>" dir="<?= e(daem_lang_dir()) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($site) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css?v=1">
<script>
(function(){try{var t=localStorage.getItem('daem-theme');
if(!t){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}
document.documentElement.setAttribute('data-theme',t);}catch(err){}})();
</script>
</head>
<body>
<div class="app">
  <div class="sidebar-overlay" id="sidebar-overlay"></div>
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= u('dashboard') ?>">
      <span class="brand-logo">◈</span>
      <span class="brand-text"><?= e($site) ?><span class="brand-sub"><?= e(t('app.tagline')) ?></span></span>
    </a>
    <nav class="nav">
      <p class="nav-label"><?= e(t('nav.workspace')) ?></p>
      <?php foreach ($nav as [$key, $label, $ic]): ?>
        <a class="nav-item <?= $active === $key ? 'active' : '' ?>" href="<?= u($key) ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
      <?php if ($adminNav): ?>
        <p class="nav-label"><?= e(t('nav.administration')) ?></p>
        <?php foreach ($adminNav as [$key, $label, $ic]): ?>
          <a class="nav-item <?= $active === $key ? 'active' : '' ?>" href="<?= u($key) ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>
    <a class="btn btn-primary sidebar-new" href="<?= u('new-task') ?>"><?= icon('plus') ?> <?= e(t('nav.new_task')) ?></a>
    <div class="sidebar-foot"><?= e(t('app.version')) ?></div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn" id="nav-toggle" aria-label="Menu"><?= icon('menu') ?></button>
      <h1 class="page-title"><?= e($title) ?></h1>
      <div class="topbar-actions">
        <?= daem_lang_switcher() ?>
        <button class="icon-btn" id="theme-toggle" aria-label="<?= e(t('nav.theme')) ?>"><?= icon('moon') ?></button>
        <a class="icon-btn bell" href="<?= u('notifications') ?>" aria-label="<?= e(t('nav.notifications')) ?>"><?= icon('bell') ?><?php if ($unread): ?><span class="bell-badge"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?></a>
        <div class="user-menu" id="user-menu">
          <button class="user-chip" id="user-chip" aria-haspopup="true">
            <span class="avatar"><?= e(initials($user['name'])) ?></span>
            <span class="user-meta"><span class="user-name"><?= e($user['name']) ?></span><span class="user-role"><?= e(roles()[$user['role']] ?? $user['role']) ?></span></span>
            <span class="chev">▾</span>
          </button>
          <div class="dropdown" id="user-dropdown" hidden>
            <a href="<?= u('profile') ?>"><?= icon('user') ?> <?= e(t('nav.profile')) ?></a>
            <a href="<?= u('logout') ?>" class="danger"><?= icon('logout') ?> <?= e(t('nav.sign_out')) ?></a>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <?php foreach (take_flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
<?php
}

function layout_footer(): void
{
    ?>
    </main>
    <footer class="footer">© <?= e(t('nav.footer', ['year' => date('Y'), 'site' => setting('site_name', t('app.name'))])) ?></footer>
  </div>
</div>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
<?php
}
