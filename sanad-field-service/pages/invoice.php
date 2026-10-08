<?php
declare(strict_types=1);

$id  = (int) ($_GET['id'] ?? 0);
$inv = Invoices::find($id);
if (!$inv) {
    http_response_code(404);
    exit(t('e404.title'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Auth::requireFinance();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'item_add') {
        $desc = trim((string) ($_POST['description'] ?? ''));
        $qty  = (float) ($_POST['qty'] ?? 0);
        $unit = (float) ($_POST['unit_price'] ?? 0);
        if ($desc === '') {
            flash('error', t('inv.err_item'));
        } elseif ($qty <= 0) {
            flash('error', t('inv.err_amount'));
        } else {
            Invoices::addItem($id, $desc, $qty, $unit);
            Invoices::recalculate($id);
            flash('success', t('common.saved'));
        }
    } elseif ($action === 'item_remove') {
        Invoices::removeItem((int) ($_POST['item_id'] ?? 0));
        flash('success', t('common.saved'));
    } elseif ($action === 'tax') {
        Invoices::setTaxRate($id, (float) ($_POST['tax_rate'] ?? 0));
        flash('success', t('common.saved'));
    } elseif ($action === 'payment') {
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            flash('error', t('inv.err_amount'));
        } else {
            Invoices::addPayment(
                $id,
                $amount,
                (string) ($_POST['method'] ?? 'cash'),
                (string) ($_POST['reference'] ?? ''),
                (string) ($_POST['paid_at'] ?? date('Y-m-d'))
            );
            flash('success', t('inv.payment_saved'));
        }
    } elseif ($action === 'status') {
        Invoices::setStatus($id, (string) ($_POST['status'] ?? 'unpaid'));
        flash('success', t('inv.status_saved'));
    }
    redirect('invoice&id=' . $id);
}

$items    = Invoices::items($id);
$payments = Invoices::payments($id);
$paid     = Invoices::paidAmount($id);
$open     = (float) $inv['total'] - $paid;

