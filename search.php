<?php
/**
 * Public site search — server-rendered over the content the site already
 * publishes (pages, programmes, weekly schedule, notices). No database writes,
 * no external service and no session state.
 */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$searchContent = $content['search'];
$query = trim((string)($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $query = mb_substr($query, 0, 80, 'UTF-8');
} else {
    $query = substr($query, 0, 80);
}

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
        $haystack = $pageEntry['title'] . ' ' . $pageEntry['title_am'] . ' ' . $pageEntry['keywords'];
        if ($pageEntry['path'] === '/search') {
            continue;
        }
        $meta = $content['page_copy'][$pageEntry['path']] ?? '';
        $haystack .= ' ' . $meta;
        if (site_search_match($haystack, $query)) {
            $results[] = [
                'kind'  => 'Page',
                'title' => $pageEntry['title'],
                'url'   => site_page($pageEntry['path']),
                'text'  => $meta !== '' ? site_search_snippet($meta, $query) : '',
                'label' => $pageEntry['title_am'],
            ];
        }
    }
    // Programmes.
    foreach (site_programs() as $program) {
        $haystack = $program['title'] . ' ' . $program['title_am'] . ' ' . $program['ages'] . ' '
            . $program['summary'] . ' ' . implode(' ', $program['features']);
        if (site_search_match($haystack, $query)) {
            $results[] = [
                'kind'  => 'Programme',
                'title' => $program['title'] . ($program['ages'] !== '' ? ' · ' . $program['ages'] : ''),
                'url'   => site_page('/programs'),
                'text'  => site_search_snippet($program['summary'] !== '' ? $program['summary'] : implode(', ', $program['features']), $query),
                'label' => $program['title_am'],
            ];
        }
    }
    // Weekly schedule.
    foreach (site_schedule_rows() as $row) {
        $haystack = $row['day'] . ' ' . $row['day_am'] . ' ' . $row['time'] . ' ' . $row['activity'] . ' ' . $row['activity_am'] . ' ' . $row['place'];
        if (site_search_match($haystack, $query)) {
            $results[] = [
                'kind'  => 'Schedule',
                'title' => $row['day'] . ' · ' . $row['time'],
                'url'   => site_page('/schedule'),
                'text'  => site_search_snippet($row['activity'] . ' — ' . $row['place'], $query),
                'label' => '',
            ];
        }
    }
    // Notices and announcements.
    foreach (site_news_items() as $item) {
        $haystack = $item['title'] . ' ' . $item['title_am'] . ' ' . $item['text'];
        if (site_search_match($haystack, $query)) {
            $results[] = [
                'kind'  => $item['kind'] === 'announcement' ? 'Announcement' : 'Notice',
                'title' => $item['title'],
                'url'   => site_page('/news'),
                'text'  => site_search_snippet($item['text'], $query),
                'label' => $item['title_am'],
            ];
        }
    }
    // Frequently asked questions.
    foreach ($content['admissions']['faq'] as $faq) {
        if (site_search_match($faq['q'] . ' ' . $faq['a'], $query)) {
            $results[] = [
                'kind'  => 'Question',
                'title' => $faq['q'],
                'url'   => site_page('/admissions'),
                'text'  => site_search_snippet($faq['a'], $query),
                'label' => '',
            ];
        }
    }
}

$page = [
    'slug'        => '/search',
    'title'       => 'Search',
    'title_tag'   => 'Search · ' . SCHOOL_NAME,
    'description' => 'Search the pages, programmes, weekly schedule and notices published by Wilude Birhan Sunday School.',
    'breadcrumbs' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'Search', 'path' => '/search'],
    ],
];
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs']);
site_page_hero($searchContent['eyebrow'], $searchContent['title'], $searchContent['lead']);
?>

<section class="section">
    <div class="wrap">
        <form class="search-form" role="search" method="get" action="<?= e(site_page('/search')) ?>">
            <label class="visually-hidden" for="search-q">Search this website</label>
            <input id="search-q" type="search" name="q" value="<?= e($query) ?>" placeholder="Search programmes, notices, schedule…" autofocus>
            <button class="btn btn--primary" type="submit">Search<?= site_icon('search') ?></button>
        </form>

        <?php if ($query === ''): ?>
        <p class="lead" style="margin-top:1.6rem"><?= e($searchContent['empty']) ?></p>
        <?php elseif (!$results): ?>
        <div class="notice" style="margin-top:1.6rem">
            <?= site_icon('search') ?>
            <p><?= e($searchContent['none']) ?> Try a shorter word, or browse <a href="<?= e(site_page('/programs')) ?>">programmes</a> and <a href="<?= e(site_page('/schedule')) ?>">the schedule</a>.</p>
        </div>
        <?php else: ?>
        <p class="muted" style="margin-top:1.4rem"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= e($query) ?>”.</p>
        <ul class="search-results">
            <?php foreach ($results as $result): ?>
            <li class="search-result">
                <p class="search-result__kind"><?= e($result['kind']) ?></p>
                <h2><a href="<?= e($result['url']) ?>"><?= e($result['title']) ?></a></h2>
                <?php if (!empty($result['label'])): ?>
                <p class="am" lang="am" style="color:var(--green-700);margin:0 0 .3rem"><?= e($result['label']) ?></p>
                <?php endif; ?>
                <?php if ($result['text'] !== ''): ?>
                <p><?= $result['text'] ?></p>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>

<?php
site_footer();
