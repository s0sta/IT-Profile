<?php
declare(strict_types=1);

$me     = Auth::current();
$id     = (int) ($_GET['id'] ?? 0);
$task   = Tasks::find($id);

if (!$task) {
    http_response_code(404);
    layout_header(t('e404.title'), '');
    echo '<div class="panel"><h1 class="ticket-subject">404</h1><p class="muted-text">' . e(t('e404.text')) . '</p><a class="btn btn-primary" href="' . u('dashboard') . '">' . e(t('e404.back')) . '</a></div>';
    layout_footer();
    exit;
}
if (!Tasks::canView($task, $me)) {
    http_response_code(403);
    layout_header(t('auth.denied'), '');
    echo '<div class="panel"><h1 class="ticket-subject">403</h1><p class="muted-text">' . e(t('auth.denied')) . '</p><a class="btn btn-primary" href="' . u('dashboard') . '">' . e(t('common.back')) . '</a></div>';
    layout_footer();
    exit;
}

$canEdit   = Tasks::canEdit($task, $me);
$canManage = Tasks::canManage($task, $me);

// ---------------------------------------------------------------- POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($canEdit && $action === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        if (array_key_exists($status, task_statuses())) {
            Tasks::setStatus($id, $status);
            flash('success', t('tasks.status_changed', ['status' => task_statuses()[$status]]));
        }
    } elseif ($canEdit && $action === 'progress') {
        Tasks::setProgress($id, (int) ($_POST['progress'] ?? 0));
        flash('success', t('tasks.updated_ok'));
    } elseif ($canManage && $action === 'assign') {
        Tasks::assign($id, (int) ($_POST['assignee_id'] ?? 0) ?: null);
        flash('success', t('tasks.updated_ok'));
    } elseif ($canEdit && $action === 'add_item') {
        $title = trim((string) ($_POST['item_title'] ?? ''));
        if ($title === '') {
            flash('error', t('tasks.err_item'));
        } else {
            Tasks::addItem($id, $title);
            flash('success', t('tasks.item_added'));
        }
    } elseif ($canEdit && $action === 'toggle_item') {
        Tasks::toggleItem((int) ($_POST['item_id'] ?? 0));
    } elseif ($canEdit && $action === 'delete_item') {
        Tasks::deleteItem((int) ($_POST['item_id'] ?? 0));
    } elseif ($canEdit && $action === 'add_update') {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '') {
            flash('error', t('tasks.err_update'));
        } else {
            $progress = ($_POST['progress'] ?? '') === '' ? null : (int) $_POST['progress'];
            $updateId = Tasks::addUpdate($id, $body, $progress);
            handle_uploads($id, $updateId, null, (int) $me['id']);
            flash('success', t('tasks.update_added'));
        }
    } elseif ($canManage && $action === 'edit') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', t('tasks.err_title'));
        } else {
            Tasks::update($id, [
                'title'         => $title,
                'description'   => (string) ($_POST['description'] ?? ''),
                'category_id'   => (int) ($_POST['category_id'] ?? 0) ?: null,
                'priority'      => (string) ($_POST['priority'] ?? 'medium'),
                'assignee_id'   => (int) ($_POST['assignee_id'] ?? 0) ?: null,
                'department_id' => (int) ($_POST['department_id'] ?? 0) ?: null,
                'start_date'    => (string) ($_POST['start_date'] ?? '') ?: null,
                'due_date'      => (string) ($_POST['due_date'] ?? '') ?: null,
            ]);
            flash('success', t('tasks.updated_ok'));
        }
    } elseif ($canManage && $action === 'delete') {
        Database::exec('DELETE FROM task_items WHERE task_id = ?', [$id]);
        Database::exec('DELETE FROM task_updates WHERE task_id = ?', [$id]);
        Database::exec('DELETE FROM attachments WHERE task_id = ?', [$id]);
        Database::exec('DELETE FROM tasks WHERE id = ?', [$id]);
        audit('task_deleted', 'task', $id);
        flash('success', t('tasks.deleted'));
        redirect('tasks');
    }
    redirect('task&id=' . $id);
}

