<?php
/**
 * Public website editorial content (Wilude Birhan Sunday School).
 *
 * This file is the single place to edit the words shown on the public site.
 * It is plain PHP data: no database access, no side effects, safe to include.
 *
 * BILINGUAL COPY — AMHARIC IS THE DEFAULT
 * ---------------------------------------
 * Every visible string is written as a pair: L('English', 'አማርኛ').
 * Templates print pairs through t(), which returns the active language
 * (see site_lang.php). Amharic is served unless the visitor switches to
 * English with the header control.
 *
 * Keep copy short. A page should be understood at a glance: one lead line per
 * page, one sentence per card. Long explanatory paragraphs make a school
 * website look unprofessional, and they double the translation burden.
 *
 * Numbers, dates, staff names and contact details are never invented. The
 * office phone number is left empty until the school supplies it, and every
 * surface that would print a phone number hides itself while it is empty.
 */

require_once __DIR__ . '/site_lang.php';

define('WBSS_SITE_CONTENT_DRAFT', false);

return [
    // ── Interface strings (chrome, buttons, form labels) ───────────────
    'ui' => [
        'skip_link'        => L('Skip to main content', 'ወደ ዋናው ይዘት ዝለል'),
        'language'         => L('Language', 'ቋንቋ'),
        'lang_am'          => L('አማርኛ', 'አማርኛ'),
        'lang_en'          => L('English', 'English'),
        'home'             => L('Home', 'መነሻ'),
        'contact'          => L('Contact', 'ያግኙን'),
        'register'         => L('Register', 'ይመዝገቡ'),
        'register_request' => L('Registration request', 'የምዝገባ ጥያቄ'),
        'search_site'      => L('Search this website', 'ይህን ድረ-ገጽ ይፈልጉ'),
        'search'           => L('Search', 'ፍለጋ'),
        'primary_nav'      => L('Primary', 'ዋና ምናሌ'),
        'mobile_nav'       => L('Site menu', 'የገጹ ምናሌ'),
        'breadcrumb'       => L('Breadcrumb', 'የገጹ መንገድ'),
        'open_menu'        => L('Open menu', 'ምናሌ ክፈት'),
        'close_menu'       => L('Close menu', 'ምናሌ ዝጋ'),
        'back_to_top'      => L('Back to top', 'ወደ ላይ ተመለስ'),
        'home_aria'        => L('home page', 'የመነሻ ገጽ'),
        'read_more'        => L('Read more', 'ተጨማሪ ያንብቡ'),
        'view_all_news'    => L('All news and notices', 'ሁሉንም ዜናና ማሳሰቢያዎች'),
        'full_programme'   => L('See the programme', 'መርሃ ግብሩን ይመልከቱ'),
        'about_school'     => L('About the school', 'ስለ ትምህርት ቤቱ'),
        'ask_class'        => L('Ask about a class', 'ስለ ክፍል ይጠይቁ'),
        'visit_contact'    => L('Visit and contact', 'ይጎብኙና ያግኙን'),
        'explore'          => L('Explore', 'ያስሱ'),
        'join_us'          => L('Join us', 'ይቀላቀሉን'),
        'privacy_link'     => L('Privacy and safeguarding', 'ግላዊነትና ደህንነት'),
        'office_phone'     => L('Office phone', 'የጽ/ቤት ስልክ'),
        'office_hours'     => L('Office hours', 'የሥራ ሰዓት'),
        'where_we_are'     => L('Where we are', 'የምንገኝበት'),
        'open_maps'        => L('Open in Google Maps', 'በGoogle Maps ይክፈቱ'),
        'send_email'       => L('Send an email', 'ኢሜይል ይላኩ'),
        'notice'           => L('Notice', 'ማሳሰቢያ'),
        'standing_notice'  => L('Standing notice', 'ቋሚ ማሳሰቢያ'),
        'announcement'     => L('Announcement', 'ማስታወቂያ'),
        'photo'            => L('Photograph', 'ፎቶ'),
        'q_result'         => L('result', 'ውጤት'),
        'q_results'        => L('results', 'ውጤቶች'),
        'for_query'        => L('for', 'ለ'),
        'not_found_kicker' => L('Error 404', 'ስህተት 404'),
        'back_home'        => L('Back to the home page', 'ወደ መነሻ ገጽ ይመለሱ'),
        'page_not_here'    => L('That page is not here', 'ይህ ገጽ አልተገኘም'),
        'join_heading'     => L('Who is joining?', 'የሚመዘገበው ማን ነው?'),
        'select_one'       => L('Select one', 'ይምረጡ'),
        'prefer_not_say'   => L('Prefer not to say', 'አልናገርም'),
        'male'             => L('Male', 'ወንድ'),
        'female'           => L('Female', 'ሴት'),
        'sending'          => L('Sending…', 'በመላክ ላይ…'),
        'sent'             => L('Sent', 'ተልኳል'),
        'send_request'     => L('Send registration request', 'የምዝገባ ጥያቄ ይላኩ'),
        'send_enquiry'     => L('Send message', 'መልእክት ይላኩ'),
        'enquiry_title'    => L('Send the school office a message', 'ለትምህርት ቤቱ ጽ/ቤት መልእክት ይላኩ'),
        'enquiry_note'     => L('We reply during office hours.', 'በሥራ ሰዓት ውስጥ እንመልሳለን።'),
        'form_privacy'     => L('We use these details only to answer your request.', 'ይህን መረጃ የምንጠቀመው ጥያቄዎን ለመመለስ ብቻ ነው።'),
        'required_note'    => L('Fields marked with * are required.', '* ምልክት ያለባቸው መስኮች የግዴታ ናቸው።'),
        'your_name'        => L('Your name', 'ስምዎ'),
        'your_name_full'   => L('Your name (parent, guardian or member)', 'ስምዎ (ወላጅ፣ አሳዳጊ ወይም አባል)'),
        'phone'            => L('Phone number', 'ስልክ ቁጥር'),
        'joining_name'     => L('Name of the person joining', 'የሚመዘገበው ሰው ስም'),
        'joining_hint'     => L('A child, a young person, or yourself.', 'ልጅ፣ ወጣት ወይም ራስዎ።'),
        'age'              => L('Age', 'ዕድሜ'),
        'age_hint'         => L('Any age — from a small child to a grandparent.', 'ማንኛውም ዕድሜ — ከትንሽ ልጅ እስከ አያት።'),
        'gender'           => L('Gender (optional)', 'ጾታ (አማራጭ)'),
        'email'            => L('Email', 'ኢሜይል'),
        'area'             => L('Area / address', 'አካባቢ / አድራሻ'),
        'message'          => L('Message', 'መልእክት'),
        'questions_notes'  => L('Questions or comments', 'ጥያቄ ወይም አስተያየት'),
        'message_placeholder' => L('Anything the office should know?', 'ጽ/ቤቱ ማወቅ ያለበት ነገር አለ?'),
        'honeypot'         => L('Leave this field empty', 'ይህን መስክ ባዶ ይተዉት'),
        'noscript'         => L('JavaScript is off, so the form will reload the page after sending. You can also call the office.', 'ጃቫስክሪፕት ጠፍቷል፤ ቅጹ ከተላከ በኋላ ገጹ ይታደሳል። በስልክም ማግኘት ይችላሉ።'),
        'gallery_empty'    => L('Photographs will appear here once the school publishes them.', 'ፎቶዎች ሲታተሙ እዚህ ይታያሉ።'),
        'gallery_loading'  => L('Loading photographs…', 'ፎቶዎች በመጫን ላይ…'),
        'gallery_failed'   => L('The gallery could not load. Please try again.', 'ፎቶዎቹ አልተጫኑም። እባክዎ እንደገና ይሞክሩ።'),
        'gallery_offline'  => L('You appear to be offline.', 'ከመስመር ውጭ ያሉ ይመስላል።'),
        'gallery_more_failed' => L('More photographs could not load.', 'ተጨማሪ ፎቶዎች አልተጫኑም።'),
        'gallery_img_failed'  => L('This photograph could not be loaded.', 'ይህ ፎቶ አልተጫነም።'),
        'gallery_more'     => L('Show more photos', 'ተጨማሪ ፎቶዎች ያሳዩ'),
        'search_placeholder' => L('Search the website', 'ድረ-ገጹን ይፈልጉ'),
        'search_hint'      => L('Try “መዝሙር”, “ግዕዝ”, “ምዝገባ” or “adults”.', '“መዝሙር”፣ “ግዕዝ”፣ “ምዝገባ” ወይም “adults” ይሞክሩ።'),
        'no_results_hint'  => L('Try a shorter word, or browse the programme and the notices.', 'አጭር ቃል ይሞክሩ ወይም መርሃ ግብሩንና ማሳሰቢያዎቹን ይመልከቱ።'),
        'kind_page'        => L('Page', 'ገጽ'),
        'kind_programme'   => L('Programme', 'መርሃ ግብር'),
        'kind_curriculum'  => L('Curriculum', 'ስርዓተ ትምህርት'),
        'kind_question'    => L('Question', 'ጥያቄ'),
        'cta_title'        => L('Begin with a conversation', 'በውይይት ይጀምሩ'),
        'cta_text'         => L('Send a request for a child or for yourself. The office will confirm the class.', 'ለልጅዎ ወይም ለራስዎ ጥያቄ ይላኩ። ጽ/ቤቱ ክፍሉን ያረጋግጣል።'),
        'cta_contact'      => L('Contact the office', 'ጽ/ቤቱን ያግኙ'),
        // Client-side form feedback (also mirrored in data attributes for JS).
        'msg_required'     => L('Please complete the required fields marked with *.', 'እባክዎ * ያለባቸውን የግዴታ መስኮች ይሙሉ።'),
        'msg_email'        => L('Please check the email address.', 'እባክዎ የኢሜይል አድራሻውን ያረጋግጡ።'),
        'msg_sending'      => L('Sending your request…', 'ጥያቄዎ በመላክ ላይ…'),
        'msg_sent'         => L('Thank you. Your request has been received.', 'እናመሰግናለን። ጥያቄዎ ደርሷል።'),
        'msg_failed'       => L('We could not send the request. Please try again or call the office.', 'ጥያቄው አልተላከም። እንደገና ይሞክሩ ወይም ጽ/ቤቱን ይደውሉ።'),
        'msg_joining'      => L('Joining', 'የሚመዘገበው'),
    ],

    // ── Site-wide settings ─────────────────────────────────────────────
    'site' => [
        'default_title_suffix' => 'Wilude Birhan Sunday School',
        'founded_note'        => L(
            'Serving the parish community of Gelan Gura Felege Giyon St. Gabriel Church.',
            'የገላን ጉራ ፈለገ ግዮን ቅዱስ ገብርኤል ቤተክርስቲያንን ደብር ማህበረሰብ ያገለግላል።'
        ),
    ],

    // ── Contact details ────────────────────────────────────────────────
    'contact' => [
        // `draft` is an editorial flag inside this file only: the phone number
        // is still not supplied by the office, and the email is inherited from
        // the previous page. Neither is invented, and no placeholder is shown.
        'phone_primary'   => ['label' => L('Office phone', 'የጽ/ቤት ስልክ'), 'value' => '', 'href' => '', 'draft' => true],
        'email_primary'   => ['label' => L('Email', 'ኢሜይል'), 'value' => 'info@wulidebirhan.org', 'href' => 'mailto:info@wulidebirhan.org', 'draft' => true],
        'address_lines'   => [
            L('Gelan Gura, Felege Giyon St. Gabriel Church', 'ገላን ጉራ፣ ፈለገ ግዮን ቅዱስ ገብርኤል ቤተክርስቲያን'),
            L('Addis Ababa, Ethiopia', 'አዲስ አበባ፣ ኢትዮጵያ'),
        ],
        'map_url'         => 'https://maps.app.goo.gl/iHSzmE5BphXrWo8Z8',
        'map_label'       => L('Parish location', 'የደብሩ አድራሻ'),
        'office_hours'    => [
            ['day' => L('Sunday', 'እሁድ'), 'time' => '8:30 – 12:30'],
            ['day' => L('Wednesday', 'ረቡዕ'), 'time' => '5:30 – 8:00'],
        ],
        'response_note'   => L(
            'The office answers within one working week.',
            'ጽ/ቤቱ በአንድ የሥራ ሳምንት ውስጥ ይመልሳል።'
        ),
        'newsletter_note' => L(
            'Ask the office to add you to the announcement list.',
            'ማስታወቂያውን እንዲያገኙ ጽ/ቤቱን ይጠይቁ።'
        ),
    ],

    // ── Home page ──────────────────────────────────────────────────────
    'home' => [
        'eyebrow'      => L('Sunday School · Ethiopian Orthodox Tewahdo', 'ሰንበት ትምህርት ቤት · የኢትዮጵያ ኦርቶዶክስ ተዋሕዶ'),
        'lead'         => L(
            'Scripture, the prayers and hymns of the Church, Ge’ez and Amharic — taught every Sunday.',
            'ቅዱስ መጽሐፍ፣ የቤተክርስቲያን ጸሎትና ዝማሬ፣ ግዕዝና አማርኛ — በየሰንበቱ።'
        ),
        'primary_cta'   => ['label' => L('Register now', 'አሁን ይመዝገቡ'), 'path' => '/admissions'],
        'secondary_cta' => ['label' => L('Our programmes', 'መርሃ ግብሮቻችን'), 'path' => '/programs'],
        'highlights' => [
            ['icon' => 'book',  'title' => L('Scripture first', 'ቅዱስ መጽሐፍ ቀዳሚ'),
             'text' => L('Lessons from the Old and New Testaments.', 'ከብሉይና ከአዲስ ኪዳን የሚሰጥ ትምህርት።')],
            ['icon' => 'users', 'title' => L('Every age', 'ለሁሉም ዕድሜ'),
             'text' => L('Children, young people and adults together.', 'ልጆች፣ ወጣቶችና አዋቂዎች አንድ ላይ።')],
            ['icon' => 'music', 'title' => L('Prayer and Mezmur', 'ጸሎትና መዝሙር'),
             'text' => L('Hymns practised every Sunday.', 'በየሰንበቱ የሚለማመድ ዝማሬ።')],
            ['icon' => 'globe', 'title' => L('Ge’ez and Amharic', 'ግዕዝና አማርኛ'),
             'text' => L('Reading the language of the liturgy.', 'የቅዳሴውን ቋንቋ ማንበብ።')],
        ],
        'intro' => [
            'eyebrow'  => L('Who we are', 'እኛ ማን ነን'),
            'title'    => L('A parish school, every Sunday', 'በየሰንበቱ የሚሰበሰብ የደብር ትምህርት ቤት'),
            'body'     => [
                L('Wilude Birhan is the Sunday school of Gelan Gura Felege Giyon St. Gabriel Church, teaching the faith to the children, young people and adults of the parish.',
                  'ውሉደ ብርሃን የገላን ጉራ ፈለገ ግዮን ቅዱስ ገብርኤል ቤተክርስቲያን ሰንበት ትምህርት ቤት ነው፤ ለደብሩ ልጆች፣ ወጣቶችና አዋቂዎች እምነቱን ያስተምራል።'),
            ],
            'image'    => '/themes/fkss/assets/wbss-teaching-room.jpg',
            'image_alt'=> L('A teacher holding a carved Ethiopian hand cross explains a lesson to children seated with open books, beneath Ethiopian Orthodox wall paintings.',
                            'የኢትዮጵያ ኦርቶዶክስ የግድግዳ ሥዕል ሥር፣ የእጅ መስቀል ይዞ ለልጆች ትምህርት የሚያስረዳ አስተማሪ።'),
            'points'   => [
                L('A class for every age', 'ለሁሉም ዕድሜ ክፍል'),
                L('Teachers formed in the parish', 'ከደብሩ የተውጣጡ አስተማሪዎች'),
                L('Attendance and progress recorded', 'የመገኘትና የዕድገት መዝገብ'),
            ],
            'caption'  => L('Faith handed on: Scripture, language and worship.', 'እምነት ተላልፎ ይሰጣል፦ መጽሐፍ፣ ቋንቋና አምልኮ።'),
        ],
        'quote' => [
            'text'    => L('Your word is a lamp to my feet and a light to my path.', 'ቃልህ ለእግሬ መብራት ለመንገዴም ብርሃን ነው።'),
            'source'  => L('Psalm 119:105', 'መዝሙር 119፥105'),
        ],
        'commitments' => [
            'eyebrow' => L('What shapes our teaching', 'ትምህርታችንን የሚመራው'),
            'title'   => L('Four commitments', 'አራት ቁርጠኝነቶች'),
        ],
        'programmes_head' => [
            'eyebrow' => L('Curriculum', 'ስርዓተ ትምህርት'),
            'title'   => L('What pupils learn', 'ተማሪዎች የሚማሩት'),
        ],
        'visit_head' => [
            'eyebrow' => L('Visit us', 'ይጎብኙን'),
            'title'   => L('Where to find the school', 'ትምህርት ቤቱ የሚገኝበት'),
        ],
    ],

    // ── Four commitments shown on Home and About ───────────────────────
    'pillars' => [
        ['icon' => 'book',  'title' => L('Holy Scripture', 'ቅዱስ መጽሐፍ'),
         'text' => L('The Old and New Testaments, the Gospels and the Psalms.', 'ብሉይና አዲስ ኪዳን፣ ወንጌላትና መዝሙረ ዳዊት።')],
        ['icon' => 'cross', 'title' => L('Faith and worship', 'እምነትና አምልኮ'),
         'text' => L('Prayer, fasts, feasts and the meaning of the liturgy.', 'ጸሎት፣ ጾም፣ በዓላትና የቅዳሴው ትርጉም።')],
        ['icon' => 'globe', 'title' => L('Language and heritage', 'ቋንቋና ቅርስ'),
         'text' => L('Ge’ez, Amharic, Mezmur and the heritage of the Church.', 'ግዕዝ፣ አማርኛ፣ መዝሙርና የቤተክርስቲያን ቅርስ።')],
        ['icon' => 'users', 'title' => L('Community and service', 'ማህበረሰብና አገልግሎት'),
         'text' => L('Friendship, responsibility and service in the parish.', 'በደብሩ ውስጥ ወዳጅነት፣ ኃላፊነትና አገልግሎት።')],
    ],

    // ── About page ─────────────────────────────────────────────────────
    'about' => [
        'eyebrow' => L('About the school', 'ስለ ትምህርት ቤቱ'),
        'title'   => L('A ministry of the parish', 'የደብሩ አገልግሎት'),
        'lead'    => L('Wilude Birhan teaches the faith to the children, young people and adults of Gelan Gura Felege Giyon St. Gabriel Church.',
                       'ውሉደ ብርሃን የገላን ጉራ ፈለገ ግዮን ቅዱስ ገብርኤል ቤተክርስቲያንን ልጆች፣ ወጣቶችና አዋቂዎች እምነቱን ያስተምራል።'),
        'story_title' => L('Our story', 'የእኛ ታሪክ'),
        'story_heading' => L('How the school began', 'ትምህርት ቤቱ እንዴት ተጀመረ'),
        'story'   => [
            L('The school began as a parish ministry, teaching children the prayers and the Scriptures in the church compound. Today it gathers every Sunday for lesson, prayer and Mezmur.',
              'ትምህርት ቤቱ በደብሩ ግቢ ውስጥ ልጆችን ጸሎትና ቅዱስ መጽሐፍ እያስተማረ ተጀመረ። ዛሬም በየሰንበቱ ለትምህርት፣ ለጸሎትና ለመዝሙር ይሰበሰባል።'),
            L('The programme is set by the school’s Education Department with the parish, and taught by volunteer teachers. Adults who wish to learn the faith join the same classes.',
              'መርሃ ግብሩ በትምህርት ቤቱ የትምህርት ክፍልና በደብሩ ይዘጋጃል፤ በበጎ ፈቃደኛ አስተማሪዎች ይሰጣል። እምነቱን ለመማር የሚፈልጉ አዋቂዎችም በዚሁ ክፍሎች ይሳተፋሉ።'),
        ],
        'caption' => L('The parish compound where the school meets each Sunday.', 'ትምህርት ቤቱ በየሰንበቱ የሚሰበሰብበት የደብሩ ግቢ።'),
        'mission_title' => L('Mission', 'ተልእኮ'),
        'mission' => L('To teach the faith of the Ethiopian Orthodox Tewahdo Church, and to raise a generation that prays, reads and serves.',
                      'የኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተክርስቲያንን እምነት ማስተማር፤ የሚጸልይ፣ የሚያነብና የሚያገለግል ትውልድ ማፍራት።'),
        'vision_title' => L('Vision', 'ራዕይ'),
        'vision'  => L('A generation that knows the Scriptures and loves the Church.', 'ቅዱስ መጽሐፍን የሚያውቅና ቤተክርስቲያኑን የሚወድ ትውልድ።'),
        'values_title' => L('Our values', 'እሴቶቻችን'),
        'values_eyebrow' => L('What we hold to', 'የምንይዘው'),
        'values'  => [
            ['title' => L('Faithfulness', 'ታማኝነት'), 'text' => L('We teach the faith we received.', 'የተቀበልነውን እምነት እናስተምራለን።')],
            ['title' => L('Care', 'እንክብካቤ'), 'text' => L('Every pupil is known by name.', 'እያንዳንዱ ተማሪ በስሙ ይታወቃል።')],
            ['title' => L('Diligence', 'ትጋት'), 'text' => L('Lessons prepared, attendance recorded.', 'ትምህርት ይዘጋጃል፣ መገኘት ይመዘገባል።')],
            ['title' => L('Honesty', 'ታማኝነት በመዝገብ'), 'text' => L('Records show what happened.', 'መዝገቦች የተፈጸመውን ያሳያሉ።')],
            ['title' => L('Service', 'አገልግሎት'), 'text' => L('Teachers serve freely; pupils learn to serve.', 'አስተማሪዎች በበጎ ፈቃድ ያገለግላሉ፤ ተማሪዎችም ማገልገል ይማራሉ።')],
        ],
        'leadership_note' => L('Teacher names are published once the parish approves them.', 'የአስተማሪዎች ስም ደብሩ ሲያጸድቅ ይታተማል።'),
        'milestones_title' => L('Milestones', 'ድል ደረጃዎች'),
        'milestones_eyebrow' => L('How we began', 'እንዴት ተጀመረ'),
        'timeline' => [
            ['title' => L('The beginning', 'መጀመሪያ'), 'text' => L('A parish ministry teaching prayers and Scripture.', 'ጸሎትና መጽሐፍ የሚያስተምር የደብር አገልግሎት።')],
            ['title' => L('Classes by age', 'በዕድሜ የተከፋፈሉ ክፍሎች'), 'text' => L('The Education Department took on the curriculum.', 'የትምህርት ክፍሉ ስርዓተ ትምህርቱን ተረከበ።')],
            ['title' => L('Records kept', 'መዝገብ መያዝ'), 'text' => L('Attendance and progress recorded each term.', 'በየሴሚስተሩ መገኘትና ዕድገት ይመዘገባል።')],
            ['title' => L('Today', 'ዛሬ'), 'text' => L('Weekly lessons with a digital administration system.', 'በዲጂታል አስተዳደር ሥርዓት የተደገፈ ሳምንታዊ ትምህርት።')],
        ],
        'governance_title' => L('Who is responsible', 'ኃላፊነት ያለው ማን ነው'),
        'governance_eyebrow' => L('Governance', 'አስተዳደር'),
        'governance' => [
            ['title' => L('Parish oversight', 'የደብር ክትትል'), 'text' => L('The school works under the parish and its administration.', 'ትምህርት ቤቱ በደብሩና በአስተዳደሩ ሥር ይሠራል።')],
            ['title' => L('Education Department', 'የትምህርት ክፍል'), 'text' => L('Coordinates curriculum, assessment and reports.', 'ስርዓተ ትምህርት፣ ግምገማና ሪፖርት ያስተባብራል።')],
            ['title' => L('Records and privacy', 'መዝገብና ግላዊነት'), 'text' => L('Pupil records stay inside the school system.', 'የተማሪዎች መዝገብ በትምህርት ቤቱ ሥርዓት ውስጥ ብቻ ነው።')],
        ],
        'teachers_title' => L('Our teachers', 'አስተማሪዎቻችን'),
        'teachers_eyebrow' => L('Teaching team', 'የማስተማሪያ ቡድን'),
        'teacher_roles' => [
            ['icon' => 'staff', 'title' => L('Sunday school head', 'የሰንበት ትምህርት ቤት ኃላፊ'), 'text' => L('Leads the school with the parish.', 'ትምህርት ቤቱን ከደብሩ ጋር ይመራል።')],
            ['icon' => 'book', 'title' => L('Class teachers', 'የክፍል አስተማሪዎች'), 'text' => L('Teach the weekly lesson.', 'ሳምንታዊውን ትምህርት ያስተምራሉ።')],
            ['icon' => 'music', 'title' => L('Mezmur leader', 'የመዝሙር መሪ'), 'text' => L('Leads hymn and prayer practice.', 'የዝማሬና የጸሎት ልምምድ ይመራል።')],
            ['icon' => 'users', 'title' => L('Education Department', 'የትምህርት ክፍል'), 'text' => L('Coordinates curriculum and reports.', 'ስርዓተ ትምህርትና ሪፖርት ያስተባብራል።')],
        ],
    ],

    // ── Programs page ──────────────────────────────────────────────────
    'programs' => [
        'eyebrow' => L('Programmes', 'መርሃ ግብሮች'),
        'title'   => L('What pupils learn', 'ተማሪዎች የሚማሩት'),
        'lead'    => L('The same curriculum at every level — for children, young people and adults.', 'በሁሉም ደረጃ አንድ ስርዓተ ትምህርት — ለልጆች፣ ለወጣቶችና ለአዋቂዎች።'),
        'classes_eyebrow' => L('Classes', 'ክፍሎች'),
        'classes_title' => L('Classes published by the school', 'በትምህርት ቤቱ የታተሙ ክፍሎች'),
        'curriculum_eyebrow' => L('Curriculum', 'ስርዓተ ትምህርት'),
        'curriculum_title' => L('The subjects taught', 'የሚሰጡ ትምህርቶች'),
        'curriculum' => [
            ['title' => L('Scripture', 'ቅዱስ መጽሐፍ'), 'text' => L('Old and New Testament, the Gospels and the Psalms.', 'ብሉይና አዲስ ኪዳን፣ ወንጌላትና መዝሙረ ዳዊት።')],
            ['title' => L('Church teaching', 'የቤተክርስቲያን ትምህርት'), 'text' => L('The creed, sacraments, fasts and feasts.', 'ጸሎተ ሃይማኖት፣ ምሥጢራት፣ ጾማትና በዓላት።')],
            ['title' => L('Language', 'ቋንቋ'), 'text' => L('Ge’ez and Amharic reading, including the alphabet.', 'የግዕዝና የአማርኛ ንባብ፣ ፊደልን ጨምሮ።')],
            ['title' => L('Mezmur and prayer', 'መዝሙርና ጸሎት'), 'text' => L('The hymns and prayers of the Church.', 'የቤተክርስቲያን ዝማሬና ጸሎት።')],
            ['title' => L('Character and service', 'ምግባርና አገልግሎት'), 'text' => L('Conduct, responsibility and care for others.', 'ምግባር፣ ኃላፊነትና ለሌሎች እንክብካቤ።')],
            ['title' => L('Assessment', 'ግምገማ'), 'text' => L('Lesson checks, term reports and parents’ evenings.', 'የትምህርት ፍተሻ፣ የሴሚስተር ሪፖርትና ከወላጆች ጋር ውይይት።')],
        ],
        'week_eyebrow' => L('A Sunday', 'አንድ እሁድ'),
        'week_title' => L('A Sunday at school', 'በትምህርት ቤቱ አንድ እሁድ'),
        'week_steps' => [
            ['title' => L('Arrival', 'መድረስ'), 'text' => L('Greeting and attendance before 8:30.', 'ከ8:30 በፊት መድረስ፤ መገኘት ይመዘገባል።')],
            ['title' => L('Opening prayer', 'የመክፈቻ ጸሎት'), 'text' => L('The school prays together and sings a hymn.', 'ትምህርት ቤቱ አንድ ላይ ይጸልያል፤ ዝማሬም ይዘምራል።')],
            ['title' => L('Lesson', 'ትምህርት'), 'text' => L('Each class works through the lesson of the week.', 'እያንዳንዱ ክፍል የሳምንቱን ትምህርት ይሠራል።')],
            ['title' => L('Mezmur and closing', 'መዝሙርና ማጠቃለያ'), 'text' => L('Hymns, notices and the closing prayer.', 'ዝማሬ፣ ማሳሰቢያና የመዝጊያ ጸሎት።')],
        ],
        'notices_title' => L('Good to know', 'ማወቅ ያለብዎት'),
        'notices' => [
            L('Learners are placed in a class that suits their age and previous study.', 'ተማሪዎች ከዕድሜያቸውና ካለፈው ትምህርታቸው ጋር በሚስማማ ክፍል ይመደባሉ።'),
            L('Tell the school about any special need so support can be arranged.', 'ልዩ ፍላጎት ካለ ለትምህርት ቤቱ ይንገሩ፤ ድጋፍ ይዘጋጃል።'),
        ],
        'ask_text' => L('Not sure which class fits? Send a request and the office will advise.', 'የትኛው ክፍል እንደሚስማማ እርግጠኛ ካልሆኑ ጥያቄ ይላኩ፤ ጽ/ቤቱ ይመክራል።'),
        'cta_title' => L('Ready to join a class?', 'ክፍል ለመቀላቀል ተዘጋጅተዋል?'),
        'cta_text' => L('Registration is open to everyone in the parish.', 'ምዝገባ ለደብሩ ሁሉም ክፍት ነው።'),
    ],

    // ── Admissions page ────────────────────────────────────────────────
    'admissions' => [
        'eyebrow' => L('Admissions', 'ምዝገባ'),
        'title'   => L('Registration and membership', 'ምዝገባና አባልነት'),
        'lead'    => L('Register a child or yourself. Any age is accepted.', 'ልጅዎን ወይም ራስዎን ይመዝግቡ። ማንኛውም ዕድሜ ይቀበላል።'),
        'steps_eyebrow' => L('How it works', 'እንዴት ይሠራል'),
        'steps_title' => L('Four steps', 'አራት ደረጃዎች'),
        'steps' => [
            ['title' => L('Send a request', 'ጥያቄ ይላኩ'), 'text' => L('Fill in the details of the person joining.', 'የሚመዘገበውን ሰው መረጃ ይሙሉ።')],
            ['title' => L('Talk with the office', 'ከጽ/ቤቱ ጋር ይወያዩ'), 'text' => L('The office confirms the class and answers questions.', 'ጽ/ቤቱ ክፍሉን ያረጋግጣል፤ ጥያቄዎችንም ይመልሳል።')],
            ['title' => L('Come and visit', 'መጥተው ይጎብኙ'), 'text' => L('Come on a Sunday and meet the teacher.', 'በእሁድ መጥተው አስተማሪውን ያግኙ።')],
            ['title' => L('Enrolment', 'ምዝገባ ማጠናቀቅ'), 'text' => L('The office records the member in the school system.', 'ጽ/ቤቱ አባሉን በትምህርት ቤቱ ሥርዓት ይመዘግባል።')],
        ],
        'form_note' => L('The form sends a request for the office to contact you. It does not create an account or enrol anyone automatically.',
                         'ቅጹ ጽ/ቤቱ እንዲያገኝዎ ጥያቄ ይልካል። መለያ አይፈጥርም፤ በራስሱም አያስመዘግብም።'),
        'requirements_eyebrow' => L('Before you apply', 'ከማመልከትዎ በፊት'),
        'requirements_title' => L('What to have ready', 'ያዘጋጁት'),
        'requirements' => [
            L('The name and age of the person joining.', 'የሚመዘገበው ሰው ስምና ዕድሜ።'),
            L('A phone number the office can reach.', 'ጽ/ቤቱ የሚያገኝበት ስልክ ቁጥር።'),
            L('The class attended last year, if continuing.', 'የሚቀጥል ከሆነ ያለፈው ዓመት ክፍል።'),
            L('Anything the teacher should know.', 'አስተማሪው ማወቅ ያለበት ነገር።'),
        ],
        'fees_note' => L('Fees are not published here — please ask the office.', 'ክፍያዎች እዚህ አልታተሙም — ጽ/ቤቱን ይጠይቁ።'),
        'faq_eyebrow' => L('Questions', 'ጥያቄዎች'),
        'faq_title' => L('Frequently asked', 'ተደጋጋሚ ጥያቄዎች'),
        'faq' => [
            ['q' => L('Is the form the same as enrolling?', 'ቅጹ ማስመዝገብ ነው?'),
             'a' => L('No. It asks the office to contact you; enrolment is recorded by the office.', 'አይደለም። ጽ/ቤቱ እንዲያገኝዎ ጥያቄ ይልካል፤ ምዝገባው በጽ/ቤቱ ይመዘገባል።')],
            ['q' => L('Is there an age limit?', 'የዕድሜ ገደብ አለ?'),
             'a' => L('No. There is no upper age limit — a child, a young person, a parent or a grandparent can join.', 'የለም። የላይኛው የዕድሜ ገደብ የለም — ልጅ፣ ወጣት፣ ወላጅ ወይም አያት መቀላቀል ይችላል።')],
            ['q' => L('Can I register myself?', 'ራሴን ማስመዝገብ እችላለሁ?'),
             'a' => L('Yes. Enter your own name as the person joining and give your age and phone number.', 'አዎ። የሚመዘገበው ሰው ቦታ ራስዎን ያስገቡ፤ ዕድሜና ስልክ ቁጥርዎን ይስጡ።')],
            ['q' => L('My child has never attended. Can they join?', 'ልጄ ቀደም ሲል አልተማረም። መቀላቀል ይችላል?'),
             'a' => L('Yes. New pupils join a class for their age and the teacher helps them settle in.', 'አዎ። አዲስ ተማሪዎች ከዕድሜያቸው ጋር በሚስማማ ክፍል ይገባሉ፤ አስተማሪውም እንዲላመዱ ይረዳል።')],
            ['q' => L('What should a pupil bring?', 'ተማሪው ምን ያምጣ?'),
             'a' => L('A notebook and pen, and any lesson book the class uses.', 'ደብተርና ብዕር፤ ክፍሉ የሚጠቀምበት መጽሐፍ ካለ።')],
            ['q' => L('Do you publish results or photographs?', 'ውጤት ወይም ፎቶ ይታተማል?'),
             'a' => L('Individual results are never published. Photographs appear only with the agreement of parents.', 'የግል ውጤቶች አይታተሙም። ፎቶዎች በወላጆች ፈቃድ ብቻ ይታያሉ።')],
        ],
        'form' => [
            'title'    => L('Registration request', 'የምዝገባ ጥያቄ'),
            'note'     => L('We reply with the class and start date. Any age is accepted.', 'የክፍሉንና የመጀመሪያ ቀን እንመልስልዎታለን። ማንኛውም ዕድሜ ይቀበላል።'),
            'levels' => [
                ['value' => 'A child (under 13)', 'label' => L('A child (under 13)', 'ልጅ (ከ13 ዓመት በታች)')],
                ['value' => 'A young person (13-17)', 'label' => L('A young person (13–17)', 'ወጣት (13–17)')],
                ['value' => 'An adult (18 or over)', 'label' => L('An adult (18 or over)', 'አዋቂ (18 ዓመትና ከዚያ በላይ)')],
                ['value' => 'Myself - I am the person joining', 'label' => L('Myself — I am the person joining', 'ራሴ — የሚመዘገበው እኔ ነኝ')],
                ['value' => 'Not sure - please advise', 'label' => L('Not sure — please advise', 'እርግጠኛ አይደለሁም — ይመክሩኝ')],
            ],
        ],
        'cta_title' => L('Still have a question?', 'ጥያቄ አለዎት?'),
        'cta_text' => L('The office answers questions about classes, attendance and Sundays.', 'ጽ/ቤቱ ስለ ክፍሎች፣ ስለ መገኘትና ስለ እሁድ ፕሮግራም ይመልሳል።'),
    ],

    // ── News and notices ───────────────────────────────────────────────
    'news' => [
        'eyebrow' => L('News and notices', 'ዜናና ማሳሰቢያዎች'),
        'title'   => L('Life of the school', 'የትምህርት ቤቱ ዜና'),
        'lead'    => L('Announcements and photographs from the parish school.', 'ከደብሩ ትምህርት ቤት ማስታወቂያዎችና ፎቶዎች።'),
        'items'   => [
            [
                'kind'  => 'notice',
                'title' => L('Registration for the new academic year', 'ለአዲሱ የትምህርት ዓመት ምዝገባ'),
                'text'  => L('Families registering a child — and adults joining themselves — can send a request at any time.',
                             'ልጅ የሚያስመዘግቡ ወላጆችና ራሳቸውን የሚያስመዘግቡ አዋቂዎች በማንኛውም ጊዜ ጥያቄ መላክ ይችላሉ።'),
                'link'  => ['label' => L('Registration request', 'የምዝገባ ጥያቄ'), 'path' => '/admissions'],
                'image' => '/themes/fkss/assets/wbss-teaching-room.jpg',
                'image_alt' => L('Children of the Sunday school sitting with open books during a lesson.', 'የሰንበት ትምህርት ቤቱ ልጆች መጽሐፋቸውን ከፍተው በትምህርት ጊዜ።'),
            ],
            [
                'kind'  => 'notice',
                'title' => L('Sunday lessons', 'የእሁድ ትምህርት'),
                'text'  => L('Lessons run from 9:00 to 12:00. Please arrive by 8:30 for attendance.',
                             'ትምህርት ከ9:00 እስከ 12:00 ይሰጣል። ለመገኘት መዝገብ በ8:30 ይድረሱ።'),
                'link'  => ['label' => L('Ask the office', 'ጽ/ቤቱን ይጠይቁ'), 'path' => '/contact'],
                'image' => '/themes/fkss/assets/wbss-hero-courtyard.jpg',
                'image_alt' => L('Children in white netela shawls walking towards the church entrance.', 'ልጆች በነጭ ነጠላ ተጠቅልለው ወደ ቤተክርስቲያኑ መግቢያ ሲሄዱ።'),
            ],
            [
                'kind'  => 'notice',
                'title' => L('Mezmur practice', 'የመዝሙር ልምምድ'),
                'text'  => L('Hymn practice follows the lesson. New pupils and adults are welcome.',
                             'የዝማሬ ልምምድ ከትምህርቱ በኋላ ይካሄዳል። አዲስ ተማሪዎችና አዋቂዎች እንኳን ደህና መጡ።'),
                'link'  => null,
                'image' => '/themes/fkss/assets/wbss-mezmur-practice.jpg',
                'image_alt' => L('Children singing from a hymn book beside a kebero drum during Mezmur practice.', 'ልጆች በመዝሙር ልምምድ ጊዜ ከከበሮ አጠገብ የዝማሬ መጽሐፍ ይዘው ሲዘምሩ።'),
            ],
            [
                'kind'  => 'notice',
                'title' => L('A word to parents', 'ለወላጆች ማሳሰቢያ'),
                'text'  => L('Please tell the teacher if your family will be away for several Sundays.',
                             'ቤተሰብዎ ለተወሰነ ሰንበት ከቤት ውጭ ከሆነ ለአስተማሪው ይንገሩ።'),
                'link'  => null,
            ],
        ],
        'empty_note' => L('Dated announcements appear here automatically.', 'የቀን ማስታወቂያዎች እዚህ በራስሱ ይታያሉ።'),
        'announcements_title' => L('Get announcements', 'ማስታወቂያዎችን ያግኙ'),
        'privacy_title' => L('Pupil information stays private', 'የተማሪ መረጃ ሚስጥራዊ ነው'),
        'privacy_text' => L('Results, attendance and report cards are never published here.', 'ውጤት፣ መገኘትና ሪፖርት ካርድ እዚህ አይታተሙም።'),
    ],

    // ── Gallery page ───────────────────────────────────────────────────
    'gallery' => [
        'eyebrow' => L('Gallery', 'ፎቶዎች'),
        'title'   => L('Moments from school life', 'ከትምህርት ቤቱ ሕይወት'),
        'lead'    => L('Photographs published by the school, with the agreement of parents and guardians.',
                       'በትምህርት ቤቱ የታተሙ ፎቶዎች፤ በወላጆችና በአሳዳጊዎች ፈቃድ።'),
        'note'    => L('If you would like a photograph removed, please contact the office.', 'ፎቶ እንዲወገድ ከፈለጉ ጽ/ቤቱን ያግኙ።'),
        'cta_title' => L('Visit the school in person', 'ትምህርት ቤቱን በአካል ይጎብኙ'),
        'cta_text' => L('Come to a Sunday session and meet the teachers.', 'በእሁድ መጥተው አስተማሪዎቹን ያግኙ።'),
    ],

    // ── Privacy page ───────────────────────────────────────────────────
    'privacy' => [
        'eyebrow' => L('Privacy and safeguarding', 'ግላዊነትና ደህንነት'),
        'title'   => L('How this website handles information', 'ይህ ድረ-ገጽ መረጃን እንዴት ይይዛል'),
        'lead'    => L('A plain description of what the public website does with information.', 'የዚህ ድረ-ገጽ የመረጃ አያያዝ አጭር መግለጫ።'),
        'sections' => [
            ['title' => L('What the site collects', 'ገጹ የሚሰበስበው'),
             'items' => [
                 L('A registration or contact request records the name, phone number and any detail you choose to send.',
                   'የምዝገባ ወይም የመልእክት ጥያቄ ስም፣ ስልክ ቁጥርና የሚልኩትን መረጃ ይመዘግባል።'),
                 L('The server records the requesting address to limit automated abuse.', 'አላስፈላጊ ጥቃትን ለመገደብ አገልጋዩ የጥያቄውን አድራሻ ይመዘግባል።'),
                 L('No advertising, analytics or tracking scripts are used.', 'ማስታወቂያ፣ ትንተና ወይም መከታተያ ስክሪፕት አይጠቀምም።'),
             ]],
            ['title' => L('What the site publishes', 'ገጹ የሚያትም'),
             'items' => [
                 L('Only photographs approved by the school appear in the public gallery.', 'በትምህርት ቤቱ የጸደቁ ፎቶዎች ብቻ በፎቶ ገጹ ይታያሉ።'),
                 L('Pupil records, results, attendance and finances stay in the administration system.', 'የተማሪ መዝገብ፣ ውጤት፣ መገኘትና በጀት በአስተዳደር ሥርዓቱ ውስጥ ብቻ ነው።'),
             ]],
            ['title' => L('Who sees a request', 'ጥያቄውን የሚያይ'),
             'items' => [
                 L('Requests are visible only to authorised school staff.', 'ጥያቄዎች ለተፈቀደላቸው የትምህርት ቤቱ ሠራተኞች ብቻ ይታያሉ።'),
                 L('Requests are never sold or shared for marketing.', 'ጥያቄዎች ለግብይት አይሸጡም፣ አይጋሩም።'),
             ]],
            ['title' => L('Keeping children safe', 'የልጆችን ደህንነት'),
             'items' => [
                 L('Photographs of pupils are published only with parents’ agreement.', 'የተማሪዎች ፎቶ በወላጆች ፈቃድ ብቻ ይታተማል።'),
                 L('Staff use individual accounts; the public site has no login of its own.', 'ሠራተኞች የራሳቸውን መለያ ይጠቀማሉ፤ ህዝባዊው ገጽ የራሱ መግቢያ የለውም።'),
             ]],
            ['title' => L('Your choices', 'ምርጫዎችዎ'),
             'items' => [
                 L('You may ask what has been recorded about your family.', 'ስለ ቤተሰብዎ ምን እንደተመዘገበ መጠየቅ ይችላሉ።'),
                 L('You may ask for a photograph to be removed.', 'ፎቶ እንዲወገድ መጠየቅ ይችላሉ።'),
             ]],
        ],
        'review_note' => L('This description is reviewed by the school and the parish.', 'ይህ መግለጫ በትምህርት ቤቱና በደብሩ ይገመገማል።'),
    ],

    // ── Search page ────────────────────────────────────────────────────
    'search' => [
        'eyebrow' => L('Search', 'ፍለጋ'),
        'title'   => L('Search the website', 'ድረ-ገጹን ይፈልጉ'),
        'lead'    => L('Pages, programmes, notices and questions.', 'ገጾች፣ መርሃ ግብሮች፣ ማሳሰቢያዎችና ጥያቄዎች።'),
        'empty'   => L('Enter a word to search.', 'ለመፈለግ ቃል ያስገቡ።'),
        'none'    => L('No page, programme or notice matched that search.', 'የሚመሳሰል ገጽ፣ መርሃ ግብር ወይም ማሳሰቢያ አልተገኘም።'),
    ],

    // ── Static page index used by navigation, search and the footer ────
    'pages' => [
        ['path' => '/', 'title' => L('Home', 'መነሻ'), 'nav' => true, 'footer' => false, 'keywords' => 'home welcome parish sunday school መነሻ እንኳን'],
        ['path' => '/about', 'title' => L('About us', 'ስለ እኛ'), 'nav' => true, 'footer' => true, 'keywords' => 'about history mission vision values parish governance ስለ እኛ ተልእኮ ራዕይ'],
        ['path' => '/programs', 'title' => L('Programmes', 'መርሃ ግብሮች'), 'nav' => true, 'footer' => true, 'keywords' => 'programmes classes curriculum scripture geez amharic mezmur መርሃ ግብር ክፍል ትምህርት'],
        ['path' => '/admissions', 'title' => L('Admissions', 'ምዝገባ'), 'nav' => true, 'footer' => true, 'keywords' => 'admissions register registration enrol apply requirements faq ምዝገባ ጥያቄ'],
        ['path' => '/news', 'title' => L('News and notices', 'ዜናና ማሳሰቢያዎች'), 'nav' => true, 'footer' => true, 'keywords' => 'news notices announcements events ዜና ማሳሰቢያ ማስታወቂያ'],
        ['path' => '/gallery', 'title' => L('Gallery', 'ፎቶዎች'), 'nav' => true, 'footer' => true, 'keywords' => 'gallery photos pictures albums ፎቶ ምስል'],
        ['path' => '/contact', 'title' => L('Contact', 'ያግኙን'), 'nav' => true, 'footer' => true, 'keywords' => 'contact phone email address location map office hours ያግኙን ስልክ አድራሻ'],
        ['path' => '/privacy', 'title' => L('Privacy and safeguarding', 'ግላዊነትና ደህንነት'), 'nav' => false, 'footer' => true, 'keywords' => 'privacy data protection safeguarding photographs ግላዊነት ደህንነት'],
        ['path' => '/search', 'title' => L('Search', 'ፍለጋ'), 'nav' => false, 'footer' => false, 'keywords' => 'search find ፍለጋ'],
    ],

    // ── Short page summaries used by site search ──────────────────────
    'page_copy' => [
        '/'           => L('Scripture, Church teaching, Ge’ez and Amharic, Mezmur and community for every age.', 'ቅዱስ መጽሐፍ፣ የቤተክርስቲያን ትምህርት፣ ግዕዝና አማርኛ፣ መዝሙርና ማህበረሰብ — ለሁሉም ዕድሜ።'),
        '/about'      => L('Our story, mission, vision, values and governance as a ministry of the parish.', 'ታሪካችን፣ ተልእኮ፣ ራዕይ፣ እሴቶችና አስተዳደር — የደብሩ አገልግሎት።'),
        '/programs'   => L('The subjects taught at every level and how a Sunday runs.', 'በሁሉም ደረጃ የሚሰጡ ትምህርቶችና የእሁድ ፕሮግራም።'),
        '/admissions' => L('How to register a child or yourself, at any age.', 'ልጅዎን ወይም ራስዎን እንዴት እንደሚያስመዘግቡ።'),
        '/news'       => L('Announcements and photographs from school life.', 'ማስታወቂያዎችና ፎቶዎች።'),
        '/gallery'    => L('Photographs of lessons, Mezmur practice and feast days.', 'የትምህርት፣ የመዝሙር ልምምድና የበዓላት ፎቶዎች።'),
        '/contact'    => L('Address, email, office hours and the enquiry form.', 'አድራሻ፣ ኢሜይል፣ የሥራ ሰዓትና የመልእክት ቅጽ።'),
        '/privacy'    => L('What the site collects, publishes and who sees a request.', 'ገጹ የሚሰበስበው፣ የሚያትመውና ጥያቄውን የሚያየው።'),
        '/search'     => L('Search the pages, programmes and notices of this website.', 'የዚህን ድረ-ገጽ ገጾች፣ መርሃ ግብሮችና ማሳሰቢያዎች ይፈልጉ።'),
    ],

    // ── Footer ─────────────────────────────────────────────────────────
    'footer' => [
        'blurb' => L('A ministry of Gelan Gura Felege Giyon St. Gabriel Church.', 'የገላን ጉራ ፈለገ ግዮን ቅዱስ ገብርኤል ቤተክርስቲያን አገልግሎት።'),
        'columns' => [
            ['title' => L('Explore', 'ያስሱ'), 'paths' => ['/about', '/programs', '/gallery']],
            ['title' => L('Join us', 'ይቀላቀሉን'), 'paths' => ['/admissions', '/contact', '/privacy']],
        ],
        'legal_note' => L('Public information site. Pupil records are available only inside the administration system.',
                          'ህዝባዊ መረጃ ገጽ። የተማሪዎች መዝገብ በአስተዳደር ሥርዓቱ ውስጥ ብቻ ነው።'),
    ],
];
