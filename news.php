<?php
/** Public News and notices page. Dated CMS announcements are merged first. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$newsContent = $content['news'];
$items = site_news_items();
$dated = site_news_has_dated();

$page = [
    'slug'        => '/news',
    'title'       => 'News and notices',
    'title_tag'   => 'News and notices · ' . SCHOOL_NAME,
    'description' => 'Announcements, notices and photographs for pupils, parents, teachers and adult members of Wilude Birhan Sunday School.',
    'breadcrumbs' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'News and notices', 'path' => '/news'],
    ],
];
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs'], 'ዜናና ማሳሰቢያዎች');
site_page_hero($newsContent['eyebrow'], $newsContent['title'], $newsContent['lead']);
?>

<section class="section">
    <div class="wrap">
        <?php if (!$dated): ?>
        <div class="notice" style="margin-bottom:1.8rem">
            <?= site_icon('calendar') ?>
            <p><?= e($newsContent['empty_note']) ?> Standing notices are listed below; dated announcements appear here automatically once the school administration publishes them.</p>
        </div>
        <?php endif; ?>

        <ul class="news-list" style="margin-top:0">
            <?php foreach ($items as $item): ?>
            <?php $hasImage = !empty($item['image']); ?>
            <li class="news-item<?= $hasImage ? '' : ' news-item--text' ?>" data-reveal>
                <?php if ($hasImage): ?>
                <div class="news-item__media"><?php site_news_media($item); ?></div>
                <?php endif; ?>
                <div class="news-item__body">
                    <p class="search-result__kind">
                        <?= $item['kind'] === 'announcement' ? 'Announcement' : 'Standing notice' ?>
                        <?php if (!empty($item['date'])): ?> · <time datetime="<?= e($item['date_iso']) ?>"><?= e($item['date']) ?></time><?php endif; ?>
                    </p>
                    <h2><?= e($item['title']) ?></h2>
                    <?php if (!empty($item['title_am'])): ?>
                    <p class="am" lang="am" style="color:var(--green-700);margin:-.1rem 0 .4rem"><?= e($item['title_am']) ?></p>
                    <?php endif; ?>
                    <p style="color:var(--ink-2)"><?= nl2br(e($item['text'])) ?></p>
                    <?php if (!empty($item['text_am'])): ?>
                    <p class="am" lang="am" style="color:var(--muted);font-size:.95rem"><?= nl2br(e($item['text_am'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item['link'])): ?>
                    <p style="margin:.7rem 0 0">
                        <a class="link-arrow" href="<?= e(site_page($item['link']['path'])) ?>">
                            <?= e($item['link']['label']) ?><?= site_icon('arrow') ?>
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
                <h3 style="margin-top:1rem">Get announcements</h3>
                <p><?= e($content['contact']['newsletter_note']) ?></p>
                <a class="link-arrow" href="<?= e(site_page('/contact')) ?>">Contact the office<?= site_icon('arrow') ?></a>
            </div>
            <div class="card">
                <span class="icon-badge icon-badge--gold"><?= site_icon('lock') ?></span>
                <h3 style="margin-top:1rem">Pupil information stays private</h3>
                <p>Individual results, attendance and report cards are never published here. They live inside the school administration system and are visible only to authorised staff and families.</p>
                <a class="link-arrow" href="<?= e(site_page('/privacy')) ?>">Privacy and safeguarding<?= site_icon('arrow') ?></a>
            </div>
        </div>
    </div>
</section>

<?php
site_cta_band();
site_footer();