// ------------------------------------------------------------------- render
$task     = Tasks::find($id);
$items    = Tasks::items($id);
$updates  = Tasks::updates($id);
$agents   = Users::all(true);
$cats     = TaskCategories::all(true);
$depts    = Departments::all(true);
$files    = attachmentsFor($id);

layout_header($task['ref'] . ' — ' . $task['title'], 'tasks');
?>

<div class="panel ticket-head-panel">
  <div class="ticket-head">
    <div class="ticket-title">
      <span class="ref-pill"><?= e($task['ref']) ?></span>
      <h2 class="ticket-subject"><?= e($task['title']) ?></h2>
      <div class="ticket-badges">
        <?= task_status_badge((string) $task['status']) ?>
        <?= priority_badge((string) $task['priority']) ?>
        <?php if ($task['cat_name_ar'] || $task['cat_name_en']): ?>
          <span class="badge badge-cat"><?= e(bilingual($task, 'cat_name')) ?></span>
        <?php endif; ?>
        <?php if ($task['dept_name_ar'] || $task['dept_name_en']): ?>
          <span class="badge badge-cat"><?= e(bilingual($task, 'dept_name')) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($canEdit): ?>
      <div class="ticket-actions">
        <form method="post" action="<?= u('task&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="status">
          <select name="status" class="input input-select auto-submit" aria-label="<?= e(t('tasks.change_status')) ?>">
            <?php foreach (task_statuses() as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $task['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php if ($canManage): ?>
        <form method="post" action="<?= u('task&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="assign">
          <select name="assignee_id" class="input input-select auto-submit" aria-label="<?= e(t('tasks.col_assignee')) ?>">
            <option value="0"><?= e(t('common.unassigned')) ?></option>
            <?php foreach ($agents as $a): ?>
              <option value="<?= (int) $a['id'] ?>" <?= (int) ($task['assignee_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>
        <?php if ($task['status'] !== 'completed'): ?>
        <form method="post" action="<?= u('task&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status" value="completed">
          <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= e(t('tasks.mark_complete')) ?></button>
        </form>
        <?php else: ?>
        <form method="post" action="<?= u('task&id=' . $id) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status" value="in_progress">
          <button class="btn" type="submit"><?= e(t('tasks.reopen')) ?></button>
        </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="progress-row">
    <?= progress_bar((int) $task['progress']) ?>
    <?php if ($canEdit): ?>
      <form method="post" action="<?= u('task&id=' . $id) ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="progress">
        <input class="input progress-input" type="number" name="progress" min="0" max="100" step="5" value="<?= (int) $task['progress'] ?>" aria-label="<?= e(t('common.progress')) ?>">
        <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('common.save')) ?></button>
      </form>
    <?php endif; ?>
  </div>

  <div class="meta-grid">
    <div class="meta-item"><span class="meta-label"><?= e(t('tasks.assigned_to')) ?></span><span class="meta-value"><?= e($task['assignee_name'] ?? t('common.unassigned')) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('tasks.created_by')) ?></span><span class="meta-value"><?= e($task['creator_name'] ?? '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.department')) ?></span><span class="meta-value"><?= e($task['dept_name_ar'] || $task['dept_name_en'] ? bilingual($task, 'dept_name') : '—') ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('tasks.start_date')) ?></span><span class="meta-value"><?= e(fmt_date($task['start_date'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('tasks.due_date')) ?></span><span class="meta-value"><?= due_chip($task['due_date'], $task['status']) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.created')) ?></span><span class="meta-value"><?= e(fmt_dt($task['created_at'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.updated')) ?></span><span class="meta-value"><?= e(time_ago($task['updated_at'])) ?></span></div>
    <div class="meta-item"><span class="meta-label"><?= e(t('common.attachments')) ?></span><span class="meta-value"><?= count($files) ?></span></div>
  </div>
