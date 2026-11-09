<?php
/** Public Contact page — enquiries use the existing validated public endpoint. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$contact = $content['contact'];

$page = [
    'slug'        => '/contact',
    'title'       => L('Contact', 'ያግኙን'),
    'title_tag'   => null,
    'description' => L('Address, email, office hours and the enquiry form.', 'አድራሻ፣ ኢሜይል፣ የሥራ ሰዓትና የመልእክት ቅጽ።'),
    'breadcrumbs' => [
        ['label' => L('Home', 'መነሻ'), 'path' => '/'],
        ['label' => L('Contact', 'ያግኙን'), 'path' => '/contact'],
    ],
];
$page['title_tag'] = t($page['title']) . ' · ' . site_school_name();
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs']);
site_page_hero(
    L('Contact', 'ያግኙን'),
    L('Talk to the school', 'ከትምህርት ቤቱ ጋር ይነጋገሩ'),
    L('Call during office hours or send a message.', 'በሥራ ሰዓት ይደውሉ ወይም መልእክት ይላኩ።')
);
?>

<section class="section">
    <div class="wrap split" style="align-items:start">
        <div data-reveal>
            <?php site_contact_cards(); ?>
            <div class="notice notice--green" style="margin-top:1.2rem">
                <?= site_icon('pin') ?>
                <p><strong><?= e(t($contact['map_label'])) ?></strong><br>
                <a href="<?= e($contact['map_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(ui('open_maps')) ?><?= site_icon('external') ?></a></p>
            </div>
            <div class="notice" style="margin-top:1.2rem">
                <?= site_icon('clock') ?>
                <p><?= e(t($contact['response_note'])) ?></p>
            </div>
        </div>
        <div data-reveal>
            <?php site_form('enquiry'); ?>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow"><?= e(t(L('Common questions', 'ተደጋጋሚ ጥያቄዎች'))) ?></p>
            <h2><?= e(t(L('Before you write', 'ከመጻፍዎ በፊት'))) ?></h2>
        </div>
        <div class="grid grid--2">
            <div class="card">
                <h3 style="margin-top:0"><?= e(t(L('Registering a child — or yourself', 'ልጅዎን — ወይም ራስዎን — ማስመዝገብ'))) ?></h3>
                <p><?= e(t(L('Use the admissions form so the request reaches the office with the details they need.', 'ጥያቄው አስፈላጊውን መረጃ ይዞ ጽ/ቤቱን እንዲደርስ የምዝገባ ቅጹን ይጠቀሙ።'))) ?></p>
                <a class="link-arrow" href="<?= e(site_page('/admissions')) ?>"><?= e(t(L('Admissions', 'ምዝገባ'))) ?><?= site_icon('arrow') ?></a>
            </div>
            <div class="card">
                <h3 style="margin-top:0"><?= e(t(L('Member records', 'የአባል መዝገብ'))) ?></h3>
                <p><?= e(t(L('Membership verification and ID cards are handled inside the school system — please speak to the office.', 'የአባልነት ማረጋገጫና መታወቂያ በትምህርት ቤቱ ሥርዓት ውስጥ ይሠራል — ጽ/ቤቱን ያነጋግሩ።'))) ?></p>
                <a class="link-arrow" href="<?= e(site_page('/privacy')) ?>"><?= e(ui('privacy_link')) ?><?= site_icon('arrow') ?></a>
            </div>
        </div>
    </div>
</section>

<?php
site_footer();
