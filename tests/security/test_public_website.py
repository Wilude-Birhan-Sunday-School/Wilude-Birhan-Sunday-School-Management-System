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
    '/schedule': 'schedule.php',
    '/admissions': 'admissions.php',
    '/gallery': 'gallery.php',
    '/news': 'news.php',
    '/contact': 'contact.php',
    '/privacy': 'privacy.php',
    '/search': 'search.php',
}

# Shared, non-page includes of the public site.
SHARED = ['public_site.php', 'site_content.php']


def read(name):
    return (ROOT / name).read_text(encoding='utf-8')


class PublicWebsiteStructureTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.index = read('index.php')
        cls.bootstrap = read('public_site.php')
        cls.content = read('site_content.php')
        cls.pages = {name: read(name) for name in PUBLIC_PAGES.values()}
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
        self.assertIn('RewriteRule ^sitemap\\.xml$ sitemap.php [L]', self.htaccess)
        dispatch = [line for line in self.htaccess.splitlines() if 'browser_page.php [END]' in line]
        self.assertEqual(len(dispatch), 1)
        for slug in PUBLIC_PAGES:
            if slug == '/':
                continue
            self.assertIn(slug.lstrip('/'), dispatch[0])
        # Still finite: no generic rewrite may append .php to user input.
        self.assertNotIn('$1.php', self.htaccess)

    def test_sitemap_and_robots_agree_with_the_published_pages(self):
        sitemap = read('sitemap.php')
        self.assertIn("$content['pages']", sitemap)
        self.assertIn("application/xml", sitemap)
        robots = read('robots.txt')
        self.assertIn('Sitemap: https://', robots)
        self.assertIn('Disallow: /search', robots)
        for folder in ('/admin/', '/api/', '/monitor/'):
            self.assertIn(f'Disallow: {folder}', robots)

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
        for filename in list(PUBLIC_PAGES.values()) + SHARED + ['css/wbss-site.css', 'js/wbss-site.js']:
            for match in asset_rx.finditer(read(filename)):
                host = match.group(2).split('/')[0].lower()
                if host not in allowed_hosts:
                    offenders.append(f'{filename}: {match.group(0)[:70]}')
        self.assertEqual(offenders, [], 'Public pages must not depend on third-party CDNs')
        self.assertNotIn('cdn.tailwindcss.com', ''.join(self.pages.values()))
        self.assertNotIn('fontawesome', ''.join(self.pages.values()).lower())

    def test_shared_layout_has_accessibility_and_seo_hooks(self):
        for token in ('skip-link', 'aria-label="Primary"', 'aria-expanded="false"',
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
        self.assertIn("jsonResponse(['status'=>'error','message'=>'Service temporarily unavailable", endpoint)
        for filename in PUBLIC_PAGES.values():
            source = read(filename)
            for match in re.finditer(r'action="([^"]+)"', source):
                self.assertNotIn('register_submit.php', match.group(1),
                                 f'{filename} must let the shared form own the endpoint')

    def test_child_name_is_folded_into_the_message_not_a_new_field(self):
        # The endpoint has no child_name column; the browser merges it into the
        # free-text message instead of inventing an unvalidated parameter.
        self.assertIn('name="child_name"', self.bootstrap)
        self.assertIn("data.set('message'", read('js/wbss-site.js'))
        self.assertNotIn('child_name', read('register_submit.php'))

    # 4 — honest content ------------------------------------------------
    def test_unverified_school_content_stays_flagged_as_draft(self):
        self.assertIn("define('WBSS_SITE_CONTENT_DRAFT', true)", self.content)
        self.assertIn("'draft_notice'", self.content)
        self.assertIn('draft-bar', self.bootstrap)
        # Contact details reused from the previous site stay marked, so nobody
        # can publish a placeholder phone number or email as if it were real.
        for key in ("'phone_primary'", "'email_primary'"):
            with self.subTest(key=key):
                block = self.content.split(key, 1)[1][:220]
                self.assertIn("'draft' => true", block)

    def test_no_individual_pupil_information_is_published(self):
        joined = ''.join(self.pages.values())
        self.assertNotIn('member_code', joined)
        self.assertNotIn('report_card', joined)
        privacy_copy = read('site_content.php')
        self.assertIn('Individual results are not published', privacy_copy)
        self.assertIn('no member personal information', privacy_copy)

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


if __name__ == '__main__':
    unittest.main()
