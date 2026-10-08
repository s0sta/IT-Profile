<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$c  = Customers::find($id);
if (!$c) {
    http_response_code(404);
    exit(t('e404.title'));
}

// Service address (site) management — office staff only.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Auth::requireOffice();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'site_add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', t('job.err_title'));
        } else {
            Sites::create([
                'customer_id'   => $id,
                'name'          => $name,
                'address'       => (string) ($_POST['address'] ?? ''),
                'city'          => (string) ($_POST['city'] ?? ''),
                'contact_name'  => (string) ($_POST['contact_name'] ?? ''),
                'contact_phone' => (string) ($_POST['contact_phone'] ?? ''),
                'access_notes'  => (string) ($_POST['access_notes'] ?? ''),
            ]);
            flash('success', t('customer.site_created'));
        }
    } elseif ($action === 'site_update') {
        Sites::update((int) ($_POST['site_id'] ?? 0), [
            'name'          => (string) ($_POST['name'] ?? ''),
            'address'       => (string) ($_POST['address'] ?? ''),
            'city'          => (string) ($_POST['city'] ?? ''),
            'contact_name'  => (string) ($_POST['contact_name'] ?? ''),
            'contact_phone' => (string) ($_POST['contact_phone'] ?? ''),
            'access_notes'  => (string) ($_POST['access_notes'] ?? ''),
        ]);
        flash('success', t('customer.site_saved'));
    } elseif ($action === 'toggle') {
        Customers::toggleActive($id);
        flash('success', t('customers.toggled'));
    }
    redirect('customer&id=' . $id);
}

$sites    = Sites::forCustomer($id);
$jobs     = Jobs::forCustomer($id, 15);
$contracts = Contracts::all(false, (string) $c['name']);
$contracts = array_values(array_filter($contracts, static fn (array $x): bool => (int) $x['customer_id'] === $id));
$invoices = Invoices::all(['customer' => $id], 15, 0);
$balance  = Customers::outstanding($id);

layout_header($c['name'], 'customers');
?>

<div class="panel">
  <div class="ticket-head">
    <div class="ticket-title">
      <span class="ref-pill"><?= e($c['code'] ?? '') ?></span>
      <h2 class="ticket-subject"><?= e($c['name']) ?></h2>
      <div class="ticket-badges">
        <span class="badge badge-cat"><?= e(t('ctype.' . ($c['type'] ?? 'company'))) ?></span>
        <?= $c['active'] ? '<span class="badge badge-active">' . e(t('common.active')) . '</span>' : '<span class="badge badge-inactive">' . e(t('common.inactive')) . '</span>' ?>
        <?php if ($balance > 0): ?><span class="badge badge-pri-high"><?= e(t('customers.open_balance')) ?>: <?= e(money($balance)) ?></span><?php endif; ?>
      </div>
    </div>
    <?php if (Auth::isOffice()): ?>
      <div class="ticket-actions">
        <a class="btn btn-ghost btn-sm" href="<?= u('customer_edit&id=' . $id) ?>"><?= e(t('common.edit')) ?></a>
        <a class="btn btn-primary btn-sm" href="<?= u('job_new') ?>"><?= icon('plus') ?> <?= e(t('nav.new_job')) ?></a>
        <form method="post" action="<?= u('customer&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
          <button class="btn btn-ghost btn-sm" type="submit"><?= e($c['active'] ? t('common.deactivate') : t('common.activate')) ?></button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div class="meta-grid">
    <div class="meta-item"><span class="meta-label"><?= e(t('common.phone')) ?></span><span class="meta-value"><?= e($c['phone'] ?: '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.email')) ?></span><span class="meta-value"><?= e($c['email'] ?: '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.city')) ?></span><span class="meta-value"><?= e($c['city'] ?: '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.address')) ?></span><span class="meta-value"><?= e($c['address'] ?: '—') ?></span></div>
    <?php if ($c['tax_no']): ?><div class="meta-item"><span class="meta-label"><?= e(t('customer.tax_no')) ?></span><span class="meta-value"><?= e($c['tax_no']) ?></span></div><?php endif; ?>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.created')) ?></span><span class="meta-value"><?= e(fmt_date($c['created_at'])) ?></span></div>
  </div>

  <?php if ($c['notes']): ?><div class="asset-notes"><?= render_body($c['notes']) ?></div><?php endif; ?>
