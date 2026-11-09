"""Public website contracts: routing, escaping, assets, forms and honesty.

The public site is the only unauthenticated HTML surface in this repository.
These tests pin the properties that make it safe to publish:

1. every advertised page is a real file registered in the finite browser
   route registry, and its clean URL resolves through the same rules as the
   rest of the site;
2. pages do not read from non-website folders, do not embed external CDN
   assets, and echo stored or submitted text through the escaping helper;
3. the registration/contact forms keep posting to the existing, already
   validated public endpoint (no second write path, no invented API);
4. the school's unverified content stays visibly marked as draft instead of
   being silently published as fact.

These are static assertions and never skip: a skipped test is an unverified
claim (see the zero-skip rule in the backend checks). A live render is a
separate opt-in script, `tests/e2e/public_website_render.py`, because that
needs a provisioned PHP world.
"""
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

PUBLIC_PAGES = {
    '/': 'index.php',
    '/about': 'about.php',
    '/programs': 'programs.php',
    '/admissions': 'admissions.php',
    '/gallery': 'gallery.php',
    '/news': 'news.php',
    '/contact': 'contact.php',
    '/privacy': 'privacy.php',
    '/search': 'search.php',
}

# Shared, non-page includes of the public site.
SHARED = ['public_site.php', 'site_content.php', 'site_lang.php']


def read(name):
    return (ROOT / name).read_text(encoding='utf-8')


class PublicWebsiteStructureTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.index = read('index.php')
        cls.bootstrap = read('public_site.php')
        cls.content = read('site_content.php')
        cls.pages = {name: read(name) for name in PUBLIC_PAGES.values()}
        cls.lang = read('site_lang.php')
        cls.registry = read('admin/backend/services/BrowserPageRoutes.php')
        cls.htaccess = read('.htaccess')

    # 1 — routing -------------------------------------------------------
    def test_every_public_page_exists_and_is_wired_once(self):
        for path, filename in PUBLIC_PAGES.items():
            with self.subTest(page=path):
                self.assertTrue((ROOT / filename).is_file(), filename)
                self.assertIn(f"'{path}' => '/{filename}'", self.registry)

    def test_pages_share_one_bootstrap_and_declare_a_page_identity(self):
        for path, filename in PUBLIC_PAGES.items():
            with self.subTest(page=path):
                source = self.pages[filename]
                self.assertIn("require_once __DIR__ . '/public_site.php';", source)
                self.assertIn("'slug'", source)
                self.assertRegex(source, r"site_head\(\$page\)")
                self.assertRegex(source, r"site_header\(\$page\)")
                self.assertIn('site_footer();', source)

    def test_shared_bootstrap_refuses_direct_requests(self):
        self.assertIn('SCRIPT_FILENAME', self.bootstrap)
        self.assertIn('http_response_code(404)', self.bootstrap)
        # A content file must never be executable on its own either.
        self.assertTrue(self.content.lstrip().startswith('<?php'))
        for shared in SHARED:
            self.assertNotRegex(PUBLIC_PAGES.values().__str__(), re.escape(shared))

    def test_apache_dispatches_only_the_registered_public_slugs(self):
        self.assertIn('ErrorDocument 404 /404.php', self.htaccess)
        # The retired pages must not keep a handler behind them.
        self.assertNotIn('sitemap.php', self.htaccess)
        self.assertNotIn('schedule', self.htaccess)
        self.assertFalse((ROOT / 'sitemap.php').exists())
        self.assertFalse((ROOT / 'schedule.php').exists())
        dispatch = [line for line in self.htaccess.splitlines() if 'browser_page.php [END]' in line]
        self.assertEqual(len(dispatch), 1)
        for slug in PUBLIC_PAGES:
            if slug == '/':
                continue
            self.assertIn(slug.lstrip('/'), dispatch[0])
        # Still finite: no generic rewrite may append .php to user input.
        self.assertNotIn('$1.php', self.htaccess)

    def test_robots_is_scoped_and_publishes_no_sitemap(self):
        robots = read('robots.txt')
        self.assertIn('Disallow: /search', robots)
        for folder in ('/admin/', '/api/', '/monitor/'):
            self.assertIn(f'Disallow: {folder}', robots)
        # The school asked for the sitemap to be withdrawn; a robots.txt that
        # advertised one would point crawlers at a 404.
        self.assertNotIn('Sitemap:', robots)

    def test_schedule_page_and_its_copy_are_gone(self):
        for filename in list(PUBLIC_PAGES.values()) + SHARED + ['404.php', 'register_submit.php']:
            source = read(filename)
            with self.subTest(file=filename):
                self.assertNotIn("'/schedule'", source)
                self.assertNotIn('site_schedule_rows', source)
        self.assertNotIn('Weekly rhythm', self.index)
        self.assertNotIn("'schedule' =>", self.content)

    # 2 — output safety and self-containment ----------------------------
    def test_output_is_escaped_through_the_helper(self):
        # No page may echo a raw superglobal or a CMS row directly.
        forbidden = [
            r'<\?=\s*\$_GET', r'<\?=\s*\$_POST', r'<\?=\s*\$_REQUEST', r'<\?=\s*\$_SERVER',
            r'<\?=\s*\$cms\[', r'<\?=\s*\$row\[', r'<\?=\s*\$WBSS_CMS',
        ]
        for filename in list(PUBLIC_PAGES.values()) + SHARED:
            source = read(filename)
            for pattern in forbidden:
                with self.subTest(file=filename, pattern=pattern):
                    self.assertNotRegex(source, pattern)
        # The helper itself must be present in both the bootstrap and the app.
        self.assertIn('htmlspecialchars', self.bootstrap)
        self.assertIn('htmlspecialchars', read('config.php'))

    def test_pages_do_not_call_external_asset_hosts(self):
        asset_rx = re.compile(
            r'(?:src|href)\s*=\s*["\'](https?:)?//([^"\']+)', re.IGNORECASE)
        allowed_hosts = {'schema.org'}
        offenders = []
        assets = ['css/wbss-site.css', 'js/wbss-site.js',
                  'css/wbss-motion.css', 'js/wbss-motion.js']
        for filename in list(PUBLIC_PAGES.values()) + SHARED + assets:
            for match in asset_rx.finditer(read(filename)):
                host = match.group(2).split('/')[0].lower()
                if host not in allowed_hosts:
                    offenders.append(f'{filename}: {match.group(0)[:70]}')
        self.assertEqual(offenders, [], 'Public pages must not depend on third-party CDNs')
        self.assertNotIn('cdn.tailwindcss.com', ''.join(self.pages.values()))
        self.assertNotIn('fontawesome', ''.join(self.pages.values()).lower())

    def test_shared_layout_has_accessibility_and_seo_hooks(self):
        for token in ('skip-link', "ui('primary_nav')", 'aria-expanded="false"',
                      'class="visually-hidden"', 'rel="canonical"', 'property="og:title"',
                      'application/ld+json', 'name="viewport"'):
            with self.subTest(token=token):
                self.assertIn(token, self.bootstrap)

    def test_decorative_images_and_reveals_degrade_without_javascript(self):
        # Reveal animations must only hide content when JavaScript is proven.
        css = read('css/wbss-site.css')
        self.assertIn('.js [data-reveal]', css)
        self.assertNotRegex(css, r'(?<!\.js )\[data-reveal\]\s*\{\s*opacity:\s*0')
        head = self.bootstrap
        self.assertIn("classList.add('js')", head)

    # 3 — forms ---------------------------------------------------------
    def test_public_forms_reuse_the_validated_public_endpoint(self):
        self.assertIn("site_url('/register_submit.php')", self.bootstrap)
        self.assertIn('name="website"', self.bootstrap)      # honeypot kept
        self.assertIn('data-endpoint', self.bootstrap)
        endpoint = read('register_submit.php')
        # The endpoint owns validation and rate limiting; the redesign must
        # not have added a second write path or relaxed those gates.
        self.assertIn('SecurityRateLimiter', endpoint)
        self.assertIn("consume('public-registration'", endpoint)
        self.assertIn('cms_registration_submissions', endpoint)
        # The endpoint still refuses an unwritable world; the message is now
        # built by the localising helper instead of a bare English string.
        self.assertIn("$msg('Service temporarily unavailable", endpoint)
        for filename in PUBLIC_PAGES.values():
            source = read(filename)
            for match in re.finditer(r'action="([^"]+)"', source):
                self.assertNotIn('register_submit.php', match.group(1),
                                 f'{filename} must let the shared form own the endpoint')

    def test_registration_accepts_any_age_and_adults_registering_themselves(self):
        # No age bands and no upper limit: a 60-year-old joins like a child.
        self.assertIn('name="age" type="number"'.replace('name="age" type="number"', 'type="number" name="age"'), self.bootstrap)
        self.assertIn('min="1" max="120"', self.bootstrap)
        self.assertNotIn('<select id="reg-age"', self.bootstrap)
        self.assertNotIn('Ages 4', self.content)
        self.assertNotIn('Little Lambs', self.content)
        self.assertNotIn('fallback_classes', self.bootstrap)
        levels = self.content.split("'levels' => [", 1)[1][:1400]
        self.assertIn('An adult (18 or over)', levels)
        self.assertIn('Myself — I am the person joining', levels)
        endpoint = read('register_submit.php')
        self.assertIn("Input::integer($age, 'Age', 1, 120)", endpoint)

    def test_news_items_support_photographs_safely(self):
        for token in ('function site_media_url', 'function site_news_media', "'image_path'", "'image_alt'"):
            with self.subTest(token=token):
                self.assertIn(token, self.bootstrap)
        # Only same-site, root-relative image paths may render.
        self.assertIn("str_starts_with($path, '//')", self.bootstrap)
        self.assertIn('str_contains($path, \'..\')', self.bootstrap)
        self.assertIn("'/themes/fkss/assets/wbss-", self.content)
        sql = read('sql/080_announcement_images.sql')
        self.assertIn('`image_path` VARCHAR(255)', sql)
        self.assertIn('`image_alt` VARCHAR(255)', sql)
        self.assertNotIn('DROP ', sql.upper())
        self.assertNotRegex(sql.upper(), r'^\s*(DELETE\s+FROM|TRUNCATE)')
        # The photographs the pages reference must exist in the repository.
        for asset in ('themes/fkss/assets/wbss-hero-courtyard.jpg',
                      'themes/fkss/assets/wbss-teaching-room.jpg',
                      'themes/fkss/assets/wbss-mezmur-practice.jpg'):
            with self.subTest(asset=asset):
                self.assertTrue((ROOT / asset).is_file(), asset)

    def test_child_name_is_folded_into_the_message_not_a_new_field(self):
        # The endpoint has no child_name column; the browser merges it into the
        # free-text message instead of inventing an unvalidated parameter.
        self.assertIn('name="child_name"', self.bootstrap)
        self.assertIn("data.set('message'", read('js/wbss-site.js'))
        self.assertNotIn('child_name', read('register_submit.php'))

    # 4 — honest content ------------------------------------------------
    def test_draft_notice_is_withdrawn_and_no_placeholder_is_published(self):
        # The school office approved the copy, so the site-wide warning bar is
        # gone. Editorial `draft` flags stay in site_content.php as an internal
        # note only: they must not reach the rendered pages.
        self.assertIn("define('WBSS_SITE_CONTENT_DRAFT', false)", self.content)
        self.assertNotIn("'draft_notice'", self.content)
        self.assertNotIn('draft-bar', self.bootstrap)
        self.assertNotIn('draft-bar', read('css/wbss-site.css'))
        self.assertNotIn('awaiting approval', self.content)
        # A placeholder must never be published as if it were the real number.
        self.assertNotIn('+251 XXX', self.content)
        self.assertNotIn('XXX XXX', ''.join(self.pages.values()))
        for key in ("'phone_primary'", "'email_primary'"):
            with self.subTest(key=key):
                block = self.content.split(key, 1)[1][:220]
                self.assertIn("'draft' => true", block)

    def test_staff_login_is_not_advertised_on_the_public_web(self):
        joined = ''.join(self.pages.values()) + self.bootstrap + self.content
        self.assertNotIn('/admin/login', joined)
        self.assertNotIn('Staff login', joined)
        self.assertNotIn('admin_login_path', self.content)

    def test_no_individual_pupil_information_is_published(self):
        joined = ''.join(self.pages.values())
        self.assertNotIn('member_code', joined)
        self.assertNotIn('report_card', joined)
        privacy_copy = read('site_content.php')
        self.assertIn('Individual results are never published', privacy_copy)
        self.assertIn('Only photographs approved by the school appear in the public gallery', privacy_copy)

    # 5 — optional data path --------------------------------------------
    def test_optional_announcements_table_is_read_only_and_guarded(self):
        for token in ('SELECT * FROM cms_announcements WHERE is_active=1',
                      'link_url', 'published_on'):
            with self.subTest(token=token):
                self.assertIn(token, self.bootstrap)
        # Stored announcement links must be same-site and root-relative.
        self.assertIn("str_starts_with($linkUrl, '//')", self.bootstrap)
        sql = read('sql/079_public_announcements.sql')
        self.assertIn('CREATE TABLE IF NOT EXISTS `cms_announcements`', sql)
        self.assertNotIn('DROP ', sql.upper())
        self.assertNotRegex(sql.upper(), r'^\s*(DELETE\s+FROM|TRUNCATE)')

    def test_website_never_writes_to_the_database(self):
        for filename in list(PUBLIC_PAGES.values()) + SHARED:
            source = read(filename).upper()
            for statement in ('INSERT INTO', 'UPDATE ', 'DELETE FROM', 'TRUNCATE', 'DROP TABLE'):
                with self.subTest(file=filename, statement=statement.strip()):
                    self.assertNotIn(statement, source)


