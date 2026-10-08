<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/job_form.php';

$customers   = Customers::all(true);
$sites       = Sites::all();
$services    = Services::all(true);
$technicians = Users::technicians();
$prefill     = (string) ($_GET['date'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Auth::requireOffice();
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $title      = trim((string) ($_POST['title'] ?? ''));
    if (!$customerId) {
        flash('error', t('job.err_customer'));
    } elseif ($title === '') {
        flash('error', t('job.err_title'));
    } else {
        $id = Jobs::create([
            'customer_id'    => $customerId,
            'site_id'        => (int) ($_POST['site_id'] ?? 0) ?: null,
            'service_id'     => (int) ($_POST['service_id'] ?? 0) ?: null,
            'type'           => 'one_time',
            'priority'       => (string) ($_POST['priority'] ?? 'normal'),
            'status'         => !empty($_POST['scheduled_date']) ? 'scheduled' : 'new',
            'assigned_to'    => (int) ($_POST['assigned_to'] ?? 0) ?: null,
            'scheduled_date' => (string) ($_POST['scheduled_date'] ?? '') ?: null,
            'window_start'   => (string) ($_POST['window_start'] ?? '') ?: null,
            'window_end'     => (string) ($_POST['window_end'] ?? '') ?: null,
            'title'          => $title,
            'description'    => (string) ($_POST['description'] ?? ''),
            'internal_notes' => (string) ($_POST['internal_notes'] ?? ''),
        ]);
        flash('success', t('job.created', ['number' => (string) setting('job_prefix', 'JOB') . '-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)]));
        redirect('job&id=' . $id);
    }
}

layout_header(t('job.new_title'), 'jobs');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('job.new_title')) ?></h2>
    <a class="link" href="<?= u('jobs') ?>">← <?= e(t('common.back')) ?></a>
  </div>
  <form method="post" action="<?= u('job_new') ?>">
    <?= csrf_field() ?>
    <?php job_form(['scheduled_date' => $prefill], $customers, $sites, $services, $technicians); ?>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= e(t('job.create')) ?></button>
      <a class="btn btn-ghost" href="<?= u('jobs') ?>"><?= e(t('common.cancel')) ?></a>
    </div>
  </form>
</section>

<?php layout_footer(); ?>
