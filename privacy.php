<?php
/** Public privacy and safeguarding page. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$privacy = $content['privacy'];

$page = [
    'slug'        => '/privacy',
    'title'       => 'Privacy and safeguarding',
    'title_tag'   => 'Privacy and safeguarding · ' . SCHOOL_NAME,
    'description' => 'What the Wilude Birhan Sunday School public website collects, publishes and does not publish, and how to ask about your family\'s information.',
    'breadcrumbs' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'Privacy and safeguarding', 'path' => '/privacy'],
    ],
];
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs'], 'ግላዊነት');
site_page_hero($privacy['eyebrow'], $privacy['title'], $privacy['lead']);
?>

<section class="section">
    <div class="wrap">
        <ul class="policy-nav" aria-label="Sections on this page">
            <?php foreach ($privacy['sections'] as $index => $section): ?>
            <li><a href="#section-<?= (int)$index + 1 ?>"><?= e($section['title']) ?></a></li>
            <?php endforeach; ?>
        </ul>

        <div class="prose" style="margin-top:2rem">
            <?php foreach ($privacy['sections'] as $index => $section): ?>
            <section id="section-<?= (int)$index + 1 ?>">
                <h2><?= e($section['title']) ?></h2>
                <ul>
                    <?php foreach ($section['items'] as $item): ?>
                    <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>

            <div class="notice" style="margin-top:2rem">
                <?= site_icon('shield') ?>
                <p><?= e($privacy['review_note']) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow">Ask us</p>
            <h2>Questions about your family's information</h2>
        </div>
        <div class="grid grid--2">
            <div class="card">
                <span class="icon-badge"><?= site_icon('mail') ?></span>
                <h3 style="margin-top:1rem">Contact the school office</h3>
                <p>The office can confirm what has been recorded, close a request that is no longer needed, or arrange for a photograph to be removed.</p>
                <a class="link-arrow" href="<?= e(site_page('/contact')) ?>">Contact details<?= site_icon('arrow') ?></a>
            </div>
            <div class="card">
                <span class="icon-badge icon-badge--gold"><?= site_icon('lock') ?></span>
                <h3 style="margin-top:1rem">Staff access is separate</h3>
                <p>The public website has no administrative login and publishes no link to one. Staff accounts, roles and pupil records belong to the school administration system and are protected by its own permissions.</p>
                <p class="muted" style="font-size:.9rem;margin:0">Staff reach the administration system from their own bookmark or the school's internal instructions.</p>
            </div>
        </div>
    </div>
</section>

<?php
site_footer();
