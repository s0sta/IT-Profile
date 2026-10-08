<?php
declare(strict_types=1);

$cats = Categories::all(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $subject     = trim((string) ($_POST['subject'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $categoryId  = (int) ($_POST['category_id'] ?? 0) ?: null;
    $priority    = (string) ($_POST['priority'] ?? 'medium');

    $errors = [];
    if ($subject === '' || mb_strlen($subject) > 250) {
        $errors[] = t('new.err_subject');
    }
    if ($description === '' || mb_strlen($description) < 10) {
        $errors[] = t('new.err_description');
    }
    if (!array_key_exists($priority, priorities())) {
        $errors[] = t('new.err_priority');
    }
    if ($categoryId !== null && !Categories::find($categoryId)) {
        $categoryId = null;
    }

    if (!$errors) {
        $id = Tickets::create(Auth::id(), $subject, $description, $categoryId, $priority);
        flash('success', t('new.created'));
        redirect('ticket&id=' . $id);
    }
}

layout_header(t('new.title'), 'tickets');
?>

<?php if (!empty($errors)): ?>
  <div class="flash flash-error"><ul style="margin:0;padding-left:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= e(t('new.heading')) ?></h2>
    <form method="post" action="<?= u('new') ?>">
      <?= csrf_field() ?>

      <label class="field-label" for="subject"><?= e(t('new.subject')) ?></label>
      <input class="input" id="subject" name="subject" type="text" maxlength="250" required
             value="<?= e($_POST['subject'] ?? '') ?>"
             placeholder="<?= e(t('new.subject_placeholder')) ?>"
             data-kb-search="1">
      <div id="kb-suggestions" class="kb-suggest" hidden></div>

      <label class="field-label" for="category_id"><?= e(t('new.category')) ?></label>
      <select class="input input-select" id="category_id" name="category_id">
        <option value=""><?= e(t('common.select')) ?></option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($_POST['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="priority"><?= e(t('new.priority')) ?></label>
      <select class="input input-select" id="priority" name="priority">
        <?php foreach (priorities() as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= ($_POST['priority'] ?? 'medium') === $k ? 'selected' : '' ?>>
            <?= e(t('new.priority_option', ['label' => $label, 'hours' => (float) sla_policy($k)['response_hours']])) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="field-label" for="description"><?= e(t('new.description')) ?></label>
      <textarea class="input" id="description" name="description" rows="7" required
                placeholder="<?= e(t('new.description_placeholder')) ?>"><?= e($_POST['description'] ?? '') ?></textarea>

      <button class="btn btn-primary" type="submit"><?= e(t('new.submit')) ?></button>
    </form>
  </div>

  <aside class="panel panel-muted">
    <h2 class="panel-title"><?= e(t('new.tips_title')) ?></h2>
    <p class="muted-text"><?= e(t('new.tips_text')) ?></p>
    <a class="btn btn-ghost" href="<?= u('kb') ?>"><?= e(t('new.browse_kb')) ?> →</a>
    <hr>
    <h3 class="mini-title"><?= e(t('new.next_title')) ?></h3>
    <ol class="steps">
      <li><?= e(t('new.next_1')) ?></li>
      <li><?= e(t('new.next_2')) ?></li>
      <li><?= e(t('new.next_3')) ?></li>
    </ol>
  </aside>
</section>

<?php layout_footer(); ?>
