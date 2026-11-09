<?php
/** Public Admissions page — the registration request form posts to the
 *  existing register_submit.php handler (validation, honeypot, rate limit). */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$admissions = $content['admissions'];

$page = [
    'slug'        => '/admissions',
    'title'       => L('Admissions', 'ምዝገባ'),
    'title_tag'   => null,
    'description' => $admissions['lead'],
    'breadcrumbs' => [
        ['label' => L('Home', 'መነሻ'), 'path' => '/'],
        ['label' => L('Admissions', 'ምዝገባ'), 'path' => '/admissions'],
    ],
];
$page['title_tag'] = t($page['title']) . ' · ' . site_school_name();
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs']);
site_page_hero($admissions['eyebrow'], $admissions['title'], $admissions['lead']);
?>

<section class="section">
    <div class="wrap split">
        <div data-reveal>
            <p class="eyebrow"><?= e(t($admissions['steps_eyebrow'])) ?></p>
            <h2><?= e(t($admissions['steps_title'])) ?></h2>
            <ol class="steps" style="margin-top:1.2rem">
                <?php foreach ($admissions['steps'] as $step): ?>
                <li>
                    <div>
                        <h3><?= e(t($step['title'])) ?></h3>
                        <p><?= e(t($step['text'])) ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
            <div class="notice" style="margin-top:1.4rem">
                <?= site_icon('shield') ?>
                <p><?= e(t($admissions['form_note'])) ?></p>
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
                <p class="eyebrow"><?= e(t($admissions['requirements_eyebrow'])) ?></p>
                <h2><?= e(t($admissions['requirements_title'])) ?></h2>
                <ul class="check-list" style="margin-top:1rem">
                    <?php foreach ($admissions['requirements'] as $requirement): ?>
                    <li><?= site_icon('check') ?><span><?= e(t($requirement)) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <div class="notice notice--green" style="margin-top:1.2rem">
                    <?= site_icon('heart') ?>
                    <p><?= e(t($admissions['fees_note'])) ?></p>
                </div>
            </div>
            <div data-reveal>
                <p class="eyebrow"><?= e(t($admissions['faq_eyebrow'])) ?></p>
                <h2><?= e(t($admissions['faq_title'])) ?></h2>
                <div class="accordion" style="margin-top:1.2rem">
                    <?php foreach ($admissions['faq'] as $index => $faq): ?>
                    <details<?= $index === 0 ? ' open' : '' ?>>
                        <summary>
                            <span><?= e(t($faq['q'])) ?></span>
                            <?= site_icon('chevron') ?>
                        </summary>
                        <p><?= e(t($faq['a'])) ?></p>
                    </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
site_cta_band($admissions['cta_title'], $admissions['cta_text']);
site_footer();
