<?php
/** Public not-found page. Kept tiny: no data access, no session, no user input. */
http_response_code(404);
require_once __DIR__ . '/public_site.php';

$page = [
    'slug'      => '/404',
    'title'     => 'Page not found',
    'title_tag' => 'Page not found · ' . SCHOOL_NAME,
    'description' => 'The page you asked for is not part of the Wilude Birhan Sunday School public website.',
];
site_head($page);
site_header($page);
?>
<section class="page-hero">
    <div class="wrap page-hero__inner">
        <p class="eyebrow eyebrow--gold">Error 404</p>
        <h1 class="page-hero__title">That page is not here</h1>
        <p class="page-hero__am" lang="am">ይህ ገጽ አልተገኘም</p>
        <p class="page-hero__lead">The address may have changed, or the link may be out of date. These pages will get you back on track.</p>
    </div>
</section>
<section class="section">
    <div class="wrap">
        <div class="grid grid--3">
            <article class="card">
                <span class="icon-badge"><?= site_icon('book') ?></span>
                <h3 style="margin-top:1rem"><a href="<?= e(site_page('/programs')) ?>" style="text-decoration:none">Programs</a></h3>
                <p>Class levels and the curriculum for each age.</p>
            </article>
            <article class="card">
                <span class="icon-badge"><?= site_icon('calendar') ?></span>
                <h3 style="margin-top:1rem"><a href="<?= e(site_page('/news')) ?>" style="text-decoration:none">News and notices</a></h3>
                <p>Announcements, photographs and school updates.</p>
            </article>
            <article class="card">
                <span class="icon-badge"><?= site_icon('mail') ?></span>
                <h3 style="margin-top:1rem"><a href="<?= e(site_page('/contact')) ?>" style="text-decoration:none">Contact</a></h3>
                <p>Reach the school office and find the parish.</p>
            </article>
        </div>
        <p style="margin-top:1.8rem">
            <a class="btn btn--primary" href="<?= e(site_page('/')) ?>">Back to the home page<?= site_icon('arrow') ?></a>
        </p>
        <form class="search-form" role="search" method="get" action="<?= e(site_page('/search')) ?>" style="margin-top:1.6rem">
            <label class="visually-hidden" for="notfound-q">Search this website</label>
            <input id="notfound-q" type="search" name="q" placeholder="Search the website">
            <button class="btn btn--outline" type="submit">Search</button>
        </form>
    </div>
</section>
<?php
site_footer();
