<?php
/**
 * Public home page — Wilude Birhan Sunday School.
 *
 * Content comes from site_content.php (editorial) and the existing CMS tables
 * through public_site.php. Nothing here writes data or requires a session.
 */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$home = $content['home'];
$programs = site_programs();
$schedule = site_schedule_rows();
$news = site_news_items();

$page = [
    'slug'        => '/',
    'title'       => 'Home',
    'title_tag'   => SCHOOL_NAME_FULL_AM . ' · ' . SCHOOL_TRANSLATION_EN . ' ' . SCHOOL_TYPE,
    'description' => 'Wilude Birhan Sunday School, a ministry of ' . PARISH_NAME_EN . ': Scripture, Church teaching, Ge\'ez and Amharic, Mezmur and community for children and young people aged 4 to 18.',
    'gallery'     => false,
];
site_head($page);
site_header($page);
?>

<section class="hero">
    <div class="hero__media">
        <img src="<?= e(site_page('/themes/fkss/assets/wbss-heritage-hero.jpg')) ?>"
             alt="A sunlit church courtyard with carved wooden doors and an Ethiopian woven textile."
             width="1568" height="882" fetchpriority="high" decoding="async">
    </div>
    <div class="wrap hero__inner">
        <div class="hero__content">
            <p class="hero__parish" lang="am"><?= e(PARISH_NAME_AM) ?></p>
            <p class="eyebrow eyebrow--gold"><?= e($home['eyebrow']) ?></p>
            <h1 class="hero__title"><?= e(SCHOOL_TRANSLATION_EN . ' ' . SCHOOL_TYPE) ?></h1>
            <p class="hero__title-am" lang="am"><?= e(SCHOOL_NAME_AMHARIC) ?></p>
            <p class="hero__name-en"><?= e(DENOMINATION_EN) ?></p>
            <p class="hero__lead"><?= e($home['lead']) ?></p>
            <p class="hero__lead-am" lang="am"><?= e($home['lead_am']) ?></p>
            <div class="hero__actions">
                <a class="btn btn--gold" href="<?= e(site_page($home['primary_cta']['path'])) ?>">
                    <?= e($home['primary_cta']['label']) ?><?= site_icon('arrow') ?>
                </a>
                <a class="btn btn--ghost" href="<?= e(site_page($home['secondary_cta']['path'])) ?>">
                    <?= e($home['secondary_cta']['label']) ?>
                </a>
            </div>
            <div class="hero__meta">
                <p><?= site_icon('calendar') ?>Sunday lessons 9:00 AM – 12:00 PM</p>
                <p><?= site_icon('pin') ?><?= e($content['contact']['address_lines'][0]) ?></p>
                <p><?= site_icon('users') ?>Ages 4 – 18</p>
            </div>
        </div>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap">
        <ul class="highlights" style="list-style:none;padding:0;margin:0">
            <?php foreach ($home['highlights'] as $highlight): ?>
            <li class="highlight" data-reveal>
                <span class="icon-badge"><?= site_icon($highlight['icon']) ?></span>
                <h3><?= e($highlight['title']) ?></h3>
                <p><?= e($highlight['text']) ?></p>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="section">
    <div class="wrap split">
        <div class="split__media" data-reveal>
            <img src="<?= e(site_page($home['intro']['image'])) ?>" alt="<?= e($home['intro']['image_alt']) ?>"
                 width="1568" height="882" loading="lazy" decoding="async">
            <p class="split__caption">Faith handed on: Scripture, language and worship.</p>
        </div>
        <div data-reveal>
            <p class="eyebrow"><?= e($home['intro']['eyebrow']) ?></p>
            <h2><?= e($home['intro']['title']) ?></h2>
            <?php foreach ($home['intro']['body'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
            <ul class="check-list">
                <?php foreach ($home['intro']['points'] as $point): ?>
                <li><?= site_icon('check') ?><span><?= e($point) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top:1.6rem">
                <a class="btn btn--outline" href="<?= e(site_page('/about')) ?>">About the school<?= site_icon('arrow') ?></a>
            </p>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head section-head--center">
            <p class="eyebrow">What shapes our teaching</p>
            <h2>Four commitments</h2>
            <p class="section-head__lead">Everything in the Sunday programme rests on these four commitments, and every class is planned around them.</p>
        </div>
        <div class="grid grid--4">
            <?php foreach ($content['pillars'] as $pillar): ?>
            <article class="card" data-reveal>
                <span class="icon-badge"><?= site_icon($pillar['icon']) ?></span>
                <h3><?= e($pillar['title']) ?></h3>
                <p class="card__am" lang="am"><?= e($pillar['title_am']) ?></p>
                <p><?= e($pillar['text']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow">Programs</p>
            <h2>A class for every age</h2>
            <p class="section-head__lead"><?= e($content['programs']['lead']) ?></p>
        </div>
        <div class="grid grid--3">
            <?php foreach ($programs as $program): ?>
            <article class="card card--program" data-reveal>
                <div class="card__top">
                    <span class="icon-badge"><?= site_icon($program['icon'] ?? 'book') ?></span>
                    <?php if (!empty($program['ages'])): ?>
                    <p class="card__ages"><?= site_icon('users') ?><?= e($program['ages']) ?></p>
                    <?php endif; ?>
                    <h3><?= e($program['title']) ?></h3>
                    <?php if (!empty($program['title_am'])): ?>
                    <p class="card__am" lang="am"><?= e($program['title_am']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="card__body">
                    <?php if (!empty($program['summary'])): ?>
                    <p><?= nl2br(e($program['summary'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($program['features'])): ?>
                    <ul class="card__list">
                        <?php foreach (array_slice($program['features'], 0, 4) as $feature): ?>
                        <li><?= site_icon('check') ?><span><?= e($feature) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:1.8rem">
            <a class="link-arrow" href="<?= e(site_page('/programs')) ?>">See the full curriculum<?= site_icon('arrow') ?></a>
        </p>
    </div>
</section>

<section class="section section--green">
    <div class="wrap split">
        <div data-reveal>
            <p class="eyebrow">Weekly rhythm</p>
            <h2>Sunday, step by step</h2>
            <p class="lead">The pattern does not change from week to week, which is how children learn it. Feast days and holidays are announced separately.</p>
            <p style="margin-top:1.4rem"><a class="btn btn--gold" href="<?= e(site_page('/schedule')) ?>">Full schedule<?= site_icon('arrow') ?></a></p>
        </div>
        <div class="table-wrap" data-reveal tabindex="0" role="region" aria-label="Sunday programme">
            <table class="schedule">
                <caption>Next Sunday at <?= e(SCHOOL_TRANSLATION_EN) ?></caption>
                <thead>
                    <tr><th scope="col">Time</th><th scope="col">What happens</th><th scope="col">Where</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule as $row): ?>
                    <tr>
                        <td data-label="Time"><strong><?= e($row['time']) ?></strong></td>
                        <td data-label="What happens">
                            <?= e($row['activity']) ?>
                            <?php if (!empty($row['activity_am'])): ?><span class="am" lang="am"><?= e($row['activity_am']) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Where"><?= e($row['place']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="quote-band">
    <div class="wrap section--tight">
        <div class="quote-band__inner">
            <?= site_icon('quote') ?>
            <blockquote>
                <p><?= e($home['quote']['text']) ?></p>
                <p class="quote-band__am" lang="am"><?= e($home['quote']['text_am']) ?></p>
                <cite><?= e($home['quote']['source']) ?></cite>
            </blockquote>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow"><?= e($content['news']['eyebrow']) ?></p>
            <h2><?= e($content['news']['title']) ?></h2>
            <p class="section-head__lead"><?= e($content['news']['lead']) ?></p>
        </div>
        <div class="grid grid--3">
            <?php foreach (array_slice($news, 0, 3) as $item): ?>
            <article class="card" data-reveal>
                <p class="card__badge"><?= $item['kind'] === 'announcement' ? 'Announcement' : 'Notice' ?></p>
                <h3><?= e($item['title']) ?></h3>
                <?php if (!empty($item['title_am'])): ?><p class="card__am" lang="am"><?= e($item['title_am']) ?></p><?php endif; ?>
                <?php if (!empty($item['date'])): ?>
                <p class="muted" style="font-size:.85rem;margin:0 0 .5rem"><?= site_icon('calendar') ?> <?= e($item['date']) ?></p>
                <?php endif; ?>
                <p><?= nl2br(e($item['text'])) ?></p>
                <?php if (!empty($item['link'])): ?>
                <a class="link-arrow" href="<?= e(site_page($item['link']['path'])) ?>" style="margin-top:auto">
                    <?= e($item['link']['label']) ?><?= site_icon('arrow') ?>
                </a>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:1.8rem"><a class="link-arrow" href="<?= e(site_page('/news')) ?>">All news and notices<?= site_icon('arrow') ?></a></p>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow">Visit us</p>
            <h2>Where to find the school</h2>
            <p class="section-head__lead">The Sunday school meets in the parish compound. Please call the office before travelling if you are unsure of the entrance.</p>
        </div>
        <?php site_contact_cards(); ?>
    </div>
</section>

<?php
site_cta_band();
site_footer();
