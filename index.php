<?php
/**
 * Public home page — Wilude Birhan Sunday School.
 *
 * Content comes from site_content.php (editorial, bilingual) and the existing
 * CMS tables through public_site.php. Amharic is the default language; the
 * header switch serves the English text. Nothing here writes data.
 */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$home = $content['home'];
$curriculum = site_curriculum();
$news = site_news_items();
$alternateName = site_lang_is_am() ? (SCHOOL_TRANSLATION_EN . ' ' . SCHOOL_TYPE) : SCHOOL_NAME_AMHARIC;

$page = [
    'slug'        => '/',
    'title'       => L('Home', 'መነሻ'),
    'title_tag'   => site_school_name() . ' · ' . site_parish_name(),
    'description' => $home['lead'],
    'gallery'     => false,
];
site_head($page);
site_header($page);
?>

<section class="hero">
    <div class="hero__media">
        <img src="<?= e(site_page('/themes/fkss/assets/wbss-hero-courtyard.jpg')) ?>"
             alt="<?= e(t(L('Children in white netela shawls walking towards the door of a round Ethiopian Orthodox church with a thatched roof and painted frescoes.',
                            'ልጆች በነጭ ነጠላ ተጠቅልለው ወደ ጎርፎ ቤት ያለው ክብ የኢትዮጵያ ኦርቶዶክስ ቤተክርስቲያን መግቢያ ሲሄዱ።'))) ?>"
             width="1672" height="941" fetchpriority="high" decoding="async">
    </div>
    <div class="wrap hero__inner">
        <div class="hero__content">
            <p class="hero__parish"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e(site_parish_name()) ?></p>
            <p class="eyebrow eyebrow--gold"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e(t($home['eyebrow'])) ?></p>
            <h1 class="hero__title"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e(site_school_name()) ?></h1>
            <p class="hero__sub-name"<?= site_lang_is_am() ? '' : ' lang="am"' ?>><?= e($alternateName) ?></p>
            <p class="hero__lead"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e(t($home['lead'])) ?></p>
            <div class="hero__actions">
                <a class="btn btn--gold" href="<?= e(site_page($home['primary_cta']['path'])) ?>">
                    <?= e(t($home['primary_cta']['label'])) ?><?= site_icon('arrow') ?>
                </a>
                <a class="btn btn--ghost" href="<?= e(site_page($home['secondary_cta']['path'])) ?>">
                    <?= e(t($home['secondary_cta']['label'])) ?>
                </a>
            </div>
            <div class="hero__meta">
                <p><?= site_icon('pin') ?><?= e(t($content['contact']['address_lines'][0])) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap">
        <ul class="highlights" style="list-style:none;padding:0;margin:0">
            <?php foreach ($home['highlights'] as $highlight): ?>
            <li class="highlight" <?= reveal('pop') ?>>
                <span class="icon-badge"><?= site_icon($highlight['icon']) ?></span>
                <h3><?= e(t($highlight['title'])) ?></h3>
                <p><?= e(t($highlight['text'])) ?></p>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="section">
    <div class="wrap split">
        <div class="split__media" <?= reveal('left') ?>>
            <img src="<?= e(site_page($home['intro']['image'])) ?>" alt="<?= e(t($home['intro']['image_alt'])) ?>"
                 width="1568" height="882" loading="lazy" decoding="async">
            <p class="split__caption"><?= e(t($home['intro']['caption'])) ?></p>
        </div>
        <div <?= reveal('right') ?>>
            <p class="eyebrow"><?= e(t($home['intro']['eyebrow'])) ?></p>
            <h2><?= e(t($home['intro']['title'])) ?></h2>
            <?php foreach ($home['intro']['body'] as $paragraph): ?>
            <p><?= e(t($paragraph)) ?></p>
            <?php endforeach; ?>
            <ul class="check-list">
                <?php foreach ($home['intro']['points'] as $point): ?>
                <li><?= site_icon('check') ?><span><?= e(t($point)) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top:1.6rem">
                <a class="btn btn--outline" href="<?= e(site_page('/about')) ?>"><?= e(ui('about_school')) ?><?= site_icon('arrow') ?></a>
            </p>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head section-head--center">
            <p class="eyebrow"><?= e(t($home['commitments']['eyebrow'])) ?></p>
            <h2><?= e(t($home['commitments']['title'])) ?></h2>
        </div>
        <div class="grid grid--4">
            <?php foreach ($content['pillars'] as $pillar): ?>
            <article class="card" <?= reveal('up') ?>>
                <span class="icon-badge"><?= site_icon($pillar['icon']) ?></span>
                <h3><?= e(t($pillar['title'])) ?></h3>
                <p><?= e(t($pillar['text'])) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow"><?= e(t($home['programmes_head']['eyebrow'])) ?></p>
            <h2><?= e(t($home['programmes_head']['title'])) ?></h2>
            <p class="section-head__lead"><?= e(t($content['programs']['lead'])) ?></p>
        </div>
        <div class="grid grid--3">
            <?php foreach ($curriculum as $subject): ?>
            <article class="card" <?= reveal('zoom') ?>>
                <span class="icon-badge"><?= site_icon('book') ?></span>
                <h3><?= e(t($subject['title'])) ?></h3>
                <p><?= e(t($subject['text'])) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:1.8rem">
            <a class="link-arrow" href="<?= e(site_page('/programs')) ?>"><?= e(ui('full_programme')) ?><?= site_icon('arrow') ?></a>
        </p>
    </div>
</section>

<section class="quote-band">
    <div class="wrap section--tight">
        <div class="quote-band__inner">
            <?= site_icon('quote') ?>
            <blockquote>
                <p<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e(t($home['quote']['text'])) ?></p>
                <cite><?= e(t($home['quote']['source'])) ?></cite>
            </blockquote>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow"><?= e(t($content['news']['eyebrow'])) ?></p>
            <h2><?= e(t($content['news']['title'])) ?></h2>
            <p class="section-head__lead"><?= e(t($content['news']['lead'])) ?></p>
        </div>
        <div class="grid grid--3">
            <?php foreach (array_slice($news, 0, 3) as $item): ?>
            <article class="card card--news" <?= reveal('up') ?>>
                <?php site_news_media($item); ?>
                <div class="card__body">
                    <p class="card__badge"><?= e($item['kind'] === 'announcement' ? ui('announcement') : ui('notice')) ?></p>
                    <h3><?= e(t($item['title'])) ?></h3>
                    <?php if (!empty($item['date'])): ?>
                    <p class="muted" style="font-size:.85rem;margin:0 0 .5rem"><?= site_icon('calendar') ?> <?= e($item['date']) ?></p>
                    <?php endif; ?>
                    <p><?= nl2br(e(t($item['text']))) ?></p>
                    <?php if (!empty($item['link'])): ?>
                    <a class="link-arrow" href="<?= e(site_page($item['link']['path'])) ?>" style="margin-top:auto">
                        <?= e(t($item['link']['label'])) ?><?= site_icon('arrow') ?>
                    </a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:1.8rem"><a class="link-arrow" href="<?= e(site_page('/news')) ?>"><?= e(ui('view_all_news')) ?><?= site_icon('arrow') ?></a></p>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow"><?= e(t($home['visit_head']['eyebrow'])) ?></p>
            <h2><?= e(t($home['visit_head']['title'])) ?></h2>
        </div>
        <?php site_contact_cards(); ?>
    </div>
</section>

<?php
site_cta_band();
site_popup($home['popup'] ?? []);
site_footer();
