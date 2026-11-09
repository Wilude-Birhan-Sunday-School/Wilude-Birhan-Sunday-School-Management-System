<?php
/**
 * Public site search — server-rendered over the content the site already
 * publishes (pages, programmes, notices, questions). It searches both
 * languages, so an Amharic visitor can find English content and vice versa.
 */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$searchContent = $content['search'];
$query = trim((string)($_GET['q'] ?? ''));
$query = function_exists('mb_substr') ? mb_substr($query, 0, 80, 'UTF-8') : substr($query, 0, 80);

/** Case-insensitive, multibyte-safe position. */
function site_search_match(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return false;
    }
    return function_exists('mb_stripos')
        ? mb_stripos($haystack, $needle, 0, 'UTF-8') !== false
        : stripos($haystack, $needle) !== false;
}

/** Both languages of a value, for matching. */
function site_search_blob($value): string
{
    if (is_array($value)) {
        return ($value['am'] ?? '') . ' ' . ($value['en'] ?? '');
    }
    return (string) $value;
}

/**
 * Pick the language variant that actually contains the search term.
 *
 * The index is bilingual, so a visitor may search in the other language: they
 * then see the sentence that matched, marked, rather than a snippet that does
 * not contain their word.
 */
function site_search_pick($pair, string $query): array
{
    $lang = site_lang();
    $primary = t($pair);
    if (!is_array($pair)) {
        return [$primary, $lang];
    }
    $other = (string) ($pair[$lang === 'am' ? 'en' : 'am'] ?? '');
    if (site_search_match($primary, $query) || trim($other) === '') {
        return [$primary, $lang];
    }
    return [$other, $lang === 'am' ? 'en' : 'am'];
}

/** Escaped snippet around the first match, with the term highlighted. */
function site_search_snippet(string $text, string $needle, int $length = 190): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    $position = function_exists('mb_stripos')
        ? mb_stripos($text, $needle, 0, 'UTF-8')
        : stripos($text, $needle);
    if ($position === false) {
        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length);
        return e($cut) . (strlen($text) > $length ? '…' : '');
    }
    $start = max(0, $position - 70);
    $cut = function_exists('mb_substr') ? mb_substr($text, $start, $length, 'UTF-8') : substr($text, $start, $length);
    $escaped = e($cut);
    $needleEscaped = e($needle);
    $highlighted = preg_replace('/(' . preg_quote($needleEscaped, '/') . ')/iu', '<mark>$1</mark>', $escaped);
    return ($start > 0 ? '…' : '')
        . ($highlighted ?? $escaped)
        . (strlen($text) > $start + $length ? '…' : '');
}

$results = [];
if ($query !== '') {
    // Static pages.
    foreach ($content['pages'] as $pageEntry) {
        if ($pageEntry['path'] === '/search') {
            continue;
        }
        $meta = $content['page_copy'][$pageEntry['path']] ?? '';
        $haystack = site_search_blob($pageEntry['title']) . ' ' . $pageEntry['keywords'] . ' ' . site_search_blob($meta);
        if (site_search_match($haystack, $query)) {
            [$snippetText, $snippetLang] = site_search_pick($meta, $query);
            $results[] = [
                'kind'  => ui('kind_page'),
                'title' => t($pageEntry['title']),
                'url'   => site_page($pageEntry['path']),
                'text'  => $meta !== '' ? site_search_snippet($snippetText, $query) : '',
                'lang'  => $snippetLang,
            ];
        }
    }
    // Classes published by the school.
    foreach (site_programs() as $program) {
        $haystack = $program['title'] . ' ' . $program['summary'] . ' ' . implode(' ', $program['features']);
        if (site_search_match($haystack, $query)) {
            $results[] = [
                'kind'  => ui('kind_programme'),
                'title' => $program['title'],
                'url'   => site_page('/programs'),
                'text'  => site_search_snippet($program['summary'] !== '' ? $program['summary'] : implode(', ', $program['features']), $query),
            ];
        }
    }
    // Curriculum subjects.
    foreach (site_curriculum() as $subject) {
        if (site_search_match(site_search_blob($subject['title']) . ' ' . site_search_blob($subject['text']), $query)) {
            [$snippetText, $snippetLang] = site_search_pick($subject['text'], $query);
            $results[] = [
                'kind'  => ui('kind_curriculum'),
                'title' => t($subject['title']),
                'url'   => site_page('/programs'),
                'text'  => site_search_snippet($snippetText, $query),
                'lang'  => $snippetLang,
            ];
        }
    }
    // Notices and announcements.
    foreach (site_news_items() as $item) {
        $haystack = site_search_blob($item['title']) . ' ' . site_search_blob($item['text']);
        if (site_search_match($haystack, $query)) {
            [$snippetText, $snippetLang] = site_search_pick($item['text'], $query);
            $results[] = [
                'kind'  => $item['kind'] === 'announcement' ? ui('announcement') : ui('notice'),
                'title' => t($item['title']),
                'url'   => site_page('/news'),
                'text'  => site_search_snippet($snippetText, $query),
                'lang'  => $snippetLang,
            ];
        }
    }
    // Frequently asked questions.
    foreach ($content['admissions']['faq'] as $faq) {
        if (site_search_match(site_search_blob($faq['q']) . ' ' . site_search_blob($faq['a']), $query)) {
            [$snippetText, $snippetLang] = site_search_pick($faq['a'], $query);
            $results[] = [
                'kind'  => ui('kind_question'),
                'title' => t($faq['q']),
                'url'   => site_page('/admissions'),
                'text'  => site_search_snippet($snippetText, $query),
                'lang'  => $snippetLang,
            ];
        }
    }
}

