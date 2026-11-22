"""Regression tests for the ID-card subsystem fix batch.

Production incident: /admin/id_cards/view_id_card.php died with the
generic error page because the ID-card template reads branding
constants unguarded and PHP 8 turns a missing constant (deployment
config drift) into an uncaught Error.

Guards:
  - fail-soft branding defaults are loaded right after school_config,
  - the ID-card template renders with a minimal (drifted) config,
  - QR generation works from the bundled single-file library,
  - ID-card endpoints stay behind login + role map,
  - deployment diagnostics stay unreachable over HTTP.
"""

from pathlib import Path
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]


class IdCardSubsystemHardeningTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.config = (ROOT / "config.php").read_text(encoding="utf-8")
        cls.defaults = (ROOT / "branding_defaults.php").read_text(encoding="utf-8")
        cls.view = (ROOT / "admin/id_cards/view_id_card.php").read_text(encoding="utf-8")
        cls.generate = (ROOT / "admin/id_cards/generate_id_card.php").read_text(encoding="utf-8")
        cls.print_member = (ROOT / "admin/print_member.php").read_text(encoding="utf-8")
        cls.template = (
            ROOT / "admin/id_cards/id_card_template_layout.php"
        ).read_text(encoding="utf-8")
        cls.access = (ROOT / "admin/access_control.php").read_text(encoding="utf-8")
        cls.loader = (ROOT / "admin/id_cards/libs/qr_loader.php").read_text(encoding="utf-8")

    def test_config_loads_defaults_after_school_config(self):
        school_pos = self.config.find("school_config.php")
        defaults_pos = self.config.find("branding_defaults.php")
        self.assertGreater(school_pos, 0)
        self.assertGreater(defaults_pos, school_pos)

    def test_critical_constants_are_guarded(self):
        for constant in [
            "RELIGIOUS_INVOCATION",
            "PARISH_NAME_AM",
            "ID_CARD_TITLE_AM",
            "ID_CARD_TITLE_EN",
            "SCHOOL_NAME_SHORT_AM",
            "SCHOOL_TYPE_AM",
            "MEMBER_CODE_FORMAT",
            "THEME_PRIMARY",
            "SITE_URL",
        ]:
            self.assertIn("if (!defined('" + constant + "'))", self.defaults)
            self.assertIn("define('" + constant + "'", self.defaults)

    def test_template_renders_with_drifted_minimal_config(self):
        php = shutil.which("php")
        if php is None:
            self.skipTest("PHP CLI is not installed")
        harness = (
            "error_reporting(E_ALL); ini_set('display_errors','1');"
            "require '" + str(ROOT / "branding_defaults.php") + "';"
            "$member=['gender'=>'male','member_code'=>'A1','phone_number'=>'1',"
            "'address'=>'X','emergency_name'=>'g','emergency_phone'=>'1',"
            "'student_photo_path'=>'','qr_code_path'=>''];"
            "$CONFIG=['logo'=>'','seal'=>'','sig_head'=>'','sig_admin'=>''];"
            "$full_name='a';$christian_name='b';$age=1;"
            "$issueDateEth='x';$expiryDateEth='x';"
            "$ID_CARD_STYLE='';$ID_CARD_LAYOUT=[];$idCardBg='';"
            "include '" + str(ROOT / "admin/id_cards/id_card_template_layout.php") + "';"
            "echo 'TEMPLATE_OK';"
        )
        completed = subprocess.run([php, "-r", harness], capture_output=True, text=True)
        self.assertEqual(completed.returncode, 0, completed.stderr)
        self.assertIn("TEMPLATE_OK", completed.stdout)
        self.assertNotIn("Fatal error", completed.stderr)

    def test_qr_loader_restores_generation_from_bundled_lib(self):
        php = shutil.which("php")
        if php is None:
            self.skipTest("PHP CLI is not installed")
        # The multi-file entry point may be absent; the single-file bundle
        # must still define QRcode and the error-correction constants.
        harness = (
            "require '" + str(ROOT / "admin/id_cards/libs/qr_loader.php") + "';"
            "echo class_exists('QRcode') ? 'QR_OK' : 'QR_MISSING';"
            "echo defined('QR_ECLEVEL_L') ? '|CONST_OK' : '|CONST_MISSING';"
        )
        completed = subprocess.run([php, "-r", harness], capture_output=True, text=True)
        self.assertEqual(completed.returncode, 0, completed.stderr)
        self.assertIn("QR_OK", completed.stdout)
        self.assertIn("CONST_OK", completed.stdout)

    def test_qr_generation_produces_a_real_png(self):
        php = shutil.which("php")
        if php is None:
            self.skipTest("PHP CLI is not installed")
        gd = subprocess.run(
            [php, "-r", "exit(extension_loaded('gd') ? 0 : 1);"], capture_output=True
        )
        if gd.returncode != 0:
            self.skipTest("gd extension is not installed")
        harness = (
            "require '" + str(ROOT / "admin/id_cards/libs/qr_loader.php") + "';"
            "$tmp=tempnam(sys_get_temp_dir(),'qr'); "
            "QRcode::png('https://example.org/member.php?code=A1',$tmp,QR_ECLEVEL_L,4,2);"
            "$b=file_get_contents($tmp); echo substr($b,1,3)==='PNG'?'PNG_OK':'PNG_BAD';"
        )
        completed = subprocess.run([php, "-r", harness], capture_output=True, text=True)
        self.assertEqual(completed.returncode, 0, completed.stderr)
        self.assertIn("PNG_OK", completed.stdout)

    def test_view_uses_loader_not_missing_qrlib(self):
        self.assertIn("libs/qr_loader.php", self.view)
        self.assertIn("class_exists('QRcode')", self.view)
        self.assertIn("libs/qr_loader.php", self.generate)

    def test_id_card_endpoints_require_login(self):
        self.assertIn("isLoggedIn()", self.view)
        self.assertIn("isLoggedIn()", self.generate)
        self.assertIn("isLoggedIn()", self.print_member)

    def test_role_map_still_covers_id_card_pages(self):
        for page in ["view_id_card.php", "generate_id_card.php", "preview.php",
                     "print_member.php"]:
            self.assertIn("'" + page + "'", self.access)

    def test_deployment_diagnostics_unreachable_over_http(self):
        self.assertIn("'qr_diagnostic.php'", self.access)
        self.assertIn("not available over HTTP", self.access)

    def test_absolute_includes(self):
        # No cwd-dependent relative includes in the viewer.
        self.assertNotIn("require_once '../config.php'", self.view)
        self.assertNotIn("require_once 'libs/eth_date_helper.php'", self.view)

    def test_qr_directory_is_tracked_and_script_hardened(self):
        htaccess = ROOT / "admin/id_cards/assets/qr/.htaccess"
        self.assertTrue(htaccess.is_file(), "admin/id_cards/assets/qr/.htaccess must be tracked")
        ht_text = htaccess.read_text(encoding="utf-8")
        self.assertIn("Options -ExecCGI -Indexes", ht_text)
        self.assertIn("Require all denied", ht_text)
        gitignore = (ROOT / ".gitignore").read_text(encoding="utf-8")
        self.assertIn("admin/id_cards/assets/qr/*", gitignore)
        self.assertIn("!admin/id_cards/assets/qr/.htaccess", gitignore)
        self.assertNotIn("\nadmin/id_cards/assets/qr/\n", gitignore)

    def test_qr_loader_prefers_single_file_and_verifies_class_defined(self):
        single_pos = self.loader.find("phpqrcode/phpqrcode.php")
        multi_pos = self.loader.find("phpqrcode/qrlib.php")
        self.assertGreater(single_pos, 0)
        self.assertGreater(multi_pos, single_pos)
        self.assertIn("if (class_exists('QRcode', false))", self.loader)

    def test_generator_runtime_guards_and_diagnostics(self):
        self.assertIn("extension_loaded('gd')", self.generate)
        self.assertIn("function_exists('imagepng')", self.generate)
        self.assertIn("$pdo instanceof PDO", self.generate)
        self.assertIn("IdentityCodeService::", self.generate)
        self.assertNotIn("MEMBER_CODE_FORMAT", self.generate)
        self.assertNotIn("$_POST['debug']", self.generate)
        self.assertNotIn("$_GET['debug']", self.view)
        self.assertIn("get_class($error)", self.generate)
        self.assertIn("missing directory:", self.generate)
        self.assertIn("directory not writable:", self.generate)

    def test_viewer_and_identity_service_share_canonical_qr_filename(self):
        self.assertIn("'qr_member_' . (int)$member_id . '.png'", self.view)
        self.assertNotIn("qr_' . $member['member_code']", self.view)
        self.assertIn("?v=", self.view)
        identity = (ROOT / "admin/backend/services/IdentityCodeService.php").read_text(encoding="utf-8")
        self.assertIn("libs/qr_loader.php", identity)
        self.assertIn("is_writable($dir)", identity)
        self.assertIn("finally {", identity)
        eth = (ROOT / "admin/id_cards/libs/eth_date_helper.php").read_text(encoding="utf-8")
        self.assertIn("function_exists('cal_to_jd')", eth)
        self.assertIn("intdiv(", eth)

    def test_php_syntax(self):
        php = shutil.which("php")
        if php is None:
            self.skipTest("PHP CLI is not installed")
        for rel in [
            "config.php",
            "branding_defaults.php",
            "admin/id_cards/view_id_card.php",
            "admin/id_cards/generate_id_card.php",
            "admin/id_cards/libs/qr_loader.php",
            "admin/print_member.php",
        ]:
            completed = subprocess.run(
                [php, "-l", str(ROOT / rel)], capture_output=True, text=True
            )
            self.assertEqual(completed.returncode, 0, rel + ": " + completed.stdout)


if __name__ == "__main__":
    unittest.main()
