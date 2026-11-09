<?php
/** Public Gallery page — photos are served by the existing public_gallery.php API. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$galleryContent = $content['gallery'];
$GLOBALS['WBSS_PAGE_GALLERY'] = true;

$page = [
    'slug'        => '/gallery',
    'title'       => 'Gallery',
    'title_tag'   => 'Gallery · ' . SCHOOL_NAME,
    'description' => 'Photographs of lessons, Mezmur practice, feast days and events at Wilude Birhan Sunday School.',
    'gallery'     => true,
    'breadcrumbs' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'Gallery', 'path' => '/gallery'],
    ],
];
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs'], 'የፎቶ መድብር');
site_page_hero($galleryContent['eyebrow'], $galleryContent['title'], $galleryContent['lead']);
?>

<section class="section" id="photos">
    <div class="wrap">
        <div id="fkssGallery" class="fkss-gal gallery-shell" data-api="<?= e(site_url('/public_gallery.php')) ?>">
            <div class="fkss-gal-hero" data-gal="hero" hidden></div>
            <div class="fkss-gal-filters" data-gal="filters" hidden></div>
            <div class="fkss-gal-grid" data-gal="grid"></div>
            <p class="fkss-gal-status" data-gal="status" hidden></p>
            <p class="fkss-gal-empty" data-gal="empty" hidden>Photos from school life will appear here once the school publishes them.</p>
            <button type="button" class="fkss-gal-more" data-gal="more" hidden>Show more photos</button>
        </div>
        <div class="notice" style="margin-top:1.6rem">
            <?= site_icon('image') ?>
            <p><?= e($galleryContent['note']) ?></p>
        </div>
    </div>
</section>

<?php
site_cta_band('Visit the school in person', 'Photographs cannot show everything. Come to a Sunday session and meet the teachers.');
site_footer();
