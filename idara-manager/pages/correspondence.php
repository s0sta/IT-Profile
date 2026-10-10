<?php
declare(strict_types=1);

/**
 * Idara — correspondence register: filterable, paginated list of incoming and
 * outgoing letters with status, priority and due chips.
 */

$me = Auth::current();

$f = [
    'q'         => trim((string) ($_GET['q'] ?? '')),
    'direction' => (string) ($_GET['direction'] ?? ''),
    'status'    => (string) ($_GET['status'] ?? ''),
    'priority'  => (string) ($_GET['priority'] ?? ''),
    'sort'      => (string) ($_GET['sort'] ?? 'created'),
];
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['pp'] ?? 20);
if (!in_array($perPage, [20, 50, 100], true)) {
    $perPage = 20;
}

$total = Correspondence::count($f, $me);
[$page, $pages] = paginate($total, $page, $perPage);
$rows = Correspondence::list($f, $me, $perPage, ($page - 1) * $perPage);

layout_header(t('corr.title'), 'correspondence');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('corr.title')) ?></h2>
    <a class="btn btn-primary btn-sm" href="<?= u('new-letter') ?>"><?= icon('plus') ?> <?= e(t('corr.new')) ?></a>
  </div>

  <form class="filters" method="get" action="<?= u('correspondence') ?>">
    <?php /* A GET form replaces the action query string, so the route key must ride along. */ ?>
    <input type="hidden" name="p" value="correspondence">
    <div class="filter-search">
      <?= icon('search') ?>
      <input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('tasks.search_placeholder')) ?>">
    </div>
    <select class="input input-select" name="direction" aria-label="<?= e(t('corr.col_direction')) ?>">
      <option value=""><?= e(t('corr.all_directions')) ?></option>
      <?php foreach (directions() as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $f['direction'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="input input-select" name="status" aria-label="<?= e(t('common.status')) ?>">
      <option value=""><?= e(t('tasks.all_statuses')) ?></option>
      <?php foreach (correspondence_statuses() as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="input input-select" name="priority" aria-label="<?= e(t('common.priority')) ?>">
      <option value=""><?= e(t('tasks.all_priorities')) ?></option>
      <?php foreach (priorities() as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $f['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="input input-select" name="sort" aria-label="<?= e(t('common.filter')) ?>">
      <option value="created" <?= $f['sort'] === 'created' ? 'selected' : '' ?>><?= e(t('tasks.sort_created')) ?></option>
      <option value="due" <?= $f['sort'] === 'due' ? 'selected' : '' ?>><?= e(t('tasks.sort_due')) ?></option>
    </select>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= u('correspondence') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="result-count"><?= e(t('corr.title')) ?>: <?= (int) $total ?></div>

  <?php if (!$rows): ?>
    <div class="empty"><?= e(t('corr.empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('corr.col_ref')) ?></th>
            <th><?= e(t('corr.col_subject')) ?></th>
            <th><?= e(t('corr.col_direction')) ?></th>
            <th><?= e(t('corr.col_party')) ?></th>
            <th><?= e(t('corr.col_status')) ?></th>
            <th><?= e(t('common.priority')) ?></th>
            <th><?= e(t('corr.col_due')) ?></th>
            <th><?= e(t('common.assignee')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $open = in_array((string) $r['status'], ['new', 'under_review'], true);
          ?>
            <tr>
              <td><a class="ref-link" href="<?= u('letter&id=' . (int) $r['id']) ?>"><?= e($r['ref']) ?></a></td>
              <td class="cell-subject">
                <a class="row-link" href="<?= u('letter&id=' . (int) $r['id']) ?>"><?= e($r['subject']) ?></a>
                <?php if ((string) $r['reference_no'] !== ''): ?>
                  <div class="cell-muted"><?= e(t('corr.reference_no')) ?>: <?= e($r['reference_no']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= direction_badge((string) $r['direction']) ?></td>
              <td><?= e((string) $r['party'] !== '' ? $r['party'] : '—') ?></td>
              <td><?= correspondence_status_badge((string) $r['status']) ?></td>
              <td><?= priority_badge((string) $r['priority']) ?></td>
              <td><?= due_chip($r['due_date'], $open ? 'new' : 'completed') ?></td>
              <td><?= e($r['assignee_name'] ?? t('common.unassigned')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php pagination_ui('correspondence', $page, $pages, $perPage, $total); ?>
</section>

<?php layout_footer(); ?>
