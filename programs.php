<?php
/** Public Programmes page. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$programsContent = $content['programs'];
$programs = site_programs();

$page = [
    'slug'        => '/programs',
    'title'       => L('Programmes', 'መርሃ ግብሮች'),
    'title_tag'   => null,
    'description' => $programsContent['lead'],
    'breadcrumbs' => [
        ['label' => L('Home', 'መነሻ'), 'path' => '/'],
        ['label' => L('Programmes', 'መርሃ ግብሮች'), 'path' => '/programs'],
    ],
];
$page['title_tag'] = t($page['title']) . ' · ' . site_school_name();
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs']);
site_page_hero($programsContent['eyebrow'], $programsContent['title'], $programsContent['lead']);
?>

<?php if ($programs): ?>
<section class="section">
    <div class="wrap">
        <div class="section-head" <?= reveal('up') ?>>
            <p class="eyebrow"><?= e(t($programsContent['classes_eyebrow'])) ?></p>
            <h2><?= e(t($programsContent['classes_title'])) ?></h2>
        </div>
        <div class="grid grid--3">
            <?php foreach ($programs as $program): ?>
            <article class="card card--program" <?= reveal('up') ?>>
                <div class="card__top">
                    <span class="icon-badge"><?= site_icon($program['icon'] ?? 'book') ?></span>
                    <h3><?= e($program['title']) ?></h3>
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
    </div>
</section>
<?php endif; ?>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head" <?= reveal('up') ?>>
            <p class="eyebrow"><?= e(t($programsContent['curriculum_eyebrow'])) ?></p>
            <h2><?= e(t($programsContent['curriculum_title'])) ?></h2>
        </div>
        <div class="grid grid--3">
            <?php foreach ($programsContent['curriculum'] as $subject): ?>
            <article class="card" <?= reveal('up') ?>>
                <span class="icon-badge"><?= site_icon('book') ?></span>
                <h3 style="margin-top:1rem"><?= e(t($subject['title'])) ?></h3>
                <p><?= e(t($subject['text'])) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap split">
        <div <?= reveal('left') ?>>
            <p class="eyebrow"><?= e(t($programsContent['week_eyebrow'])) ?></p>
            <h2><?= e(t($programsContent['week_title'])) ?></h2>
            <ol class="steps" style="margin-top:1.4rem">
                <?php foreach ($programsContent['week_steps'] as $step): ?>
                <li>
                    <div>
                        <h3><?= e(t($step['title'])) ?></h3>
                        <p><?= e(t($step['text'])) ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div <?= reveal('right') ?>>
            <div class="notice">
                <?= site_icon('sparkle') ?>
                <div>
                    <p><strong><?= e(t($programsContent['notices_title'])) ?></strong></p>
                    <ul style="margin:.5rem 0 0;padding-left:1rem">
                        <?php foreach ($programsContent['notices'] as $notice): ?>
                        <li><?= e(t($notice)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="card" style="margin-top:1.2rem">
                <h3 style="margin-top:0"><?= e(ui('ask_class')) ?></h3>
                <p><?= e(t($programsContent['ask_text'])) ?></p>
                <p style="margin:0"><a class="btn btn--primary" href="<?= e(site_page('/admissions')) ?>"><?= e(ui('register_request')) ?><?= site_icon('arrow') ?></a></p>
            </div>
        </div>
    </div>
</section>

<?php
site_cta_band($programsContent['cta_title'], $programsContent['cta_text']);
site_footer();
