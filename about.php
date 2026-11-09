<?php
/** Public About page. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$about = $content['about'];
$teachers = site_teachers();

$page = [
    'slug'        => '/about',
    'title'       => 'About us',
    'title_tag'   => 'About us · ' . SCHOOL_NAME,
    'description' => 'Who Wilude Birhan Sunday School is: its purpose, mission, values, governance and the parish it serves.',
    'breadcrumbs' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'About us', 'path' => '/about'],
    ],
];
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs'], 'ስለ እኛ');
site_page_hero($about['eyebrow'], $about['title'], $about['lead'], '', '');
?>

<section class="section">
    <div class="wrap split">
        <div data-reveal>
            <p class="eyebrow">Our story</p>
            <h2>A ministry of the parish</h2>
            <?php foreach ($about['story'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
            <div class="notice notice--green" style="margin-top:1.4rem">
                <?= site_icon('pin') ?>
                <p><?= e(PARISH_NAME_AM) ?><br><span class="muted"><?= e(PARISH_NAME_EN) ?> · <?= e(DENOMINATION_EN) ?></span></p>
            </div>
        </div>
        <div class="split__media" data-reveal>
            <img src="<?= e(site_page('/themes/fkss/assets/wbss-hero-courtyard.jpg')) ?>"
                 alt="Children in white netela shawls walking towards the door of a round Ethiopian Orthodox church with a thatched roof and painted frescoes."
                 width="1672" height="941" loading="lazy" decoding="async">
            <p class="split__caption">The parish compound where the school meets each Sunday.</p>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="grid grid--2">
            <article class="card" data-reveal>
                <span class="icon-badge"><?= site_icon('cross') ?></span>
                <h2 style="font-size:1.5rem;margin-top:1rem">Mission</h2>
                <p><?= e($about['mission']) ?></p>
                <?php if (!empty($about['mission_am'])): ?>
                <p class="card__am" lang="am" style="margin:0"><?= e($about['mission_am']) ?></p>
                <?php endif; ?>
            </article>
            <article class="card" data-reveal>
                <span class="icon-badge icon-badge--gold"><?= site_icon('sparkle') ?></span>
                <h2 style="font-size:1.5rem;margin-top:1rem">Vision</h2>
                <p><?= e($about['vision']) ?></p>
            </article>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow">What we hold to</p>
            <h2>Our values</h2>
        </div>
        <div class="grid grid--3">
            <?php foreach ($about['values'] as $value): ?>
            <article class="card" data-reveal>
                <h3><?= e($value['title']) ?></h3>
                <p><?= e($value['text']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap split">
        <div data-reveal>
            <p class="eyebrow">How we began</p>
            <h2>Milestones</h2>
            <p class="section-head__lead">The milestones below describe how the school grew. Specific years and names are published only after the parish confirms them.</p>
            <ol class="timeline" style="margin-top:1.6rem">
                <?php foreach ($about['timeline'] as $milestone): ?>
                <li>
                    <h3><?= e($milestone['title']) ?></h3>
                    <p><?= e($milestone['text']) ?></p>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div data-reveal>
            <p class="eyebrow">Governance</p>
            <h2>Who is responsible</h2>
            <div class="grid" style="gap:1rem">
                <?php foreach ($about['governance'] as $item): ?>
                <article class="card">
                    <h3 style="margin-top:0"><?= e($item['title']) ?></h3>
                    <p><?= e($item['text']) ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow">Teaching team</p>
            <h2>Our teachers</h2>
            <p class="section-head__lead"><?= e($about['leadership_note']) ?></p>
        </div>
        <?php if ($teachers): ?>
        <div class="grid grid--4">
            <?php foreach ($teachers as $teacher): ?>
            <article class="card center" data-reveal>
                <?php if (!empty($teacher['photo'])): ?>
                <img src="<?= e(site_page($teacher['photo'])) ?>" alt="<?= e($teacher['name'] ?: 'Teacher photograph') ?>"
                     width="120" height="120" loading="lazy" decoding="async"
                     style="width:96px;height:96px;border-radius:50%;object-fit:cover;margin:0 auto 1rem;border:2px solid var(--gold-500)">
                <?php else: ?>
                <span class="icon-badge" style="margin:0 auto 1rem"><?= site_icon('staff') ?></span>
                <?php endif; ?>
                <h3 style="margin-top:0"><?= e($teacher['name_am'] ?: $teacher['name']) ?></h3>
                <?php if (!empty($teacher['role'])): ?><p class="muted" style="margin:0 0 .4rem"><?= e($teacher['role']) ?></p><?php endif; ?>
                <?php if (!empty($teacher['bio'])): ?><p style="font-size:.92rem;margin:0"><?= e($teacher['bio']) ?></p><?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="grid grid--4">
            <?php
            $roles = [
                ['icon' => 'staff', 'title' => 'Sunday School head', 'text' => 'Leads the school with the parish and the Education Department.'],
                ['icon' => 'book', 'title' => 'Class teachers', 'text' => 'Prepare and teach the weekly lesson for their class.'],
                ['icon' => 'music', 'title' => 'Mezmur leader', 'text' => 'Leads hymn and prayer practice.'],
                ['icon' => 'users', 'title' => 'Education Department', 'text' => 'Coordinates curriculum, assessment and reports.'],
            ];
            foreach ($roles as $role): ?>
            <article class="card" data-reveal>
                <span class="icon-badge"><?= site_icon($role['icon']) ?></span>
                <h3 style="margin-top:1rem"><?= e($role['title']) ?></h3>
                <p><?= e($role['text']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
        <p class="muted" style="margin-top:1.4rem">Individual teacher profiles are published from the school administration system once the parish approves them.</p>
        <?php endif; ?>
    </div>
</section>

<?php
site_cta_band();
site_footer();