class PublicWebsiteBilingualTests(unittest.TestCase):
    """Amharic is the default language; English is one switch away.

    These assertions pin the two things that break silently in a bilingual
    site: a language that cannot be chosen, and a string that was never
    translated (which shows as English text inside an Amharic page).
    """

    @classmethod
    def setUpClass(cls):
        cls.lang = read('site_lang.php')
        cls.bootstrap = read('public_site.php')
        cls.content = read('site_content.php')
        cls.css = read('css/wbss-site.css')
        cls.pages = {name: read(name) for name in PUBLIC_PAGES.values()}

    def test_amharic_is_the_default_language(self):
        self.assertIn("const WBSS_SITE_LANG_DEFAULT = 'am';", self.lang)
        self.assertIn("const WBSS_SITE_LANGS = ['am', 'en'];", self.lang)
        # Only those two values are ever accepted, whatever the request says.
        self.assertIn('in_array($requested, WBSS_SITE_LANGS, true)', self.lang)
        self.assertIn('in_array($cookie, WBSS_SITE_LANGS, true)', self.lang)
        self.assertIn("'samesite' => 'Lax'", self.lang)

    def test_document_language_and_alternates_are_declared(self):
        self.assertIn('<html lang="<?= e(site_lang()) ?>">', self.bootstrap)
        self.assertIn('hreflang="am"', self.bootstrap)
        self.assertIn('hreflang="en"', self.bootstrap)
        self.assertIn('hreflang="x-default"', self.bootstrap)
        self.assertIn("'inLanguage' => site_lang()", self.bootstrap)

    def test_the_switch_is_on_every_page(self):
        # Rendered by the shared header and again inside the mobile drawer.
        self.assertIn('function site_lang_switch', self.bootstrap)
        self.assertEqual(self.bootstrap.count('site_lang_switch('), 3)  # def + header + drawer
        self.assertIn("site_lang_url('am')", self.bootstrap)
        self.assertIn("site_lang_url('en')", self.bootstrap)
        # Amharic is listed first, because it is the default.
        am = self.bootstrap.index("site_lang_url('am')")
        en = self.bootstrap.index("site_lang_url('en')")
        self.assertLess(am, en)

    def test_every_editorial_string_is_a_pair(self):
        # L('English', 'አማርኛ') — a single-argument L() would silently ship
        # English text to Amharic readers.
        single = re.findall(r"L\('(?:[^'\\]|\\.)*'\)", self.content)
        self.assertEqual(single, [], f'strings without a translation: {single[:3]}')
        for pair in re.finditer(r"L\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'((?:[^'\\]|\\.)*)'", self.content):
            with self.subTest(text=pair.group(1)[:40]):
                self.assertRegex(pair.group(2), r'[\u1200-\u139F]|^English$',
                                 'the second argument must be Amharic')

    def test_templates_hold_no_hard_coded_interface_english(self):
        # Chrome, buttons and labels come from the ui dictionary, so a new
        # language only needs new strings, not new templates.
        for key in ('skip_link', 'register', 'privacy_link', 'search_site',
                    'primary_nav', 'open_menu', 'join_heading'):
            with self.subTest(key=key):
                self.assertRegex(self.content, rf"'{key}'\s*=> L\(")
        self.assertIn('function ui(string $key)', self.bootstrap)
        self.assertNotIn('aria-label="Primary"', self.bootstrap)
        self.assertNotIn('aria-label="Breadcrumb"', self.bootstrap)
        self.assertNotIn('>Skip to main content<', self.bootstrap)

    def test_client_side_messages_are_translated_too(self):
        for token in ('data-msg-required', 'data-msg-email', 'data-msg-sending',
                      'data-msg-failed', 'data-msg-joining'):
            with self.subTest(token=token):
                self.assertIn(token, self.bootstrap)
        js = read('js/wbss-site.js')
        self.assertIn('document.body.dataset', js)
        # The page always wins; the English string survives only as the last
        # resort fallback for a stripped-down page.
        for key, english in (('msgRequired', 'Please complete the required fields'),
                             ('msgEmail', 'Please check the email address')):
            self.assertIn(f"bodyData.{key} || '{english}", js)

    def test_the_form_endpoint_answers_in_the_visitors_language(self):
        endpoint = read('register_submit.php')
        self.assertIn("$_POST['lang']", endpoint)
        self.assertIn("'am'", endpoint)
        self.assertIn('require_once __DIR__ . \'/site_lang.php\';', endpoint)
        # An unexpected value must fall back to English, never be echoed.
        self.assertNotIn('<?=', endpoint)

    def test_amharic_typography_rules_exist(self):
        self.assertIn('--font-am', self.css)
        self.assertIn('html[lang="am"]', self.css)
        self.assertIn('.lang-switch', self.css)
        # Ethiopic must never be uppercased or tracked like Latin small caps.
        self.assertIn('text-transform: none', self.css)

    def test_nothing_depends_on_a_webfont_or_cdn(self):
        for filename in list(PUBLIC_PAGES.values()) + SHARED:
            source = read(filename)
            with self.subTest(file=filename):
                self.assertNotIn('fonts.googleapis.com', source)
                self.assertNotIn('fonts.gstatic.com', source)
        self.assertIn('"Noto Serif Ethiopic"', self.css)


