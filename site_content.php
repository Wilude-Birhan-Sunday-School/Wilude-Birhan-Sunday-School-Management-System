<?php
/**
 * Public website editorial content (Wilude Birhan Sunday School).
 *
 * This file is the single place to edit the words shown on the public site.
 * It is plain PHP data: no database access, no side effects, safe to include.
 *
 * STATUS OF COPY
 * --------------
 * The school office approved this copy for publication, so the site-wide draft
 * notice has been removed and WBSS_SITE_CONTENT_DRAFT is false. Items still
 * carrying `'draft' => true` are an editorial note inside this file only: they
 * are not rendered anywhere on the website.
 *
 * Numbers, dates, staff names and contact details are never invented. The
 * office phone number is left empty until the school supplies it, and every
 * surface that would print a phone number hides itself while it is empty.
 */

define('WBSS_SITE_CONTENT_DRAFT', false);

return [
    // ── Identity-independent site settings ─────────────────────────────
    'site' => [
        'default_title_suffix' => 'Wilude Birhan Sunday School',
        'founded_note'        => 'Serving the parish community of Gelan Gura Felege Giyon St. Gabriel Church.',
    ],

    // ── Contact details ────────────────────────────────────────────────
    // Reused from the previous public page. Confirm with the school office
    // before publishing; see 'draft' flags.
    'contact' => [
        'phone_primary'   => ['label' => 'Office phone', 'value' => '', 'href' => '', 'draft' => true],
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
        'primary_cta'   => ['label' => 'Register now', 'path' => '/admissions'],
        'secondary_cta' => ['label' => 'Explore our programs', 'path' => '/programs'],
        // Qualitative highlights only — no invented enrolment or exam figures.
        'highlights' => [
            ['icon' => 'book',  'title' => 'Scripture at the centre', 'text' => 'Weekly lessons grounded in the Holy Scriptures and the teaching of the Church.'],
            ['icon' => 'users', 'title' => 'Every age welcome', 'text' => 'Children, young people and adults all learn here — any age can join a class.'],
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
            'image'    => '/themes/fkss/assets/wbss-teaching-room.jpg',
            'image_alt'=> 'A teacher holding a carved Ethiopian hand cross explains a lesson to children seated with open books, beneath Ethiopian Orthodox wall paintings.',
            'points'   => [
                'A class suited to each age — from young children to adults',
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
            'The programme is organised by the Education Department of the school together with the parish, and is taught by volunteer teachers who give their time and preparation to this service. Adults who want to learn the faith, or to return to study, join the same classes alongside the children.',
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
        'title'   => 'What pupils learn',
        'lead'    => 'One curriculum, taught at the depth that suits the learner. Children, young people and adults are all welcome — there are no fixed age groups and no upper age limit, so a grandparent can join as readily as a child.',
        'curriculum' => [
            ['title' => 'Scripture', 'text' => 'Old and New Testament lessons, the Gospels, and the Psalms, taught at an age-appropriate level.'],
            ['title' => 'Church teaching', 'text' => 'The creed, the sacraments, the feasts and fasts, and the order of worship.'],
            ['title' => 'Language', 'text' => 'Ge\'ez and Amharic reading and comprehension, including the alphabet for younger classes.'],
            ['title' => 'Mezmur and prayer', 'text' => 'Learning the hymns and prayers of the Church and taking part in the Sunday service.'],
            ['title' => 'Character and service', 'text' => 'Good conduct, responsibility, care for others, and practical service in the parish.'],
            ['title' => 'Assessment and reports', 'text' => 'Regular lesson assessments, term reports and parent conversations about progress.'],
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
            'Lesson titles and class names may be adjusted at the start of the academic year.',
            'Learners are placed in a class that suits their age and previous study. Adults join the senior class, or an adult group when the school has enough of them.',
            'Families are asked to inform the school of any special need so support can be arranged.',
        ],
    ],

    // ── Admissions page ────────────────────────────────────────────────
    'admissions' => [
        'eyebrow' => 'Admissions',
        'title'   => 'Registration and membership',
        'lead'    => 'Registration is open to everyone in the parish — children, young people and adults. You can register a child, or put your own name forward. There is no age limit: anyone of any age may join a class.',
        'steps' => [
            ['title' => 'Send a request', 'text' => 'Complete the form with the details of the person joining — a child, or yourself. It is a request for contact, not an account and not a school record.'],
            ['title' => 'Talk with the office', 'text' => 'The school office contacts you to confirm the class and answer questions.'],
            ['title' => 'Come and visit', 'text' => 'Come on a Sunday, with your child or on your own, so you can meet the teacher and see the class.'],
            ['title' => 'Enrolment in the school system', 'text' => 'Confirmed members are registered by the school office in the administration system, where attendance and progress are recorded.'],
        ],
        'requirements' => [
            'The full name and age of the person joining — any age is accepted',
            'A phone number we can reach (for a child, the parent or guardian)',
            'The class attended last year, if they are continuing',
            'Any special need the school should know about',
        ],
        'fees_note' => 'The school does not publish fee amounts on this website. Please ask the office about any contribution, materials or uniform expectations for the current year.',
        'faq' => [
            ['q' => 'Is submitting the form the same as enrolling my child?', 'a' => 'No. The form is an enquiry that asks the school office to contact you. Enrolment happens with the office and is recorded in the school administration system.'],
            ['q' => 'Is there an age limit?', 'a' => 'No. We accept every age with no upper limit — a small child, a teenager, a parent or a grandparent can all join. The form accepts any age and the office will place the person in a suitable class.'],
            ['q' => 'Can I register myself as an adult?', 'a' => 'Yes. Enter your own name as the person joining, give your age and your phone number, and the office will contact you about the class that fits. Many adults join to learn the faith or to refresh what they learned as children.'],
            ['q' => 'My child has never attended Sunday school. Can they join?', 'a' => 'Yes. New pupils commonly join a class for their age; the teacher will help them settle in.'],
            ['q' => 'What should my child bring?', 'a' => 'A notebook and pen if possible, and any lesson book the class uses. Ask the teacher on the first Sunday.'],
            ['q' => 'How is my child\'s information used?', 'a' => 'Enquiry details are used by the school office to contact you. Attendance, progress and reports are kept in the school system. Photos are published only under the school\'s privacy practice.'],
            ['q' => 'Do you publish pupils\' results or photographs?', 'a' => 'Individual results are not published on this website. The public gallery shows only photos approved by the school, with no member personal information.'],
        ],
        'form' => [
            'title'    => 'Registration request',
            'note'     => 'Fields marked with * are required. Any age is accepted — a child, a young person or an adult. We only use these details to respond to your request.',
            'levels' => ['A child (under 13)', 'A young person (13–17)', 'An adult (18 or over)', 'Myself — I am the person joining', 'Not sure — please advise'],
        ],
    ],

    // ── News and notices ───────────────────────────────────────────────
    // Undated editorial notices. Dated announcements can additionally be
    // stored in the optional cms_announcements table and are merged in
    // automatically when that table exists (see public_site.php).
    'news' => [
        'eyebrow' => 'News and notices',
        'title'   => 'Life of the school',
        'lead'    => 'Announcements for pupils, parents, teachers and adult members, with photographs from school life. Dated notices added by the school administration appear here automatically.',
        'items'   => [
            [
                'kind'  => 'notice',
                'title' => 'Registration for the new academic year',
                'title_am' => 'ለአዲሱ የትምህርት ዓመት ምዝገባ',
                'text'  => 'Families wishing to register a child — and adults who wish to join — can send a request through the admissions form at any time. The office will contact you with class and start details.',
                'link'  => ['label' => 'Registration request', 'path' => '/admissions'],
                'image' => '/themes/fkss/assets/wbss-teaching-room.jpg',
                'image_alt' => 'Children of the Sunday school sitting with open books during a lesson.',
                'draft' => false,
            ],
            [
                'kind'  => 'notice',
                'title' => 'Sunday lessons',
                'title_am' => 'የእሁድ ትምህርት',
                'text'  => 'Lessons begin at 9:00 AM and finish at 12:00 PM. Pupils should arrive by 8:30 AM so attendance can be taken before the opening prayer.',
                'link'  => ['label' => 'Ask the office', 'path' => '/contact'],
                'image' => '/themes/fkss/assets/wbss-hero-courtyard.jpg',
                'image_alt' => 'Children in white netela shawls walking towards the church entrance in the afternoon light.',
                'draft' => false,
            ],
            [
                'kind'  => 'notice',
                'title' => 'Mezmur practice',
                'title_am' => 'የመዝሙር ልምምድ',
                'text'  => 'Hymn practice takes place at the close of the Sunday programme. New pupils and adult members are welcome to join the practice group with their class.',
                'link'  => null,
                'image' => '/themes/fkss/assets/wbss-mezmur-practice.jpg',
                'image_alt' => 'Children singing from a shared hymn book beside a kebero drum during Mezmur practice.',
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
        'lead'    => 'Search the pages, programmes, notices and questions published on this site.',
        'empty'   => 'Enter a word to search, for example “Mezmur”, “Ge\'ez”, “adults” or “registration”.',
        'none'    => 'No page, programme or notice matched that search.',
    ],

    // ── Static page index used by search and the footer ────────────────
    'pages' => [
        ['path' => '/', 'title' => 'Home', 'title_am' => 'መነሻ', 'nav' => true, 'footer' => false, 'keywords' => 'home welcome parish sunday school'],
        ['path' => '/about', 'title' => 'About us', 'title_am' => 'ስለ እኛ', 'nav' => true, 'footer' => true, 'keywords' => 'about history mission vision values parish church leadership governance'],
        ['path' => '/programs', 'title' => 'Programs', 'title_am' => 'የትምህርት መርሃ ግብሮች', 'nav' => true, 'footer' => true, 'keywords' => 'programs classes curriculum scripture geez amharic mezmur ages little lambs young disciples youth'],
        ['path' => '/admissions', 'title' => 'Admissions', 'title_am' => 'ምዝገባ', 'nav' => true, 'footer' => true, 'keywords' => 'admissions register registration enrol enrolment apply fees requirements faq'],
        ['path' => '/news', 'title' => 'News and notices', 'title_am' => 'ዜናና ማሳሰቢያዎች', 'nav' => true, 'footer' => true, 'keywords' => 'news notices announcements events mezmur practice timetable'],
        ['path' => '/gallery', 'title' => 'Gallery', 'title_am' => 'የፎቶ መድብር', 'nav' => true, 'footer' => true, 'keywords' => 'gallery photos pictures albums feast days events'],
        ['path' => '/contact', 'title' => 'Contact', 'title_am' => 'ያግኙን', 'nav' => true, 'footer' => true, 'keywords' => 'contact phone email address location map office hours enquiry'],
        ['path' => '/privacy', 'title' => 'Privacy and safeguarding', 'title_am' => 'ግላዊነት', 'nav' => false, 'footer' => true, 'keywords' => 'privacy data protection safeguarding cookies photographs'],
        ['path' => '/search', 'title' => 'Search', 'title_am' => 'ፍለጋ', 'nav' => false, 'footer' => false, 'keywords' => 'search find'],
    ],

    // ── Short page summaries used by site search ──────────────────────
    'page_copy' => [
        '/'           => 'Welcome to Wilude Birhan Sunday School: Scripture, Church teaching, Ge\'ez and Amharic, Mezmur and community for every age, from young children to adults.',
        '/about'      => 'Our story, mission, vision, values, milestones and governance as a ministry of Gelan Gura Felege Giyon St. Gabriel Church.',
        '/programs'   => 'The curriculum subjects taught at every level, and what a Sunday programme looks like from arrival to dismissal.',
        '/admissions' => 'How to register — a child or yourself, at any age: send a request, talk with the office, visit a class, and enrolment in the school administration system.',
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
            ['title' => 'Explore', 'paths' => ['/about', '/programs', '/gallery']],
            ['title' => 'Join us', 'paths' => ['/admissions', '/contact', '/privacy']],
        ],
        'legal_note' => 'This website is a public information site. Pupil records and results are available only inside the administration system.',
    ],
];
