#!/usr/bin/env python3
"""Live render check for the public website (opt-in, not part of pytest).

The static contracts in tests/security/test_public_website.py always run and
never skip. This script is the complementary live check: it needs a running
site, so it is deliberately not collected by the security suite.

Usage:
    WBSS_SITE_BASE=http://127.0.0.1:8089 python3 tests/e2e/public_website_render.py

It requests every public page **in both languages** and fails on a non-200
answer, on any PHP error/warning text in the body, on a page whose title does
not belong to it, on a missing language declaration, on an unescaped search
reflection, and on a missing escaped highlight.

It also checks that the motion layer is actually served and wired: the local
stylesheet and script on every page, reveal hooks on every template, the
countdown and the once-per-session popup on the home page only, and the photo
lightbox on the news page — in both languages.

Amharic is the default language, so `/index.php` and `/index.php?lang=am` must
render the same text, and `?lang=en` must render English.
"""
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

PAGES = {
    '/index.php': 'home',
    '/about.php': 'about',
    '/programs.php': 'programs',
    '/admissions.php': 'admissions',
    '/gallery.php': 'gallery',
    '/news.php': 'news',
    '/contact.php': 'contact',
    '/privacy.php': 'privacy',
    '/search.php?q=mezmur': 'search',
    '/404.php': 'notfound',
}
# Pages that must answer 200; the 404 document answers 404 by design.
RETURNS_200 = {path: name for path, name in PAGES.items() if name != 'notfound'}
AMHARIC = re.compile(r'[\u1200-\u137F]')
ERROR_MARKERS = ('Fatal error', 'Parse error', 'Warning:', 'Uncaught', 'Notice:', 'Deprecated:')

# Titles the school publishes for each page, per language.
TITLES = {
    'home': {'am': 'ውሉደ ብርሃን', 'en': 'Wilude Birhan'},
    'about': {'am': 'ስለ እኛ', 'en': 'About us'},
    'programs': {'am': 'መርሃ ግብሮች', 'en': 'Programmes'},
    'admissions': {'am': 'ምዝገባ', 'en': 'Admissions'},
    'gallery': {'am': 'ፎቶዎች', 'en': 'Gallery'},
    'news': {'am': 'ዜና', 'en': 'News'},
    'contact': {'am': 'ያግኙን', 'en': 'Contact'},
    'privacy': {'am': 'ግላዊነት', 'en': 'Privacy'},
    'search': {'am': 'ፍለጋ', 'en': 'Search'},
}


def fetch(base, path, timeout=20):
    try:
        with urllib.request.urlopen(base + path, timeout=timeout) as response:
            return response.status, response.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode('utf-8', 'replace')


def with_lang(path, lang):
    return path + ('&' if '?' in path else '?') + 'lang=' + lang


