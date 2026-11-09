<?php
/** Public Gallery page — photos are served by the existing public_gallery.php API. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$galleryContent = $content['gallery'];
$GLOBALS['WBSS_PAGE_GALLERY'] = true;

$page = [
    'slug'        => '/gallery',
    'title'       => L('Gallery', 'ፎቶዎች'),
    'title_tag'   => null,
    'description' => $galleryContent['lead'],
    'gallery'     => true,
    'breadcrumbs' => [
        ['label' => L('Home', 'መነሻ'), 'path' => '/'],
        ['label' => L('Gallery', 'ፎቶዎች'), 'path' => '/gallery'],
    ],
];
$page['title_tag'] = t($page['title']) . ' · ' . site_school_name();
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs']);
site_page_hero($galleryContent['eyebrow'], $galleryContent['title'], $galleryContent['lead']);
?>

<section class="section" id="photos">
    <div class="wrap">
        <div id="fkssGallery" class="fkss-gal gallery-shell" data-api="<?= e(site_url('/public_gallery.php')) ?>"
             data-empty="<?= e(ui('gallery_empty')) ?>" data-more="<?= e(ui('gallery_more')) ?>"
             data-msg-loading="<?= e(ui('gallery_loading')) ?>"
             data-msg-empty="<?= e(ui('gallery_empty')) ?>"
             data-msg-more="<?= e(ui('gallery_more')) ?>"
             data-msg-failed="<?= e(ui('gallery_failed')) ?>"
             data-msg-offline="<?= e(ui('gallery_offline')) ?>"
             data-msg-more-failed="<?= e(ui('gallery_more_failed')) ?>"
             data-msg-img-failed="<?= e(ui('gallery_img_failed')) ?>">
            <div class="fkss-gal-hero" data-gal="hero" hidden></div>
            <div class="fkss-gal-filters" data-gal="filters" hidden></div>
            <div class="fkss-gal-grid" data-gal="grid"></div>
            <p class="fkss-gal-status" data-gal="status" hidden></p>
            <p class="fkss-gal-empty" data-gal="empty" hidden><?= e(ui('gallery_empty')) ?></p>
            <button type="button" class="fkss-gal-more" data-gal="more" hidden><?= e(ui('gallery_more')) ?></button>
        </div>
        <div class="notice" style="margin-top:1.6rem">
            <?= site_icon('image') ?>
            <p><?= e(t($galleryContent['note'])) ?></p>
        </div>
    </div>
</section>

<?php
site_cta_band($galleryContent['cta_title'], $galleryContent['cta_text']);
site_footer();
