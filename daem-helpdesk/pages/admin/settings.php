<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $siteName = trim((string) ($_POST['site_name'] ?? ''));
    $prefix   = strtoupper(trim((string) ($_POST['ticket_prefix'] ?? '')));

    if ($siteName === '' || mb_strlen($siteName) > 60) {
        flash('error', t('as.err_site'));
    } elseif (!preg_match('/^[A-Z0-9]{2,6}$/', $prefix)) {
        flash('error', t('as.err_prefix'));
    } else {
        save_setting('site_name', $siteName);
        save_setting('ticket_prefix', $prefix);

        foreach (array_keys(priorities()) as $p) {
            $resp = max(0.25, (float) str_replace(',', '.', (string) ($_POST['response_' . $p] ?? '24')));
            $res  = max(0.25, (float) str_replace(',', '.', (string) ($_POST['resolution_' . $p] ?? '120')));
            Database::exec(
                'UPDATE sla SET response_hours = ?, resolution_hours = ? WHERE priority = ?',
                [$resp, $res, $p]
            );
        }
        audit('settings_updated', 'settings');
        flash('success', t('as.saved'));
    }
    redirect('admin/settings');
}

$policies = [];
foreach (Database::all('SELECT * FROM sla') as $row) {
    $policies[$row['priority']] = $row;
}

layout_header(t('as.title'), 'admin/settings');
?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('as.general')) ?></h2>
    <form method="post" action="<?= u('admin/settings') ?>">
      <?= csrf_field() ?>
      <label class="field-label"><?= e(t('as.site_name')) ?></label>
      <input class="input" name="site_name" required value="<?= e(setting('site_name', 'Daem')) ?>">
      <label class="field-label"><?= e(t('as.prefix')) ?></label>
      <input class="input" name="ticket_prefix" required value="<?= e(setting('ticket_prefix', 'TCK')) ?>" maxlength="6">
      <p class="muted-text"><?= e(t('as.prefix_note', ['example' => setting('ticket_prefix', 'TCK') . '-' . date('Y') . '-00042'])) ?></p>
      <button class="btn btn-primary" type="submit"><?= e(t('as.save')) ?></button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title"><?= e(t('as.sla')) ?></h2>
    <form method="post" action="<?= u('admin/settings') ?>">
      <?= csrf_field() ?>
      <p class="muted-text"><?= e(t('as.sla_note')) ?></p>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e(t('common.priority')) ?></th><th><?= e(t('as.response')) ?></th><th><?= e(t('as.resolution')) ?></th></tr></thead>
          <tbody>
            <?php foreach (priorities() as $p => $label): $pol = $policies[$p] ?? ['response_hours' => 24, 'resolution_hours' => 120]; ?>
              <tr>
                <td><?= priority_badge($p) ?></td>
                <td><input class="input input-sm" type="number" step="0.25" min="0.25" name="response_<?= e($p) ?>" value="<?= e((string) $pol['response_hours']) ?>"></td>
                <td><input class="input input-sm" type="number" step="0.25" min="0.25" name="resolution_<?= e($p) ?>" value="<?= e((string) $pol['resolution_hours']) ?>"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button class="btn btn-primary" type="submit"><?= e(t('as.save_sla')) ?></button>
    </form>
  </div>
</section>

<?php layout_footer(); ?>
