<?php
/** Public not-found page. Kept tiny: no data access, no session, no user input. */
http_response_code(404);
require_once __DIR__ . '/public_site.php';

$page = [
    'slug'      => '/404',
    'title'     => L('Page not found', 'ገጹ አልተገኘም'),
    'title_tag' => t(L('Page not found', 'ገጹ አልተገኘም')) . ' · ' . site_school_name(),
    'description' => L('This address is not part of the public website.', 'ይህ አድራሻ በህዝባዊው ድረ-ገጽ ውስጥ የለም።'),
];
site_head($page);
site_header($page);
?>
<section class="page-hero">
    <div class="wrap page-hero__inner">
        <p class="eyebrow eyebrow--gold"><?= e(ui('not_found_kicker')) ?></p>
        <h1 class="page-hero__title"><?= e(ui('page_not_here')) ?></h1>
        <p class="page-hero__lead"><?= e(t(L('The address may have changed, or the link may be out of date.', 'አድራሻው ተቀይሮ ሊሆን ይችላል፤ ወይም አገናኙ ጊዜ ያለፈበት ሊሆን ይችላል።'))) ?></p>
    </div>
</section>
<section class="section">
    <div class="wrap">
        <div class="grid grid--3">
            <article class="card">
                <span class="icon-badge"><?= site_icon('book') ?></span>
                <h3 style="margin-top:1rem"><a href="<?= e(site_page('/programs')) ?>" style="text-decoration:none"><?= e(t(L('Programmes', 'መርሃ ግብሮች'))) ?></a></h3>
                <p><?= e(t(L('Subjects and the Sunday programme.', 'ትምህርቶችና የእሁድ መርሃ ግብር።'))) ?></p>
            </article>
            <article class="card">
                <span class="icon-badge"><?= site_icon('calendar') ?></span>
                <h3 style="margin-top:1rem"><a href="<?= e(site_page('/news')) ?>" style="text-decoration:none"><?= e(t(L('News and notices', 'ዜናና ማሳሰቢያዎች'))) ?></a></h3>
                <p><?= e(t(L('Announcements and photographs.', 'ማስታወቂያዎችና ፎቶዎች።'))) ?></p>
            </article>
            <article class="card">
                <span class="icon-badge"><?= site_icon('mail') ?></span>
                <h3 style="margin-top:1rem"><a href="<?= e(site_page('/contact')) ?>" style="text-decoration:none"><?= e(ui('contact')) ?></a></h3>
                <p><?= e(t(L('Reach the school office.', 'የትምህርት ቤቱን ጽ/ቤት ያግኙ።'))) ?></p>
            </article>
        </div>
        <p style="margin-top:1.8rem">
            <a class="btn btn--primary" href="<?= e(site_page('/')) ?>"><?= e(ui('back_home')) ?><?= site_icon('arrow') ?></a>
        </p>
        <form class="search-form" role="search" method="get" action="<?= e(site_page('/search')) ?>" style="margin-top:1.6rem">
            <label class="visually-hidden" for="notfound-q"><?= e(ui('search_site')) ?></label>
            <input id="notfound-q" type="search" name="q" placeholder="<?= e(ui('search_placeholder')) ?>">
            <button class="btn btn--outline" type="submit"><?= e(ui('search')) ?></button>
        </form>
    </div>
</section>
<?php
site_footer();