// A match may have come from the title alone (very common: the school's Amharic
// heading against an English query), so the title is marked too — the visitor
// can then see why each result was returned.
foreach ($results as $index => $result) {
    if (site_search_match($result['title'], $query)) {
        $results[$index]['title'] = site_search_snippet($result['title'], $query, 120);
        $results[$index]['title_html'] = true;
    }
}

$page = [
    'slug'        => '/search',
    'title'       => L('Search', 'ፍለጋ'),
    'title_tag'   => null,
    'description' => $searchContent['lead'],
    'breadcrumbs' => [
        ['label' => L('Home', 'መነሻ'), 'path' => '/'],
        ['label' => L('Search', 'ፍለጋ'), 'path' => '/search'],
    ],
];
$page['title_tag'] = t($page['title']) . ' · ' . site_school_name();
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs']);
site_page_hero($searchContent['eyebrow'], $searchContent['title'], $searchContent['lead']);
?>

<section class="section">
    <div class="wrap">
        <form class="search-form" role="search" method="get" action="<?= e(site_page('/search')) ?>">
            <label class="visually-hidden" for="search-q"><?= e(ui('search_site')) ?></label>
            <input id="search-q" type="search" name="q" value="<?= e($query) ?>" placeholder="<?= e(ui('search_placeholder')) ?>">
            <button class="btn btn--primary" type="submit"><?= e(ui('search')) ?><?= site_icon('search') ?></button>
        </form>

        <?php if ($query === ''): ?>
        <p class="lead" style="margin-top:1.6rem"><?= e(t($searchContent['empty'])) ?></p>
        <p class="muted"><?= e(ui('search_hint')) ?></p>
        <?php elseif (!$results): ?>
        <div class="notice" style="margin-top:1.6rem">
            <?= site_icon('search') ?>
            <p><?= e(t($searchContent['none'])) ?> <?= e(ui('no_results_hint')) ?></p>
        </div>
        <?php else: ?>
        <p class="muted" style="margin-top:1.4rem"><?= count($results) ?> <?= e(count($results) === 1 ? ui('q_result') : ui('q_results')) ?> <?= e(ui('for_query')) ?> “<?= e($query) ?>”.</p>
        <ul class="search-results">
            <?php foreach ($results as $result): ?>
            <li class="search-result">
                <p class="search-result__kind"><?= e($result['kind']) ?></p>
                <h2><a href="<?= e($result['url']) ?>"><?= !empty($result['title_html']) ? $result['title'] : e($result['title']) ?></a></h2>
                <?php if ($result['text'] !== ''): ?>
                <p<?= (isset($result['lang']) && $result['lang'] !== site_lang()) ? ' lang="' . e($result['lang']) . '"' : '' ?>><?= $result['text'] ?></p>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>

<?php
site_footer();
