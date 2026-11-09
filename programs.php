<?php
/** Public Programs page. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$programsContent = $content['programs'];
$programs = site_programs();

$page = [
    'slug'        => '/programs',
    'title'       => 'Programs',
    'title_tag'   => 'Programs and curriculum · ' . SCHOOL_NAME,
    'description' => 'Curriculum subjects and the weekly pattern of the Wilude Birhan Sunday School programme, taught to children, young people and adults at every level.',
    'breadcrumbs' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'Programs', 'path' => '/programs'],
    ],
];
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs'], 'የትምህርት መርሃ ግብሮች');
site_page_hero($programsContent['eyebrow'], $programsContent['title'], $programsContent['lead']);
?>

<?php if ($programs): ?>
<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow">Classes</p>
            <h2>Classes published by the school</h2>
            <p class="section-head__lead">Each class follows the same curriculum at its own level, so a learner who moves up a class continues rather than restarts.</p>
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
                        <?php foreach ($program['features'] as $feature): ?>
                        <li><?= site_icon('check') ?><span><?= e($feature) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <p class="muted" style="margin-top:1.2rem">These classes are published by the school administration.</p>
    </div>
</section>
<?php endif; ?>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow">Curriculum</p>
            <h2>The subjects taught</h2>
            <p class="section-head__lead">The curriculum is set by the school's Education Department and taught by the class teachers — the same subjects at every level, from the youngest class to adult members.</p>
        </div>
        <div class="grid grid--3">
            <?php foreach ($programsContent['curriculum'] as $subject): ?>
            <article class="card" data-reveal>
                <span class="icon-badge"><?= site_icon('book') ?></span>
                <h3 style="margin-top:1rem"><?= e($subject['title']) ?></h3>
                <p><?= e($subject['text']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap split">
        <div data-reveal>
            <p class="eyebrow">The week</p>
            <h2><?= e($programsContent['week']['title']) ?></h2>
            <ol class="steps" style="margin-top:1.4rem">
                <?php foreach ($programsContent['week']['steps'] as $step): ?>
                <li>
                    <div>
                        <h3><?= e($step['title']) ?></h3>
                        <p><?= e($step['text']) ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div data-reveal>
            <div class="notice">
                <?= site_icon('sparkle') ?>
                <div>
                    <p><strong>Good to know</strong></p>
                    <ul style="margin:.5rem 0 0;padding-left:1rem">
                        <?php foreach ($programsContent['notices'] as $notice): ?>
                        <li><?= e($notice) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="card" style="margin-top:1.2rem">
                <h3 style="margin-top:0">Ask about a class</h3>
                <p>Not sure which class suits you or your child? Send a request and the office will advise — any age is welcome.</p>
                <p style="margin:0"><a class="btn btn--primary" href="<?= e(site_page('/admissions')) ?>">Registration request<?= site_icon('arrow') ?></a></p>
            </div>
        </div>
    </div>
</section>

<?php
site_cta_band('Ready to join a class?', 'Registration requests are open to everyone in the parish — children, young people and adults. The school office will confirm the class with you.');
site_footer();
