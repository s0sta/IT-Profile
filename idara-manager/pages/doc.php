<?php
declare(strict_types=1);

/**
 * Idara — beginner guide. Route 'doc' is public: the page works signed in
 * (full workspace layout) and signed out (standalone shell with the language
 * switcher and a sign-in button).
 *
 * Content comes from lang/doc-ar.php (master) / lang/doc-en.php through daem_doc().
 */

$doc    = daem_doc();
$authed = (bool) Auth::current();
$site   = setting('site_name', t('app.name'));

if (!$doc) {
    http_response_code(500);
    exit(t('err.title'));
}

$docTitle    = (string) ($doc['title'] ?? t('doc.title'));
$docSubtitle = (string) ($doc['subtitle'] ?? '');
$docIntro    = (string) ($doc['intro'] ?? '');
$docCta      = (string) ($doc['cta'] ?? t('doc.start'));
$docToc      = (string) ($doc['toc'] ?? t('doc.toc'));
$sections    = (array) ($doc['sections'] ?? []);

/** Render one content block. */
$renderBlock = static function (array $block) use ($doc): string {
    $type = (string) ($block['type'] ?? 'p');
    switch ($type) {
        case 'ul':
            $html = '<ul class="doc-ul">';
            foreach ((array) ($block['items'] ?? []) as $item) {
                $html .= '<li>' . e((string) $item) . '</li>';
            }
            return $html . '</ul>';

        case 'ol':
            $html = '<ol class="doc-ol">';
            foreach ((array) ($block['items'] ?? []) as $item) {
                $html .= '<li>' . e((string) $item) . '</li>';
            }
            return $html . '</ol>';

        case 'note':
            return '<div class="doc-note"><span class="doc-note-icon">💡</span><span>' . e((string) ($block['text'] ?? '')) . '</span></div>';

        case 'flow':
            // The flow lives at document level (daem_doc()['flow']); a block-level
            // flow is accepted as a fallback.
            $flow = (array) ($doc['flow'] ?? $block);
            $html = '<div class="doc-flow">';
            foreach ((array) ($flow['steps'] ?? []) as $i => $step) {
                if ($i > 0) {
                    $html .= '<span class="doc-arrow" aria-hidden="true">→</span>';
                }
                $html .= '<span class="doc-step"><strong>' . e((string) ($step['label'] ?? '')) . '</strong><small>' . e((string) ($step['hint'] ?? '')) . '</small></span>';
            }
            $html .= '</div>';
            if (!empty($flow['reopen'])) {
                $html .= '<div class="doc-reopen">↩ ' . e((string) $flow['reopen']) . '</div>';
            }
            return $html;

        case 'p':
        default:
            return '<p class="doc-p">' . e((string) ($block['text'] ?? '')) . '</p>';
    }
};

ob_start();
?>
<div class="doc-hero">
  <span class="doc-kicker"><?= e(t('nav.doc')) ?></span>
  <h1 class="doc-title"><?= e($docTitle) ?></h1>
  <?php if ($docSubtitle !== ''): ?><p class="doc-subtitle"><?= e($docSubtitle) ?></p><?php endif; ?>
  <?php if ($docIntro !== ''): ?><p class="doc-intro"><?= e($docIntro) ?></p><?php endif; ?>
  <div class="doc-actions">
    <a class="btn btn-primary" href="<?= u($authed ? 'dashboard' : 'login') ?>"><?= e($docCta) ?> →</a>
    <?= daem_lang_switcher() ?>
  </div>
</div>

<?php if ($sections): ?>
<nav class="doc-toc" aria-label="<?= e($docToc) ?>">
  <p class="doc-toc-title"><?= e($docToc) ?></p>
  <ol>
    <?php foreach ($sections as $i => $section): ?>
      <li><a href="#doc-s<?= $i + 1 ?>"><?= e((string) ($section['title'] ?? '')) ?></a></li>
    <?php endforeach; ?>
  </ol>
</nav>
<?php endif; ?>

<?php foreach ($sections as $i => $section): ?>
  <section class="panel doc-section" id="doc-s<?= $i + 1 ?>">
    <h2 class="doc-h2"><span class="doc-icon"><?= e((string) ($section['icon'] ?? '')) ?></span> <?= e((string) ($section['title'] ?? '')) ?></h2>
    <?php foreach ((array) ($section['blocks'] ?? []) as $block): ?>
      <?= $renderBlock((array) $block) ?>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>

<p class="doc-foot"><?= e(t('nav.footer', ['year' => date('Y'), 'site' => $site])) ?></p>
<?php
$content = (string) ob_get_clean();

if ($authed) {
    layout_header($docTitle, 'doc');
    echo $content;
    layout_footer();
} else {
    ?>
<!doctype html>
<html lang="<?= e(daem_current_lang()) ?>" dir="<?= e(daem_lang_dir()) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($docTitle) ?> · <?= e($site) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css?v=1">
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
      <span class="brand-text"><?= e($site) ?><span class="brand-sub"><?= e(t('app.tagline')) ?></span></span>
    </a>
    <div class="topbar-actions">
      <?= daem_lang_switcher() ?>
      <a class="btn" href="<?= u('login') ?>"><?= e(t('auth.sign_in')) ?></a>
    </div>
  </header>
  <main class="doc-wrap">
    <?= $content ?>
  </main>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
<?php
}
