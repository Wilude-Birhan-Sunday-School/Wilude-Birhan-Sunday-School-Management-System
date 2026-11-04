"""Synthetic checks: WBSS must not silently read another school's config."""
import os
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')


@unittest.skipUnless(PHP, 'PHP CLI is required')
class WbssEnvSelectionTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.home = Path(self.tmp.name) / 'home'
        self.site = self.home / 'public_html' / 'wbss'
        stub = self.site / 'admin/backend/services/BrowserPageRoutes.php'
        stub.parent.mkdir(parents=True)
        stub.write_text('<?php namespace App\\Services; class BrowserPageRoutes {}')
        source = (ROOT / 'config.php').read_text()
        cut = source.index('// SCHOOL BRANDING (loaded from school_config.php)')
        # Execute only the real bootstrap and env-selection section, not a DB.
        (self.site / 'config.php').write_text(source[:cut] + "echo defined('TEST_MARKER') ? TEST_MARKER : 'NONE';\n")
        self.old = self.site.parent / '.fkss_env.php'
        self.legacy = self.site.parent / '.wbws_env.php'
        self.new = self.home / '.wbss_env.php'
        self.old.write_text("<?php define('TEST_MARKER', 'OLD');")
        self.legacy.write_text("<?php define('TEST_MARKER', 'LEGACY');")
        self.new.write_text("<?php define('TEST_MARKER', 'WBSS');")

    def tearDown(self):
        self.tmp.cleanup()

    def run_loader(self, audit=False):
        env = os.environ.copy()
        env.pop('SSMS_AUDIT_TESTING', None)
        if audit:
            env['SSMS_AUDIT_TESTING'] = '1'
        code = "$_SERVER['PHP_SELF']='/index.php'; require " + repr(str(self.site / 'config.php')) + ";"
        return subprocess.run([PHP, '-r', code], env=env, capture_output=True, text=True, check=True).stdout

    def test_wbss_in_home_wins_over_nearer_fkss_file(self):
        self.assertEqual(self.run_loader(), 'WBSS')

    def test_missing_wbss_fails_closed_instead_of_loading_fkss(self):
        self.new.unlink()
        result = self.run_loader()
        self.assertIn('Setup required', result)
        self.assertNotIn('OLD', result)
        self.assertNotIn('LEGACY', result)

    def test_legacy_fixture_only_allowed_in_explicit_audit_mode(self):
        self.new.unlink()
        self.assertEqual(self.run_loader(audit=True), 'OLD')


if __name__ == '__main__':
    unittest.main()
