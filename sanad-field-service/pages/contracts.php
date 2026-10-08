<?php
declare(strict_types=1);

$q = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Auth::requireOffice();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $start      = (string) ($_POST['start_date'] ?? '');
        if (!$customerId) {
            flash('error', t('job.err_customer'));
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            flash('error', t('contract.err_start'));
        } else {
            $id = Contracts::create([
                'customer_id'     => $customerId,
                'site_id'         => (int) ($_POST['site_id'] ?? 0) ?: null,
                'service_id'      => (int) ($_POST['service_id'] ?? 0) ?: null,
                'frequency'       => (string) ($_POST['frequency'] ?? 'quarterly'),
                'price_per_visit' => (float) ($_POST['price_per_visit'] ?? 0),
                'start_date'      => $start,
                'end_date'        => (string) ($_POST['end_date'] ?? '') ?: null,
                'notes'           => (string) ($_POST['notes'] ?? ''),
            ]);
            flash('success', t('contracts.created'));
            redirect('contract&id=' . $id);
        }
    } elseif ($action === 'job_from_visit') {
        $jobId = Contracts::createJobFromVisit((int) ($_POST['visit_id'] ?? 0), (int) ($_POST['assigned_to'] ?? 0) ?: null);
        flash($jobId ? 'success' : 'error', $jobId ? t('contract.job_created') : t('auth.denied'));
        redirect($jobId ? ('job&id=' . $jobId) : 'contracts');
    }
    redirect('contracts');
}

$contracts = Contracts::all(false, $q);
$due       = Contracts::dueVisits((int) setting('alert_days', '30'));
$technicians = Users::technicians();
$customers = Customers::all(true);
$sites     = Sites::all();
$services  = Services::all(true);

layout_header(t('contracts.title'), 'contracts');
?>

<?php if ($due): ?>
<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('contract.due_visits')) ?></h2>
    <span class="result-count"><?= count($due) ?></span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th><?= e(t('contract.number')) ?></th><th><?= e(t('contract.customer')) ?></th><th><?= e(t('contract.site')) ?></th><th><?= e(t('contract.next_due')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($due as $visit): ?>
          <tr>
            <td><a class="ref-link" href="<?= u('contract&id=' . (int) $visit['contract_id']) ?>"><?= e($visit['contract_number']) ?></a></td>
            <td><?= e($visit['customer_name']) ?></td>
            <td><?= e($visit['site_name'] ?? '—') ?></td>
            <td><?= due_chip($visit['due_date']) ?></td>
            <td class="cell-actions">
              <form method="post" action="<?= u('contracts') ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="job_from_visit">
                <input type="hidden" name="visit_id" value="<?= (int) $visit['id'] ?>">
                <select class="input input-select input-sm" name="assigned_to">
                  <option value=""><?= e(t('job.unassigned')) ?></option>
                  <?php foreach ($technicians as $tech): ?><option value="<?= (int) $tech['id'] ?>"><?= e($tech['name']) ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" type="submit"><?= e(t('contract.create_job')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<section class="panel">
  <form class="filters" method="get" action="<?= u('contracts') ?>">
    <div class="filter-search"><?= icon('search') ?><input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('contracts.search_placeholder')) ?>"></div>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= u('contracts') ?>"><?= e(t('common.reset')) ?></a>
  </form>

  <div class="panel-head">
    <span class="result-count"><?= e(t('contract.active_count')) ?>: <?= Contracts::activeCount() ?> · <?= e(t('contract.monthly_value', ['currency' => (string) setting('currency', 'AED')])) ?>: <?= e(money(Contracts::monthlyRecurring())) ?></span>
  </div>

  <?php if (!$contracts): ?>
    <div class="empty"><?= e(t('contracts.empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('contract.number')) ?></th>
            <th><?= e(t('contract.customer')) ?></th>
            <th><?= e(t('contract.service')) ?></th>
            <th><?= e(t('contract.frequency')) ?></th>
            <th><?= e(t('contract.price_per_visit')) ?></th>
            <th><?= e(t('contract.visits')) ?></th>
            <th><?= e(t('contract.next_due')) ?></th>
            <th><?= e(t('common.status')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contracts as $ct): ?>
            <tr>
              <td><a class="ref-link" href="<?= u('contract&id=' . (int) $ct['id']) ?>"><?= e($ct['number']) ?></a></td>
              <td><?= e($ct['customer_name'] ?? '—') ?>
                <?php if ($ct['site_name']): ?><div class="cell-muted"><?= e($ct['site_name']) ?></div><?php endif; ?></td>
              <td><?= e($ct['service_name'] ?? '—') ?></td>
              <td><?= e(contract_frequencies()[$ct['frequency']] ?? $ct['frequency']) ?></td>
              <td><?= e(money((float) $ct['price_per_visit'])) ?></td>
              <td><?= (int) $ct['visits_done'] ?>/<?= (int) $ct['visits_generated'] ?></td>
              <td><?= due_chip($ct['next_due'] ?? null) ?></td>
              <td><span class="badge badge-con-<?= e($ct['status']) ?>"><?= e(contract_statuses()[$ct['status']] ?? $ct['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('contracts.add')) ?></h2>
  <form method="post" action="<?= u('contracts') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="form-col">
        <label class="field-label"><?= e(t('contract.customer')) ?> *</label>
        <select class="input input-select" name="customer_id" required data-customer-select>
          <option value=""><?= e(t('common.select')) ?></option>
          <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <label class="field-label"><?= e(t('contract.site')) ?></label>
        <select class="input input-select" name="site_id" data-site-select>
          <option value=""><?= e(t('job.no_site')) ?></option>
          <?php foreach ($sites as $s): ?><option value="<?= (int) $s['id'] ?>" data-customer="<?= (int) $s['customer_id'] ?>"><?= e($s['customer_name'] . ' — ' . $s['name']) ?></option><?php endforeach; ?>
        </select>
        <label class="field-label"><?= e(t('contract.service')) ?></label>
        <select class="input input-select" name="service_id">
          <option value=""><?= e(t('job.no_service')) ?></option>
          <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> — <?= e(money((float) $s['price'])) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-col">
        <label class="field-label"><?= e(t('contract.frequency')) ?></label>
        <select class="input input-select" name="frequency">
          <?php foreach (contract_frequencies() as $k => $label): ?><option value="<?= e($k) ?>" <?= $k === 'quarterly' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <label class="field-label"><?= e(t('contract.price_per_visit', ['currency' => (string) setting('currency', 'AED')])) ?></label>
        <input class="input" type="number" step="0.01" min="0" name="price_per_visit" value="0">
        <div class="form-row">
          <div><label class="field-label"><?= e(t('contract.start')) ?> *</label><input class="input" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required></div>
          <div><label class="field-label"><?= e(t('contract.end')) ?></label><input class="input" type="date" name="end_date" value="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>"></div>
        </div>
        <label class="field-label"><?= e(t('contract.notes')) ?></label>
        <textarea class="input" name="notes" rows="3"></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= e(t('common.create')) ?></button>
    </div>
  </form>
</section>

<script>
(function () {
  var cust = document.querySelector('[data-customer-select]');
  var site = document.querySelector('[data-site-select]');
  if (!cust || !site) { return; }
  var all = Array.prototype.slice.call(site.options);
  cust.addEventListener('change', function () {
    var id = cust.value;
    site.innerHTML = '';
    all.forEach(function (opt) {
      if (!id || !opt.dataset.customer || opt.dataset.customer === id || opt.value === '') { site.appendChild(opt.cloneNode(true)); }
    });
  });
})();
</script>

<?php layout_footer(); ?>
