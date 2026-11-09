<?php
/**
 * Public website bootstrap, layout and helpers.
 *
 * Included by every public page (index.php, about.php, programs.php, ...).
 * It is not a page itself: requesting it directly returns 404.
 *
 * Responsibilities
 * - load the school identity constants and the editorial content file;
 * - read the existing CMS tables in a read-only, fail-soft way;
 * - render the shared document head (SEO, JSON-LD), header, navigation,
 *   breadcrumbs, calls to action and footer.
 *
 * Security notes
 * - Every database read is a fixed SELECT; no request value ever reaches SQL.
 * - Every echo of stored or submitted text passes through e() escaping.
 * - No CSRF token is invented here: public forms post to the existing
 *   register_submit.php handler, which owns validation and rate limiting.
 */

if (PHP_SAPI !== 'cli') {
    $wbssSelf = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($wbssSelf !== false && $wbssSelf === realpath(__FILE__)) {
        http_response_code(404);
        exit;
    }
}

require_once __DIR__ . '/school_config.php';
require_once __DIR__ . '/branding_defaults.php';
require_once __DIR__ . '/site_lang.php';

/** @var array $WBSS_CONTENT Editorial content, editable without touching layout. */
$WBSS_CONTENT = require __DIR__ . '/site_content.php';

// Public site brand palette. Optional overrides live in school_config.php;
// these defaults are the approved WBSS green and gold.
if (!defined('PUBLIC_SITE_PRIMARY'))       define('PUBLIC_SITE_PRIMARY', '#004820');
if (!defined('PUBLIC_SITE_PRIMARY_DARK'))  define('PUBLIC_SITE_PRIMARY_DARK', '#003018');
if (!defined('PUBLIC_SITE_ACCENT'))        define('PUBLIC_SITE_ACCENT', '#F0B818');
if (!defined('PUBLIC_SITE_ASSET_VERSION')) define('PUBLIC_SITE_ASSET_VERSION', '20261103');

// ── CMS content (read-only, fail-soft) ────────────────────────────────────
$WBSS_CMS = [
    'available' => false,
    'programs'  => [],
    'teachers'  => [],
    'social'    => [],
    'news'      => [],
];

$_wbssCmsConfig = __DIR__ . '/admin/config.php';
if (file_exists($_wbssCmsConfig)) {
    require_once $_wbssCmsConfig;
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $WBSS_CMS['available'] = true;
        $_wbssFetch = static function (mysqli $db, string $sql): array {
            $rows = [];
            try {
                if ($res = $db->query($sql)) {
                    while ($row = $res->fetch_assoc()) {
                        $rows[] = $row;
                    }
                    $res->free();
                }
            } catch (Throwable $error) {
                // A table that has not been created yet is not a page error.
                error_log('public site cms: ' . $error->getMessage());
            }
            return $rows;
        };
        $WBSS_CMS['programs'] = $_wbssFetch($conn, 'SELECT * FROM cms_programs WHERE is_active=1 ORDER BY sort_order, id');
        $WBSS_CMS['teachers'] = $_wbssFetch($conn, 'SELECT * FROM cms_teachers WHERE is_active=1 ORDER BY sort_order, id');
        $WBSS_CMS['social']   = $_wbssFetch($conn, 'SELECT * FROM cms_social_links WHERE is_active=1 AND url IS NOT NULL AND url NOT IN (\'\', \'#\') ORDER BY sort_order, id');
        // Optional table: dated announcements appear automatically once the
        // school creates cms_announcements (see sql/053_public_announcements.sql).
        $WBSS_CMS['news']     = $_wbssFetch($conn, 'SELECT * FROM cms_announcements WHERE is_active=1 ORDER BY COALESCE(published_on, DATE(created_at)) DESC, id DESC LIMIT 12');
    }
}

// Escaping helper. The application bootstrap (config.php) defines e(); this
// fallback keeps the public pages working if that bootstrap is absent. It must
// be declared AFTER the CMS include so the two definitions never collide.
if (!function_exists('e')) {
    function e($value, $default = ''): string
    {
        $value = ($value === null || $value === '') ? $default : $value;
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ── Small helpers ─────────────────────────────────────────────────────────
function site_content(): array
{
    return $GLOBALS['WBSS_CONTENT'];
}

function site_cms(): array
{
    return $GLOBALS['WBSS_CMS'];
}

/**
 * Interface string from the 'ui' dictionary, resolved for the active
 * language. Every button, label and aria-label on the site comes from here,
 * so no English text is left hard-coded in the templates.
 */
function ui(string $key): string
{
    return t(site_content()['ui'][$key] ?? '');
}

/** The name of the organisation in the active language. */
function site_school_name(): string
{
    return site_lang_is_am() ? SCHOOL_NAME_AMHARIC : (SCHOOL_TRANSLATION_EN . ' ' . SCHOOL_TYPE);
}

/** The parish name in the active language. */
function site_parish_name(): string
{
    return site_lang_is_am() ? PARISH_NAME_AM : PARISH_NAME_EN;
}

/** Deployment base path ('' at a domain root, '/school' behind an Alias). */
function site_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $base = ($dir === '.' || $dir === '/') ? '' : $dir;
    if (!preg_match('~^(?:/[A-Za-z0-9_-]+)*$~D', $base)) {
        $base = '';
    }
    return $base;
}

/** Absolute site path for an asset or handler (e.g. '/css/wbss-site.css'). */
function site_url(string $path = '/'): string
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    return site_base() . $path;
}

