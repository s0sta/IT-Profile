<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/customer_form.php';

$id = (int) ($_GET['id'] ?? 0);
$c  = Customers::find($id);
if (!$c) {
    http_response_code(404);
    exit(t('e404.title'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Auth::requireOffice();
    $name  = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    if ($name === '') {
        flash('error', t('customers.err_name'));
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', t('customers.err_email'));
    } else {
        Customers::update($id, [
            'name'    => $name,
            'type'    => (string) ($_POST['type'] ?? 'company'),
            'email'   => $email,
            'phone'   => (string) ($_POST['phone'] ?? ''),
            'address' => (string) ($_POST['address'] ?? ''),
            'city'    => (string) ($_POST['city'] ?? ''),
            'tax_no'  => (string) ($_POST['tax_no'] ?? ''),
            'notes'   => (string) ($_POST['notes'] ?? ''),
        ]);
        flash('success', t('customers.saved'));
        redirect('customer&id=' . $id);
    }
}

layout_header(t('customers.edit'), 'customers');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('customers.edit')) ?> — <?= e($c['name']) ?></h2>
    <a class="link" href="<?= u('customer&id=' . $id) ?>">← <?= e(t('common.back')) ?></a>
  </div>
  <form method="post" action="<?= u('customer_edit&id=' . $id) ?>">
    <?= csrf_field() ?>
    <?php customer_form($c); ?>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
      <a class="btn btn-ghost" href="<?= u('customer&id=' . $id) ?>"><?= e(t('common.cancel')) ?></a>
    </div>
  </form>
</section>

<?php layout_footer(); ?>
