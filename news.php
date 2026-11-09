<?php
/** Public News and notices page. Dated CMS announcements are merged first. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$newsContent = $content['news'];
$items = site_news_items();
$dated = site_news_has_dated();

$page = [
    'slug'        => '/news',
    'title'       => L('News and notices', 'ዜናና ማሳሰቢያዎች'),
    'title_tag'   => null,
    'description' => $newsContent['lead'],
    'breadcrumbs' => [
        ['label' => L('Home', 'መነሻ'), 'path' => '/'],
        ['label' => L('News and notices', 'ዜናና ማሳሰቢያዎች'), 'path' => '/news'],
    ],
];
$page['title_tag'] = t($page['title']) . ' · ' . site_school_name();
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs']);
site_page_hero($newsContent['eyebrow'], $newsContent['title'], $newsContent['lead']);
?>

<section class="section">
    <div class="wrap">
        <?php if (!$dated): ?>
        <div class="notice" style="margin-bottom:1.8rem">
            <?= site_icon('calendar') ?>
            <p><?= e(t($newsContent['empty_note'])) ?></p>
        </div>
        <?php endif; ?>

        <ul class="news-list" style="margin-top:0">
            <?php foreach ($items as $item): ?>
            <?php $hasImage = !empty($item['image']); ?>
            <li class="news-item<?= $hasImage ? '' : ' news-item--text' ?>" <?= reveal('up') ?>>
                <?php if ($hasImage): ?>
                <div class="news-item__media"><?php site_news_media($item); ?></div>
                <?php endif; ?>
                <div class="news-item__body">
                    <p class="search-result__kind">
                        <?= e($item['kind'] === 'announcement' ? ui('announcement') : ui('standing_notice')) ?>
                        <?php if (!empty($item['date'])): ?> · <time datetime="<?= e($item['date_iso']) ?>"><?= e($item['date']) ?></time><?php endif; ?>
                    </p>
                    <h2><?= e(t($item['title'])) ?></h2>
                    <p style="color:var(--ink-2)"><?= nl2br(e(t($item['text']))) ?></p>
                    <?php if (!empty($item['link'])): ?>
                    <p style="margin:.7rem 0 0">
                        <a class="link-arrow" href="<?= e(site_page($item['link']['path'])) ?>">
                            <?= e(t($item['link']['label'])) ?><?= site_icon('arrow') ?>
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="grid grid--2" style="margin-top:2.2rem">
            <div class="card">
                <span class="icon-badge"><?= site_icon('mail') ?></span>
                <h3 style="margin-top:1rem"><?= e(t($newsContent['announcements_title'])) ?></h3>
                <p><?= e(t($content['contact']['newsletter_note'])) ?></p>
                <a class="link-arrow" href="<?= e(site_page('/contact')) ?>"><?= e(ui('cta_contact')) ?><?= site_icon('arrow') ?></a>
            </div>
            <div class="card">
                <span class="icon-badge icon-badge--gold"><?= site_icon('lock') ?></span>
                <h3 style="margin-top:1rem"><?= e(t($newsContent['privacy_title'])) ?></h3>
                <p><?= e(t($newsContent['privacy_text'])) ?></p>
                <a class="link-arrow" href="<?= e(site_page('/privacy')) ?>"><?= e(ui('privacy_link')) ?><?= site_icon('arrow') ?></a>
            </div>
        </div>
    </div>
</section>

<?php
site_cta_band();
site_footer();
