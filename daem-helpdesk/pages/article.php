<?php
declare(strict_types=1);

$slug = trim((string) ($_GET['slug'] ?? ''));
$article = $slug !== '' ? Kb::articleBySlug($slug) : null;

if (!$article) {
    http_response_code(404);
    exit(t('kb.not_found'));
}
if (!$article['published'] && !Auth::isStaff()) {
    http_response_code(404);
    exit(t('kb.not_found'));
}

// Feedback vote (POST), once per user per article.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $vote = (int) ($_POST['vote'] ?? 0);
    if ($vote === 1 || $vote === -1) {
        if (Kb::vote((int) $article['id'], Auth::id(), $vote)) {
            flash('success', t('kb.thanks'));
        } else {
            flash('info', t('kb.already_voted'));
        }
    }
    redirect('article&slug=' . $slug);
}

Kb::registerView((int) $article['id']);
$related = Kb::articles((int) $article['category_id'], '', 4, 0, true);

layout_header($article['title'], 'kb');
?>

<article class="panel article-panel">
  <div class="article-head">
    <div>
      <h2 class="article-h"><?= e($article['title']) ?></h2>
      <div class="article-meta">
        <span class="badge badge-cat"><?= e($article['category_name'] ?? t('common.general')) ?></span>
        <span class="cell-muted"><?= e(t('kb.by', ['name' => $article['author_name'] ?? 'System'])) ?> · <?= e(t('kb.updated', ['when' => fmt_date($article['updated_at'])])) ?> · <?= e(t('kb.views', ['n' => (int) $article['views']])) ?></span>
        <?php if (!$article['published']): ?><span class="badge badge-draft"><?= e(t('kb.draft')) ?></span><?php endif; ?>
      </div>
    </div>
    <?php if (Auth::isStaff()): ?>
      <a class="btn btn-ghost" href="<?= u('admin/kb&edit=' . (int) $article['id']) ?>"><?= e(t('kb.edit')) ?></a>
    <?php endif; ?>
  </div>

  <div class="article-body"><?= render_body($article['body']) ?></div>

  <div class="article-feedback">
    <span class="muted-text"><?= e(t('kb.helpful_q')) ?></span>
    <form method="post" action="<?= u('article&slug=' . e($slug)) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="vote" value="1">
      <button class="btn btn-soft" type="submit">👍 <?= e(t('kb.yes')) ?> (<?= (int) $article['helpful'] ?>)</button>
    </form>
    <form method="post" action="<?= u('article&slug=' . e($slug)) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="vote" value="-1">
      <button class="btn btn-soft" type="submit">👎 <?= e(t('kb.no')) ?> (<?= (int) $article['not_helpful'] ?>)</button>
    </form>
  </div>
</article>

<?php if ($related): ?>
<section class="panel">
  <h2 class="panel-title"><?= e(t('kb.related')) ?></h2>
  <ul class="kb-mini">
    <?php foreach ($related as $r): ?>
      <?php if ((int) $r['id'] === (int) $article['id']) { continue; } ?>
      <li><a href="<?= u('article&slug=' . e($r['slug'])) ?>"><?= e($r['title']) ?></a><span class="cell-muted"><?= e(t('kb.views', ['n' => (int) $r['views']])) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php layout_footer(); ?>