class PublicWebsiteMotionTests(unittest.TestCase):
    """The motion layer: shipped locally, additive, and bilingual.

    Motion is an enhancement, never a dependency: a browser without JavaScript,
    or a visitor who asked for reduced motion, must still get the whole page.
    """

    @classmethod
    def setUpClass(cls):
        cls.bootstrap = read('public_site.php')
        cls.content = read('site_content.php')
        cls.motion_css = read('css/wbss-motion.css')
        cls.motion_js = read('js/wbss-motion.js')
        cls.site_css = read('css/wbss-site.css')
        cls.pages = {name: read(name) for name in PUBLIC_PAGES.values()}

    def test_motion_assets_are_local_and_loaded_once(self):
        self.assertIn("site_asset('/css/wbss-motion.css')", self.bootstrap)
        self.assertIn("site_asset('/js/wbss-motion.js')", self.bootstrap)
        self.assertEqual(self.bootstrap.count('wbss-motion.css'), 1)
        self.assertEqual(self.bootstrap.count('wbss-motion.js'), 1)

    def test_every_page_asks_for_animation(self):
        # Each template opts in through the shared helper, never by hand.
        for path, filename in PUBLIC_PAGES.items():
            with self.subTest(page=path):
                self.assertIn('<?= reveal(', self.pages[filename])
        self.assertIn('function reveal(', self.bootstrap)
        # The helper only emits the variants the stylesheet defines.
        for variant in ('up', 'left', 'right', 'zoom', 'pop'):
            self.assertIn(f"'{variant}'", self.bootstrap)

    def test_animation_never_hides_content_without_javascript(self):
        # Bare [data-reveal] rules may only come from the .js-gated layer.
        self.assertNotRegex(self.motion_css, r'(?<![\.\w-])\[data-reveal\][^{]*\{[^}]*opacity:\s*0')
        self.assertIn('.js [data-reveal]', self.motion_css)
        self.assertIn('.js [data-reveal]', self.site_css)

    def test_reduced_motion_and_print_switch_everything_off(self):
        self.assertIn('prefers-reduced-motion: reduce', self.motion_css)
        self.assertIn('prefers-reduced-motion: reduce', self.motion_js)
        # Both the stylesheet and the script honour the preference.
        self.assertIn('@media print', self.motion_css)
        self.assertIn('.scroll-progress, .modal, .to-top { display: none !important; }', self.motion_css)

    def test_script_is_dependency_free_and_never_writes(self):
        self.assertNotIn('import ', self.motion_js)
        self.assertNotIn('require(', self.motion_js)
        self.assertNotIn('fetch(', self.motion_js)
        self.assertNotIn('XMLHttpRequest', self.motion_js)
        for forbidden in ('http://', 'https://', '//cdn.'):
            self.assertNotIn(forbidden, self.motion_js)
            self.assertNotIn(forbidden, self.motion_css)

    def test_motion_layer_does_not_touch_the_database(self):
        for name in ('css/wbss-motion.css', 'js/wbss-motion.js'):
            with self.subTest(file=name):
                source = read(name)
                for forbidden in ('SELECT ', 'INSERT ', 'UPDATE ', 'DELETE ', 'mysqli', 'PDO'):
                    self.assertNotIn(forbidden, source)

    def test_countdown_is_configured_and_bilingual(self):
        self.assertIn("'countdown'", self.content)
        for key in ('cd_label', 'cd_days', 'cd_hours', 'cd_minutes', 'cd_seconds'):
            self.assertIn(f"'{key}'", self.content)
        self.assertIn('data-countdown', self.pages['index.php'])
        self.assertIn('data-cd="days"', self.pages['index.php'])
        # The count is computed in the visitor's browser from the school week,
        # so no stale "next Sunday" date is ever served from PHP.
        self.assertIn('data-weekday', self.pages['index.php'])
        self.assertNotIn('new Date(', self.bootstrap)

    def test_popup_is_opt_in_bilingual_and_once_per_session(self):
        self.assertIn('function site_popup(', self.bootstrap)
        self.assertIn('data-store-key', self.bootstrap)
        self.assertIn("window.sessionStorage", self.motion_js)
        self.assertIn("'popup'", self.content)
        # It must be dismissible three ways and never trap the visitor.
        self.assertIn("event.key === 'Escape'", self.motion_js)
        self.assertIn('data-modal-close', self.bootstrap)
        # Shown on the home page only.
        self.assertIn("site_popup($home[\'popup\']", self.pages['index.php'])
        for filename in PUBLIC_PAGES.values():
            if filename == 'index.php':
                continue
            with self.subTest(page=filename):
                self.assertNotIn('site_popup(', self.pages[filename])

    def test_lightbox_is_keyboard_reachable_and_labelled(self):
        self.assertIn('function site_lightbox(', self.bootstrap)
        self.assertIn('aria-modal="true"', self.bootstrap)
        self.assertIn('role="button" tabindex="0"', self.bootstrap)
        self.assertIn("event.key === 'ArrowRight'", self.motion_js)
        self.assertIn("event.key === 'Escape'", self.motion_js)
        self.assertIn('data-lightbox-counter', self.bootstrap)
        self.assertIn('data-lightbox-caption', self.bootstrap)
        # Photographs stay in the news list; the lightbox adds no new source.
        self.assertIn('data-lightbox', self.bootstrap)

    def test_motion_strings_are_all_pairs(self):
        for key in ('close', 'photo_open', 'photo_viewer', 'photo_prev', 'photo_next'):
            with self.subTest(key=key):
                self.assertRegex(
                    self.content,
                    r"'%s'\s*=>\s*L\(" % key,
                    'UI strings on the public site must be bilingual pairs')

    def test_scrolling_only_moves_compositor_properties(self):
        # The scroll handler may touch transform and class names only; a
        # height/top animation would thrash layout on a school phone.
        handler = self.motion_js.split('function onScroll()', 1)[1].split('/* ── 4.', 1)[0]
        self.assertNotIn('style.height', handler)
        self.assertNotIn('style.top', handler)
        self.assertIn('scaleX', handler)



if __name__ == '__main__':
    unittest.main()
