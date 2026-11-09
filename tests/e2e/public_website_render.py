#!/usr/bin/env python3
"""Live render check for the public website (opt-in, not part of pytest).

The static contracts in tests/security/test_public_website.py always run and
never skip. This script is the complementary live check: it needs a running
site, so it is deliberately not collected by the security suite.

Usage:
    WBSS_SITE_BASE=http://127.0.0.1:8089 python3 tests/e2e/public_website_render.py

It requests every public page and fails on a non-200 answer, on any PHP
error/warning text in the body, on a page whose title does not belong to it,
on an unescaped search reflection, and on a missing escaped highlight.
"""
import os
import re
import sys
import urllib.error
import urllib.request

PAGES = {
    '/index.php': 'Wilude Birhan Sunday School',
    '/about.php': 'About us',
    '/programs.php': 'Programs',
    '/schedule.php': 'Schedule',
    '/admissions.php': 'Admissions',
    '/gallery.php': 'Gallery',
    '/news.php': 'News and notices',
    '/contact.php': 'Contact',
    '/privacy.php': 'Privacy',
    '/search.php?q=mezmur': 'Search',
}
ERROR_MARKERS = ('Fatal error', 'Parse error', 'Warning:', 'Uncaught', 'Notice:')


def fetch(base, path, timeout=20):
    try:
        with urllib.request.urlopen(base + path, timeout=timeout) as response:
            return response.status, response.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode('utf-8', 'replace')


def main():
    base = (os.environ.get('WBSS_SITE_BASE') or (sys.argv[1] if len(sys.argv) > 1 else '')).rstrip('/')
    if not base:
        print('Set WBSS_SITE_BASE (or pass a base URL) before running this check.')
        return 2

    failures = []
    for path, expected in PAGES.items():
        status, body = fetch(base, path)
        title = re.search(r'<title>(.*?)</title>', body, re.S)
        print(f'{path:<34} {status}  {(title.group(1)[:60] if title else "no title")}')
        if status != 200:
            failures.append(f'{path}: HTTP {status}')
        for marker in ERROR_MARKERS:
            if marker in body:
                failures.append(f'{path}: body contains "{marker}"')
        if expected not in body:
            failures.append(f'{path}: expected text "{expected}" not found')
        if '<h1' not in body:
            failures.append(f'{path}: no <h1> element')

    status, body = fetch(base, '/search.php?q=mezmur')
    if '<mark>' not in body:
        failures.append('search: expected an escaped <mark> highlight')

    status, body = fetch(base, '/search.php?q=%3Cscript%3Ealert(1)%3C/script%3E')
    if '<script>alert(1)</script>' in body:
        failures.append('search: reflected an unescaped script tag')

    status, body = fetch(base, '/sitemap.php')
    if status != 200 or '<urlset' not in body:
        failures.append('sitemap: expected XML with a urlset element')

    # An unknown path is not asserted here: PHP's built-in development server
    # answers unmatched paths with the index page, so the 404 document and the
    # finite rewrite can only be checked on the configured web server.

    if failures:
        print('\nFAILED:')
        for failure in failures:
            print(' -', failure)
        return 1
    print('\nAll public website render checks passed.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
