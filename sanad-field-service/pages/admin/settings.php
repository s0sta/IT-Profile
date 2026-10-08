<?php
declare(strict_types=1);

/**
 * Admin — settings: business name, number prefixes, currency, VAT,
 * payment terms and the alert window. Only administrators reach this page.
 */

Auth::requireAdmin();

$s = [
    'site_name'         => (string) setting('site_name', 'Sanad'),
    'job_prefix'        => (string) setting('job_prefix', 'JOB'),
    'invoice_prefix'    => (string) setting('invoice_prefix', 'INV'),
    'customer_prefix'   => (string) setting('customer_prefix', 'CUS'),
    'contract_prefix'   => (string) setting('contract_prefix', 'AMC'),
    'currency'          => (string) setting('currency', 'AED'),
    'vat_rate'          => (string) setting('vat_rate', '5'),
    'payment_term_days' => (string) setting('payment_term_days', '30'),
    'alert_days'        => (string) setting('alert_days', '30'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $s = [
        'site_name'         => trim((string) ($_POST['site_name'] ?? '')),
        'job_prefix'        => strtoupper(trim((string) ($_POST['job_prefix'] ?? ''))),
        'invoice_prefix'    => strtoupper(trim((string) ($_POST['invoice_prefix'] ?? ''))),
        'customer_prefix'   => strtoupper(trim((string) ($_POST['customer_prefix'] ?? ''))),
        'contract_prefix'   => strtoupper(trim((string) ($_POST['contract_prefix'] ?? ''))),
        'currency'          => strtoupper(trim((string) ($_POST['currency'] ?? ''))),
        'vat_rate'          => trim((string) ($_POST['vat_rate'] ?? '')),
        'payment_term_days' => trim((string) ($_POST['payment_term_days'] ?? '')),
        'alert_days'        => trim((string) ($_POST['alert_days'] ?? '')),
    ];
    $error = '';

    if ($s['site_name'] === '' || mb_strlen($s['site_name']) > 60) {
        $error = t('as.err_site');
    } elseif (
        !preg_match('/^[A-Z0-9]{2,6}$/', $s['job_prefix'])
        || !preg_match('/^[A-Z0-9]{2,6}$/', $s['invoice_prefix'])
        || !preg_match('/^[A-Z0-9]{2,6}$/', $s['customer_prefix'])
        || !preg_match('/^[A-Z0-9]{2,6}$/', $s['contract_prefix'])
    ) {
        $error = t('as.err_prefix');
    } elseif (!preg_match('/^[A-Z]{2,5}$/', $s['currency'])) {
        $error = t('as.err_currency');
    } elseif (!is_numeric($s['vat_rate']) || (float) $s['vat_rate'] < 0 || (float) $s['vat_rate'] > 100) {
        $error = t('as.err_vat');
    } elseif (!ctype_digit($s['payment_term_days']) || (int) $s['payment_term_days'] > 365) {
        $error = t('as.err_terms');
    } elseif (!ctype_digit($s['alert_days']) || (int) $s['alert_days'] < 1 || (int) $s['alert_days'] > 365) {
        $error = t('as.err_alert');
    } else {
        foreach ($s as $key => $value) {
            save_setting($key, $value);
        }
        audit('settings_updated', 'settings');
        flash('success', t('as.saved'));
        redirect('admin/settings');
    }

    if ($error !== '') {
        flash('error', $error);
    }
}

// Example numbers as the models will build them from the current prefix.
$examples = [
    'job_prefix'      => $s['job_prefix'] . '-' . date('Y') . '-00001',
    'invoice_prefix'  => $s['invoice_prefix'] . '-' . date('Y') . '-00001',
    'customer_prefix' => $s['customer_prefix'] . '-00001',
    'contract_prefix' => $s['contract_prefix'] . '-' . date('Y') . '-00001',
];

layout_header(t('as.title'), 'admin/settings');
?>

<form method="post" action="<?= u('admin/settings') ?>">
  <?= csrf_field() ?>
  <section class="grid-2">
    <div class="panel">
      <h2 class="panel-title"><?= e(t('as.general')) ?></h2>

      <label class="field-label" for="site_name"><?= e(t('as.site_name')) ?></label>
      <input class="input" id="site_name" name="site_name" type="text" maxlength="60" value="<?= e($s['site_name']) ?>" required>

      <label class="field-label" for="job_prefix"><?= e(t('as.job_prefix')) ?></label>
      <input class="input" id="job_prefix" name="job_prefix" type="text" maxlength="6" value="<?= e($s['job_prefix']) ?>" required>
      <div class="cell-muted"><?= e(t('as.prefix_note', ['example' => $examples['job_prefix']])) ?></div>

      <label class="field-label" for="invoice_prefix"><?= e(t('as.invoice_prefix')) ?></label>
      <input class="input" id="invoice_prefix" name="invoice_prefix" type="text" maxlength="6" value="<?= e($s['invoice_prefix']) ?>" required>
      <div class="cell-muted"><?= e(t('as.prefix_note', ['example' => $examples['invoice_prefix']])) ?></div>

      <label class="field-label" for="customer_prefix"><?= e(t('as.customer_prefix')) ?></label>
      <input class="input" id="customer_prefix" name="customer_prefix" type="text" maxlength="6" value="<?= e($s['customer_prefix']) ?>" required>
      <div class="cell-muted"><?= e(t('as.prefix_note', ['example' => $examples['customer_prefix']])) ?></div>

      <label class="field-label" for="contract_prefix"><?= e(t('as.contract_prefix')) ?></label>
      <input class="input" id="contract_prefix" name="contract_prefix" type="text" maxlength="6" value="<?= e($s['contract_prefix']) ?>" required>
      <div class="cell-muted"><?= e(t('as.prefix_note', ['example' => $examples['contract_prefix']])) ?></div>
    </div>

    <div class="panel">
      <h2 class="panel-title"><?= e(t('as.billing')) ?></h2>

      <label class="field-label" for="currency"><?= e(t('as.currency')) ?></label>
      <input class="input" id="currency" name="currency" type="text" maxlength="5" value="<?= e($s['currency']) ?>" required>

      <label class="field-label" for="vat_rate"><?= e(t('as.vat')) ?></label>
      <input class="input" id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" value="<?= e($s['vat_rate']) ?>" required>

      <label class="field-label" for="payment_term_days"><?= e(t('as.payment_terms')) ?></label>
      <input class="input" id="payment_term_days" name="payment_term_days" type="number" step="1" min="0" max="365" value="<?= e($s['payment_term_days']) ?>" required>

      <h3 class="panel-title" style="margin-top:20px"><?= e(t('as.alerts')) ?></h3>

      <label class="field-label" for="alert_days"><?= e(t('as.alert_days')) ?></label>
      <input class="input" id="alert_days" name="alert_days" type="number" step="1" min="1" max="365" value="<?= e($s['alert_days']) ?>" required>
      <p class="muted-text"><?= e(t('as.alert_note')) ?></p>
    </div>
  </section>

  <div class="form-actions" style="margin-top:18px">
    <button class="btn btn-primary" type="submit"><?= e(t('as.save')) ?></button>
  </div>
</form>

<?php layout_footer(); ?>
