"""Guard website/mobile identity alignment without changing data or deployment IDs."""
import base64
import hashlib
import hmac
import json
import shutil
import subprocess
import time
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MOBILE = ROOT / 'Mobile/wbws_flutter_app'


def mobile_names():
    result = {}
    for line in (MOBILE / 'ios/Flutter/SchoolNames.xcconfig').read_text(encoding='utf-8').splitlines():
        key, value = line.split('=', 1)
        result[key.strip()] = value.strip()
    return result


class BrandIdentityAlignmentTests(unittest.TestCase):
    def test_mobile_and_static_metadata_match(self):
        names = mobile_names()
        self.assertEqual(set(names), {'SCHOOL_APP_NAME', 'SCHOOL_FULL_NAME_EN',
                                     'SCHOOL_SHORT_NAME_EN', 'SCHOOL_NAME_AM',
                                     'SCHOOL_FULL_NAME_AM'})
        self.assertTrue(names['SCHOOL_FULL_NAME_AM'].endswith(names['SCHOOL_NAME_AM']))
        web = json.loads((MOBILE / 'web/manifest.json').read_text(encoding='utf-8'))
        retired = json.loads((ROOT / 'app/manifest.json').read_text(encoding='utf-8'))
        for manifest in (web, retired):
            self.assertEqual(manifest['short_name'], names['SCHOOL_APP_NAME'])
            self.assertEqual(manifest['name'], names['SCHOOL_FULL_NAME_EN'])

    @unittest.skipUnless(shutil.which('php'), 'php-cli is required')
    def test_php_web_and_mobile_names_and_detector_keywords_align(self):
        code = r'''require 'school_config.php'; echo json_encode([
            'short' => SCHOOL_NAME_SHORT, 'english' => SCHOOL_NAME,
            'school_am' => SCHOOL_NAME_AMHARIC, 'full_am' => SCHOOL_NAME_FULL_AM,
            'parish_am' => PARISH_NAME_AM, 'keywords' => unserialize(LEAK_DETECT_KEYWORDS)
        ], JSON_UNESCAPED_UNICODE);'''
        result = subprocess.run(['php', '-r', code], cwd=ROOT, text=True,
                                capture_output=True, check=True)
        web, mobile = json.loads(result.stdout), mobile_names()
        self.assertEqual(web['short'], mobile['SCHOOL_APP_NAME'])
        self.assertEqual(web['english'], mobile['SCHOOL_FULL_NAME_EN'])
        self.assertEqual(web['school_am'], mobile['SCHOOL_NAME_AM'])
        self.assertEqual(web['full_am'], mobile['SCHOOL_FULL_NAME_AM'])
        self.assertEqual(web['full_am'], web['parish_am'] + ' ' + web['school_am'])
        for keyword in web['keywords']:
            self.assertNotIn(keyword.casefold(), web['full_am'].casefold())
            self.assertNotIn(keyword.casefold(), web['english'].casefold())

    @unittest.skipUnless(shutil.which('php'), 'php-cli is required')
    def test_operational_keys_and_legacy_token_fallback_are_unchanged(self):
        code = r'''define('DB_NAME', 'fixture'); define('DB_PASS', 'fixture');
            require 'school_config.php'; require 'api/v1/core/auth.php';
            echo json_encode(['export' => EXPORT_PREFIX, 'cache' => PWA_CACHE_NAME,
                'cache_prefix' => PWA_CACHE_PREFIX, 'monitor' => MONITOR_PROJECT_NAME,
                'state' => verifyTokenState($argv[1])['state']]);'''
        now = int(time.time())
        body = base64.b64encode(json.dumps({'uid': 1, 'iat': now, 'exp': now + 300,
                                            'typ': 'access'}).encode()).decode()
        key = ('fkss_api_v1_fixture_' + hashlib.md5(b'fixture').hexdigest()).encode()
        token = body + '.' + hmac.new(key, body.encode(), hashlib.sha256).hexdigest()
        result = subprocess.run(['php', '-r', code, token], cwd=ROOT, text=True,
                                capture_output=True, check=True)
        self.assertEqual(json.loads(result.stdout), {'export': 'fkss', 'cache': 'fkss-v5',
                         'cache_prefix': 'fkss_', 'monitor': 'FKSS Sunday School',
                         'state': 'valid'})

    @unittest.skipUnless(shutil.which('php'), 'php-cli is required')
    def test_retired_web_app_displays_configured_short_name(self):
        result = subprocess.run(['php', 'app/index.php'], cwd=ROOT, text=True,
                                capture_output=True, check=True)
        self.assertIn(mobile_names()['SCHOOL_APP_NAME'], result.stdout)
        self.assertNotIn('FKSS', result.stdout)
        self.assertNotIn('WBWS', result.stdout)


if __name__ == '__main__':
    unittest.main()
