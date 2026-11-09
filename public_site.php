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
    'schedule'  => [],
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
        $WBSS_CMS['schedule'] = $_wbssFetch($conn, 'SELECT * FROM cms_schedule WHERE is_active=1 ORDER BY sort_order, id');
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
            $items[] = ['path' => $page['path'], 'title' => $page['title'], 'title_am' => $page['title_am']];
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
    $title = $page['title_tag'] ?? ($page['title'] . ' · ' . SCHOOL_NAME);
    $description = $page['description'] ?? $content['home']['lead'];
    $canonical = site_canonical();
    $ogImage = 'https://' . SITE_DOMAIN . '/themes/fkss/assets/wbss-heritage-hero.jpg';
    $needsGallery = !empty($page['gallery']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="robots" content="index,follow">
<meta name="theme-color" content="<?= e(PUBLIC_SITE_PRIMARY) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SCHOOL_NAME) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:locale" content="en_ET">
<meta property="og:locale:alternate" content="am_ET">
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
        'name' => SCHOOL_NAME,
        'alternateName' => [SCHOOL_NAME_AMHARIC, SCHOOL_NAME_SHORT],
        'description' => $content['home']['lead'],
        'url' => 'https://' . SITE_DOMAIN . '/',
        'logo' => 'https://' . SITE_DOMAIN . SCHOOL_LOGO_PATH,
        'parentOrganization' => [
            '@type' => 'Church',
            'name' => PARISH_NAME_EN,
            'alternateName' => PARISH_NAME_AM,
        ],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $contact['address_lines'][0] ?? '',
            'addressLocality' => 'Addis Ababa',
            'addressCountry' => 'ET',
        ],
        'areaServed' => 'Addis Ababa',
    ];
    $office = [];
    foreach ($contact['office_hours'] as $hours) {
        $office[] = ['@type' => 'OpeningHoursSpecification', 'description' => $hours['day'] . ' ' . $hours['time']];
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
        'inLanguage' => 'en',
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

// ── Header, notice bar and navigation ─────────────────────────────────────
function site_header(array $page): void
{
    $content = site_content();
    $slug = $page['slug'] ?? '/';
    $nav = site_nav();
    $phone = $content['contact']['phone_primary']['value'] ?? '';
    ?>
<body class="site">
<a class="skip-link" href="#main">Skip to main content</a>
<?php if (defined('WBSS_SITE_CONTENT_DRAFT') && WBSS_SITE_CONTENT_DRAFT): ?>
<div class="draft-bar" id="draft-bar">
    <div class="wrap draft-bar__inner">
        <span><?= e($content['site']['draft_notice']) ?></span>
        <button type="button" class="draft-bar__close" data-dismiss="draft-bar" aria-label="Dismiss notice"><?= site_icon('close') ?></button>
    </div>
</div>
<?php endif; ?>
<div class="topbar">
    <div class="wrap topbar__inner">
        <p class="topbar__identity">
            <span lang="am"><?= e(PARISH_NAME_AM) ?></span>
            <span class="topbar__sep" aria-hidden="true">·</span>
            <span><?= e(PARISH_NAME_EN) ?></span>
        </p>
        <ul class="topbar__links">
            <li><?= site_icon('clock') ?><span>Sunday lessons 9:00 AM – 12:00 PM</span></li>
            <?php if ($phone !== ''): ?>
            <li><?= site_icon('phone') ?><span><?= e($phone) ?></span></li>
            <?php endif; ?>
            <li><a href="<?= e(site_page('/contact')) ?>">Contact</a></li>
        </ul>
    </div>
</div>
<header class="site-header" id="site-header" data-header>
    <div class="wrap site-header__inner">
        <a class="brand" href="<?= e(site_page('/')) ?>" aria-label="<?= e(SCHOOL_NAME . ' home') ?>">
            <img class="brand__mark" src="<?= e(site_page(SCHOOL_LOGO_PATH)) ?>" alt="" width="52" height="52" decoding="async">
            <span class="brand__text">
                <span class="brand__name" lang="am"><?= e(SCHOOL_NAME_SHORT_AM) ?></span>
                <span class="brand__sub"><?= e(SCHOOL_TRANSLATION_EN . ' ' . SCHOOL_TYPE) ?></span>
            </span>
        </a>
        <nav class="primary-nav" aria-label="Primary">
            <ul>
                <?php foreach ($nav as $item): ?>
                <li>
                    <a href="<?= e(site_page($item['path'])) ?>"<?= site_is_current($item['path'], $slug) ? ' class="is-active" aria-current="page"' : '' ?>>
                        <?= e($item['title']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="header-actions">
            <a class="header-search-btn" href="<?= e(site_page('/search')) ?>" aria-label="Search this website" title="Search">
                <?= site_icon('search') ?>
            </a>
            <a class="btn btn--primary btn--sm header-cta" href="<?= e(site_page('/admissions')) ?>">Register</a>
            <button type="button" class="nav-toggle" id="nav-toggle" aria-controls="mobile-drawer" aria-expanded="false" aria-label="Open menu">
                <span class="nav-toggle__open"><?= site_icon('menu') ?></span>
                <span class="nav-toggle__close"><?= site_icon('close') ?></span>
            </button>
        </div>
    </div>
</header>
<div class="drawer" id="mobile-drawer" hidden>
    <div class="drawer__panel" role="dialog" aria-modal="true" aria-label="Site menu">
        <form class="drawer__search" role="search" method="get" action="<?= e(site_page('/search')) ?>">
            <label class="visually-hidden" for="drawer-search-input">Search this website</label>
            <input id="drawer-search-input" type="search" name="q" placeholder="Search this website">
            <button type="submit" aria-label="Search"><?= site_icon('search') ?></button>
        </form>
        <nav aria-label="Mobile">
            <ul class="drawer__nav">
                <?php foreach ($nav as $item): ?>
                <li>
                    <a href="<?= e(site_page($item['path'])) ?>"<?= site_is_current($item['path'], $slug) ? ' class="is-active" aria-current="page"' : '' ?>>
                        <span><?= e($item['title']) ?></span>
                        <span class="drawer__am" lang="am"><?= e($item['title_am']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <a class="btn btn--primary btn--block" href="<?= e(site_page('/admissions')) ?>">Register your child</a>
        <div class="drawer__meta">
            <p><?= site_icon('pin') ?><?= e($content['contact']['address_lines'][0]) ?></p>
            <?php if ($phone !== ''): ?><p><?= site_icon('phone') ?><?= e($phone) ?></p><?php endif; ?>
        </div>
    </div>
</div>
<main id="main">
<?php
}

function site_breadcrumbs(array $trail, string $titleAm = ''): void
{
    if (!$trail) {
        return;
    }
    ?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <div class="wrap">
        <ol>
            <?php foreach ($trail as $i => $crumb): ?>
            <li>
                <?php if ($i < count($trail) - 1): ?>
                <a href="<?= e(site_page($crumb['path'])) ?>"><?= e($crumb['label']) ?></a>
                <?php else: ?>
                <span aria-current="page"><?= e($crumb['label']) ?></span>
                <?php if ($titleAm !== ''): ?><span class="am" lang="am"><?= e($titleAm) ?></span><?php endif; ?>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</nav>
<?php
}

/** Inner page hero used by every page except the home page. */
function site_page_hero(string $eyebrow, string $title, string $lead = '', string $titleAm = '', string $leadAm = ''): void
{
    ?>
<section class="page-hero">
    <div class="wrap page-hero__inner">
        <?php if ($eyebrow !== ''): ?><p class="eyebrow eyebrow--gold"><?= e($eyebrow) ?></p><?php endif; ?>
        <h1 class="page-hero__title"><?= e($title) ?></h1>
        <?php if ($titleAm !== ''): ?><p class="page-hero__am" lang="am"><?= e($titleAm) ?></p><?php endif; ?>
        <?php if ($lead !== ''): ?><p class="page-hero__lead"><?= e($lead) ?></p><?php endif; ?>
        <?php if ($leadAm !== ''): ?><p class="page-hero__lead-am" lang="am"><?= e($leadAm) ?></p><?php endif; ?>
    </div>
</section>
<?php
}

function site_cta_band(string $title = '', string $text = ''): void
{
    $content = site_content();
    $title = $title !== '' ? $title : 'Begin with a conversation';
    $text = $text !== '' ? $text : 'Send a registration request and the school office will contact you about the right class for your child.';
    ?>
<section class="cta-band">
    <div class="wrap cta-band__inner">
        <div>
            <p class="eyebrow eyebrow--gold">Admissions</p>
            <h2><?= e($title) ?></h2>
            <p><?= e($text) ?></p>
        </div>
        <div class="cta-band__actions">
            <a class="btn btn--gold" href="<?= e(site_page('/admissions')) ?>">Registration request <?= site_icon('arrow') ?></a>
            <a class="btn btn--ghost" href="<?= e(site_page('/contact')) ?>">Contact the office</a>
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
                    <span class="brand__name" lang="am"><?= e(SCHOOL_NAME_AMHARIC) ?></span>
                    <span class="brand__sub"><?= e(SCHOOL_TRANSLATION_EN . ' ' . SCHOOL_TYPE) ?></span>
                </span>
            </a>
            <p class="site-footer__blurb"><?= e($content['footer']['blurb']) ?></p>
            <p class="site-footer__parish" lang="am"><?= e(PARISH_NAME_AM) ?></p>
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
        <nav class="site-footer__col" aria-label="<?= e($column['title']) ?>">
            <h2><?= e($column['title']) ?></h2>
            <ul>
                <?php foreach ($column['paths'] as $path): ?>
                <?php if (!isset($pages[$path])) continue; ?>
                <li><a href="<?= e(site_page($path)) ?>"><?= e($pages[$path]['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endforeach; ?>
        <div class="site-footer__col">
            <h2>Visit and contact</h2>
            <address>
                <?php foreach ($contact['address_lines'] as $line): ?>
                <span><?= e($line) ?></span>
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
                <span><?= e($hours['day']) ?>: <?= e($hours['time']) ?></span>
                <?php endforeach; ?>
            </p>
        </div>
    </div>
    <div class="site-footer__bar">
        <div class="wrap site-footer__bar-inner">
            <p>&copy; <?= e(COPYRIGHT_YEAR) ?> <?= e(SCHOOL_NAME_AMHARIC) ?> · <?= e(SCHOOL_TRANSLATION_EN . ' ' . SCHOOL_TYPE) ?>. <?= e(DENOMINATION_EN) ?>.</p>
            <ul>
                <li><a href="<?= e(site_page('/privacy')) ?>">Privacy and safeguarding</a></li>
                <li><a href="<?= e(site_page('/sitemap.xml')) ?>">Sitemap</a></li>
                <li><a href="<?= e(site_url($content['site']['admin_login_path'])) ?>"><?= site_icon('lock') ?>Staff login</a></li>
            </ul>
        </div>
    </div>
</footer>
<button type="button" class="to-top" id="to-top" hidden aria-label="Back to top"><?= site_icon('chevron') ?></button>
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
function site_form(string $mode = 'registration', string $title = ''): void
{
    $content = site_content();
    $programs = $content['admissions']['form']['programs'];
    $isEnquiry = $mode === 'enquiry';
    $endpoint = site_url('/register_submit.php');
    ?>
<form class="site-form" id="<?= $isEnquiry ? 'contact-form' : 'registration-form' ?>" method="post" action="<?= e($endpoint) ?>"
      data-endpoint="<?= e($endpoint) ?>" novalidate>
    <div class="site-form__head">
        <h2><?= e($title !== '' ? $title : ($isEnquiry ? 'Send an enquiry' : $content['admissions']['form']['title'])) ?></h2>
        <p><?= e($isEnquiry ? 'Tell us how we can help and the office will reply.' : $content['admissions']['form']['note']) ?></p>
    </div>
    <div class="form-status" data-form-status role="status" aria-live="polite" hidden></div>
    <div class="field-row">
        <div class="field">
            <label for="<?= $isEnquiry ? 'enq' : 'reg' ?>-name"><?= $isEnquiry ? 'Your name' : 'Parent / guardian name' ?> <span aria-hidden="true">*</span></label>
            <input id="<?= $isEnquiry ? 'enq' : 'reg' ?>-name" type="text" name="full_name" required maxlength="150" autocomplete="name">
        </div>
        <div class="field">
            <label for="<?= $isEnquiry ? 'enq' : 'reg' ?>-phone">Phone number <span aria-hidden="true">*</span></label>
            <input id="<?= $isEnquiry ? 'enq' : 'reg' ?>-phone" type="tel" name="phone" required maxlength="30" autocomplete="tel" placeholder="+251 …">
        </div>
    </div>
    <?php if (!$isEnquiry): ?>
    <div class="field-row">
        <div class="field">
            <label for="reg-child">Child's full name <span aria-hidden="true">*</span></label>
            <input id="reg-child" type="text" name="child_name" required maxlength="150" autocomplete="off">
        </div>
        <div class="field">
            <label for="reg-age">Child's age</label>
            <select id="reg-age" name="age">
                <option value="">Select age</option>
                <?php for ($age = 4; $age <= 18; $age++): ?>
                <option value="<?= $age ?>"><?= $age ?> years</option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
    <div class="field-row">
        <div class="field">
            <label for="reg-program">Programme interest</label>
            <select id="reg-program" name="program_interest">
                <option value="">Select a class</option>
                <?php foreach ($programs as $program): ?>
                <option value="<?= e($program) ?>"><?= e($program) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="reg-gender">Gender (optional)</label>
            <select id="reg-gender" name="gender">
                <option value="">Prefer not to say</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>
        </div>
    </div>
    <?php else: ?>
    <input type="hidden" name="program_interest" value="General enquiry">
    <?php endif; ?>
    <div class="field-row">
        <div class="field">
            <label for="<?= $isEnquiry ? 'enq' : 'reg' ?>-email">Email</label>
            <input id="<?= $isEnquiry ? 'enq' : 'reg' ?>-email" type="email" name="email" maxlength="120" autocomplete="email">
        </div>
        <div class="field">
            <label for="<?= $isEnquiry ? 'enq' : 'reg' ?>-address">Area / address</label>
            <input id="<?= $isEnquiry ? 'enq' : 'reg' ?>-address" type="text" name="address" maxlength="255" autocomplete="address-level2">
        </div>
    </div>
    <div class="field">
        <label for="<?= $isEnquiry ? 'enq' : 'reg' ?>-message"><?= $isEnquiry ? 'Message' : 'Questions or special needs' ?> <span aria-hidden="true">*</span></label>
        <textarea id="<?= $isEnquiry ? 'enq' : 'reg' ?>-message" name="message" rows="4"<?= $isEnquiry ? ' required' : '' ?> maxlength="5000"
            placeholder="<?= $isEnquiry ? 'How can the school office help?' : 'Tell us about your child, their previous class, or anything the teacher should know.' ?>"></textarea>
    </div>
    <div class="honeypot" aria-hidden="true">
        <label for="<?= $isEnquiry ? 'enq' : 'reg' ?>-website">Leave this field empty</label>
        <input id="<?= $isEnquiry ? 'enq' : 'reg' ?>-website" type="text" name="website" tabindex="-1" autocomplete="off">
    </div>
    <div class="site-form__foot">
        <button type="submit" class="btn btn--primary" data-submit>
            <span data-submit-label><?= $isEnquiry ? 'Send enquiry' : 'Send registration request' ?></span>
            <?= site_icon('arrow') ?>
        </button>
        <p class="site-form__privacy">
            We use these details only to answer your request. See <a href="<?= e(site_page('/privacy')) ?>">privacy and safeguarding</a>.
        </p>
    </div>
    <noscript>
        <p class="site-form__noscript">
            JavaScript is switched off in your browser. You can still send this form, but the confirmation
            message will appear as plain text. Please write your child's name at the start of the message
            so the office receives it with your request.
            To speak to someone instead, call the school office<?php if (!empty($content['contact']['phone_primary']['value'])): ?> on <?= e($content['contact']['phone_primary']['value']) ?><?php endif; ?>
            or email<?php if (!empty($content['contact']['email_primary']['value'])): ?> <a href="<?= e($content['contact']['email_primary']['href']) ?>"><?= e($content['contact']['email_primary']['value']) ?></a><?php endif; ?>.
        </p>
    </noscript>
</form>
<?php
}

/** Compact contact card reused on Home and Contact. */
function site_contact_cards(): void
{
    $contact = site_content()['contact'];
    $items = [
        ['icon' => 'pin', 'label' => 'Where we are', 'lines' => $contact['address_lines'], 'link' => ['label' => 'Open in maps', 'href' => $contact['map_url'], 'external' => true]],
        ['icon' => 'phone', 'label' => $contact['phone_primary']['label'] ?? 'Phone', 'lines' => [$contact['phone_primary']['value']], 'link' => null],
        ['icon' => 'mail', 'label' => $contact['email_primary']['label'] ?? 'Email', 'lines' => [$contact['email_primary']['value']], 'link' => ['label' => 'Send an email', 'href' => $contact['email_primary']['href'], 'external' => false]],
        ['icon' => 'clock', 'label' => 'Office hours', 'lines' => array_map(static fn($h) => $h['day'] . ': ' . $h['time'], $contact['office_hours']), 'link' => null],
    ];
    ?>
<ul class="contact-cards">
    <?php foreach ($items as $item): ?>
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
 * Programs: CMS rows when the school has published them, otherwise the
 * standard class structure from site_content.php. Both return the same keys.
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
                'title'     => trim((string)($row['title'] ?? '')) ?: 'Programme',
                'title_am'  => trim((string)($row['title_am'] ?? '')),
                'ages'      => '',
                'summary'   => trim((string)($row['description'] ?? '')),
                'features'  => $features,
                'icon'      => 'book',
                'source'    => 'cms',
            ];
        }
        return $programs;
    }
    $programs = [];
    foreach (site_content()['programs']['fallback_classes'] as $class) {
        $class['icon'] = 'book';
        $class['source'] = 'site';
        $programs[] = $class;
    }
    return $programs;
}

/** Weekly schedule: CMS rows, otherwise the published standard week. */
function site_schedule_rows(): array
{
    $cms = site_cms();
    if (!empty($cms['schedule'])) {
        $rows = [];
        foreach ($cms['schedule'] as $row) {
            $rows[] = [
                'day'        => (string)($row['day_of_week'] ?? ''),
                'day_am'     => (string)($row['day_of_week_am'] ?? ''),
                'time'       => (string)($row['time_label'] ?? ''),
                'activity'   => (string)($row['activity'] ?? ''),
                'activity_am'=> (string)($row['activity_am'] ?? ''),
                'place'      => (string)($row['location'] ?? ''),
                'source'     => 'cms',
            ];
        }
        return $rows;
    }
    $rows = [];
    foreach (site_content()['schedule']['fallback_week'] as $row) {
        $row['source'] = 'site';
        $rows[] = $row;
    }
    return $rows;
}

function site_teachers(): array
{
    $teachers = [];
    foreach (site_cms()['teachers'] as $row) {
        $teachers[] = [
            'name'    => (string)($row['name'] ?? ''),
            'name_am' => (string)($row['name_am'] ?? ''),
            'role'    => (string)($row['role_title'] ?? ''),
            'role_am' => (string)($row['role_title_am'] ?? ''),
            'bio'     => (string)($row['bio'] ?? ''),
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
                'label' => trim((string)($row['link_label'] ?? '')) ?: 'Read more',
                'path'  => $linkUrl,
            ];
        }
        $items[] = [
            'kind'     => 'announcement',
            'title'    => (string)($row['title'] ?? ''),
            'title_am' => (string)($row['title_am'] ?? ''),
            'text'     => (string)($row['body'] ?? ''),
            'date'     => $timestamp ? date('j F Y', $timestamp) : '',
            'date_iso' => $timestamp ? date('c', $timestamp) : '',
            'link'     => $link,
            'draft'    => false,
        ];
    }
    foreach (site_content()['news']['items'] as $item) {
        $item['date'] = '';
        $item['date_iso'] = '';
        $items[] = $item;
    }
    return $items;
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
