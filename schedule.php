<?php
/** Public Schedule page. */
require_once __DIR__ . '/public_site.php';

$content = site_content();
$scheduleContent = $content['schedule'];
$rows = site_schedule_rows();

$page = [
    'slug'        => '/schedule',
    'title'       => 'Schedule',
    'title_tag'   => 'Weekly schedule and office hours · ' . SCHOOL_NAME,
    'description' => 'Sunday lesson times, arrival, Mezmur practice and school office hours at Wilude Birhan Sunday School.',
    'breadcrumbs' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'Schedule', 'path' => '/schedule'],
    ],
];
site_head($page);
site_header($page);
site_breadcrumbs($page['breadcrumbs'], 'የሳምንት መርሃ ግብር');
site_page_hero($scheduleContent['eyebrow'], $scheduleContent['title'], $scheduleContent['lead']);
?>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <p class="eyebrow">Sunday</p>
            <h2>The weekly programme</h2>
            <?php if (($rows[0]['source'] ?? 'site') === 'cms'): ?>
            <p class="section-head__lead">Published by the school administration.</p>
            <?php else: ?>
            <p class="section-head__lead">This is the standard weekly programme. Feast days, holidays and term breaks are announced separately.</p>
            <?php endif; ?>
        </div>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Sunday programme">
            <table class="schedule">
                <caption>Sunday programme</caption>
                <thead>
                    <tr>
                        <th scope="col">Day</th>
                        <th scope="col">Time</th>
                        <th scope="col">Activity</th>
                        <th scope="col">Place</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="Day">
                            <span class="day-pill"><?= e($row['day']) ?></span>
                            <?php if (!empty($row['day_am'])): ?><span class="am" lang="am"><?= e($row['day_am']) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Time"><strong><?= e($row['time']) ?></strong></td>
                        <td data-label="Activity">
                            <?= e($row['activity']) ?>
                            <?php if (!empty($row['activity_am'])): ?><span class="am" lang="am"><?= e($row['activity_am']) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Place"><?= e($row['place']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="grid grid--2" style="margin-top:1.6rem">
            <div class="notice notice--green">
                <?= site_icon('clock') ?>
                <p><strong>Please arrive by 8:30 AM.</strong> Attendance is taken before the opening prayer, and a late start makes it harder for the class to begin together.</p>
            </div>
            <div class="notice">
                <?= site_icon('calendar') ?>
                <p><?= e($scheduleContent['term_note']) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section section--sand">
    <div class="wrap split">
        <div data-reveal>
            <p class="eyebrow">Office</p>
            <h2><?= e($scheduleContent['office_hours']['title']) ?></h2>
            <p><?= e($scheduleContent['office_hours']['note']) ?></p>
            <div class="table-wrap" style="margin-top:1.2rem" tabindex="0" role="region" aria-label="Office hours">
                <table class="schedule">
                    <caption>Office hours</caption>
                    <thead><tr><th scope="col">Day</th><th scope="col">Hours</th></tr></thead>
                    <tbody>
                        <?php foreach ($content['contact']['office_hours'] as $hours): ?>
                        <tr>
                            <td data-label="Day">
                                <?= e($hours['day']) ?>
                                <?php if (!empty($hours['day_am'])): ?><span class="am" lang="am"><?= e($hours['day_am']) ?></span><?php endif; ?>
                            </td>
                            <td data-label="Hours"><?= e($hours['time']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div data-reveal>
            <div class="card">
                <span class="icon-badge"><?= site_icon('phone') ?></span>
                <h3 style="margin-top:1rem">Speak to the office</h3>
                <p><?= e($content['contact']['response_note']) ?></p>
                <?php if (!empty($content['contact']['phone_primary']['value'])): ?>
                <p style="margin:0 0 .4rem"><strong><?= e($content['contact']['phone_primary']['value']) ?></strong></p>
                <?php endif; ?>
                <?php if (!empty($content['contact']['email_primary']['value'])): ?>
                <p style="margin:0 0 1rem"><a href="<?= e($content['contact']['email_primary']['href']) ?>"><?= e($content['contact']['email_primary']['value']) ?></a></p>
                <?php endif; ?>
                <a class="link-arrow" href="<?= e(site_page('/contact')) ?>">Contact page<?= site_icon('arrow') ?></a>
            </div>
        </div>
    </div>
</section>

<?php
site_cta_band('Come and see a Sunday', 'Visitors are welcome to see the school in session. Speak to the office first so a teacher can meet you.');
site_footer();