/** Public URL for a clean page route ('/' stays '/'). */
function site_page(string $path = '/'): string
{
    $path = rtrim($path, '/');
    return site_url($path === '' ? '/' : $path);
}

function site_asset(string $path): string
{
    return site_page($path) . '?v=' . rawurlencode(PUBLIC_SITE_ASSET_VERSION);
}

function site_canonical(): string
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $path = preg_replace('~/+~', '/', $path);
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
    return 'https://' . SITE_DOMAIN . $path;
}

function site_contact(string $key): array
{
    return site_content()['contact'][$key] ?? [];
}

function site_page_index(): array
{
    $pages = [];
    foreach (site_content()['pages'] as $page) {
        $pages[$page['path']] = $page;
    }
    return $pages;
}

/** Navigation labels come from the single page index. */
function site_nav(): array
{
    $items = [];
    foreach (site_content()['pages'] as $page) {
        if (!empty($page['nav'])) {
            $items[] = ['path' => $page['path'], 'title' => $page['title']];
        }
    }
    return $items;
}

function site_is_current(string $path, string $slug): bool
{
    return rtrim($path, '/') === rtrim($slug === '/' ? '' : $slug, '/');
}

/** Inline SVG icon set — no icon font, no external request. */
function site_icon(string $name, string $class = ''): string
{
    $paths = [
        'book'      => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H19v15.5H6.5A2.5 2.5 0 0 0 4 21z"/><path d="M19 18.5H6.5A2.5 2.5 0 0 0 4 21"/><path d="M8 7.5h7M8 11h5"/>',
        'cross'     => '<path d="M12 3v18M7.5 8.5h9"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M17.5 20a5.6 5.6 0 0 0-2-4.3"/>',
        'music'     => '<path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9S14.5 18.4 12 21c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
        'calendar'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'pin'       => '<path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'phone'     => '<path d="M6.6 3.5h3l1.5 4-2 1.4a11 11 0 0 0 5.9 5.9l1.4-2 4 1.5v3a2.5 2.5 0 0 1-2.7 2.5C11.4 19.2 4.8 12.6 4.1 6.2A2.5 2.5 0 0 1 6.6 3.5z"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 6.5 8.2 6 8.2-6"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5l3.5 2"/>',
        'search'    => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'     => '<path d="m6 6 12 12M18 6 6 18"/>',
        'arrow'     => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'chevron'   => '<path d="m6 9 6 6 6-6"/>',
        'check'     => '<path d="m4.5 12.5 5 5 10-11"/>',
        'heart'     => '<path d="M12 20s-7-4.4-7-9.3A4.2 4.2 0 0 1 12 7.6a4.2 4.2 0 0 1 7 3.1C19 15.6 12 20 12 20z"/>',
        'shield'    => '<path d="M12 3 5 6v6c0 4.4 3 7.6 7 9 4-1.4 7-4.6 7-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'sparkle'   => '<path d="M12 3.5 13.7 9l5.5 1.7-5.5 1.7L12 18l-1.7-5.6L4.8 10.7 10.3 9z"/>',
        'quote'     => '<path d="M9 7.5c-2.5.8-4 3-4 5.7 0 2 1.2 3.3 2.9 3.3 1.5 0 2.6-1.1 2.6-2.6 0-1.4-1-2.4-2.3-2.4-.3 0-.6 0-.8.1.3-1 1.2-1.9 2.4-2.4zM18 7.5c-2.5.8-4 3-4 5.7 0 2 1.2 3.3 2.9 3.3 1.5 0 2.6-1.1 2.6-2.6 0-1.4-1-2.4-2.3-2.4-.3 0-.6 0-.8.1.3-1 1.2-1.9 2.4-2.4z"/>',
        'image'     => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.8"/><path d="m4.5 17.5 4.6-4.3 3.4 3.1 3-2.8 4.5 4"/>',
        'external'  => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14.5V19a1.5 1.5 0 0 1-1.5 1.5H5.5A1.5 1.5 0 0 1 4 19V7.5A1.5 1.5 0 0 1 5.5 6H10"/>',
        'lock'      => '<rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
        'staff'     => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/>',
    ];
    $body = $paths[$name] ?? $paths['sparkle'];
    return '<svg class="ico' . ($class === '' ? '' : ' ' . e($class)) . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $body . '</svg>';
}

