<?php
declare(strict_types=1);

$id  = (int) ($_GET['id'] ?? 0);
$job = Jobs::find($id);
if (!$job) {
    http_response_code(404);
    exit(t('e404.title'));
}

$isOffice = Auth::isOffice();
$isMine   = (int) ($job['assigned_to'] ?? 0) === Auth::id();

// Technicians may only open their own jobs.
if (Auth::isTechnician() && !$isMine) {
    http_response_code(403);
    exit(t('auth.denied'));
}
$canWork = $isOffice || $isMine;

// ---------------------------------------------------------------- POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$canWork) {
        http_response_code(403);
        exit(t('auth.denied'));
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'status') {
        Jobs::setStatus($id, (string) ($_POST['status'] ?? ''), trim((string) ($_POST['status_note'] ?? '')));
        flash('success', t('job.status_saved'));
    } elseif ($action === 'note') {
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            flash('error', t('job.err_note'));
        } else {
            $noteId = Jobs::addNote($id, $note);
            if (!empty($_FILES['files']['name'])) {
                handle_job_uploads($id, Auth::id(), $noteId);
            }
            flash('success', t('job.note_added'));
        }
    } elseif ($action === 'upload') {
        $res = handle_job_uploads($id, Auth::id());
        if ($res['ok']) {
            flash('success', t('job.files_uploaded', ['n' => $res['ok']]));
        }
        foreach ($res['errors'] as $err) {
            flash('error', $err);
        }
    } elseif ($action === 'check_add') {
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label !== '') {
            Jobs::addChecklistItem($id, $label);
        }
    } elseif ($action === 'check_toggle') {
        Jobs::toggleChecklistItem((int) ($_POST['item_id'] ?? 0));
    } elseif ($action === 'check_delete') {
        Jobs::deleteChecklistItem((int) ($_POST['item_id'] ?? 0));
    } elseif ($action === 'part_add') {
        $err = Jobs::addPart($id, (int) ($_POST['part_id'] ?? 0), (float) ($_POST['qty'] ?? 0));
        flash($err ? 'error' : 'success', $err ? t($err) : t('job.part_added'));
    } elseif ($action === 'part_remove') {
        Jobs::removePart((int) ($_POST['row_id'] ?? 0));
        flash('success', t('job.part_removed'));
    } elseif ($action === 'invoice') {
        if (!Auth::isFinance()) {
            flash('error', t('auth.denied'));
        } else {
            $invoiceId = Invoices::createFromJob($id);
            flash('success', t('job.invoice_created'));
            redirect('invoice&id=' . (int) $invoiceId);
        }
    }
    redirect('job&id=' . $id);
}

$job        = Jobs::find($id);
$checklist  = Jobs::checklist($id);
$parts      = Jobs::partsUsed($id);
$partsTotal = Jobs::partsTotal($id);
$notes      = Jobs::notes($id);
$files      = Jobs::files($id);
$allParts   = $canWork ? Parts::all(true) : [];
$invoice    = Database::one('SELECT * FROM invoices WHERE job_id = ?', [$id]);
$done       = count(array_filter($checklist, static fn (array $c): bool => (bool) $c['done']));
$jobValue   = (float) ($job['service_price'] ?? 0) + $partsTotal;

layout_header($job['number'] . ' — ' . $job['title'], 'jobs');
?>