// ---------------------------------------------------------------- printable sheet
if ((string) ($_GET['print'] ?? '') === '1') {
    ?>
<!doctype html>
<html lang="<?= e(sanad_current_lang()) ?>" dir="<?= e(sanad_lang_dir()) ?>">
<head>
<meta charset="utf-8">
<title><?= e($inv['number']) ?></title>
<style>
  body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#0f172a;margin:0;padding:32px;background:#fff}
  .sheet{max-width:760px;margin:0 auto}
  .head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0f766e;padding-bottom:14px;margin-bottom:22px}
  .brand{font-size:22px;font-weight:800;color:#0f766e}
  .muted{color:#64748b;font-size:13px}
  h1{font-size:20px;margin:0 0 4px}
  table{width:100%;border-collapse:collapse;margin-top:18px}
  th,td{padding:9px 8px;border-bottom:1px solid #e2e8f0;font-size:14px;text-align:<?= sanad_is_rtl() ? 'right' : 'left' ?>}
  th{background:#f1f5f9;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#475569}
  .num{text-align:<?= sanad_is_rtl() ? 'left' : 'right' ?>;font-variant-numeric:tabular-nums}
  .totals{margin-top:16px;margin-<?= sanad_is_rtl() ? 'right' : 'left' ?>:auto;width:300px}
  .totals div{display:flex;justify-content:space-between;padding:6px 0;font-size:14px}
  .totals .grand{border-top:2px solid #0f172a;margin-top:6px;padding-top:10px;font-size:17px;font-weight:800}
  .box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-top:20px;font-size:13px}
  .pay{display:inline-block;border:1px solid #0f766e;color:#0f766e;border-radius:8px;padding:6px 12px;font-weight:700;font-size:13px}
  @media print{.noprint{display:none}}
</style>
</head>
<body>
<div class="sheet">
  <div class="head">
    <div>
      <div class="brand"><?= e(setting('site_name', 'Sanad')) ?></div>
      <div class="muted"><?= e(t('brand.tagline')) ?></div>
    </div>
    <div style="text-align:<?= sanad_is_rtl() ? 'left' : 'right' ?>">
      <h1><?= e(t('inv.title')) ?> <?= e($inv['number']) ?></h1>
      <div class="muted"><?= e(t('inv.issued')) ?>: <?= e(fmt_date($inv['issued_at'])) ?></div>
      <div class="muted"><?= e(t('inv.due')) ?>: <?= e(fmt_date($inv['due_at'])) ?></div>
      <div style="margin-top:8px" class="pay"><?= e(invoice_statuses()[$inv['status']] ?? $inv['status']) ?></div>
    </div>
  </div>

  <div>
    <div class="muted"><?= e(t('inv.bill_to')) ?></div>
    <strong><?= e($inv['customer_name']) ?></strong>
    <?php if ($inv['customer_address'] || $inv['customer_city']): ?><div class="muted"><?= e(trim(($inv['customer_address'] ?? '') . ', ' . ($inv['customer_city'] ?? ''), ', ')) ?></div><?php endif; ?>
    <?php if ($inv['customer_phone']): ?><div class="muted"><?= e($inv['customer_phone']) ?></div><?php endif; ?>
    <?php if ($inv['customer_tax_no']): ?><div class="muted"><?= e(t('customer.tax_no')) ?>: <?= e($inv['customer_tax_no']) ?></div><?php endif; ?>
    <?php if ($inv['job_number']): ?><div class="muted"><?= e(t('inv.job')) ?>: <?= e($inv['job_number']) ?></div><?php endif; ?>
  </div>

  <table>
    <thead><tr><th><?= e(t('inv.item')) ?></th><th class="num"><?= e(t('inv.qty')) ?></th><th class="num"><?= e(t('inv.unit_price')) ?></th><th class="num"><?= e(t('inv.amount')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= e($item['description']) ?></td>
          <td class="num"><?= e((string) (float) $item['qty']) ?></td>
          <td class="num"><?= e(money((float) $item['unit_price'], false)) ?></td>
          <td class="num"><?= e(money((float) $item['amount'], false)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <div><span><?= e(t('inv.subtotal')) ?></span><span><?= e(money((float) $inv['subtotal'], false)) ?></span></div>
    <div><span><?= e(t('inv.tax', ['rate' => (string) (float) $inv['tax_rate']])) ?></span><span><?= e(money((float) $inv['tax_amount'], false)) ?></span></div>
    <div class="grand"><span><?= e(t('inv.total')) ?></span><span><?= e(money((float) $inv['total'], false)) ?> <?= e((string) setting('currency', 'AED')) ?></span></div>
    <?php if ($paid > 0): ?>
      <div><span><?= e(t('inv.paid')) ?></span><span><?= e(money($paid, false)) ?></span></div>
      <div><span><?= e(t('inv.open')) ?></span><span><?= e(money(max(0.0, $open), false)) ?></span></div>
    <?php endif; ?>
  </div>

  <?php if ($inv['notes']): ?><div class="box"><?= render_body($inv['notes']) ?></div><?php endif; ?>
  <?php if ($payments): ?>
    <div class="box">
      <strong><?= e(t('inv.payments')) ?></strong>
      <?php foreach ($payments as $p): ?>
        <div class="muted"><?= e(fmt_date($p['paid_at'])) ?> — <?= e(money((float) $p['amount'])) ?> · <?= e(payment_methods()[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p class="noprint" style="margin-top:26px">
    <button onclick="window.print()" style="padding:10px 18px;border-radius:10px;border:0;background:#0f766e;color:#fff;font-weight:700;cursor:pointer"><?= e(t('inv.print')) ?></button>
  </p>
</div>
</body>
</html>
    <?php
    exit;
}

layout_header(t('inv.title') . ' ' . $inv['number'], 'invoices');
?>

<div class="panel">
  <div class="ticket-head">
    <div class="ticket-title">
      <span class="ref-pill"><?= e($inv['number']) ?></span>
      <h2 class="ticket-subject"><?= e($inv['customer_name']) ?></h2>
      <div class="ticket-badges">
        <?= invoice_status_badge($inv['status']) ?>
        <?php if ($inv['job_number']): ?><a class="badge badge-cat" href="<?= u('job&id=' . (int) $inv['job_id']) ?>"><?= e($inv['job_number']) ?></a><?php endif; ?>
      </div>
    </div>
    <div class="ticket-actions">
      <a class="btn btn-ghost btn-sm" href="<?= u('invoice&id=' . $id) ?>&print=1" target="_blank"><?= e(t('inv.print')) ?></a>
      <form method="post" action="<?= u('invoice&id=' . $id) ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="status">
        <select name="status" class="input input-select auto-submit">
          <?php foreach (invoice_statuses() as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $inv['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <div class="meta-grid">
    <div class="meta-item"><span class="meta-label"><?= e(t('inv.issued')) ?></span><span class="meta-value"><?= e(fmt_date($inv['issued_at'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('inv.due')) ?></span><span class="meta-value"><?= due_chip($inv['due_at']) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('inv.subtotal')) ?></span><span class="meta-value"><?= e(money((float) $inv['subtotal'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('inv.tax', ['rate' => (string) (float) $inv['tax_rate']])) ?></span><span class="meta-value"><?= e(money((float) $inv['tax_amount'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('inv.total')) ?></span><span class="meta-value"><strong><?= e(money((float) $inv['total'])) ?></strong></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('inv.paid')) ?></span><span class="meta-value"><?= e(money($paid)) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('inv.open')) ?></span><span class="meta-value"><?= e(money(max(0.0, $open))) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.created')) ?></span><span class="meta-value"><?= e(fmt_date($inv['created_at'])) ?></span></div>
  </div>
  <?php if ($inv['notes']): ?><div class="asset-notes"><?= render_body($inv['notes']) ?></div><?php endif; ?>
</div>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('inv.items')) ?></h2>
    <?php if (!$items): ?>
      <div class="empty"><?= e(t('inv.no_items')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e(t('inv.item')) ?></th><th><?= e(t('inv.qty')) ?></th><th><?= e(t('inv.unit_price')) ?></th><th><?= e(t('inv.amount')) ?></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= e($item['description']) ?></td>
                <td><?= e((string) (float) $item['qty']) ?></td>
                <td><?= e(money((float) $item['unit_price'], false)) ?></td>
                <td><?= e(money((float) $item['amount'])) ?></td>
                <td class="cell-actions">
                  <form method="post" action="<?= u('invoice&id=' . $id) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="item_remove">
                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                    <button class="btn btn-ghost btn-sm text-danger" type="submit">✕</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= u('invoice&id=' . $id) ?>" class="stack-form" style="margin-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="item_add">
      <label class="field-label"><?= e(t('inv.add_item')) ?></label>
      <input class="input" name="description" placeholder="<?= e(t('inv.item')) ?>" required>
      <div class="form-row">
        <div><label class="field-label"><?= e(t('inv.qty')) ?></label><input class="input" type="number" step="0.01" min="0.01" name="qty" value="1"></div>
        <div><label class="field-label"><?= e(t('inv.unit_price')) ?></label><input class="input" type="number" step="0.01" min="0" name="unit_price" value="0"></div>
      </div>
      <button class="btn" type="submit"><?= e(t('common.add')) ?></button>
    </form>

    <form method="post" action="<?= u('invoice&id=' . $id) ?>" class="inline-form" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="tax">
      <label class="field-label"><?= e(t('inv.tax_rate')) ?></label>
      <input class="input input-sm" type="number" step="0.01" min="0" max="100" name="tax_rate" value="<?= e((string) (float) $inv['tax_rate']) ?>" style="width:90px">
      <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('inv.save_tax')) ?></button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('inv.payments')) ?></h2>
    <?php if (!$payments): ?>
      <div class="empty"><?= e(t('inv.no_payments')) ?></div>
    <?php else: ?>
      <ul class="mini-list">
        <?php foreach ($payments as $p): ?>
          <li>
            <span><?= e(money((float) $p['amount'])) ?></span>
            <span class="cell-muted"><?= e(payment_methods()[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></span>
            <span class="mini-right cell-muted"><?= e(fmt_date($p['paid_at'])) ?><?= $p['recorded_by_name'] ? ' · ' . e($p['recorded_by_name']) : '' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($open > 0 && $inv['status'] !== 'cancelled'): ?>
      <form method="post" action="<?= u('invoice&id=' . $id) ?>" class="stack-form" style="margin-top:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="payment">
        <label class="field-label"><?= e(t('inv.add_payment')) ?></label>
        <div class="form-row">
          <div><label class="field-label"><?= e(t('common.amount')) ?></label><input class="input" type="number" step="0.01" min="0.01" name="amount" value="<?= e(number_format($open, 2, '.', '')) ?>"></div>
          <div><label class="field-label"><?= e(t('common.date')) ?></label><input class="input" type="date" name="paid_at" value="<?= e(date('Y-m-d')) ?>"></div>
        </div>
        <label class="field-label"><?= e(t('inv.payment_method')) ?></label>
        <select class="input input-select" name="method">
          <?php foreach (payment_methods() as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <label class="field-label"><?= e(t('inv.payment_reference')) ?></label>
        <input class="input" name="reference" placeholder="<?= e(t('common.optional')) ?>">
        <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php layout_footer(); ?>
