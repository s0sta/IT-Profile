<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'cat_add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', t('ak.err_name'));
        } elseif (!Kb::categoryCreate($name, trim((string) ($_POST['description'] ?? '')))) {
            flash('error', t('ak.err_duplicate'));
        } else {
            flash('success', t('ak.cat_created'));
        }
    } elseif ($action === 'cat_update') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', t('ak.err_name'));
        } elseif (!Kb::categoryUpdate((int) ($_POST['id'] ?? 0), $name, trim((string) ($_POST['description'] ?? '')))) {
            flash('error', t('ak.err_duplicate'));
        } else {
            flash('success', t('ak.cat_updated'));
        }
    } elseif ($action === 'cat_delete') {
        Kb::categoryDelete((int) ($_POST['id'] ?? 0));
        flash('success', t('ak.cat_deleted'));
    } elseif ($action === 'article_add' || $action === 'article_update') {
        $title    = trim((string) ($_POST['title'] ?? ''));
        $body     = trim((string) ($_POST['body'] ?? ''));
        $catId    = (int) ($_POST['category_id'] ?? 0) ?: null;
        $published = !empty($_POST['published']);
        if ($title === '' || $body === '') {
            flash('error', t('ak.err_fields'));
        } elseif ($action === 'article_add') {
            Kb::articleCreate(Auth::id(), (int) $catId, $title, $body, $published);
            flash('success', t('ak.created'));
        } else {
            Kb::articleUpdate((int) ($_POST['id'] ?? 0), (int) $catId, $title, $body, $published);
            flash('success', t('ak.updated_flash'));
        }
        redirect('admin/kb');
    } elseif ($action === 'article_delete') {
        Kb::articleDelete((int) ($_POST['id'] ?? 0));
        flash('success', t('ak.deleted'));
    }
    redirect('admin/kb');
}

$editId   = (int) ($_GET['edit'] ?? 0);
$editArt  = $editId ? Kb::articleFind($editId) : null;
$cats     = Kb::categories();
$articles = Kb::articles(null, '', 100, 0, false);

layout_header(t('ak.title'), 'admin/kb');
?>

<section class="grid-2">
  <div class="panel">
    <h2 class="panel-title"><?= $editArt ? e(t('ak.edit')) : e(t('ak.new')) ?></h2>
    <form method="post" action="<?= u('admin/kb') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editArt ? 'article_update' : 'article_add' ?>">
      <?php if ($editArt): ?><input type="hidden" name="id" value="<?= (int) $editArt['id'] ?>"><?php endif; ?>

      <label class="field-label"><?= e(t('ak.article_title')) ?></label>
      <input class="input" name="title" required value="<?= e($editArt['title'] ?? '') ?>">
      <label class="field-label"><?= e(t('ak.category')) ?></label>
      <select class="input input-select" name="category_id">
        <option value="0"><?= e(t('common.select')) ?></option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($editArt['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="field-label"><?= e(t('ak.body')) ?></label>
      <textarea class="input" name="body" rows="12" required><?= e($editArt['body'] ?? '') ?></textarea>
      <label class="check-label"><input type="checkbox" name="published" value="1" <?= $editArt === null || $editArt['published'] ? 'checked' : '' ?>> <?= e(t('ak.published')) ?></label>
      <button class="btn btn-primary" type="submit"><?= $editArt ? e(t('ak.save')) : e(t('ak.create')) ?></button>
      <?php if ($editArt): ?><a class="btn btn-ghost" href="<?= u('admin/kb') ?>"><?= e(t('ak.cancel')) ?></a><?php endif; ?>
    </form>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head">
        <h2 class="panel-title"><?= e(t('ak.categories')) ?></h2>
      </div>
      <form method="post" action="<?= u('admin/kb') ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cat_add">
        <input class="input input-sm" name="name" placeholder="<?= e(t('ak.new_category')) ?>" required>
        <input class="input input-sm" name="description" placeholder="<?= e(t('ak.category_description')) ?>">
        <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('ak.add')) ?></button>
      </form>
      <ul class="kb-cats" style="margin-top:12px">
        <?php foreach ($cats as $c): ?>
          <li>
            <span><?= e($c['name']) ?></span>
            <form method="post" action="<?= u('admin/kb') ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cat_delete">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-ghost btn-sm text-danger" type="submit" data-confirm="<?= e(t('ak.confirm_cat')) ?>"><?= e(t('ak.delete')) ?></button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="panel">
      <h2 class="panel-title"><?= e(t('ak.articles')) ?> (<?= count($articles) ?>)</h2>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e(t('ak.article_title')) ?></th><th><?= e(t('ak.category')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('ak.views')) ?></th><th><?= e(t('ak.updated')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($articles as $a): ?>
              <tr>
                <td class="cell-subject"><?= e($a['title']) ?></td>
                <td><?= e($a['category_name'] ?? '—') ?></td>
                <td><?= $a['published'] ? '<span class="badge badge-active">' . e(t('ak.published_state')) . '</span>' : '<span class="badge badge-draft">' . e(t('ak.draft')) . '</span>' ?></td>
                <td><?= (int) $a['views'] ?></td>
                <td class="cell-muted"><?= e(time_ago($a['updated_at'])) ?></td>
                <td class="cell-actions">
                  <a class="btn btn-ghost btn-sm" href="<?= u('admin/kb&edit=' . (int) $a['id']) ?>"><?= e(t('common.edit')) ?></a>
                  <form method="post" action="<?= u('admin/kb') ?>" class="inline-form" data-confirm="<?= e(t('ak.confirm_article')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="article_delete">
                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                    <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= e(t('ak.delete')) ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<?php layout_footer(); ?>
