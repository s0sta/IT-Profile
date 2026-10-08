<?php
declare(strict_types=1);

/**
 * Beginner documentation — public page (works with and without a login).
 * All texts come from lang/doc-<lang>.php (A1 level, English/German/Arabic).
 */

$doc    = daem_doc();
$authed = (bool) Auth::current();
$site   = setting('site_name', 'Daem');

if (!$doc) {
    http_response_code(500);
    exit(t('err.title'));
}

/** Render one content block. */
$renderBlock = static function (array $block) use ($doc): string {
    $type = $block['type'] ?? 'p';
    switch ($type) {
        case 'ul':
            $html = '<ul class="doc-ul">';
            foreach ($block['items'] ?? [] as $item) {
                $html .= '<li>' . e($item) . '</li>';
            }
            return $html . '</ul>';

        case 'ol':
            $html = '<ol class="doc-ol">';
            foreach ($block['items'] ?? [] as $item) {
                $html .= '<li>' . e($item) . '</li>';
            }
            return $html . '</ol>';

        case 'note':
            return '<div class="doc-note"><span class="doc-note-icon">💡</span><span>' . e($block['text'] ?? '') . '</span></div>';

        case 'flow':
            $flow = $doc['flow'] ?? ['steps' => [], 'reopen' => ''];
            $html = '<div class="doc-flow">';
            foreach ($flow['steps'] as $i => $step) {
                if ($i > 0) {
                    $html .= '<span class="doc-arrow" aria-hidden="true">→</span>';
                }
                $html .= '<span class="doc-step"><strong>' . e($step['label']) . '</strong><small>' . e($step['hint']) . '</small></span>';
            }
            $html .= '</div>';
            if (!empty($flow['reopen'])) {
                $html .= '<div class="doc-reopen">↩ ' . e($flow['reopen']) . '</div>';
            }
            return $html;

        case 'p':
        default:
            return '<p class="doc-p">' . e($block['text'] ?? '') . '</p>';
    }
};

ob_start();
?>
<div class="doc-hero">
  <span class="doc-kicker"><?= e(t('nav.doc')) ?></span>
  <h1 class="doc-title"><?= e($doc['title']) ?></h1>
  <p class="doc-subtitle"><?= e($doc['subtitle']) ?></p>
  <p class="doc-intro"><?= e($doc['intro']) ?></p>
  <div class="doc-actions">
    <a class="btn btn-primary" href="<?= u($authed ? 'dashboard' : 'login') ?>"><?= e($doc['cta']) ?> →</a>
    <?= daem_lang_switcher() ?>
  </div>
</div>

<nav class="doc-toc" aria-label="<?= e($doc['toc']) ?>">
  <p class="doc-toc-title"><?= e($doc['toc']) ?></p>
  <ol>
    <?php foreach ($doc['sections'] as $i => $section): ?>
      <li><a href="#doc-s<?= $i + 1 ?>"><?= e($section['title']) ?></a></li>
    <?php endforeach; ?>
  </ol>
</nav>

<?php foreach ($doc['sections'] as $i => $section): ?>
  <section class="panel doc-section" id="doc-s<?= $i + 1 ?>">
    <h2 class="doc-h2"><span class="doc-icon"><?= e($section['icon']) ?></span> <?= e($section['title']) ?></h2>
    <?php foreach ($section['blocks'] as $block): ?>
      <?= $renderBlock($block) ?>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>

<p class="doc-foot"><?= e(t('nav.footer', ['year' => date('Y'), 'site' => $site])) ?></p>
<?php
$content = (string) ob_get_clean();

if ($authed) {
    layout_header($doc['title'], 'doc');
    echo $content;
    layout_footer();
} else {
    ?>
<!doctype html>
<html lang="<?= e(daem_current_lang()) ?>" dir="<?= e(daem_lang_dir()) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($doc['title']) ?> · <?= e($site) ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=2">
<script>
(function(){try{var t=localStorage.getItem('daem-theme');
if(!t){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}
document.documentElement.setAttribute('data-theme',t);}catch(err){}})();
</script>
</head>
<body class="doc-body">
  <header class="doc-topbar">
    <a class="brand" href="<?= u('login') ?>">
      <span class="brand-logo">◈</span>
      <span class="brand-text"><?= e($site) ?><span class="brand-sub"><?= e(t('brand.tagline')) ?></span></span>
    </a>
    <div class="topbar-actions">
      <?= daem_lang_switcher() ?>
      <a class="btn" href="<?= u('login') ?>"><?= e(t('auth.sign_in')) ?></a>
    </div>
  </header>
  <main class="doc-wrap">
    <?= $content ?>
  </main>
<script src="assets/js/app.js?v=2"></script>
</body>
</html>
<?php
}
