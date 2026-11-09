<?php
/**
 * Public XML sitemap. Lists only the public pages of this website; staff and
 * API areas are excluded (see robots.txt). Served as /sitemap.xml by .htaccess.
 */
require_once __DIR__ . '/public_site.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$content = site_content();
$host = 'https://' . SITE_DOMAIN;
$urls = [];
foreach ($content['pages'] as $pageEntry) {
    if ($pageEntry['path'] === '/search') {
        continue;
    }
    $path = $pageEntry['path'] === '/' ? '/' : $pageEntry['path'];
    $urls[] = [
        'loc'      => $host . $path,
        'priority' => $pageEntry['path'] === '/' ? '1.0' : '0.7',
        'freq'     => in_array($pageEntry['path'], ['/news'], true) ? 'weekly' : 'monthly',
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= e($url['loc']) ?></loc>
    <changefreq><?= e($url['freq']) ?></changefreq>
    <priority><?= e($url['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