<div class="panel job-head-panel">
  <div class="ticket-head">
    <div class="ticket-title">
      <span class="ref-pill"><?= e($job['number']) ?></span>
      <h2 class="ticket-subject"><?= e($job['title']) ?></h2>
      <div class="ticket-badges">
        <?= job_status_badge($job['status']) ?>
        <?= priority_badge($job['priority']) ?>
        <?php if ($job['type'] === 'contract'): ?><span class="badge badge-cat"><?= e(t('jtype.contract')) ?><?= $job['contract_number'] ? ' · ' . e($job['contract_number']) : '' ?></span><?php endif; ?>
        <?php if ($job['service_name']): ?><span class="badge badge-cat"><?= e($job['service_name']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="ticket-actions">
      <?php if ($canWork): ?>
        <form method="post" action="<?= u('job&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="status">
          <select name="status" class="input input-select auto-submit" aria-label="<?= e(t('common.status')) ?>">
            <?php foreach (job_statuses() as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $job['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <?php if ($isOffice): ?>
        <form method="post" action="<?= u('job&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="assign">
          <a class="btn btn-ghost btn-sm" href="<?= u('job_edit&id=' . $id) ?>"><?= e(t('common.edit')) ?></a>
        </form>
        <?php if (!$invoice && Auth::isFinance()): ?>
          <form method="post" action="<?= u('job&id=' . $id) ?>" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="invoice">
            <button class="btn btn-primary btn-sm" type="submit"><?= e(t('job.make_invoice')) ?></button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="meta-grid">
    <div class="meta-item"><span class="meta-label"><?= e(t('job.customer')) ?></span><span class="meta-value"><a href="<?= u('customer&id=' . (int) $job['customer_id']) ?>"><?= e($job['customer_name']) ?></a></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('job.site')) ?></span><span class="meta-value"><?= e($job['site_name'] ?? '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('job.date')) ?></span><span class="meta-value"><?= due_chip($job['scheduled_date']) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('job.window')) ?></span><span class="meta-value"><?= e(fmt_time($job['window_start'])) ?><?= $job['window_end'] ? ' – ' . e(fmt_time($job['window_end'])) : '' ?><?= !$job['window_start'] ? '—' : '' ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('job.technician')) ?></span><span class="meta-value"><?= tech_chip($job['technician_name'] ?? null, $job['technician_colour'] ?? null) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('job.contact')) ?></span><span class="meta-value"><?= e($job['contact_name'] ?? '—') ?><?= $job['contact_phone'] ? ' · ' . e($job['contact_phone']) : '' ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('job.phone')) ?></span><span class="meta-value"><?= e($job['customer_phone'] ?? '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('job.value')) ?></span><span class="meta-value"><?= e(money($jobValue)) ?></span></div>
    <?php if ($invoice): ?>
      <div class="meta-item"><span class="meta-label"><?= e(t('inv.title')) ?></span><span class="meta-value"><a href="<?= u('invoice&id=' . (int) $invoice['id']) ?>"><?= e($invoice['number']) ?></a> <?= invoice_status_badge($invoice['status']) ?></span></div>
    <?php endif; ?>
    <?php if ($job['completed_at']): ?>
      <div class="meta-item"><span class="meta-label"><?= e(t('job.completed_at')) ?></span><span class="meta-value"><?= e(fmt_dt($job['completed_at'])) ?></span></div>
    <?php endif; ?>
  </div>

  <?php if ($job['site_address'] || $job['access_notes']): ?>
    <div class="asset-notes">
      <?php if ($job['site_address']): ?><div><strong><?= e(t('site.address')) ?>:</strong> <?= e($job['site_address']) ?><?= $job['site_city'] ? ', ' . e($job['site_city']) : '' ?></div><?php endif; ?>
      <?php if ($job['access_notes']): ?><div><strong><?= e(t('site.access_notes')) ?>:</strong> <?= e($job['access_notes']) ?></div><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($job['description'] || $job['internal_notes']): ?>
<section class="panel">
  <?php if ($job['description']): ?>
    <h3 class="mini-title"><?= e(t('job.description')) ?></h3>
    <div class="post-text"><?= render_body($job['description']) ?></div>
  <?php endif; ?>
  <?php if ($job['internal_notes'] && !Auth::isTechnician()): ?>
    <h3 class="mini-title" style="margin-top:16px"><?= e(t('job.internal_notes')) ?></h3>
    <div class="post-text muted-text"><?= render_body($job['internal_notes']) ?></div>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="grid-2">
  <!-- ------------------------------------------------ checklist -->
  <div class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e(t('job.checklist')) ?></h2>
      <span class="result-count"><?= $done ?>/<?= count($checklist) ?></span>
    </div>
    <?php if (!$checklist): ?>
      <div class="empty"><?= e(t('job.no_checklist')) ?></div>
    <?php else: ?>
      <ul class="check-list">
        <?php foreach ($checklist as $item): ?>
          <li class="check-item <?= $item['done'] ? 'check-done' : '' ?>">
            <?php if ($canWork): ?>
              <form method="post" action="<?= u('job&id=' . $id) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="check_toggle">
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                <button class="check-box" type="submit" aria-label="<?= e(t('job.toggle')) ?>"><?= $item['done'] ? '✓' : '' ?></button>
              </form>
            <?php else: ?>
              <span class="check-box"><?= $item['done'] ? '✓' : '' ?></span>
            <?php endif; ?>
            <span><?= e($item['label']) ?></span>
            <?php if ($canWork): ?>
              <form method="post" action="<?= u('job&id=' . $id) ?>" class="inline-form check-delete">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="check_delete">
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">✕</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($canWork): ?>
      <form method="post" action="<?= u('job&id=' . $id) ?>" class="inline-form check-add">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="check_add">
        <input class="input" name="label" placeholder="<?= e(t('job.checklist_ph')) ?>" required>
        <button class="btn" type="submit"><?= e(t('common.add')) ?></button>
      </form>
    <?php endif; ?>
  </div>

  <!-- ------------------------------------------------ parts used -->
  <div class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e(t('job.parts')) ?></h2>
      <span class="result-count"><?= e(money($partsTotal)) ?></span>
    </div>
    <?php if (!$parts): ?>
      <div class="empty"><?= e(t('job.no_parts')) ?></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e(t('part.name')) ?></th><th><?= e(t('job.qty')) ?></th><th><?= e(t('job.unit_price')) ?></th><th><?= e(t('job.line_total')) ?></th><?php if ($canWork): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($parts as $row): ?>
              <tr>
                <td><?= e($row['part_name']) ?><div class="cell-muted"><?= e($row['sku']) ?></div></td>
                <td><?= e((string) (float) $row['qty']) ?> <?= e($row['unit']) ?></td>
                <td><?= e(money((float) $row['unit_price'], false)) ?></td>
                <td><?= e(money((float) $row['qty'] * (float) $row['unit_price'])) ?></td>
                <?php if ($canWork): ?>
                  <td class="cell-actions">
                    <form method="post" action="<?= u('job&id=' . $id) ?>" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="part_remove">
                      <input type="hidden" name="row_id" value="<?= (int) $row['id'] ?>">
                      <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= e(t('common.delete')) ?></button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <?php if ($canWork && $allParts): ?>
      <form method="post" action="<?= u('job&id=' . $id) ?>" class="inline-form part-add">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="part_add">
        <select class="input input-select" name="part_id" required>
          <option value=""><?= e(t('job.choose_part')) ?></option>
          <?php foreach ($allParts as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> — <?= e(money((float) $p['sell_price'])) ?> (<?= e((string) (float) $p['stock_qty']) ?> <?= e($p['unit']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <input class="input input-sm" type="number" step="0.01" min="0.01" name="qty" value="1" style="width:90px" aria-label="<?= e(t('job.qty')) ?>">
        <button class="btn" type="submit"><?= e(t('job.add_part')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('job.activity')) ?></h2>
  <?php if (!$notes && !$files): ?>
    <div class="empty"><?= e(t('job.no_activity')) ?></div>
  <?php else: ?>
    <ul class="timeline">
      <?php foreach ($notes as $note): ?>
        <li class="timeline-item timeline-note">
          <span class="timeline-dot"></span>
          <div>
            <strong><?= e($note['author_name'] ?? t('common.none')) ?></strong>
            <span class="cell-muted">· <?= e(fmt_dt($note['created_at'])) ?></span>
            <?php if ($note['status_from'] && $note['status_to'] && $note['status_from'] !== $note['status_to']): ?>
              <span class="cell-muted">· <?= e(job_statuses()[$note['status_from']] ?? $note['status_from']) ?> → <?= e(job_statuses()[$note['status_to']] ?? $note['status_to']) ?></span>
            <?php endif; ?>
            <div class="post-text"><?= render_body($note['note']) ?></div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($files): ?>
      <div class="file-list" style="margin-top:12px">
        <?php foreach ($files as $file): ?>
          <a class="att" href="<?= u('download&id=' . (int) $file['id']) ?>">📎 <?= e($file['original_name']) ?>
            <span class="cell-muted">(<?= round($file['size'] / 1024, 1) ?> KB · <?= e(time_ago($file['created_at'])) ?>)</span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($canWork): ?>
    <form method="post" action="<?= u('job&id=' . $id) ?>" enctype="multipart/form-data" class="stack-form" style="margin-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="note">
      <label class="field-label"><?= e(t('job.add_note')) ?></label>
      <textarea class="input" name="note" rows="3" required placeholder="<?= e(t('job.note_ph')) ?>"></textarea>
      <div class="reply-bar">
        <label class="file-label">
          <input type="file" name="files[]" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip">
          📎 <?= e(t('job.attach')) ?> <span class="cell-muted">(<?= e(t('job.attach_hint')) ?>)</span>
        </label>
        <button class="btn btn-primary" type="submit"><?= e(t('job.save_note')) ?></button>
      </div>
    </form>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
