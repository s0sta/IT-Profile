<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$ct = Contracts::find($id);
if (!$ct) {
    http_response_code(404);
    exit(t('e404.title'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Auth::requireOffice();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update') {
        Contracts::update($id, [
            'site_id'         => (int) ($_POST['site_id'] ?? 0) ?: null,
            'service_id'      => (int) ($_POST['service_id'] ?? 0) ?: null,
            'frequency'       => (string) ($_POST['frequency'] ?? 'quarterly'),
            'price_per_visit' => (float) ($_POST['price_per_visit'] ?? 0),
            'start_date'      => (string) ($_POST['start_date'] ?? date('Y-m-d')),
            'end_date'        => (string) ($_POST['end_date'] ?? '') ?: null,
            'notes'           => (string) ($_POST['notes'] ?? ''),
        ]);
        flash('success', t('contracts.saved'));
    } elseif ($action === 'status') {
        Contracts::setStatus($id, (string) ($_POST['status'] ?? 'active'));
        flash('success', t('contracts.status_saved'));
    } elseif ($action === 'generate') {
        $n = Contracts::generateVisits($id);
        flash('success', t('contract.generated', ['n' => $n]));
    } elseif ($action === 'job_from_visit') {
        $jobId = Contracts::createJobFromVisit((int) ($_POST['visit_id'] ?? 0), (int) ($_POST['assigned_to'] ?? 0) ?: null);
        flash($jobId ? 'success' : 'error', $jobId ? t('contract.job_created') : t('auth.denied'));
        if ($jobId) {
            redirect('job&id=' . $jobId);
        }
    }
    redirect('contract&id=' . $id);
}

$visits      = Contracts::visits($id);
$jobs        = Jobs::forContract($id);
$technicians = Users::technicians();
$sites       = Sites::forCustomer((int) $ct['customer_id']);
$services    = Services::all(true);
$done        = count(array_filter($visits, static fn (array $v): bool => (bool) $v['job_id']));

layout_header($ct['number'], 'contracts');
?>

<div class="panel">
  <div class="ticket-head">
    <div class="ticket-title">
      <span class="ref-pill"><?= e($ct['number']) ?></span>
      <h2 class="ticket-subject"><?= e($ct['customer_name']) ?></h2>
      <div class="ticket-badges">
        <span class="badge badge-con-<?= e($ct['status']) ?>"><?= e(contract_statuses()[$ct['status']] ?? $ct['status']) ?></span>
        <span class="badge badge-cat"><?= e(contract_frequencies()[$ct['frequency']] ?? $ct['frequency']) ?></span>
        <span class="badge badge-cat"><?= e(t('contract.visits_planned', ['done' => $done, 'total' => count($visits)])) ?></span>
      </div>
    </div>
    <div class="ticket-actions">
      <form method="post" action="<?= u('contract&id=' . $id) ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="status">
        <select name="status" class="input input-select auto-submit">
          <?php foreach (contract_statuses() as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $ct['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <form method="post" action="<?= u('contract&id=' . $id) ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="generate">
        <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('contract.generate')) ?></button>
      </form>
    </div>
  </div>

  <div class="meta-grid">
    <div class="meta-item"><span class="meta-label"><?= e(t('contract.site')) ?></span><span class="meta-value"><?= e($ct['site_name'] ?? '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('contract.service')) ?></span><span class="meta-value"><?= e($ct['service_name'] ?? '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('contract.price_per_visit', ['currency' => (string) setting('currency', 'AED')])) ?></span><span class="meta-value"><?= e(money((float) $ct['price_per_visit'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('contract.start')) ?></span><span class="meta-value"><?= e(fmt_date($ct['start_date'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('contract.end')) ?></span><span class="meta-value"><?= e(fmt_date($ct['end_date'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('contract.monthly_value', ['currency' => (string) setting('currency', 'AED')])) ?></span><span class="meta-value"><?= e(money(((float) $ct['price_per_visit']) / (['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'annual' => 12][$ct['frequency']] ?? 3))) ?></span></div>
  </div>
  <?php if ($ct['notes']): ?><div class="asset-notes"><?= render_body($ct['notes']) ?></div><?php endif; ?>
</div>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('contract.schedule')) ?></h2>
    <span class="result-count"><?= e(t('contract.visits_planned', ['done' => $done, 'total' => count($visits)])) ?></span>
  </div>
  <?php if (!$visits): ?>
    <div class="empty"><?= e(t('contract.no_due_visits')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('common.date')) ?></th><th><?= e(t('inv.job')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($visits as $visit): ?>
            <tr>
              <td><?= due_chip($visit['due_date']) ?></td>
              <td>
                <?php if ($visit['job_id']): ?>
                  <a class="ref-link" href="<?= u('job&id=' . (int) $visit['job_id']) ?>"><?= e($visit['job_number']) ?></a>
                  <?= job_status_badge((string) $visit['job_status']) ?>
                <?php else: ?>
                  <span class="cell-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= $visit['job_id'] ? '<span class="badge badge-active">' . e(t('jstatus.done')) . '</span>' : '<span class="badge badge-cat">' . e(t('dash.due_visits', ['days' => days_until($visit['due_date']) ?? 0])) . '</span>' ?></td>
              <td class="cell-actions">
                <?php if (!$visit['job_id']): ?>
                  <form method="post" action="<?= u('contract&id=' . $id) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="job_from_visit">
                    <input type="hidden" name="visit_id" value="<?= (int) $visit['id'] ?>">
                    <select class="input input-select input-sm" name="assigned_to">
                      <option value=""><?= e(t('job.unassigned')) ?></option>
                      <?php foreach ($technicians as $tech): ?><option value="<?= (int) $tech['id'] ?>"><?= e($tech['name']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-sm" type="submit"><?= e(t('contract.create_job')) ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('contracts.title')) ?></h2>
  <form method="post" action="<?= u('contract&id=' . $id) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <div class="form-grid">
      <div class="form-col">
        <label class="field-label"><?= e(t('contract.site')) ?></label>
        <select class="input input-select" name="site_id">
          <option value=""><?= e(t('job.no_site')) ?></option>
          <?php foreach ($sites as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $ct['site_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
        <label class="field-label"><?= e(t('contract.service')) ?></label>
        <select class="input input-select" name="service_id">
          <option value=""><?= e(t('job.no_service')) ?></option>
          <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $ct['service_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
        <label class="field-label"><?= e(t('contract.frequency')) ?></label>
        <select class="input input-select" name="frequency">
          <?php foreach (contract_frequencies() as $k => $label): ?><option value="<?= e($k) ?>" <?= $ct['frequency'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-col">
        <label class="field-label"><?= e(t('contract.price_per_visit', ['currency' => (string) setting('currency', 'AED')])) ?></label>
        <input class="input" type="number" step="0.01" min="0" name="price_per_visit" value="<?= e((string) (float) $ct['price_per_visit']) ?>">
        <div class="form-row">
          <div><label class="field-label"><?= e(t('contract.start')) ?></label><input class="input" type="date" name="start_date" value="<?= e((string) $ct['start_date']) ?>"></div>
          <div><label class="field-label"><?= e(t('contract.end')) ?></label><input class="input" type="date" name="end_date" value="<?= e((string) $ct['end_date']) ?>"></div>
        </div>
        <label class="field-label"><?= e(t('contract.notes')) ?></label>
        <textarea class="input" name="notes" rows="3"><?= e((string) $ct['notes']) ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
    </div>
  </form>
</section>

<?php if ($jobs): ?>
<section class="panel">
  <h2 class="panel-title"><?= e(t('customer.tab_jobs')) ?></h2>
  <?php job_table($jobs, false); ?>
</section>
<?php endif; ?>

<?php layout_footer(); ?>
