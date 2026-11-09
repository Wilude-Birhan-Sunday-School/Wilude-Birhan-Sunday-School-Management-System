<?php
/**
 * Public website language layer (Amharic + English).
 *
 * The site is bilingual. Amharic is the default: a visitor who has not chosen
 * a language is served Amharic, and the header switch (አማ / EN) records the
 * choice in a first-party cookie.
 *
 * How copy is written
 * -------------------
 * Every user-visible string is stored as a pair: L('English', 'አማርኛ').
 * Templates always print it through t(), which returns the string for the
 * active language:
 *
 *     <h2><?= e(t($content['about']['title'])) ?></h2>
 *     'title' => L('About the school', 'ስለ ትምህርት ቤቱ'),
 *
 * t() also accepts a plain string, so CMS rows and legacy content keep working
 * unchanged. When an Amharic translation is missing the English text is shown
 * rather than an empty element.
 *
 * Security notes
 * - Only 'am' and 'en' are ever accepted; anything else falls back to 'am'.
 * - The chosen language is never echoed into markup from request input: the
 *   templates print site_lang(), which is one of the two literals below.
 * - No session is started and nothing is written server-side.
 */

const WBSS_SITE_LANGS = ['am', 'en'];
const WBSS_SITE_LANG_DEFAULT = 'am';
const WBSS_SITE_LANG_COOKIE = 'wbss_lang';

/**
 * Active language: 'am' or 'en'.
 *
 * Precedence: explicit ?lang= (validated, then remembered) → cookie →
 * Amharic default. Decided before any output so the cookie can be set.
 */
function site_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }

    $requested = isset($_GET['lang']) ? strtolower(trim((string) $_GET['lang'])) : '';
    if (in_array($requested, WBSS_SITE_LANGS, true)) {
        $lang = $requested;
        if (!headers_sent()) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie(WBSS_SITE_LANG_COOKIE, $lang, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        return $lang;
    }

    $cookie = strtolower(trim((string) ($_COOKIE[WBSS_SITE_LANG_COOKIE] ?? '')));
    if (in_array($cookie, WBSS_SITE_LANGS, true)) {
        return $lang = $cookie;
    }

    return $lang = WBSS_SITE_LANG_DEFAULT;
}

function site_lang_is_am(): bool
{
    return site_lang() === 'am';
}

/** Build a bilingual string pair: L('English text', 'የአማርኛ ጽሑፍ'). */
function L(string $en, string $am = ''): array
{
    return ['en' => $en, 'am' => $am !== '' ? $am : $en];
}

/**
 * Resolve a string (plain or pair) for the active language.
 * Falls back to the other language when a translation is missing.
 */
function t($value): string
{
    if (is_array($value)) {
        $lang = site_lang();
        $text = trim((string) ($value[$lang] ?? ''));
        if ($text !== '') {
            return $text;
        }
        return trim((string) ($value[$lang === 'am' ? 'en' : 'am'] ?? ''));
    }
    return (string) ($value ?? '');
}

/** CMS rows: pick the column suffix that matches the active language. */
function cms_text(array $row, string $field): string
{
    $primary = trim((string) ($row[$field] ?? ''));
    $alternate = trim((string) ($row[$field . '_am'] ?? ''));
    if (site_lang_is_am()) {
        return $alternate !== '' ? $alternate : $primary;
    }
    return $primary !== '' ? $primary : $alternate;
}

/** Current request path with the language switch applied, for the switcher. */
function site_lang_url(string $lang): string
{
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $query = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_QUERY) ?: '');
    $params = [];
    if ($query !== '') {
        parse_str($query, $params);
    }
    unset($params['lang']);
    $params['lang'] = $lang;
    return $path . '?' . http_build_query($params);
}
