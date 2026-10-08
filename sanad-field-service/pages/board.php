<?php
declare(strict_types=1);

$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Auth::requireOffice();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'assign') {
        Jobs::assign((int) ($_POST['job_id'] ?? 0), (int) ($_POST['assigned_to'] ?? 0) ?: null);
        flash('success', t('job.assigned'));
    } elseif ($action === 'status') {
        Jobs::setStatus((int) ($_POST['job_id'] ?? 0), (string) ($_POST['status'] ?? ''));
        flash('success', t('job.status_saved'));
    } elseif ($action === 'schedule') {
        Jobs::update((int) ($_POST['job_id'] ?? 0), [
            'customer_id'    => (int) ($_POST['customer_id'] ?? 0),
            'site_id'        => (int) ($_POST['site_id'] ?? 0),
            'service_id'     => (int) ($_POST['service_id'] ?? 0),
            'priority'       => (string) ($_POST['priority'] ?? 'normal'),
            'assigned_to'    => (int) ($_POST['assigned_to'] ?? 0),
            'scheduled_date' => (string) ($_POST['scheduled_date'] ?? ''),
            'window_start'   => (string) ($_POST['window_start'] ?? ''),
            'window_end'     => (string) ($_POST['window_end'] ?? ''),
            'title'          => (string) ($_POST['title'] ?? ''),
            'description'    => (string) ($_POST['description'] ?? ''),
            'internal_notes' => (string) ($_POST['internal_notes'] ?? ''),
        ]);
        flash('success', t('job.saved'));
    }
    redirect('board&date=' . urlencode((string) ($_POST['return_date'] ?? $date)));
}

$board      = Jobs::board($date);
$technicians = Users::technicians();
$unscheduled = Jobs::list(['technician' => 'none', 'sort' => 'priority'], 50, 0);
$unscheduled = array_values(array_filter($unscheduled, static fn (array $j): bool => is_open_job($j['status'])));
$total = 0;
foreach ($board as $col) {
    $total += count($col['jobs']);
}

layout_header(t('board.title', ['date' => $date]), 'board');
?>

<section class="panel board-toolbar">
  <div class="board-nav">
    <a class="btn btn-ghost btn-sm" href="<?= u('board&date=' . urlencode(date('Y-m-d', strtotime($date . ' -1 day')))) ?>">← <?= e(date('D j M', strtotime($date . ' -1 day'))) ?></a>
    <form method="get" action="<?= u('board') ?>" class="inline-form">
      <input type="hidden" name="p" value="board">
      <input class="input" type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
    </form>
    <a class="btn btn-ghost btn-sm" href="<?= u('board&date=' . urlencode(date('Y-m-d', strtotime($date . ' +1 day')))) ?>"><?= e(date('D j M', strtotime($date . ' +1 day'))) ?> →</a>
    <a class="btn btn-ghost btn-sm" href="<?= u('board') ?>"><?= e(t('board.today')) ?></a>
    <span class="result-count"><?= e(t('board.count', ['n' => $total])) ?></span>
    <?php if (Auth::isOffice()): ?>
      <a class="btn btn-primary btn-sm" href="<?= u('job_new&date=' . urlencode($date)) ?>"><?= icon('plus') ?> <?= e(t('nav.new_job')) ?></a>
    <?php endif; ?>
  </div>
</section>

<section class="board">
  <?php foreach ($technicians as $tech): $key = (string) $tech['id']; $col = $board[$key] ?? null; ?>
    <div class="board-col">
      <div class="board-col-head" <?= $tech['colour'] ? 'style="border-top:3px solid ' . e($tech['colour']) . '"' : '' ?>>
        <span class="avatar"><?= e(initials($tech['name'])) ?></span>
        <strong><?= e($tech['name']) ?></strong>
        <span class="badge badge-cat"><?= $col ? count($col['jobs']) : 0 ?></span>
      </div>
      <?php if (!$col): ?>
        <div class="board-empty"><?= e(t('board.free')) ?></div>
      <?php else: ?>
        <?php foreach ($col['jobs'] as $job): ?>
          <div class="board-card board-card-<?= e($job['status']) ?>">
            <div class="board-card-top">
              <a class="ref-link" href="<?= u('job&id=' . (int) $job['id']) ?>"><?= e($job['number']) ?></a>
              <?= priority_badge($job['priority']) ?>
            </div>
            <div class="board-card-title"><?= e($job['title']) ?></div>
            <div class="cell-muted"><?= e($job['customer_name'] ?? '—') ?><?= $job['site_name'] ? ' · ' . e($job['site_name']) : '' ?></div>
            <div class="board-card-foot">
              <span class="cell-muted"><?= e(fmt_time($job['window_start'])) ?><?= $job['window_end'] ? '–' . e(fmt_time($job['window_end'])) : '' ?></span>
              <?= job_status_badge($job['status']) ?>
            </div>
            <?php if (Auth::isOffice()): ?>
              <form method="post" action="<?= u('board') ?>" class="inline-form board-actions">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                <input type="hidden" name="return_date" value="<?= e($date) ?>">
                <select name="status" class="input input-select auto-submit">
                  <?php foreach (job_statuses() as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $job['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="board-col">
    <div class="board-col-head board-col-none">
      <span class="avatar">?</span>
      <strong><?= e(t('job.unassigned')) ?></strong>
      <span class="badge badge-cat"><?= count($unscheduled) ?></span>
    </div>
    <?php if (!$unscheduled): ?>
      <div class="board-empty"><?= e(t('board.free')) ?></div>
    <?php else: ?>
      <?php foreach (array_slice($unscheduled, 0, 20) as $job): ?>
        <div class="board-card board-card-unscheduled">
          <div class="board-card-top">
            <a class="ref-link" href="<?= u('job&id=' . (int) $job['id']) ?>"><?= e($job['number']) ?></a>
            <?= priority_badge($job['priority']) ?>
          </div>
          <div class="board-card-title"><?= e($job['title']) ?></div>
          <div class="cell-muted"><?= e($job['customer_name'] ?? '—') ?></div>
          <?php if (Auth::isOffice()): ?>
            <form method="post" action="<?= u('board') ?>" class="inline-form board-actions">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="assign">
              <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
              <input type="hidden" name="return_date" value="<?= e($date) ?>">
              <select name="assigned_to" class="input input-select auto-submit">
                <option value=""><?= e(t('board.assign_to')) ?>…</option>
                <?php foreach ($technicians as $tech): ?>
                  <option value="<?= (int) $tech['id'] ?>"><?= e($tech['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php layout_footer(); ?>
