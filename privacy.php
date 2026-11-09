<?php
/** Public privacy and safeguarding page. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$privacy = $content['privacy'];

$page = [
    'slug'        => '/privacy',
    'title'       => L('Privacy and safeguarding', 'ግላዊነትና ደህንነት'),
    'title_tag'   => null,
    'description' => $privacy['lead'],
    'breadcrumbs' => [
        ['label' => L('Home', 'መነሻ'), 'path' => '/'],
        ['label' => L('Privacy and safeguarding', 'ግላዊነትና ደህንነት'), 'path' => '/privacy'],
    ],
];
$page['title_tag'] = t($page['title']) . ' · ' . site_school_name();
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs']);
site_page_hero($privacy['eyebrow'], $privacy['title'], $privacy['lead']);
?>

<section class="section">
    <div class="wrap">
        <ul class="policy-nav" aria-label="<?= e(t(L('Sections on this page', 'በዚህ ገጽ ላይ ያሉ ክፍሎች'))) ?>">
            <?php foreach ($privacy['sections'] as $index => $section): ?>
            <li><a href="#section-<?= (int)$index + 1 ?>"><?= e(t($section['title'])) ?></a></li>
            <?php endforeach; ?>
        </ul>

        <div class="prose" style="margin-top:2rem">
            <?php foreach ($privacy['sections'] as $index => $section): ?>
            <section id="section-<?= (int)$index + 1 ?>">
                <h2><?= e(t($section['title'])) ?></h2>
                <ul>
                    <?php foreach ($section['items'] as $item): ?>
                    <li><?= e(t($item)) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>

            <div class="notice" style="margin-top:2rem">
                <?= site_icon('shield') ?>
                <p><?= e(t($privacy['review_note'])) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head" <?= reveal('up') ?>>
            <p class="eyebrow"><?= e(t(L('Ask us', 'ይጠይቁን'))) ?></p>
            <h2><?= e(t(L('Questions about your information', 'ስለ መረጃዎ ጥያቄ'))) ?></h2>
        </div>
        <div class="grid grid--2">
            <div class="card">
                <span class="icon-badge"><?= site_icon('mail') ?></span>
                <h3 style="margin-top:1rem"><?= e(ui('cta_contact')) ?></h3>
                <p><?= e(t(L('The office can confirm what has been recorded or remove a photograph.', 'ጽ/ቤቱ የተመዘገበውን ማረጋገጥ ወይም ፎቶ ማስወገድ ይችላል።'))) ?></p>
                <a class="link-arrow" href="<?= e(site_page('/contact')) ?>"><?= e(ui('contact')) ?><?= site_icon('arrow') ?></a>
            </div>
            <div class="card">
                <span class="icon-badge icon-badge--gold"><?= site_icon('lock') ?></span>
                <h3 style="margin-top:1rem"><?= e(t(L('Staff access is separate', 'የሠራተኞች መግቢያ የተለየ ነው'))) ?></h3>
                <p><?= e(t(L('The public website has no administrative login and links to none. Staff accounts belong to the school administration system.', 'ህዝባዊው ገጽ የአስተዳደር መግቢያ የለውም፤ ወደዚያም አያገናኝም። የሠራተኞች መለያ የትምህርት ቤቱ የአስተዳደር ሥርዓት ነው።'))) ?></p>
            </div>
        </div>
    </div>
</section>

<?php
site_footer();