/** Text for an optional CMS row, falling back to the English field. */
function site_localised(array $row, string $field, string $suffix = '_am'): string
{
    $value = trim((string)($row[$field] ?? ''));
    $am = trim((string)($row[$field . $suffix] ?? ''));
    return $value !== '' ? $value : $am;
}

// ── Document head ─────────────────────────────────────────────────────────
function site_head(array $page): void
{
    $content = site_content();
    $title = t($page['title_tag'] ?? ($page['title'] . ' · ' . SCHOOL_NAME));
    $description = t($page['description'] ?? $content['home']['lead']);
    $canonical = site_canonical();
    $ogImage = 'https://' . SITE_DOMAIN . '/themes/fkss/assets/wbss-hero-courtyard.jpg';
    $needsGallery = !empty($page['gallery']);
    ?>
<!DOCTYPE html>
<html lang="<?= e(site_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<link rel="alternate" hreflang="am" href="<?= e($canonical) ?>?lang=am">
<link rel="alternate" hreflang="en" href="<?= e($canonical) ?>?lang=en">
<link rel="alternate" hreflang="x-default" href="<?= e($canonical) ?>">
<meta name="robots" content="index,follow">
<meta name="theme-color" content="<?= e(PUBLIC_SITE_PRIMARY) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SCHOOL_NAME) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:locale" content="<?= site_lang_is_am() ? 'am_ET' : 'en_ET' ?>">
<meta property="og:locale:alternate" content="<?= site_lang_is_am() ? 'en_ET' : 'am_ET' ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<link rel="icon" href="<?= e(site_page(THEME_BASE_PATH . '/assets/logos/icon-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(site_page(THEME_BASE_PATH . '/assets/logos/icon-192.png')) ?>">
<script>document.documentElement.classList.add('js');</script>
<link rel="stylesheet" href="<?= e(site_asset('/css/wbss-site.css')) ?>">
<?php if ($needsGallery): ?>
<link rel="stylesheet" href="<?= e(site_asset('/css/public_gallery.css')) ?>">
<?php endif; ?>
<script type="application/ld+json"><?= site_json_ld($page) ?></script>
</head>
<?php
}

function site_json_ld(array $page): string
{
    $content = site_content();
    $contact = $content['contact'];
    $organisation = [
        '@context' => 'https://schema.org',
        '@type' => 'EducationalOrganization',
        '@id' => 'https://' . SITE_DOMAIN . '/#school',
        'name' => site_school_name(),
        'alternateName' => [SCHOOL_NAME_AMHARIC, SCHOOL_NAME_SHORT],
        'description' => t($content['home']['lead']),
        'url' => 'https://' . SITE_DOMAIN . '/',
        'logo' => 'https://' . SITE_DOMAIN . SCHOOL_LOGO_PATH,
        'parentOrganization' => [
            '@type' => 'Church',
            'name' => PARISH_NAME_EN,
            'alternateName' => PARISH_NAME_AM,
        ],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => t($contact['address_lines'][0] ?? ''),
            'addressLocality' => 'Addis Ababa',
            'addressCountry' => 'ET',
        ],
        'areaServed' => 'Addis Ababa',
    ];
    $office = [];
    foreach ($contact['office_hours'] as $hours) {
        $office[] = ['@type' => 'OpeningHoursSpecification', 'description' => t($hours['day']) . ' ' . t($hours['time'])];
    }
    if ($office) {
        $organisation['openingHoursSpecification'] = $office;
    }
    $email = (string)($contact['email_primary']['value'] ?? '');
    if ($email !== '' && str_contains($email, '@')) {
        $organisation['email'] = $email;
    }
    $graph = [$organisation, [
        '@type' => 'WebSite',
        '@id' => 'https://' . SITE_DOMAIN . '/#website',
        'url' => 'https://' . SITE_DOMAIN . '/',
        'name' => SCHOOL_NAME,
        'inLanguage' => site_lang(),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => 'https://' . SITE_DOMAIN . '/search?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ]];
    if (!empty($page['breadcrumbs'])) {
        $items = [];
        foreach ($page['breadcrumbs'] as $i => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['label'],
                'item' => 'https://' . SITE_DOMAIN . ($crumb['path'] === '/' ? '/' : $crumb['path']),
            ];
        }
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
    $payload = ['@context' => 'https://schema.org', '@graph' => $graph];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    return $json === false ? '{}' : $json;
}

/**
 * Language switch: two links, the active language marked for assistive tech.
 * Amharic is the default, so አማ comes first.
 */
function site_lang_switch(string $class = ''): void
{
    ?>
    <div class="lang-switch<?= $class === '' ? '' : ' ' . e($class) ?>" role="group" aria-label="<?= e(ui('language')) ?>">
        <a href="<?= e(site_lang_url('am')) ?>" lang="am" hreflang="am"<?= site_lang_is_am() ? ' class="is-active" aria-current="true"' : '' ?>>አማ</a>
        <a href="<?= e(site_lang_url('en')) ?>" lang="en" hreflang="en"<?= site_lang_is_am() ? '' : ' class="is-active" aria-current="true"' ?>>EN</a>
    </div>
    <?php
}

// ── Header, notice bar and navigation ─────────────────────────────────────
function site_header(array $page): void
{
    $content = site_content();
    $slug = $page['slug'] ?? '/';
    $nav = site_nav();
    $phone = $content['contact']['phone_primary']['value'] ?? '';
    ?>
<body class="site"
      data-msg-required="<?= e(ui('msg_required')) ?>"
      data-msg-email="<?= e(ui('msg_email')) ?>"
      data-msg-sending="<?= e(ui('msg_sending')) ?>"
      data-msg-failed="<?= e(ui('msg_failed')) ?>"
      data-msg-joining="<?= e(ui('msg_joining')) ?>">
<a class="skip-link" href="#main"><?= e(ui('skip_link')) ?></a>
<div class="topbar">
    <div class="wrap topbar__inner">
        <p class="topbar__identity"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e(site_parish_name()) ?></p>
        <ul class="topbar__links">
            <?php if ($phone !== ''): ?>
            <li><?= site_icon('phone') ?><span><?= e($phone) ?></span></li>
            <?php endif; ?>
            <li><a href="<?= e(site_page('/contact')) ?>"><?= e(ui('contact')) ?></a></li>
        </ul>
    </div>
</div>
<header class="site-header" id="site-header" data-header>
    <div class="wrap site-header__inner">
        <a class="brand" href="<?= e(site_page('/')) ?>" aria-label="<?= e(site_school_name() . ' — ' . ui('home_aria')) ?>">
            <img class="brand__mark" src="<?= e(site_page(SCHOOL_LOGO_PATH)) ?>" alt="" width="52" height="52" decoding="async">
            <span class="brand__text">
                <span class="brand__name"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e(site_lang_is_am() ? SCHOOL_NAME_SHORT_AM : SCHOOL_NAME_SHORT) ?></span>
                <span class="brand__sub"><?= e(site_lang_is_am() ? SCHOOL_TYPE_AM : (SCHOOL_TRANSLATION_EN . ' ' . SCHOOL_TYPE)) ?></span>
            </span>
        </a>
        <nav class="primary-nav" aria-label="<?= e(ui('primary_nav')) ?>">
            <ul>
                <?php foreach ($nav as $item): ?>
                <li>
                    <a href="<?= e(site_page($item['path'])) ?>"<?= site_is_current($item['path'], $slug) ? ' class="is-active" aria-current="page"' : '' ?>>
                        <?= e(t($item['title'])) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="header-actions">
            <?php site_lang_switch('lang-switch--header'); ?>
            <a class="header-search-btn" href="<?= e(site_page('/search')) ?>" aria-label="<?= e(ui('search_site')) ?>" title="<?= e(ui('search')) ?>">
                <?= site_icon('search') ?>
            </a>
            <a class="btn btn--primary btn--sm header-cta" href="<?= e(site_page('/admissions')) ?>"><?= e(ui('register')) ?></a>
            <button type="button" class="nav-toggle" id="nav-toggle" aria-controls="mobile-drawer" aria-expanded="false" aria-label="<?= e(ui('open_menu')) ?>" data-label-open="<?= e(ui('open_menu')) ?>" data-label-close="<?= e(ui('close_menu')) ?>">
                <span class="nav-toggle__open"><?= site_icon('menu') ?></span>
                <span class="nav-toggle__close"><?= site_icon('close') ?></span>
            </button>
        </div>
    </div>
</header>
<div class="drawer" id="mobile-drawer" hidden>
    <div class="drawer__panel" role="dialog" aria-modal="true" aria-label="<?= e(ui('mobile_nav')) ?>">
        <form class="drawer__search" role="search" method="get" action="<?= e(site_page('/search')) ?>">
            <label class="visually-hidden" for="drawer-search-input"><?= e(ui('search_site')) ?></label>
            <input id="drawer-search-input" type="search" name="q" placeholder="<?= e(ui('search_placeholder')) ?>">
            <button type="submit" aria-label="<?= e(ui('search')) ?>"><?= site_icon('search') ?></button>
        </form>
        <nav aria-label="<?= e(ui('primary_nav')) ?>">
            <ul class="drawer__nav">
                <?php foreach ($nav as $item): ?>
                <li>
                    <a href="<?= e(site_page($item['path'])) ?>"<?= site_is_current($item['path'], $slug) ? ' class="is-active" aria-current="page"' : '' ?>>
                        <span><?= e(t($item['title'])) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <a class="btn btn--primary btn--block" href="<?= e(site_page('/admissions')) ?>"><?= e(ui('register_request')) ?></a>
        <?php site_lang_switch('lang-switch--drawer'); ?>
        <div class="drawer__meta">
            <p><?= site_icon('pin') ?><?= e(t($content['contact']['address_lines'][0])) ?></p>
            <?php if ($phone !== ''): ?><p><?= site_icon('phone') ?><?= e($phone) ?></p><?php endif; ?>
        </div>
    </div>
</div>
<main id="main">
<?php
}

function site_breadcrumbs(array $trail): void
{
    if (!$trail) {
        return;
    }
    ?>
<nav class="breadcrumbs" aria-label="<?= e(ui('breadcrumb')) ?>">
    <div class="wrap">
        <ol>
            <?php foreach ($trail as $i => $crumb): ?>
            <li>
                <?php if ($i < count($trail) - 1): ?>
                <a href="<?= e(site_page($crumb['path'])) ?>"><?= e(t($crumb['label'])) ?></a>
                <?php else: ?>
                <span aria-current="page"><?= e(t($crumb['label'])) ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</nav>
<?php
}

/** Inner page hero used by every page except the home page. */
function site_page_hero($eyebrow, $title, $lead = ''): void
{
    $eyebrow = t($eyebrow);
    $title = t($title);
    $lead = t($lead);
    ?>
<section class="page-hero">
    <div class="wrap page-hero__inner">
        <?php if ($eyebrow !== ''): ?><p class="eyebrow eyebrow--gold"><?= e($eyebrow) ?></p><?php endif; ?>
        <h1 class="page-hero__title"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e($title) ?></h1>
        <?php if ($lead !== ''): ?><p class="page-hero__lead"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e($lead) ?></p><?php endif; ?>
    </div>
</section>
<?php
}

function site_cta_band($title = '', $text = ''): void
{
    $title = t($title !== '' && $title !== null ? $title : ui('cta_title'));
    $text = t($text !== '' && $text !== null ? $text : ui('cta_text'));
    ?>
<section class="cta-band">
    <div class="wrap cta-band__inner">
        <div>
            <p class="eyebrow eyebrow--gold"><?= e(ui('register_request')) ?></p>
            <h2><?= e($title) ?></h2>
            <p><?= e($text) ?></p>
        </div>
        <div class="cta-band__actions">
            <a class="btn btn--gold" href="<?= e(site_page('/admissions')) ?>"><?= e(ui('register_request')) ?> <?= site_icon('arrow') ?></a>
            <a class="btn btn--ghost" href="<?= e(site_page('/contact')) ?>"><?= e(ui('cta_contact')) ?></a>
        </div>
    </div>
</section>
<?php
}

function site_footer(): void
{
    $content = site_content();
    $contact = $content['contact'];
    $pages = site_page_index();
    $social = site_cms()['social'];
    ?>
</main>
<footer class="site-footer">
    <div class="wrap site-footer__grid">
        <div class="site-footer__brand">
            <a class="brand brand--footer" href="<?= e(site_page('/')) ?>">
                <img class="brand__mark" src="<?= e(site_page(SCHOOL_LOGO_PATH)) ?>" alt="" width="56" height="56" decoding="async">
                <span class="brand__text">
                    <span class="brand__name"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e(site_school_name()) ?></span>
                    <span class="brand__sub"><?= e(site_parish_name()) ?></span>
                </span>
            </a>
            <p class="site-footer__blurb"><?= e(t($content['footer']['blurb'])) ?></p>
            <?php if ($social): ?>
            <ul class="site-footer__social">
                <?php foreach ($social as $link): ?>
                <li>
                    <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($link['label'] ?: $link['platform']) ?>">
                        <?= e($link['label'] ?: $link['platform']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php foreach ($content['footer']['columns'] as $column): ?>
        <nav class="site-footer__col" aria-label="<?= e(t($column['title'])) ?>">
            <h2><?= e(t($column['title'])) ?></h2>
            <ul>
                <?php foreach ($column['paths'] as $path): ?>
                <?php if (!isset($pages[$path])) continue; ?>
                <li><a href="<?= e(site_page($path)) ?>"><?= e(t($pages[$path]['title'])) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endforeach; ?>
        <div class="site-footer__col">
            <h2><?= e(ui('visit_contact')) ?></h2>
            <address>
                <?php foreach ($contact['address_lines'] as $line): ?>
                <span><?= e(t($line)) ?></span>
                <?php endforeach; ?>
                <?php if (!empty($contact['phone_primary']['value'])): ?>
                <span><?= site_icon('phone') ?><?= e($contact['phone_primary']['value']) ?></span>
                <?php endif; ?>
                <?php if (!empty($contact['email_primary']['value'])): ?>
                <span><?= site_icon('mail') ?><a href="<?= e($contact['email_primary']['href']) ?>"><?= e($contact['email_primary']['value']) ?></a></span>
                <?php endif; ?>
            </address>
            <p class="site-footer__hours">
                <?php foreach ($contact['office_hours'] as $hours): ?>
                <span><?= e(t($hours['day'])) ?>: <?= e(t($hours['time'])) ?></span>
                <?php endforeach; ?>
            </p>
        </div>
    </div>
    <div class="site-footer__bar">
        <div class="wrap site-footer__bar-inner">
            <p>&copy; <?= e(COPYRIGHT_YEAR) ?> <?= e(site_school_name()) ?><?= site_lang_is_am() ? '' : ' · ' . e(SCHOOL_NAME_AMHARIC) ?></p>
            <ul>
                <li><a href="<?= e(site_page('/privacy')) ?>"><?= e(ui('privacy_link')) ?></a></li>
            </ul>
        </div>
    </div>
</footer>
<button type="button" class="to-top" id="to-top" hidden aria-label="<?= e(ui('back_to_top')) ?>"><?= site_icon('chevron') ?></button>
<script src="<?= e(site_asset('/js/wbss-site.js')) ?>" defer></script>
<?php if (!empty($GLOBALS['WBSS_PAGE_GALLERY'])): ?>
<script src="<?= e(site_asset('/js/public_gallery.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
<?php
}

/**
 * Shared registration / enquiry form.
 *
 * $mode 'registration' posts the child's details used by the admissions page;
 *      'enquiry'      posts a general message from the contact page.
 * Both post to the existing, already-validated public endpoint.
 */
function site_form(string $mode = 'registration', $title = ''): void
{
    $content = site_content();
    $levels = $content['admissions']['form']['levels'] ?? [];
    $isEnquiry = $mode === 'enquiry';
    $endpoint = site_url('/register_submit.php');
    $prefix = $isEnquiry ? 'enq' : 'reg';
    $heading = $title !== '' ? t($title) : ($isEnquiry ? ui('enquiry_title') : t($content['admissions']['form']['title']));
    $note = $isEnquiry ? ui('enquiry_note') : t($content['admissions']['form']['note']);
    ?>
<form class="site-form" id="<?= $isEnquiry ? 'contact-form' : 'registration-form' ?>" method="post" action="<?= e($endpoint) ?>"
      data-endpoint="<?= e($endpoint) ?>" novalidate>
    <div class="site-form__head">
        <h2><?= e($heading) ?></h2>
        <p><?= e($note) ?></p>
    </div>
    <div class="form-status" data-form-status role="status" aria-live="polite" hidden></div>
    <input type="hidden" name="lang" value="<?= e(site_lang()) ?>">
    <div class="field-row">
        <div class="field">
            <label for="<?= $prefix ?>-name"><?= e($isEnquiry ? ui('your_name') : ui('your_name_full')) ?> <span aria-hidden="true">*</span></label>
            <input id="<?= $prefix ?>-name" type="text" name="full_name" required maxlength="150" autocomplete="name">
        </div>
        <div class="field">
            <label for="<?= $prefix ?>-phone"><?= e(ui('phone')) ?> <span aria-hidden="true">*</span></label>
            <input id="<?= $prefix ?>-phone" type="tel" name="phone" required maxlength="30" autocomplete="tel" placeholder="+251 …">
        </div>
    </div>
    <?php if (!$isEnquiry): ?>
    <div class="field-row">
        <div class="field">
            <label for="reg-child"><?= e(ui('joining_name')) ?> <span aria-hidden="true">*</span></label>
            <input id="reg-child" type="text" name="child_name" required maxlength="150" autocomplete="off"
                   aria-describedby="reg-child-hint">
            <p class="field__hint" id="reg-child-hint"><?= e(ui('joining_hint')) ?></p>
        </div>
        <div class="field">
            <label for="reg-age"><?= e(ui('age')) ?></label>
            <input id="reg-age" type="number" name="age" min="1" max="120" step="1" inputmode="numeric"
                   aria-describedby="reg-age-hint">
            <p class="field__hint" id="reg-age-hint"><?= e(ui('age_hint')) ?></p>
        </div>
    </div>
    <div class="field-row">
        <div class="field">
            <label for="reg-program"><?= e(ui('join_heading')) ?></label>
            <select id="reg-program" name="program_interest">
                <option value=""><?= e(ui('select_one')) ?></option>
                <?php foreach ($levels as $level): ?>
                <option value="<?= e((string) ($level['value'] ?? '')) ?>"><?= e(t($level['label'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="reg-gender"><?= e(ui('gender')) ?></label>
            <select id="reg-gender" name="gender">
                <option value=""><?= e(ui('prefer_not_say')) ?></option>
                <option value="male"><?= e(ui('male')) ?></option>
                <option value="female"><?= e(ui('female')) ?></option>
            </select>
        </div>
    </div>
    <?php else: ?>
    <input type="hidden" name="program_interest" value="General enquiry">
    <?php endif; ?>
    <div class="field-row">
        <div class="field">
            <label for="<?= $prefix ?>-email"><?= e(ui('email')) ?></label>
            <input id="<?= $prefix ?>-email" type="email" name="email" maxlength="120" autocomplete="email">
        </div>
        <div class="field">
            <label for="<?= $prefix ?>-address"><?= e(ui('area')) ?></label>
            <input id="<?= $prefix ?>-address" type="text" name="address" maxlength="255" autocomplete="address-level2">
        </div>
    </div>
    <div class="field">
        <label for="<?= $prefix ?>-message"><?= e($isEnquiry ? ui('message') : ui('questions_notes')) ?><?= $isEnquiry ? ' <span aria-hidden="true">*</span>' : '' ?></label>
        <textarea id="<?= $prefix ?>-message" name="message" rows="4"<?= $isEnquiry ? ' required' : '' ?> maxlength="5000"
            placeholder="<?= e(ui('message_placeholder')) ?>"></textarea>
    </div>
    <div class="honeypot" aria-hidden="true">
        <label for="<?= $prefix ?>-website"><?= e(ui('honeypot')) ?></label>
        <input id="<?= $prefix ?>-website" type="text" name="website" tabindex="-1" autocomplete="off">
    </div>
    <div class="site-form__foot">
        <button type="submit" class="btn btn--primary" data-submit>
            <span data-submit-label data-sent-label="<?= e(ui('sent')) ?>"><?= e($isEnquiry ? ui('send_enquiry') : ui('send_request')) ?></span>
            <?= site_icon('arrow') ?>
        </button>
        <p class="site-form__privacy">
            <?= e(ui('form_privacy')) ?>
            <a href="<?= e(site_page('/privacy')) ?>"><?= e(ui('privacy_link')) ?></a>.
        </p>
    </div>
    <noscript>
        <p class="site-form__noscript"><?= e(ui('noscript')) ?><?php if (!empty($content['contact']['email_primary']['value'])): ?>
            <a href="<?= e($content['contact']['email_primary']['href']) ?>"><?= e($content['contact']['email_primary']['value']) ?></a><?php endif; ?></p>
    </noscript>
</form>
<?php
}

/** Compact contact card reused on Home and Contact. */
function site_contact_cards(): void
{
    $contact = site_content()['contact'];
    $items = [
        ['icon' => 'pin', 'label' => ui('where_we_are'), 'lines' => array_map('t', $contact['address_lines']), 'link' => ['label' => ui('open_maps'), 'href' => $contact['map_url'], 'external' => true]],
        ['icon' => 'phone', 'label' => t($contact['phone_primary']['label'] ?? ui('office_phone')), 'lines' => array_filter([(string) ($contact['phone_primary']['value'] ?? '')]), 'link' => null],
        ['icon' => 'mail', 'label' => t($contact['email_primary']['label'] ?? ui('email')), 'lines' => [$contact['email_primary']['value']], 'link' => ['label' => ui('send_email'), 'href' => $contact['email_primary']['href'], 'external' => false]],
        ['icon' => 'clock', 'label' => ui('office_hours'), 'lines' => array_map(static fn($h) => t($h['day']) . ': ' . t($h['time']), $contact['office_hours']), 'link' => null],
    ];
    ?>
<ul class="contact-cards">
    <?php foreach ($items as $item): ?>
    <?php if (!$item['lines']) { continue; } ?>
    <li class="contact-card">
        <span class="contact-card__icon"><?= site_icon($item['icon']) ?></span>
        <h3><?= e($item['label']) ?></h3>
        <?php foreach ($item['lines'] as $line): ?>
        <p><?= e($line) ?></p>
        <?php endforeach; ?>
        <?php if ($item['link']): ?>
        <a class="link-arrow" href="<?= e($item['link']['href']) ?>"<?= $item['link']['external'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
            <?= e($item['link']['label']) ?><?= $item['link']['external'] ? site_icon('external') : site_icon('arrow') ?>
        </a>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
<?php
}

// ── Normalised content accessors ──────────────────────────────────────────
/**
 * Programs: class rows the school published through the CMS. The public
 * website no longer invents an age-banded class list of its own.
 */
function site_programs(): array
{
    $cms = site_cms();
    if (!empty($cms['programs'])) {
        $programs = [];
        foreach ($cms['programs'] as $row) {
            $features = [];
            foreach (preg_split('/\r\n|\r|\n/', (string)($row['features'] ?? '')) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $features[] = $line;
                }
            }
            $programs[] = [
                'title'     => cms_text($row, 'title') ?: ui('kind_programme'),
                'ages'      => '',
                'summary'   => cms_text($row, 'description') ?: '',
                'features'  => $features,
                'icon'      => 'book',
                'source'    => 'cms',
            ];
        }
        return $programs;
    }
    // No class list is hard-coded in the website: the school publishes classes
    // through the CMS, and the page falls back to the curriculum subjects.
    return [];
}

/** Curriculum subjects taught at every level (editorial content). */
function site_curriculum(): array
{
    return site_content()['programs']['curriculum'] ?? [];
}

function site_teachers(): array
{
    $teachers = [];
    foreach (site_cms()['teachers'] as $row) {
        $teachers[] = [
            'name'    => (string)($row['name'] ?? ''),
            'role'    => cms_text($row, 'role_title'),
            'bio'     => cms_text($row, 'bio'),
            'photo'   => (string)($row['photo_path'] ?? ''),
        ];
    }
    return $teachers;
}

/**
 * News items: dated CMS announcements (when that table exists) followed by
 * the school's standing notices. Keys are identical for both sources.
 */
function site_news_items(): array
{
    $items = [];
    foreach (site_cms()['news'] as $row) {
        $published = trim((string)($row['published_on'] ?? ''));
        $timestamp = $published !== '' ? strtotime($published) : false;
        $link = null;
        $linkUrl = trim((string)($row['link_url'] ?? ''));
        if ($linkUrl !== '' && ($linkUrl[0] === '/' && !str_starts_with($linkUrl, '//'))) {
            // Only same-site, root-relative links are rendered, so a stored
            // value can never inject a javascript: or off-site target.
            $link = [
                'label' => cms_text($row, 'link_label') ?: ui('read_more'),
                'path'  => $linkUrl,
            ];
        }
        $items[] = [
            'kind'     => 'announcement',
            'title'    => cms_text($row, 'title'),
            'text'     => cms_text($row, 'body'),
            'date'     => $timestamp ? date('j F Y', $timestamp) : '',
            'date_iso' => $timestamp ? date('c', $timestamp) : '',
            'link'     => $link,
            'image'    => site_media_url((string)($row['image_path'] ?? '')),
            'image_alt'=> (string)($row['image_alt'] ?? ''),
            'draft'    => false,
        ];
    }
    foreach (site_content()['news']['items'] as $item) {
        $item['date'] = '';
        $item['date_iso'] = '';
        $item['image'] = site_media_url((string) ($item['image'] ?? ''));
        $item['image_alt'] = (string)($item['image_alt'] ?? '');
        $items[] = $item;
    }
    // Newest dated announcement first; undated standing notices keep their
    // editorial order after them (usort is stable in PHP 8).
    usort($items, static function (array $a, array $b): int {
        $at = ($a['date_iso'] ?? '') !== '' ? (int) strtotime((string) $a['date_iso']) : 0;
        $bt = ($b['date_iso'] ?? '') !== '' ? (int) strtotime((string) $b['date_iso']) : 0;
        return $bt <=> $at;
    });
    return $items;
}

/**
 * Accept only same-site image paths: root-relative, no scheme or host, no
 * traversal, no whitespace, and a known image extension. Anything else
 * renders nothing, so a stored value can never inject an off-site image.
 */
function site_media_url(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return '';
    }
    if (str_contains($path, '..') || preg_match('/\s/', $path)) {
        return '';
    }
    if (!preg_match('#^/[A-Za-z0-9._/-]+\.(?:jpe?g|png|webp|avif)$#i', $path)) {
        return '';
    }
    return $path;
}

/** Optional news photograph. Prints nothing when the item has no valid image. */
function site_news_media(array $item): void
{
    $src = site_media_url((string) ($item['image'] ?? ''));
    if ($src === '') {
        return;
    }
    ?>
    <figure class="news-media">
        <img src="<?= e(site_page($src)) ?>" alt="<?= e(t($item['image_alt'] ?? '')) ?>"
             width="1672" height="941" loading="lazy" decoding="async">
    </figure>
    <?php
}

/** Days since a CMS row was published, used only for ordering messages. */
function site_news_has_dated(): bool
{
    foreach (site_news_items() as $item) {
        if (!empty($item['date'])) {
            return true;
        }
    }
    return false;
}
