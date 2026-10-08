<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/customer_form.php';

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
        $id = Customers::create([
            'name'    => $name,
            'type'    => (string) ($_POST['type'] ?? 'company'),
            'email'   => $email,
            'phone'   => (string) ($_POST['phone'] ?? ''),
            'address' => (string) ($_POST['address'] ?? ''),
            'city'    => (string) ($_POST['city'] ?? ''),
            'tax_no'  => (string) ($_POST['tax_no'] ?? ''),
            'notes'   => (string) ($_POST['notes'] ?? ''),
        ]);
        flash('success', t('customers.created'));
        redirect('customer&id=' . $id);
    }
}

layout_header(t('customers.add'), 'customers');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('customers.add')) ?></h2>
    <a class="link" href="<?= u('customers') ?>">← <?= e(t('common.back')) ?></a>
  </div>
  <form method="post" action="<?= u('customer_new') ?>">
    <?= csrf_field() ?>
    <?php customer_form([]); ?>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= e(t('common.create')) ?></button>
      <a class="btn btn-ghost" href="<?= u('customers') ?>"><?= e(t('common.cancel')) ?></a>
    </div>
  </form>
</section>

<?php layout_footer(); ?>
