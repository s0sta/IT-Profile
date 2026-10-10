<?php
declare(strict_types=1);

/**
 * Admin · Settings — one form for the system name and the four document prefixes.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $siteName       = trim((string) ($_POST['site_name'] ?? ''));
    $taskPrefix     = strtoupper(trim((string) ($_POST['task_prefix'] ?? '')));
    $approvalPrefix = strtoupper(trim((string) ($_POST['approval_prefix'] ?? '')));
    $corrInPrefix   = strtoupper(trim((string) ($_POST['corr_in_prefix'] ?? '')));
    $corrOutPrefix  = strtoupper(trim((string) ($_POST['corr_out_prefix'] ?? '')));

    if ($siteName === '' || mb_strlen($siteName) > 60) {
        flash('error', t('as.err_site'));
    } elseif (!preg_match('/^[A-Z0-9]{2,6}$/', $taskPrefix)) {
        flash('error', t('as.err_prefix'));
    } elseif (!preg_match('/^[A-Z0-9]{2,6}$/', $approvalPrefix)) {
        flash('error', t('as.err_prefix'));
    } elseif (!preg_match('/^[A-Z0-9]{2,6}$/', $corrInPrefix)) {
        flash('error', t('as.err_prefix'));
    } elseif (!preg_match('/^[A-Z0-9]{2,6}$/', $corrOutPrefix)) {
        flash('error', t('as.err_prefix'));
    } else {
        save_setting('site_name', $siteName);
        save_setting('task_prefix', $taskPrefix);
        save_setting('approval_prefix', $approvalPrefix);
        save_setting('corr_in_prefix', $corrInPrefix);
        save_setting('corr_out_prefix', $corrOutPrefix);
        audit('settings_updated', 'settings');
        flash('success', t('as.saved'));
    }

    redirect('admin/settings');
}

layout_header(t('as.title'), 'admin/settings');
?>

<?php $storageProblems = storage_problems(); ?>
<?php if ($storageProblems): ?>
  <div class="flash flash-warning">
    <?= e(t('common.storage_warning', ['folders' => implode(', ', $storageProblems)])) ?>
  </div>
<?php endif; ?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('as.general')) ?></h2>
    <form method="post" action="<?= u('admin/settings') ?>">
      <?= csrf_field() ?>
      <label class="field-label"><?= e(t('as.site_name')) ?></label>
      <input class="input" name="site_name" required maxlength="60" value="<?= e(setting('site_name', t('app.name'))) ?>">
      <label class="field-label"><?= e(t('as.task_prefix')) ?></label>
      <input class="input" name="task_prefix" required maxlength="6" value="<?= e(setting('task_prefix', 'TSK')) ?>">
      <label class="field-label"><?= e(t('as.approval_prefix')) ?></label>
      <input class="input" name="approval_prefix" required maxlength="6" value="<?= e(setting('approval_prefix', 'APR')) ?>">
      <label class="field-label"><?= e(t('as.corr_in_prefix')) ?></label>
      <input class="input" name="corr_in_prefix" required maxlength="6" value="<?= e(setting('corr_in_prefix', 'IN')) ?>">
      <label class="field-label"><?= e(t('as.corr_out_prefix')) ?></label>
      <input class="input" name="corr_out_prefix" required maxlength="6" value="<?= e(setting('corr_out_prefix', 'OUT')) ?>">
      <p class="muted-text"><?= e(t('as.prefix_note', ['example' => setting('task_prefix', 'TSK') . '-' . date('Y') . '-0001'])) ?></p>
      <button class="btn btn-primary" type="submit"><?= e(t('as.save')) ?></button>
    </form>
  </div>
</section>

<?php layout_footer(); ?>
