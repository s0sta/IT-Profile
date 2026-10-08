<?php
declare(strict_types=1);

/**
 * Language layer: English (en) · German (de) · Arabic (ar).
 *
 * Usage:  t('tickets.title')  ·  t('new.priority_option', ['hours' => 4])
 * Missing keys fall back to English; unknown keys return the key itself.
 */

const DAEM_LANGS = [
    'en' => ['label' => 'English', 'short' => 'EN', 'flag' => '🇬🇧', 'dir' => 'ltr', 'name' => 'English'],
    'de' => ['label' => 'Deutsch', 'short' => 'DE', 'flag' => '🇩🇪', 'dir' => 'ltr', 'name' => 'German'],
    'ar' => ['label' => 'العربية', 'short' => 'AR', 'flag' => '🇸🇦', 'dir' => 'rtl', 'name' => 'Arabic'],
];

function daem_langs(): array
{
    return DAEM_LANGS;
}

function daem_current_lang(): string
{
    static $lang = null;
    if ($lang === null) {
        $candidate = $_SESSION['lang'] ?? $_COOKIE['daem_lang'] ?? 'en';
        $lang = isset(DAEM_LANGS[$candidate]) ? (string) $candidate : 'en';
    }
    return $lang;
}

function daem_set_lang(string $code): void
{
    if (!isset(DAEM_LANGS[$code])) {
        $code = 'en';
    }
    $_SESSION['lang'] = $code;
    if (!headers_sent()) {
        setcookie('daem_lang', $code, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
    }
    // Reset the cached translation table for this request.
    $GLOBALS['daem_lang_changed'] = true;
}

function daem_lang_dir(): string
{
    return DAEM_LANGS[daem_current_lang()]['dir'] ?? 'ltr';
}

function daem_is_rtl(): bool
{
    return daem_lang_dir() === 'rtl';
}

/** @return array<string,string> */
function daem_translations(): array
{
    static $table = null;
    static $loadedFor = null;

    if ($table === null || $loadedFor !== daem_current_lang()) {
        $loadedFor = daem_current_lang();
        $table = [];

        $fallback = APP_ROOT . '/lang/en.php';
        if (is_file($fallback)) {
            $table = (array) require $fallback;
        }
        $file = APP_ROOT . '/lang/' . $loadedFor . '.php';
        if ($loadedFor !== 'en' && is_file($file)) {
            $table = array_merge($table, (array) require $file);
        }
    }
    return $table;
}

/** Translate a key, replacing {placeholders}. */
function t(string $key, array $vars = []): string
{
    $text = daem_translations()[$key] ?? $key;
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

/** Structured documentation content for the current language. */
function daem_doc(): array
{
    static $doc = null;
    static $loadedFor = null;

    if ($doc === null || $loadedFor !== daem_current_lang()) {
        $loadedFor = daem_current_lang();
        $en = APP_ROOT . '/lang/doc-en.php';
        $doc = is_file($en) ? (array) require $en : [];
        $file = APP_ROOT . '/lang/doc-' . $loadedFor . '.php';
        if ($loadedFor !== 'en' && is_file($file)) {
            $doc = (array) require $file;
        }
    }
    return $doc;
}

/** Language switcher markup (keeps the current page, drops ?lang). */
function daem_lang_switcher(): string
{
    $current = daem_current_lang();
    $qs = $_GET;
    unset($qs['lang']);
    $base = strtok((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'), '?');

    $html = '<div class="lang-switch" role="group" aria-label="' . e(t('nav.language')) . '">';
    foreach (DAEM_LANGS as $code => $meta) {
        $query = $qs;
        $query['lang'] = $code;
        $href = e($base . '?' . http_build_query($query));
        $class = $code === $current ? 'lang-btn on' : 'lang-btn';
        $html .= '<a class="' . $class . '" href="' . $href . '" title="' . e($meta['label']) . '" hreflang="' . e($code) . '">'
            . e($meta['short']) . '</a>';
    }
    $html .= '</div>';
    return $html;
}
