<?php
declare(strict_types=1);

/**
 * Shared page layout: sidebar navigation, topbar (language switcher, theme
 * toggle, notifications, user menu), flash messages and footer.
 */

function icon(string $name): string
{
    static $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'board'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="10" y="4" width="5" height="11" rx="1.5"/><rect x="17" y="4" width="4" height="7" rx="1.5"/></svg>',
        'job'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 4h6a2 2 0 0 1 2 2v1H7V6a2 2 0 0 1 2-2z"/><rect x="4" y="7" width="16" height="13" rx="2"/><path d="M8 12h8M8 16h5"/></svg>',
        'people'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.5c3 .3 5.5 2.5 5.5 5.5"/></svg>',
        'contract'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h4"/></svg>',
        'part'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg>',
        'invoice'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>',
        'chart'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
        'users'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.6 3.1-6.5 7-6.5s7 2.9 7 6.5"/></svg>',
        'tools'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 4a5 5 0 0 0-4.6 7L4 17.4V20h2.6l6.4-6.4A5 5 0 1 0 15 4z"/><circle cx="16.5" cy="7.5" r="1"/></svg>',
        'sliders'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h13M20 18h1"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
        'list'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/></svg>',
        'help'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.4 2.3c-.6.3-.9.8-.9 1.4v.6"/><circle cx="12" cy="17" r="1"/></svg>',
        'plus'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>',
        'bell'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>',
        'search'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>',
        'logout'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>',
        'user'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>',
        'moon'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>',
        'sun'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
        'menu'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function job_status_badge(string $status): string
{
    return '<span class="badge badge-job badge-job-' . e($status) . '">' . e(job_statuses()[$status] ?? $status) . '</span>';
}

function priority_badge(string $priority): string
{
    return '<span class="badge badge-pri badge-pri-' . e($priority) . '">' . e(job_priorities()[$priority] ?? $priority) . '</span>';
}

function invoice_status_badge(string $status): string
{
    return '<span class="badge badge-inv badge-inv-' . e($status) . '">' . e(invoice_statuses()[$status] ?? $status) . '</span>';
}

function role_badge(string $role): string
{
    return '<span class="badge badge-role badge-role-' . e($role) . '">' . e(roles()[$role] ?? $role) . '</span>';
}

/** Coloured chip with the technician name (dispatch board). */
function tech_chip(?string $name, ?string $colour = null): string
{
    if (!$name) {
        return '<span class="cell-muted">' . e(t('job.unassigned')) . '</span>';
    }
    $style = $colour ? ' style="--tech:' . e($colour) . '"' : '';
    return '<span class="tech-chip"' . $style . '>' . e($name) . '</span>';
}

/** Date + colour: late (red) · today (amber) · soon. */
function due_chip(?string $date): string
{
    $state = due_state($date);
    if ($state === null) {
        return '<span class="cell-muted">—</span>';
    }
    $label = fmt_date($date);
    $map = [
        'late'  => 'chip-late',
        'today' => 'chip-today',
        'soon'  => 'chip-soon',
        'ok'    => 'chip-ok',
    ];
    return '<span class="chip ' . $map[$state] . '">' . e($label) . '</span>';
}

/** Shared job table. */
function job_table(array $rows, bool $showCustomer = true): void
{
    if (!$rows) {
        echo '<div class="empty">' . e(t('jobs.empty')) . '</div>';
        return;
    }
    ?>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e(t('jobs.col_number')) ?></th>
          <th><?= e(t('jobs.col_title')) ?></th>
          <?php if ($showCustomer): ?><th><?= e(t('jobs.col_customer')) ?></th><?php endif; ?>
          <th><?= e(t('jobs.col_when')) ?></th>
          <th><?= e(t('jobs.col_technician')) ?></th>
          <th><?= e(t('common.priority')) ?></th>
          <th><?= e(t('common.status')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $j): ?>
        <tr>
          <td><a class="ref-link" href="<?= u('job&id=' . (int) $j['id']) ?>"><?= e($j['number']) ?></a>
            <?php if (($j['type'] ?? '') === 'contract'): ?><div class="cell-muted"><?= e(t('jtype.contract')) ?></div><?php endif; ?></td>
          <td class="cell-subject"><?= e($j['title']) ?>
            <?php if (!empty($j['service_name'])): ?><div class="cell-muted"><?= e($j['service_name']) ?></div><?php endif; ?></td>
          <?php if ($showCustomer): ?>
            <td><?= e($j['customer_name'] ?? '—') ?>
              <?php if (!empty($j['site_name'])): ?><div class="cell-muted"><?= e($j['site_name']) ?></div><?php endif; ?></td>
          <?php endif; ?>
          <td><?= due_chip($j['scheduled_date']) ?>
            <?php if (!empty($j['window_start'])): ?><div class="cell-muted"><?= e(fmt_time($j['window_start'])) ?>–<?= e(fmt_time($j['window_end'])) ?></div><?php endif; ?></td>
          <td><?= tech_chip($j['technician_name'] ?? null, $j['technician_colour'] ?? null) ?></td>
          <td><?= priority_badge($j['priority']) ?></td>
          <td><?= job_status_badge($j['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php
}

function layout_header(string $title, string $active = ''): void
{
    $user   = Auth::current();
    $unread = Notifications::unreadCount(Auth::id());
    $site   = setting('site_name', 'Sanad');

    $nav = [['dashboard', t('nav.dashboard'), 'dashboard']];
    if (!Auth::isTechnician() && !Auth::isAccountant()) {
        $nav[] = ['board', t('nav.board'), 'board'];
    }
    $nav[] = ['jobs', Auth::isTechnician() ? t('nav.my_jobs') : t('nav.jobs'), 'job'];
    if (!Auth::isAccountant()) {
        $nav[] = ['customers', t('nav.customers'), 'people'];
        $nav[] = ['contracts', t('nav.contracts'), 'contract'];
        $nav[] = ['parts', t('nav.parts'), 'part'];
    }
    if (Auth::isFinance()) {
        $nav[] = ['invoices', t('nav.invoices'), 'invoice'];
        $nav[] = ['reports', t('nav.reports'), 'chart'];
    }
    $nav[] = ['doc', t('nav.doc'), 'help'];

    $adminNav = [];
    if (Auth::isAdmin()) {
        $adminNav = [
            ['admin/users', t('nav.users'), 'users'],
            ['admin/services', t('nav.services'), 'tools'],
            ['admin/settings', t('nav.settings'), 'sliders'],
            ['admin/audit', t('nav.audit'), 'list'],
        ];
    }
    ?>
<!doctype html>
<html lang="<?= e(sanad_current_lang()) ?>" dir="<?= e(sanad_lang_dir()) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($site) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css?v=4">
<script>
(function(){try{var t=localStorage.getItem('sanad-theme');
if(!t){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}
document.documentElement.setAttribute('data-theme',t);}catch(err){}})();
</script>
</head>
<body>
<div class="app">
  <div class="sidebar-overlay" id="sidebar-overlay"></div>
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= u('dashboard') ?>">
      <span class="brand-logo">◆</span>
      <span class="brand-text"><?= e($site) ?><span class="brand-sub"><?= e(t('brand.tagline')) ?></span></span>
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
    <?php if (Auth::isOffice()): ?>
      <a class="btn btn-primary sidebar-new" href="<?= u('job_new') ?>"><?= icon('plus') ?> <?= e(t('nav.new_job')) ?></a>
    <?php endif; ?>
    <div class="sidebar-foot">v1.0 · by s0sta</div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn" id="nav-toggle" aria-label="Menu"><?= icon('menu') ?></button>
      <h1 class="page-title"><?= e($title) ?></h1>
      <div class="topbar-actions">
        <?= sanad_lang_switcher() ?>
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
    <footer class="footer">© <?= e(t('nav.footer', ['year' => date('Y'), 'site' => setting('site_name', 'Sanad')])) ?></footer>
  </div>
</div>
<script src="assets/js/app.js?v=4"></script>
</body>
</html>
<?php
}