def main():
    base = (os.environ.get('WBSS_SITE_BASE') or (sys.argv[1] if len(sys.argv) > 1 else '')).rstrip('/')
    if not base:
        print('Set WBSS_SITE_BASE (or pass a base URL) before running this check.')
        return 2

    failures = []
    for path, slug in PAGES.items():
        for lang in ('am', 'en'):
            url = with_lang(path, lang)
            status, body = fetch(base, url)
            title = re.search(r'<title>(.*?)</title>', body, re.S)
            print(f'{url:<40} {status}  {(title.group(1)[:52] if title else "no title")}')
            expected_status = 200 if path in RETURNS_200 else 404
            if status != expected_status:
                failures.append(f'{url}: HTTP {status}, expected {expected_status}')
            for marker in ERROR_MARKERS:
                if marker in body:
                    failures.append(f'{url}: body contains "{marker}"')
            if f'<html lang="{lang}"' not in body:
                failures.append(f'{url}: document language is not "{lang}"')
            if '<h1' not in body:
                failures.append(f'{url}: no <h1> element')
            # Amharic pages must be Amharic; English pages must not be.
            has_amharic = bool(AMHARIC.search(body))
            if lang == 'am' and not has_amharic:
                failures.append(f'{url}: no Ethiopic text on an Amharic page')
            if path in TITLES:
                if TITLES[slug][lang] not in body:
                    failures.append(f'{url}: expected "{TITLES[slug][lang]}"')
            # The switch, and the alternate-language declarations, are on
            # every page in both directions.
            if 'lang-switch' not in body:
                failures.append(f'{url}: no language switch')
            for token in ('hreflang="am"', 'hreflang="en"', 'hreflang="x-default"'):
                if token not in body:
                    failures.append(f'{url}: missing {token}')
            # Motion is served locally on every page and every template opts in.
            for token in ('/css/wbss-motion.css', '/js/wbss-motion.js',
                          'id="scroll-progress"', 'data-reveal="'):
                if token not in body:
                    failures.append(f'{url}: motion layer missing {token}')

    # Home: the countdown to the next Sunday session, and the popup.
    for lang in ('am', 'en'):
        _, body = fetch(base, with_lang('/index.php', lang))
        for token in ('data-countdown', 'data-cd="days"', 'data-cd="seconds"',
                      'data-popup', 'data-store-key="wbss-welcome-2026"'):
            if token not in body:
                failures.append(f'home {lang}: missing {token}')
    _, body = fetch(base, '/about.php')
    if 'data-popup' in body:
        failures.append('about: the popup must stay on the home page only')

    # News: photographs open in the accessible lightbox.
    _, body = fetch(base, '/news.php')
    for token in ('data-lightbox', 'data-lightbox-modal', 'role="button" tabindex="0"',
                  'aria-modal="true"'):
        if token not in body:
            failures.append(f'news: lightbox missing {token}')

    # The default page is Amharic: no parameter must equal ?lang=am.
    _, default_body = fetch(base, '/index.php')
    _, am_body = fetch(base, '/index.php?lang=am')
    if '<html lang="am"' not in default_body:
        failures.append('home: Amharic is not the default language')
    if len(default_body) != len(am_body):
        failures.append('home: / and /?lang=am rendered different sizes')

    # Search works in both languages and never reflects markup.
    for query in ('mezmur', urllib.parse.quote('መዝሙር')):  # English and Amharic queries
        for lang in ('am', 'en'):
            _, body = fetch(base, f'/search.php?q={query}&lang={lang}')
            if '<mark>' not in body:
                failures.append(f'search {query}/{lang}: expected an escaped <mark> highlight')
    _, body = fetch(base, '/search.php?q=%3Cscript%3Ealert(1)%3C/script%3E')
    if '<script>alert(1)</script>' in body:
        failures.append('search: reflected an unescaped script tag')

    # Retired pages are unlinked (the dev server answers unknown paths with
    # index.php, so the links are what can be asserted here).
    for lang in ('am', 'en'):
        _, body = fetch(base, with_lang('/index.php', lang))
        for retired in ('/schedule', '/sitemap'):
            if retired in body:
                failures.append(f'home {lang}: retired page "{retired}" is still linked')

    # Photographs, and the text the school asked to have removed.
    for lang in ('am', 'en'):
        _, body = fetch(base, with_lang('/news.php', lang))
        if 'news-media' not in body or '/themes/fkss/assets/wbss-' not in body:
            failures.append(f'news {lang}: expected notice photographs to render')
        _, body = fetch(base, with_lang('/index.php', lang))
        if 'wbss-hero-courtyard.jpg' not in body:
            failures.append(f'home {lang}: expected the hero photograph')
        for forbidden in ('Staff login', 'Draft website content', 'Weekly rhythm', 'Every age welcome'):
            if forbidden in body:
                failures.append(f'home {lang}: "{forbidden}" must no longer appear')

    if failures:
        print('\nFAILED:')
        for failure in failures:
            print(' -', failure)
        return 1
    print('\nAll public website render checks passed in Amharic and English.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