</div>

<?php if (trim((string) $task['description']) !== ''): ?>
<section class="panel">
  <h2 class="panel-title"><?= e(t('common.description')) ?></h2>
  <div class="post-text"><?= render_body($task['description']) ?></div>
</section>
<?php endif; ?>

<section class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e(t('tasks.checklist')) ?></h2>
      <span class="cell-muted"><?= count($items) ? count(array_filter($items, static fn ($i) => (int) $i['is_done'] === 1)) . '/' . count($items) : '0' ?></span>
    </div>
    <?php if (!$items): ?>
      <div class="empty"><?= e(t('tasks.no_items')) ?></div>
    <?php else: ?>
      <ul class="checklist">
        <?php foreach ($items as $item): ?>
          <li class="<?= (int) $item['is_done'] ? 'is-done' : '' ?>">
            <?php if ($canEdit): ?>
              <form method="post" action="<?= u('task&id=' . $id) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_item">
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                <button class="check-btn" type="submit" aria-label="<?= e(t('common.save')) ?>"><?= (int) $item['is_done'] ? '☑' : '☐' ?></button>
              </form>
              <span class="check-text"><?= e($item['title']) ?></span>
              <form method="post" action="<?= u('task&id=' . $id) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                <button class="btn btn-ghost btn-sm text-danger" type="submit">✕</button>
              </form>
            <?php else: ?>
              <span class="check-btn"><?= (int) $item['is_done'] ? '☑' : '☐' ?></span>
              <span class="check-text"><?= e($item['title']) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($canEdit): ?>
      <form method="post" action="<?= u('task&id=' . $id) ?>" class="inline-form inline-add">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_item">
        <input class="input" type="text" name="item_title" placeholder="<?= e(t('tasks.add_item')) ?>" required>
        <button class="btn btn-ghost" type="submit"><?= icon('plus') ?></button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($canManage): ?>
  <div class="panel">
    <h2 class="panel-title"><?= e(t('tasks.edit')) ?></h2>
    <form method="post" action="<?= u('task&id=' . $id) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="edit">
      <label class="field-label"><?= e(t('tasks.col_task')) ?></label>
      <input class="input" name="title" required value="<?= e($task['title']) ?>">
      <label class="field-label"><?= e(t('common.description')) ?></label>
      <textarea class="input" name="description" rows="4"><?= e($task['description']) ?></textarea>
      <div class="row-2">
        <div>
          <label class="field-label"><?= e(t('common.category')) ?></label>
          <select class="input input-select" name="category_id">
            <option value="0"><?= e(t('common.none')) ?></option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) ($task['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e(bilingual($c, 'name')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="field-label"><?= e(t('common.priority')) ?></label>
          <select class="input input-select" name="priority">
            <?php foreach (priorities() as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $task['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row-2">
        <div>
          <label class="field-label"><?= e(t('tasks.col_assignee')) ?></label>
          <select class="input input-select" name="assignee_id">
            <option value="0"><?= e(t('common.unassigned')) ?></option>
            <?php foreach ($agents as $a): ?>
              <option value="<?= (int) $a['id'] ?>" <?= (int) ($task['assignee_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="field-label"><?= e(t('common.department')) ?></label>
          <select class="input input-select" name="department_id">
            <option value="0"><?= e(t('common.none')) ?></option>
            <?php foreach ($depts as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= (int) ($task['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e(bilingual($d, 'name')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row-2">
        <div>
          <label class="field-label"><?= e(t('tasks.start_date')) ?></label>
          <input class="input" type="date" name="start_date" value="<?= e(substr((string) $task['start_date'], 0, 10)) ?>">
        </div>
        <div>
          <label class="field-label"><?= e(t('tasks.due_date')) ?></label>
          <input class="input" type="date" name="due_date" value="<?= e(substr((string) $task['due_date'], 0, 10)) ?>">
        </div>
      </div>
      <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
      <button class="btn btn-ghost text-danger" type="submit" name="action" value="delete"
              formnovalidate data-confirm="<?= e(t('tasks.delete_confirm')) ?>"><?= e(t('common.delete')) ?></button>
    </form>
  </div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2 class="panel-title"><?= e(t('tasks.updates')) ?></h2>
  <?php if (!$updates): ?>
    <div class="empty"><?= e(t('tasks.no_updates')) ?></div>
  <?php else: ?>
    <div class="thread">
      <?php foreach ($updates as $u): $atts = attachmentsFor(null, (int) $u['id']); ?>
        <div class="post">
          <span class="avatar"><?= e(initials($u['author_name'])) ?></span>
          <div class="post-body">
            <div class="post-head">
              <strong><?= e($u['author_name']) ?></strong>
              <?= role_badge((string) $u['author_role']) ?>
              <?php if ($u['progress'] !== null): ?><span class="badge badge-cat"><?= (int) $u['progress'] ?>%</span><?php endif; ?>
              <span class="post-time"><?= e(fmt_dt($u['created_at'])) ?></span>
            </div>
            <div class="post-text"><?= render_body($u['body']) ?></div>
            <?php if ($atts): ?>
              <div class="post-attachments">
                <?php foreach ($atts as $a): ?>
                  <a class="att" href="<?= u('download&id=' . (int) $a['id']) ?>">📎 <?= e($a['original_name']) ?>
                    <span class="cell-muted">(<?= round($a['size'] / 1024, 1) ?> KB)</span></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($canEdit): ?>
    <form method="post" action="<?= u('task&id=' . $id) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_update">
      <label class="field-label"><?= e(t('tasks.add_update')) ?></label>
      <textarea class="input" name="body" rows="4" required placeholder="<?= e(t('tasks.update_placeholder')) ?>"></textarea>
      <label class="switch-label">
        <input type="checkbox" data-progress-toggle>
        <span class="switch" aria-hidden="true"></span>
        <span><?= e(t('tasks.update_progress')) ?></span>
      </label>
      <div class="progress-edit" data-progress-edit hidden>
        <input class="pe-range" type="range" min="0" max="100" step="5" value="<?= (int) $task['progress'] ?>" data-progress-range aria-label="<?= e(t('common.progress')) ?>">
        <div class="pe-number">
          <input class="pe-input" type="number" name="progress" min="0" max="100" step="5" value="<?= (int) $task['progress'] ?>" data-progress-number disabled aria-label="<?= e(t('common.progress')) ?>">
          <span class="pe-suffix">%</span>
        </div>
        <div class="pe-quick">
          <?php foreach ([0, 25, 50, 75, 100] as $pe): ?>
            <button type="button" class="pe-chip" data-progress-set="<?= (int) $pe ?>"><?= (int) $pe ?>%</button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="reply-bar">
        <div class="reply-left">
          <label class="file-label">
            <input type="file" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip">
            📎 <?= e(t('common.attach')) ?> <span class="cell-muted">(<?= e(t('common.max_size')) ?>)</span>
          </label>
        </div>
        <button class="btn btn-primary" type="submit"><?= e(t('tasks.post_update')) ?></button>
      </div>
    </form>
  <?php endif; ?>
</section>

<?php if ($files): ?>
<section class="panel">
  <h2 class="panel-title"><?= e(t('common.attached_files')) ?></h2>
  <div class="post-attachments">
    <?php foreach ($files as $a): ?>
      <a class="att" href="<?= u('download&id=' . (int) $a['id']) ?>">📎 <?= e($a['original_name']) ?>
        <span class="cell-muted">(<?= round($a['size'] / 1024, 1) ?> KB)</span></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php layout_footer(); ?>
