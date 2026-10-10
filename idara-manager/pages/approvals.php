<?php
declare(strict_types=1);

/**
 * Idara — قائمة الاعتمادات (route 'approvals').
 * Filters (GET): q, status, type, priority, inbox, mine, sort, page.
 * Tabs are plain links: ?inbox=1 · ?mine=1 · (none = all).
 */

$me = Auth::current();

$f = [
    'q'        => trim((string) ($_GET['q'] ?? '')),
    'status'   => (string) ($_GET['status'] ?? ''),
    'type'     => (string) ($_GET['type'] ?? ''),
    'priority' => (string) ($_GET['priority'] ?? ''),
    'inbox'    => !empty($_GET['inbox']) ? 1 : 0,
    'mine'     => !empty($_GET['mine']) ? 1 : 0,
    'sort'     => (string) ($_GET['sort'] ?? 'updated'),
];

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['pp'] ?? 20);
if (!in_array($perPage, [20, 50, 100], true)) {
    $perPage = 20;
}

$total = Approvals::count($f, $me);
[$page, $pages] = paginate($total, $page, $perPage);
$rows = Approvals::list($f, $me, $perPage, ($page - 1) * $perPage);

$types      = ApprovalTypes::all(true);
$inboxCount = Approvals::inboxCount($me);
$allTab     = !$f['inbox'] && !$f['mine'];

layout_header(t('approvals.title'), 'approvals');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('approvals.title')) ?></h2>
    <a class="btn btn-primary" href="<?= u('new-approval') ?>"><?= icon('plus') ?> <?= e(t('approvals.new')) ?></a>
  </div>

  <div class="quick-actions">
    <a class="btn <?= $f['inbox'] ? 'btn-primary' : 'btn-ghost' ?>" href="<?= u('approvals&inbox=1') ?>"><?= e(t('approvals.inbox')) ?> (<?= (int) $inboxCount ?>)</a>
    <a class="btn <?= $f['mine'] ? 'btn-primary' : 'btn-ghost' ?>" href="<?= u('approvals&mine=1') ?>"><?= e(t('approvals.my_requests')) ?></a>
    <a class="btn <?= $allTab ? 'btn-primary' : 'btn-ghost' ?>" href="<?= u('approvals') ?>"><?= e(t('approvals.all')) ?></a>
  </div>

  <form class="filters" method="get" action="<?= u('approvals') ?>">
    <input type="hidden" name="p" value="approvals">
    <?php if ($f['inbox']): ?><input type="hidden" name="inbox" value="1"><?php endif; ?>
    <?php if ($f['mine']): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
    <div class="filter-search">
      <?= icon('search') ?>
      <input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('common.search')) ?>">
    </div>
    <select name="status" class="input input-select">
      <option value=""><?= e(t('common.all')) ?></option>
      <?php foreach (approval_statuses() as $k => $label): ?>
        <option value="<?= e((string) $k) ?>" <?= $f['status'] === (string) $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type" class="input input-select">
      <option value=""><?= e(t('common.all')) ?></option>
      <?php foreach ($types as $ty): ?>
        <option value="<?= (int) $ty['id'] ?>" <?= $f['type'] === (string) $ty['id'] ? 'selected' : '' ?>><?= e(ApprovalTypes::label($ty)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="priority" class="input input-select">
      <option value=""><?= e(t('common.all')) ?></option>
      <?php foreach (priorities() as $k => $label): ?>
        <option value="<?= e((string) $k) ?>" <?= $f['priority'] === (string) $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort" class="input input-select">
      <option value="updated" <?= $f['sort'] === 'updated' ? 'selected' : '' ?>><?= e(t('tasks.sort_updated')) ?></option>
      <option value="created" <?= $f['sort'] === 'created' ? 'selected' : '' ?>><?= e(t('tasks.sort_created')) ?></option>
      <option value="due" <?= $f['sort'] === 'due' ? 'selected' : '' ?>><?= e(t('tasks.sort_due')) ?></option>
      <option value="priority" <?= $f['sort'] === 'priority' ? 'selected' : '' ?>><?= e(t('tasks.sort_priority')) ?></option>
    </select>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= u('approvals') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <?php approval_table($rows); ?>

  <?php pagination_ui('approvals', $page, $pages, $perPage, $total); ?>
</section>

<?php layout_footer(); ?>
