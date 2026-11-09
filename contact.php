<?php
/** Public Contact page — enquiries use the existing validated public endpoint. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$contact = $content['contact'];

$page = [
    'slug'        => '/contact',
    'title'       => 'Contact',
    'title_tag'   => 'Contact us · ' . SCHOOL_NAME,
    'description' => 'Contact Wilude Birhan Sunday School: address, phone, email, office hours and the enquiry form.',
    'breadcrumbs' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'Contact', 'path' => '/contact'],
    ],
];
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs'], 'ያግኙን');
site_page_hero('Contact', 'Talk to the school', 'The school office is staffed by volunteers. Call during office hours or send a message and the office will reply.');
?>

<section class="section">
    <div class="wrap split" style="align-items:start">
        <div data-reveal>
            <?php site_contact_cards(); ?>
            <div class="notice notice--green" style="margin-top:1.2rem">
                <?= site_icon('pin') ?>
                <p><strong><?= e($contact['map_label']) ?></strong><br>
                <a href="<?= e($contact['map_url']) ?>" target="_blank" rel="noopener noreferrer">Open the location in Google Maps<?= site_icon('external') ?></a></p>
            </div>
            <div class="notice" style="margin-top:1.2rem">
                <?= site_icon('clock') ?>
                <p><?= e($contact['response_note']) ?></p>
            </div>
        </div>
        <div data-reveal>
            <?php site_form('enquiry', 'Send the school office a message'); ?>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow">Common questions</p>
            <h2>Before you write</h2>
        </div>
        <div class="grid grid--2">
            <div class="card">
                <h3 style="margin-top:0">Registering a child</h3>
                <p>Registration requests are handled by the school office. Use the admissions page so your request reaches the right person with the details they need.</p>
                <a class="link-arrow" href="<?= e(site_page('/admissions')) ?>">Admissions<?= site_icon('arrow') ?></a>
            </div>
            <div class="card">
                <h3 style="margin-top:0">Requesting a member record</h3>
                <p>Membership verification and ID cards are handled inside the school system. Families should speak to the office rather than send identity details through this website.</p>
                <a class="link-arrow" href="<?= e(site_page('/privacy')) ?>">How information is handled<?= site_icon('arrow') ?></a>
            </div>
        </div>
    </div>
</section>

<?php
site_footer();
