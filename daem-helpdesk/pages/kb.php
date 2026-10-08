<?php
declare(strict_types=1);

$q  = trim((string) ($_GET['q'] ?? ''));
$cat = (int) ($_GET['cat'] ?? 0) ?: null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$total = Kb::articleCount($cat, $q);
[$page, $pages] = paginate($total, $page, $perPage);
$articles   = Kb::articles($cat, $q, $perPage, ($page - 1) * $perPage);
$categories = Kb::categories();

layout_header(t('kb.title'), 'kb');
?>

<section class="grid-2 kb-layout">
  <aside class="panel panel-muted kb-side">
    <form method="get" action="<?= u('kb') ?>" class="kb-search">
    <input type="hidden" name="p" value="kb">
      <?= icon('search') ?>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('kb.search_placeholder')) ?>">
    </form>
    <h3 class="mini-title"><?= e(t('kb.categories')) ?></h3>
    <ul class="kb-cats">
      <li><a href="<?= u('kb') ?>" class="<?= $cat === null ? 'on' : '' ?>"><?= e(t('kb.all')) ?></a></li>
      <?php foreach ($categories as $c): ?>
        <li><a href="<?= u('kb') ?>&cat=<?= (int) $c['id'] ?>" class="<?= $cat === (int) $c['id'] ? 'on' : '' ?>"><?= e($c['name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <div class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e($q !== '' ? t('kb.search_results') : t('kb.articles')) ?></h2>
      <span class="result-count"><?= e($total === 1 ? t('kb.count_one') : t('kb.count', ['n' => $total])) ?></span>
    </div>

    <?php if (!$articles): ?>
      <div class="empty"><?= e(t('kb.empty')) ?></div>
    <?php else: ?>
      <ul class="article-list">
        <?php foreach ($articles as $a): ?>
          <li class="article-row">
            <a class="article-title" href="<?= u('article&slug=' . e($a['slug'])) ?>"><?= e($a['title']) ?></a>
            <span class="article-meta">
              <span class="badge badge-cat"><?= e($a['category_name'] ?? t('common.general')) ?></span>
              <?php if (!$a['published']): ?><span class="badge badge-draft"><?= e(t('kb.draft')) ?></span><?php endif; ?>
              <span class="cell-muted"><?= e(t('kb.views', ['n' => (int) $a['views']])) ?> · <?= e(t('kb.updated', ['when' => time_ago($a['updated_at'])])) ?></span>
            </span>
            <span class="article-excerpt"><?= e(excerpt(strip_tags($a['body']), 160)) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?><a class="page-btn" href="<?= u('kb') ?>&<?= e(keep_query()) ?>&page=<?= $page - 1 ?>">← <?= e(t('common.prev')) ?></a><?php endif; ?>
        <span class="page-info"><?= e(t('common.page')) ?> <?= $page ?> <?= e(t('common.of')) ?> <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="page-btn" href="<?= u('kb') ?>&<?= e(keep_query()) ?>&page=<?= $page + 1 ?>"><?= e(t('common.next')) ?> →</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php layout_footer(); ?>
