<?php
/**
 * Public website editorial content (Wilude Birhan Sunday School).
 *
 * This file is the single place to edit the words shown on the public site.
 * It is plain PHP data: no database access, no side effects, safe to include.
 *
 * SCHEDULE/STATUS OF COPY
 * -----------------------
 * The paragraphs below are DRAFT editorial copy written for the new site.
 * Facts that only the school can confirm are marked with `'draft' => true`
 * and surfaced through the site-wide draft notice until the school approves
 * them. Numbers, dates, staff names and contact details were deliberately NOT
 * invented: where a real value was not supplied by the school, the value that
 * was already published on the previous website is reused and flagged.
 *
 * To publish the site without the draft bar, set WBSS_SITE_CONTENT_DRAFT to
 * false once every flagged item has been checked by the school office.
 */

define('WBSS_SITE_CONTENT_DRAFT', true);

return [
    // ── Identity-independent site settings ─────────────────────────────
    'site' => [
        'default_title_suffix' => 'Wilude Birhan Sunday School',
        'founded_note'        => 'Serving the parish community of Gelan Gura Felege Giyon St. Gabriel Church.',
        'admin_login_path'    => '/admin/login',
        'draft_notice'        => 'Draft website content: contact details and some text are placeholders awaiting approval by the school office.',
    ],

    // ── Contact details ────────────────────────────────────────────────
    // Reused from the previous public page. Confirm with the school office
    // before publishing; see 'draft' flags.
    'contact' => [
        'phone_primary'   => ['label' => 'Office phone', 'value' => '+251 XXX XXX XXX', 'href' => '', 'draft' => true],
        'email_primary'   => ['label' => 'Email', 'value' => 'info@wulidebirhan.org', 'href' => 'mailto:info@wulidebirhan.org', 'draft' => true],
        'address_lines'   => [
            'Gelan Gura, Felege Giyon St. Gabriel Church',
            'Addis Ababa, Ethiopia',
        ],
        'address_draft'   => true,
        'map_url'         => 'https://maps.app.goo.gl/iHSzmE5BphXrWo8Z8',
        'map_label'       => 'Open the parish location in Google Maps',
        'office_hours'    => [
            ['day' => 'Sunday', 'day_am' => 'እሁድ', 'time' => '8:30 AM – 12:30 PM'],
            ['day' => 'Wednesday', 'day_am' => 'ረቡዕ', 'time' => '5:30 PM – 8:00 PM'],
        ],
        'response_note'   => 'We aim to reply to enquiries within one working week. For urgent matters please call the office during the hours above.',
        'newsletter_note' => 'Ask the office to add you to the announcement list for term news and events.',
    ],

    // ── Home page ──────────────────────────────────────────────────────
    'home' => [
        'eyebrow'      => 'Sunday School · Ethiopian Orthodox Tewahdo',
        'lead'         => 'A parish Sunday school where children and young people learn Holy Scripture, the prayers and hymns of the Church, and the Ge\'ez and Amharic languages — growing in faith, character and service.',
        'lead_am'      => 'ውሉደ ብርሃን ሰንበት ትምህርት ቤት ልጆች እና ወጣቶች ቅዱስ መጽሐፍን፣ የቤተክርስቲያንን ጸሎትና ዝማሬ፣ የግዕዝ እና የአማርኛ ቋንቋ ትምህርት የሚማሩበት የደብር ሰንበት ትምህርት ቤት ነው።',
        'primary_cta'   => ['label' => 'Register your child', 'path' => '/admissions'],
        'secondary_cta' => ['label' => 'Explore our programs', 'path' => '/programs'],
        // Qualitative highlights only — no invented enrolment or exam figures.
        'highlights' => [
            ['icon' => 'book',  'title' => 'Scripture at the centre', 'text' => 'Weekly lessons grounded in the Holy Scriptures and the teaching of the Church.'],
            ['icon' => 'users', 'title' => 'Ages 4 to 18', 'text' => 'Classes arranged by age so every child learns at the right level.'],
            ['icon' => 'music', 'title' => 'Prayer and hymnody', 'text' => 'Mezmur, prayers and Church order practised together each Sunday.'],
            ['icon' => 'globe', 'title' => 'Ge\'ez and Amharic', 'text' => 'Reading and understanding the language of our liturgy.'],
        ],
        'intro' => [
            'eyebrow'  => 'Who we are',
            'title'    => 'Formation in faith, language and character',
            'body'     => [
                'Wilude Birhan Sunday School serves the children and young people of Gelan Gura Felege Giyon St. Gabriel Church. We are one of the parish ministries through which the Church hands on the faith to the next generation.',
                'Our programme brings together Scripture study, the prayers and hymns of the Ethiopian Orthodox Tewahdo Church, Ge\'ez and Amharic literacy, and the habits of service and fellowship that shape a Christian life.',
            ],
            'image'    => '/themes/fkss/assets/wbss-study-hero.jpg',
            'image_alt'=> 'An open illuminated manuscript and a hand cross on green cloth.',
            'points'   => [
                'Age-appropriate classes with a consistent weekly rhythm',
                'Teachers formed in the faith and supported by the parish',
                'Progress records kept by the school administration',
            ],
        ],
        'quote' => [
            'text'    => 'Your word is a lamp to my feet and a light to my path.',
            'source'  => 'Psalm 119:105',
            'text_am' => 'ቃልህ ለእግሬ መብራት ለመንገዴም ብርሃን ነው።',
        ],
    ],

    // ── Pillars shown on Home and About ────────────────────────────────
    'pillars' => [
        [
            'icon'  => 'book',
            'title' => 'Holy Scripture',
            'title_am' => 'ቅዱስ መጽሐፍ',
            'text'  => 'A structured journey through the Old and New Testaments, the Gospels and the Psalms, taught in a way each age can receive.',
        ],
        [
            'icon'  => 'cross',
            'title' => 'Faith and worship',
            'title_am' => 'እምነትና አምልኮ',
            'text'  => 'Prayer, fasting seasons, the feasts of the Church and the meaning of the liturgy practised together each Sunday.',
        ],
        [
            'icon'  => 'globe',
            'title' => 'Language and heritage',
            'title_am' => 'ቋንቋና ቅርስ',
            'text'  => 'Ge\'ez and Amharic reading, the Mezmur tradition, and the history and heritage of the Ethiopian Orthodox Tewahdo Church.',
        ],
        [
            'icon'  => 'users',
            'title' => 'Community and service',
            'title_am' => 'ማህበረሰብና አገልግሎት',
            'text'  => 'A dependable community where children build friendships, learn responsibility and begin a life of service.',
        ],
    ],

    // ── About page ─────────────────────────────────────────────────────
    'about' => [
        'eyebrow' => 'About the school',
        'title'   => 'A parish school with a clear purpose',
        'lead'    => 'Wilude Birhan Sunday School exists so that every child of our parish can read, understand and live the faith of the Church.',
        'story'   => [
            'Wilude Birhan Sunday School is a ministry of Gelan Gura Felege Giyon St. Gabriel Church, part of the Ethiopian Orthodox Tewahdo Church. The school gathers children and young people of the parish every Sunday for lesson, prayer and hymn practice.',
            'The programme is organised by the Education Department of the school together with the parish, and is taught by volunteer teachers who give their time and preparation to this service.',
            'Our work is deliberately simple and repeatable: a lesson, a prayer, a hymn, a record of attendance and progress, and a community that notices when a child is absent.',
        ],
        'mission' => 'To nurture young souls in the teaching of the Ethiopian Orthodox Tewahdo Church — fostering spiritual growth, moral values and a living connection with our faith, our language and our heritage.',
        'mission_am' => 'የኛ ተልእኮ ልጆችን በየኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተክርስቲያን ትምህርቶች እያሳደግን መንፈሳዊ እድገትን፣ የሞራል እሴቶችን እና ከእምነታችንና ከቅርሶቻችን ጋር ጥልቅ ግንኙነት መፍጠር ነው።',
        'vision'  => 'A generation that knows the Scriptures, prays with understanding, loves the Church and serves its community with integrity.',
        'values'  => [
            ['title' => 'Faithfulness', 'text' => 'We teach the faith we have received, without addition or omission.'],
            ['title' => 'Care', 'text' => 'Every child is known by name, and absence is followed up.'],
            ['title' => 'Diligence', 'text' => 'Lessons are prepared, attendance is recorded and progress is reviewed.'],
            ['title' => 'Honesty', 'text' => 'Records and reports reflect what actually happened.'],
            ['title' => 'Service', 'text' => 'Teachers serve voluntarily and pupils learn to serve each other.'],
        ],
        'leadership_note' => 'The school leadership and teaching team are listed in the school administration system. Staff names are published here only after the parish approves them.',
        // No names, dates or claims are invented for the timeline.
        'timeline' => [
            ['title' => 'Our beginning', 'text' => 'The school began as a parish ministry teaching children the prayers and Scriptures in the church compound.', 'draft' => true],
            ['title' => 'Growing structure', 'text' => 'Classes were organised by age and the Education Department took responsibility for the curriculum.', 'draft' => true],
            ['title' => 'Keeping records', 'text' => 'Attendance, lesson progress and term reports became part of the school\'s weekly discipline.', 'draft' => true],
            ['title' => 'Today', 'text' => 'The school serves the children of the parish with a weekly programme of lesson, prayer and hymnody, supported by a digital administration system.', 'draft' => false],
        ],
        'governance' => [
            ['title' => 'Parish oversight', 'text' => 'The school operates under the authority and blessing of the parish and its administration.'],
            ['title' => 'Education Department', 'text' => 'Curriculum, class levels, assessment and teacher support are coordinated by the school\'s Education Department.'],
            ['title' => 'Records and privacy', 'text' => 'Pupil records are kept in the school system and used for attendance, progress, reports and ID cards.'],
        ],
    ],

    // ── Programs page ──────────────────────────────────────────────────
    'programs' => [
        'eyebrow' => 'Programs',
        'title'   => 'A place for every age',
        'lead'    => 'Classes are arranged by age so that lessons, expectations and activities suit the children in them. The programme below is our standard structure; the school may adjust class names in a given year.',
        'curriculum' => [
            ['title' => 'Scripture', 'text' => 'Old and New Testament lessons, the Gospels, and the Psalms, taught at an age-appropriate level.'],
            ['title' => 'Church teaching', 'text' => 'The creed, the sacraments, the feasts and fasts, and the order of worship.'],
            ['title' => 'Language', 'text' => 'Ge\'ez and Amharic reading and comprehension, including the alphabet for younger classes.'],
            ['title' => 'Mezmur and prayer', 'text' => 'Learning the hymns and prayers of the Church and taking part in the Sunday service.'],
            ['title' => 'Character and service', 'text' => 'Good conduct, responsibility, care for others, and practical service in the parish.'],
            ['title' => 'Assessment and reports', 'text' => 'Regular lesson assessments, term reports and parent conversations about progress.'],
        ],
        'fallback_classes' => [
            [
                'title' => 'Little Lambs', 'title_am' => 'ትንንሽ በጎች', 'ages' => 'Ages 4–6',
                'summary' => 'First steps in the faith through story, song and play.',
                'features' => ['Basic Bible stories', 'Simple prayers and hymns', 'Introduction to the Ge\'ez alphabet', 'Arts and crafts activities'],
            ],
            [
                'title' => 'Young Disciples', 'title_am' => 'ወጣት ደቀ መዛሙርት', 'ages' => 'Ages 7–12',
                'summary' => 'Building a foundation in Scripture and Church practice.',
                'features' => ['In-depth Scripture lessons', 'Ge\'ez language lessons', 'Memory verses and prayers', 'Church history and heritage'],
            ],
            [
                'title' => 'Youth in Faith', 'title_am' => 'በእምነት ያደጉ ወጣቶች', 'ages' => 'Ages 13–18',
                'summary' => 'Deeper study, Mezmur, and preparation for service.',
                'features' => ['Advanced Scripture study', 'Mezmur and hymnody', 'Doctrine and Church teaching', 'Service and leadership opportunities'],
            ],
        ],
        'week' => [
            'title' => 'What a Sunday looks like',
            'steps' => [
                ['title' => 'Arrival and registration', 'text' => 'Pupils arrive, greet their teachers and attendance is taken.'],
                ['title' => 'Opening prayer', 'text' => 'The school gathers for prayer and a hymn before lessons.'],
                ['title' => 'Class lesson', 'text' => 'Each class works through the lesson for the week.'],
                ['title' => 'Mezmur and review', 'text' => 'Hymns are practised and the week\'s lesson is reviewed.'],
                ['title' => 'Closing', 'text' => 'Closing prayer and announcements, then dismissal in an orderly way.'],
            ],
        ],
        'notices' => [
            'Programmes and class names may be adjusted at the start of the academic year.',
            'Children are placed according to age and, where appropriate, previous year\'s progress.',
            'Families are asked to inform the school of any special need so support can be arranged.',
        ],
    ],

    // ── Schedule page ──────────────────────────────────────────────────
    'schedule' => [
        'eyebrow' => 'Weekly rhythm',
        'title'   => 'When the school meets',
        'lead'    => 'The Sunday programme is the same every week during term. Feast days and school breaks may change the pattern — announcements are made in the school system and in the parish.',
        'fallback_week' => [
            ['day' => 'Sunday', 'day_am' => 'እሁድ', 'time' => '8:30 AM', 'activity' => 'Arrival, registration and opening prayer', 'activity_am' => 'መግቢያ፣ ምዝገባና የመክፈቻ ጸሎት', 'place' => 'Parish compound'],
            ['day' => 'Sunday', 'day_am' => 'እሁድ', 'time' => '9:00 AM – 12:00 PM', 'activity' => 'Class lessons by age', 'activity_am' => 'በእድሜ የተከፋፈሉ የትምህርት ክፍሎች', 'place' => 'Class rooms'],
            ['day' => 'Sunday', 'day_am' => 'እሁድ', 'time' => '12:00 PM', 'activity' => 'Mezmur practice and closing', 'activity_am' => 'የመዝሙር ልምምድና መዝጊያ', 'place' => 'Church yard'],
        ],
        'office_hours' => [
            'title' => 'School office',
            'note'  => 'The office is staffed by volunteers, so please call before travelling.',
        ],
        'term_note' => 'Term dates follow the parish calendar and are announced to parents through the school system. There is no promotion or public listing of individual pupil dates.',
    ],

    // ── Admissions page ────────────────────────────────────────────────
    'admissions' => [
        'eyebrow' => 'Admissions',
        'title'   => 'Registering your child',
        'lead'    => 'Registration is open to the children and young people of the parish. Send a request using the form below and a member of the school office will contact you with the next steps.',
        'steps' => [
            ['title' => 'Send a request', 'text' => 'Complete the form with your child\'s details. It is a request for contact — not an account and not a school record.'],
            ['title' => 'Talk with the office', 'text' => 'The school office contacts you to confirm the class and answer questions.'],
            ['title' => 'Bring your child', 'text' => 'Come with your child on a Sunday so they can meet the teacher and see the class.'],
            ['title' => 'Enrolment in the school system', 'text' => 'Confirmed pupils are registered by the school office in the administration system, where attendance and progress are recorded.'],
        ],
        'requirements' => [
            'The child\'s full name and age',
            'A parent or guardian phone number that we can reach',
            'The class the child attended last year, if they are continuing',
            'Any special need the school should know about',
        ],
        'requirements_draft' => true,
        'fees_note' => 'The school does not publish fee amounts on this website. Please ask the office about any contribution, materials or uniform expectations for the current year.',
        'faq' => [
            ['q' => 'Is submitting the form the same as enrolling my child?', 'a' => 'No. The form is an enquiry that asks the school office to contact you. Enrolment happens with the office and is recorded in the school administration system.'],
            ['q' => 'From what age do you accept children?', 'a' => 'The Sunday school programme is arranged for children from around four to eighteen years old, in classes by age.'],
            ['q' => 'My child has never attended Sunday school. Can they join?', 'a' => 'Yes. New pupils commonly join a class for their age; the teacher will help them settle in.'],
            ['q' => 'What should my child bring?', 'a' => 'A notebook and pen if possible, and any lesson book the class uses. Ask the teacher on the first Sunday.'],
            ['q' => 'How is my child\'s information used?', 'a' => 'Enquiry details are used by the school office to contact you. Attendance, progress and reports are kept in the school system. Photos are published only under the school\'s privacy practice.'],
            ['q' => 'Do you publish pupils\' results or photographs?', 'a' => 'Individual results are not published on this website. The public gallery shows only photos approved by the school, with no member personal information.'],
        ],
        'form' => [
            'title'    => 'Registration request',
            'note'     => 'Fields marked with * are required. We will only use these details to respond to your request.',
            'programs' => ['Little Lambs (ages 4–6)', 'Young Disciples (ages 7–12)', 'Youth in Faith (ages 13–18)', 'Not sure — please advise'],
        ],
    ],

    // ── News and notices ───────────────────────────────────────────────
    // Undated editorial notices. Dated announcements can additionally be
    // stored in the optional cms_announcements table and are merged in
    // automatically when that table exists (see public_site.php).
    'news' => [
        'eyebrow' => 'News and notices',
        'title'   => 'Life of the school',
        'lead'    => 'Announcements for pupils, parents and teachers. Dated notices added by the school administration appear here automatically.',
        'items'   => [
            [
                'kind'  => 'notice',
                'title' => 'Registration for the new academic year',
                'title_am' => 'ለአዲሱ የትምህርት ዓመት ምዝገባ',
                'text'  => 'Families wishing to register a child can send a request through the admissions form at any time. The office will contact you with class and start details.',
                'link'  => ['label' => 'Registration request', 'path' => '/admissions'],
                'draft' => false,
            ],
            [
                'kind'  => 'notice',
                'title' => 'Sunday timetable',
                'title_am' => 'የእሁድ መርሃ ግብር',
                'text'  => 'Lessons begin at 9:00 AM and finish at 12:00 PM. Pupils should arrive by 8:30 AM so attendance can be taken before the opening prayer.',
                'link'  => ['label' => 'Weekly schedule', 'path' => '/schedule'],
                'draft' => false,
            ],
            [
                'kind'  => 'notice',
                'title' => 'Mezmur practice',
                'title_am' => 'የመዝሙር ልምምድ',
                'text'  => 'Hymn practice takes place at the close of the Sunday programme. New pupils are welcome to join the practice group with their class.',
                'link'  => null,
                'draft' => false,
            ],
            [
                'kind'  => 'notice',
                'title' => 'A word to parents',
                'title_am' => 'ለወላጆች ማሳሰቢያ',
                'text'  => 'Please tell your child\'s teacher if your family will be away for several Sundays. Following up absence is part of how the school cares for its pupils.',
                'link'  => null,
                'draft' => false,
            ],
        ],
        'empty_note' => 'Dated announcements will be listed here as the school publishes them.',
    ],

    // ── Gallery page ───────────────────────────────────────────────────
    'gallery' => [
        'eyebrow' => 'Gallery',
        'title'   => 'Moments from school life',
        'lead'    => 'Photographs of lessons, Mezmur practice, feast days and school events, published by the school. Only photos approved for public viewing appear here — no pupil records or personal details.',
        'note'    => 'The school publishes photographs with the agreement of parents and guardians. If you would like a photograph removed, please contact the office.',
    ],

    // ── Privacy page ───────────────────────────────────────────────────
    'privacy' => [
        'eyebrow' => 'Privacy and safeguarding',
        'title'   => 'How this website handles information',
        'lead'    => 'This page describes what the public website does with information. It is a plain description of the current system and is not a substitute for a reviewed school policy.',
        'sections' => [
            ['title' => 'What the website collects', 'items' => [
                'When you send a registration or contact request, the form records the name, phone number, and any email, age, programme interest and message you choose to give.',
                'The server records the requesting internet address with the submission so that automated abuse can be limited. This is a technical safeguard, not a profile of you.',
                'No advertising, analytics or tracking scripts are used on this website, and no third-party cookies are set by the site itself.',
            ]],
            ['title' => 'What the website publishes', 'items' => [
                'The public gallery shows photographs selected by the school. The public gallery service returns only active photos and no member personal information.',
                'Member verification, pupil records, results, attendance and finances are inside the administration system and are not published on this website.',
                'Individual results, report cards and ID cards are never shown publicly.',
            ]],
            ['title' => 'Who can see a request', 'items' => [
                'Registration requests are visible to authorised school staff in the administration system, where they are handled and can be marked as contacted or enrolled.',
                'Requests are not sold, published or shared for marketing.',
            ]],
            ['title' => 'Keeping children safe', 'items' => [
                'Photographs of pupils are published only with the agreement of parents or guardians.',
                'Staff use individual accounts with role-based access; the public website has no administrative login of its own.',
                'If you have a safeguarding concern, contact the school office or the parish directly.',
            ]],
            ['title' => 'Your choices', 'items' => [
                'You can ask the school office what has been recorded about your family\'s request.',
                'You can ask for a photograph of your child to be removed from the public gallery.',
                'You can ask for a registration request that is no longer needed to be closed.',
            ]],
        ],
        'review_note' => 'This description must be reviewed and approved by the school and parish before the website is treated as a published privacy statement.',
    ],

    // ── Search page ────────────────────────────────────────────────────
    'search' => [
        'eyebrow' => 'Search',
        'title'   => 'Search the website',
        'lead'    => 'Search the pages, programs, weekly schedule and notices published on this site.',
        'empty'   => 'Enter a word to search, for example “Mezmur”, “Ge\'ez”, “ages” or “schedule”.',
        'none'    => 'No page, programme or notice matched that search.',
    ],

    // ── Static page index used by search, footer and sitemap ───────────
    'pages' => [
        ['path' => '/', 'title' => 'Home', 'title_am' => 'መነሻ', 'nav' => true, 'footer' => false, 'keywords' => 'home welcome parish sunday school'],
        ['path' => '/about', 'title' => 'About us', 'title_am' => 'ስለ እኛ', 'nav' => true, 'footer' => true, 'keywords' => 'about history mission vision values parish church leadership governance'],
        ['path' => '/programs', 'title' => 'Programs', 'title_am' => 'የትምህርት መርሃ ግብሮች', 'nav' => true, 'footer' => true, 'keywords' => 'programs classes curriculum scripture geez amharic mezmur ages little lambs young disciples youth'],
        ['path' => '/schedule', 'title' => 'Schedule', 'title_am' => 'የሳምንት መርሃ ግብር', 'nav' => true, 'footer' => true, 'keywords' => 'schedule timetable sunday time office hours term calendar'],
        ['path' => '/admissions', 'title' => 'Admissions', 'title_am' => 'ምዝገባ', 'nav' => true, 'footer' => true, 'keywords' => 'admissions register registration enrol enrolment apply fees requirements faq'],
        ['path' => '/news', 'title' => 'News and notices', 'title_am' => 'ዜናና ማሳሰቢያዎች', 'nav' => true, 'footer' => true, 'keywords' => 'news notices announcements events mezmur practice timetable'],
        ['path' => '/gallery', 'title' => 'Gallery', 'title_am' => 'የፎቶ መድብር', 'nav' => true, 'footer' => true, 'keywords' => 'gallery photos pictures albums feast days events'],
        ['path' => '/contact', 'title' => 'Contact', 'title_am' => 'ያግኙን', 'nav' => true, 'footer' => true, 'keywords' => 'contact phone email address location map office hours enquiry'],
        ['path' => '/privacy', 'title' => 'Privacy and safeguarding', 'title_am' => 'ግላዊነት', 'nav' => false, 'footer' => true, 'keywords' => 'privacy data protection safeguarding cookies photographs'],
        ['path' => '/search', 'title' => 'Search', 'title_am' => 'ፍለጋ', 'nav' => false, 'footer' => false, 'keywords' => 'search find'],
    ],

    // ── Short page summaries used by site search ──────────────────────
    'page_copy' => [
        '/'           => 'Welcome to Wilude Birhan Sunday School: Scripture, Church teaching, Ge\'ez and Amharic, Mezmur and community for children aged four to eighteen.',
        '/about'      => 'Our story, mission, vision, values, milestones and governance as a ministry of Gelan Gura Felege Giyon St. Gabriel Church.',
        '/programs'   => 'Class levels for ages 4 to 18, the curriculum subjects taught, and what a Sunday programme looks like from arrival to dismissal.',
        '/schedule'   => 'Sunday arrival at 8:30 AM, lessons from 9:00 AM to 12:00 PM, Mezmur practice at the close, and school office hours.',
        '/admissions' => 'How to register a child: send a request, talk with the office, visit a class, and enrolment in the school administration system.',
        '/news'       => 'Announcements for pupils, parents and teachers, including registration, timetable and Mezmur practice notices.',
        '/gallery'    => 'Photographs of lessons, Mezmur practice, feast days and school events, published with the agreement of parents.',
        '/contact'    => 'Address, phone, email, office hours and the enquiry form for the school office.',
        '/privacy'    => 'What the public website collects, what it publishes, who can see a request, and how to ask about your family\'s information.',
        '/search'     => 'Search the pages, programmes, weekly schedule, notices and questions published on this website.',
    ],

    // ── Footer ─────────────────────────────────────────────────────────
    'footer' => [
        'blurb' => 'A ministry of Gelan Gura Felege Giyon St. Gabriel Church, teaching the faith to the children and young people of the parish.',
        'columns' => [
            ['title' => 'Explore', 'paths' => ['/about', '/programs', '/schedule']],
            ['title' => 'Join us', 'paths' => ['/admissions', '/contact', '/privacy']],
        ],
        'legal_note' => 'This website is a public information site. Pupil records and results are available only inside the administration system.',
    ],
];
