<?php
/** Public Admissions page — the registration request form posts to the
 *  existing register_submit.php handler (validation, honeypot, rate limit). */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$admissions = $content['admissions'];

$page = [
    'slug'        => '/admissions',
    'title'       => 'Admissions',
    'title_tag'   => 'Admissions and registration · ' . SCHOOL_NAME,
    'description' => 'How to register a child at Wilude Birhan Sunday School: steps, what to prepare, common questions and the registration request form.',
    'breadcrumbs' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'Admissions', 'path' => '/admissions'],
    ],
];
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs'], 'ምዝገባ');
site_page_hero($admissions['eyebrow'], $admissions['title'], $admissions['lead']);
?>

<section class="section">
    <div class="wrap split">
        <div data-reveal>
            <p class="eyebrow">How it works</p>
            <h2>Four steps</h2>
            <ol class="steps" style="margin-top:1.2rem">
                <?php foreach ($admissions['steps'] as $step): ?>
                <li>
                    <div>
                        <h3><?= e($step['title']) ?></h3>
                        <p><?= e($step['text']) ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
            <div class="notice" style="margin-top:1.4rem">
                <?= site_icon('shield') ?>
                <p><strong>Please note:</strong> the form below sends a request for the school office to contact you. It does not create a member account, and it does not enrol your child automatically.</p>
            </div>
        </div>
        <div data-reveal>
            <?php site_form('registration'); ?>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="grid grid--2">
            <div data-reveal>
                <p class="eyebrow">Before you apply</p>
                <h2>What to have ready</h2>
                <ul class="check-list" style="margin-top:1rem">
                    <?php foreach ($admissions['requirements'] as $requirement): ?>
                    <li><?= site_icon('check') ?><span><?= e($requirement) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <div class="notice notice--green" style="margin-top:1.2rem">
                    <?= site_icon('heart') ?>
                    <p><?= e($admissions['fees_note']) ?></p>
                </div>
            </div>
            <div data-reveal>
                <p class="eyebrow">Questions</p>
                <h2>Frequently asked</h2>
                <div class="accordion" style="margin-top:1.2rem">
                    <?php foreach ($admissions['faq'] as $index => $faq): ?>
                    <details<?= $index === 0 ? ' open' : '' ?>>
                        <summary>
                            <span><?= e($faq['q']) ?></span>
                            <?= site_icon('chevron') ?>
                        </summary>
                        <p><?= e($faq['a']) ?></p>
                    </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
site_cta_band('Still have a question?', 'The school office answers questions about classes, attendance and what a Sunday looks like.');
site_footer();