</div>

<section class="grid-2">
  <!-- service addresses -->
  <div class="panel">
    <h2 class="panel-title"><?= e(t('customer.sites')) ?></h2>
    <?php if (!$sites): ?><div class="empty"><?= e(t('customer.no_sites')) ?></div><?php else: ?>
      <ul class="mini-list">
        <?php foreach ($sites as $s): ?>
          <li>
            <span><?= e($s['name']) ?></span>
            <span class="cell-muted"><?= e($s['address'] ?: '') ?><?= $s['city'] ? ', ' . e($s['city']) : '' ?></span>
            <span class="mini-right">
              <?php if ($s['contact_phone']): ?><span class="cell-muted"><?= e($s['contact_phone']) ?></span><?php endif; ?>
            </span>
          </li>
          <?php if ($s['access_notes']): ?>
            <li class="mini-sub"><span class="cell-muted">🔑 <?= e($s['access_notes']) ?></span></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (Auth::isOffice()): ?>
      <form method="post" action="<?= u('customer&id=' . $id) ?>" class="stack-form" style="margin-top:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="site_add">
        <label class="field-label"><?= e(t('customer.add_site')) ?></label>
        <input class="input" name="name" required placeholder="<?= e(t('customer.site_name_ph')) ?>">
        <div class="form-row">
          <div><label class="field-label"><?= e(t('common.address')) ?></label><input class="input" name="address"></div>
          <div><label class="field-label"><?= e(t('common.city')) ?></label><input class="input" name="city"></div>
        </div>
        <div class="form-row">
          <div><label class="field-label"><?= e(t('customer.contact')) ?></label><input class="input" name="contact_name"></div>
          <div><label class="field-label"><?= e(t('common.phone')) ?></label><input class="input" name="contact_phone"></div>
        </div>
        <label class="field-label"><?= e(t('customer.access_notes')) ?></label>
        <input class="input" name="access_notes" placeholder="<?= e(t('customer.access_ph')) ?>">
        <button class="btn" type="submit"><?= e(t('common.add')) ?></button>
      </form>
    <?php endif; ?>
  </div>

  <!-- contracts + invoices -->
  <div class="panel">
    <h2 class="panel-title"><?= e(t('contracts.title')) ?></h2>
    <?php if (!$contracts): ?>
      <div class="empty"><?= e(t('contracts.empty')) ?></div>
    <?php else: ?>
      <ul class="mini-list">
        <?php foreach ($contracts as $ct): ?>
          <li>
            <a href="<?= u('contract&id=' . (int) $ct['id']) ?>"><?= e($ct['number']) ?></a>
            <span class="cell-muted"><?= e(contract_frequencies()[$ct['frequency']] ?? $ct['frequency']) ?> · <?= e(money((float) $ct['price_per_visit'])) ?></span>
            <span class="mini-right"><?= due_chip($ct['next_due'] ?? $ct['end_date']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <h2 class="panel-title" style="margin-top:18px"><?= e(t('invoices.title')) ?></h2>
    <?php if (!$invoices): ?>
      <div class="empty"><?= e(t('customer.no_invoices')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e(t('inv.number')) ?></th><th><?= e(t('inv.issued')) ?></th><th><?= e(t('inv.total')) ?></th><th><?= e(t('common.status')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($invoices as $inv): ?>
              <tr>
                <td><a class="ref-link" href="<?= u('invoice&id=' . (int) $inv['id']) ?>"><?= e($inv['number']) ?></a></td>
                <td class="cell-muted"><?= e(fmt_date($inv['issued_at'])) ?></td>
                <td><?= e(money((float) $inv['total'])) ?></td>
                <td><?= invoice_status_badge($inv['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('customer.tab_jobs')) ?></h2>
    <?php if (Auth::isOffice()): ?>
      <a class="link" href="<?= u('jobs&customer=' . $id) ?>"><?= e(t('common.view_all')) ?> →</a>
    <?php endif; ?>
  </div>
  <?php if (!$jobs): ?>
    <div class="empty"><?= e(t('customer.no_jobs')) ?></div>
  <?php else: ?>
    <?php job_table($jobs, false); ?>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
