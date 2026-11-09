<?php
/** Public About page. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$about = $content['about'];
$teachers = site_teachers();

$page = [
    'slug'        => '/about',
    'title'       => L('About us', 'ስለ እኛ'),
    'title_tag'   => L('About us', 'ስለ እኛ') ,
    'description' => $about['lead'],
    'breadcrumbs' => [
        ['label' => L('Home', 'መነሻ'), 'path' => '/'],
        ['label' => L('About us', 'ስለ እኛ'), 'path' => '/about'],
    ],
];
$page['title_tag'] = t($page['title']) . ' · ' . site_school_name();
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs']);
site_page_hero($about['eyebrow'], $about['title'], $about['lead']);
?>

<section class="section">
    <div class="wrap split">
        <div data-reveal>
            <p class="eyebrow"><?= e(t($about['story_title'])) ?></p>
            <h2><?= e(t($about['story_heading'])) ?></h2>
            <?php foreach ($about['story'] as $paragraph): ?>
            <p><?= e(t($paragraph)) ?></p>
            <?php endforeach; ?>
            <div class="notice notice--green" style="margin-top:1.4rem">
                <?= site_icon('pin') ?>
                <p><?= e(site_parish_name()) ?> · <?= e(site_lang_is_am() ? PARISH_NAME_EN : PARISH_NAME_AM) ?></p>
            </div>
        </div>
        <div class="split__media" data-reveal>
            <img src="<?= e(site_page('/themes/fkss/assets/wbss-hero-courtyard.jpg')) ?>"
                 alt="<?= e(t(L('Children in white netela shawls walking towards a round Ethiopian Orthodox church with a thatched roof and painted frescoes.',
                                'ልጆች በነጭ ነጠላ ተጠቅልለው ወደ ጎርፎ ቤት ያለው ክብ የኢትዮጵያ ኦርቶዶክስ ቤተክርስቲያን ሲሄዱ።'))) ?>"
                 width="1672" height="941" loading="lazy" decoding="async">
            <p class="split__caption"><?= e(t($about['caption'])) ?></p>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="grid grid--2">
            <article class="card" data-reveal>
                <span class="icon-badge"><?= site_icon('cross') ?></span>
                <h2 style="font-size:1.5rem;margin-top:1rem"><?= e(t($about['mission_title'])) ?></h2>
                <p><?= e(t($about['mission'])) ?></p>
            </article>
            <article class="card" data-reveal>
                <span class="icon-badge icon-badge--gold"><?= site_icon('sparkle') ?></span>
                <h2 style="font-size:1.5rem;margin-top:1rem"><?= e(t($about['vision_title'])) ?></h2>
                <p><?= e(t($about['vision'])) ?></p>
            </article>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow"><?= e(t($about['values_eyebrow'])) ?></p>
            <h2><?= e(t($about['values_title'])) ?></h2>
        </div>
        <div class="grid grid--3">
            <?php foreach ($about['values'] as $value): ?>
            <article class="card" data-reveal>
                <h3><?= e(t($value['title'])) ?></h3>
                <p><?= e(t($value['text'])) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap split">
        <div data-reveal>
            <p class="eyebrow"><?= e(t($about['milestones_eyebrow'])) ?></p>
            <h2><?= e(t($about['milestones_title'])) ?></h2>
            <ol class="timeline" style="margin-top:1.6rem">
                <?php foreach ($about['timeline'] as $milestone): ?>
                <li>
                    <h3><?= e(t($milestone['title'])) ?></h3>
                    <p><?= e(t($milestone['text'])) ?></p>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div data-reveal>
            <p class="eyebrow"><?= e(t($about['governance_eyebrow'])) ?></p>
            <h2><?= e(t($about['governance_title'])) ?></h2>
            <div class="grid" style="gap:1rem">
                <?php foreach ($about['governance'] as $item): ?>
                <article class="card">
                    <h3 style="margin-top:0"><?= e(t($item['title'])) ?></h3>
                    <p><?= e(t($item['text'])) ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow"><?= e(t($about['teachers_eyebrow'])) ?></p>
            <h2><?= e(t($about['teachers_title'])) ?></h2>
            <p class="section-head__lead"><?= e(t($about['leadership_note'])) ?></p>
        </div>
        <?php if ($teachers): ?>
        <div class="grid grid--4">
            <?php foreach ($teachers as $teacher): ?>
            <article class="card center" data-reveal>
                <?php if (!empty($teacher['photo'])): ?>
                <img src="<?= e(site_page($teacher['photo'])) ?>" alt="<?= e($teacher['name']) ?>"
                     width="120" height="120" loading="lazy" decoding="async"
                     style="width:96px;height:96px;border-radius:50%;object-fit:cover;margin:0 auto 1rem;border:2px solid var(--gold-500)">
                <?php else: ?>
                <span class="icon-badge" style="margin:0 auto 1rem"><?= site_icon('staff') ?></span>
                <?php endif; ?>
                <h3 style="margin-top:0"<?= site_lang_is_am() ? ' lang="am"' : '' ?>><?= e($teacher['name']) ?></h3>
                <?php if (!empty($teacher['role'])): ?><p class="muted" style="margin:0 0 .4rem"><?= e($teacher['role']) ?></p><?php endif; ?>
                <?php if (!empty($teacher['bio'])): ?><p style="font-size:.92rem;margin:0"><?= e($teacher['bio']) ?></p><?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="grid grid--4">
            <?php foreach ($about['teacher_roles'] as $role): ?>
            <article class="card" data-reveal>
                <span class="icon-badge"><?= site_icon($role['icon']) ?></span>
                <h3 style="margin-top:1rem"><?= e(t($role['title'])) ?></h3>
                <p><?= e(t($role['text'])) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php
site_cta_band();
site_footer();
