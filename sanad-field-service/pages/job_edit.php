<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/job_form.php';

$id  = (int) ($_GET['id'] ?? 0);
$job = Jobs::find($id);
if (!$job) {
    http_response_code(404);
    exit(t('e404.title'));
}

$customers   = Customers::all(true);
$sites       = Sites::all();
$services    = Services::all(true);
$technicians = Users::technicians();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Auth::requireOffice();
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '') {
        flash('error', t('job.err_title'));
    } else {
        Jobs::update($id, [
            'customer_id'    => (int) ($_POST['customer_id'] ?? 0),
            'site_id'        => (int) ($_POST['site_id'] ?? 0) ?: null,
            'service_id'     => (int) ($_POST['service_id'] ?? 0) ?: null,
            'priority'       => (string) ($_POST['priority'] ?? 'normal'),
            'assigned_to'    => (int) ($_POST['assigned_to'] ?? 0) ?: null,
            'scheduled_date' => (string) ($_POST['scheduled_date'] ?? '') ?: null,
            'window_start'   => (string) ($_POST['window_start'] ?? '') ?: null,
            'window_end'     => (string) ($_POST['window_end'] ?? '') ?: null,
            'title'          => $title,
            'description'    => (string) ($_POST['description'] ?? ''),
            'internal_notes' => (string) ($_POST['internal_notes'] ?? ''),
        ]);
        flash('success', t('job.saved'));
        redirect('job&id=' . $id);
    }
}

layout_header(t('job.edit_title', ['number' => (string) $job['number']]), 'jobs');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('job.edit_title', ['number' => (string) $job['number']])) ?></h2>
    <a class="link" href="<?= u('job&id=' . $id) ?>">← <?= e(t('common.back')) ?></a>
  </div>
  <form method="post" action="<?= u('job_edit&id=' . $id) ?>">
    <?= csrf_field() ?>
    <?php job_form($job, $customers, $sites, $services, $technicians); ?>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
      <a class="btn btn-ghost" href="<?= u('job&id=' . $id) ?>"><?= e(t('common.cancel')) ?></a>
    </div>
  </form>
</section>

<?php layout_footer(); ?>
