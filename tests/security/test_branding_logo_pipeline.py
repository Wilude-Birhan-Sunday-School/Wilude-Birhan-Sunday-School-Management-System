"""Regression checks for Super Admin branding & school logo upload/resolution.

Locks in the end-to-end fixes so none of these failure modes can return:
1. Dedicated untracked upload directory admin/uploads/branding/ with .htaccess
   so custom uploads never overwrite git-tracked packaged logos or block git pull.
2. PHP 8.5 compatibility (no deprecated finfo_close()) and post_max_size
   empty-POST handling before CSRF validation.
3. Atomic rollback swap & image validation invariants in admin/api_branding.php.
4. Built-in slot auto-upsert and theme-aware fallback (no hardcoded /themes/fkss/).
5. Single source of truth BrandingAssetService (read-only, zero DDL, repo-root
   anchored, cache-busting ?v=mtime) wired into config.php and all site-wide
   logo consumers.
6. Super Admin UI strictly matches server allowlist (no SVG in file picker) and
   refreshes the ID Card Designer preview on logo/seal/signature/background
   uploads and removals.
"""
from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[2]


class BrandingLogoPipelineTests(unittest.TestCase):
    def test_upload_storage_is_tracked_hardened_and_gitignored(self):
        htaccess = ROOT / "admin/uploads/branding/.htaccess"
        self.assertTrue(htaccess.is_file(), "admin/uploads/branding/.htaccess must exist")
        rules = htaccess.read_text(encoding="utf-8")
        self.assertIn("Options -ExecCGI -Indexes", rules)
        self.assertIn("Require all denied", rules)
        self.assertIn("svg", rules.lower())

        gitignore = (ROOT / ".gitignore").read_text(encoding="utf-8")
        self.assertIn("admin/uploads/branding/*", gitignore)
        self.assertIn("!admin/uploads/branding/.htaccess", gitignore)

    def test_api_branding_upload_hardening_and_php85_safety(self):
        src = (ROOT / "admin/api_branding.php").read_text(encoding="utf-8")
        # PHP 8.5 deprecated finfo_close() — must never be called
        self.assertNotIn("finfo_close(", src)
        # Must not hardcode fkss theme path (breaks WBSS and other themes)
        self.assertNotIn("/themes/fkss/assets/logos/school_logo.png", src)
        # Must store uploads in dedicated untracked directory
        self.assertIn("/uploads/branding", src)
        self.assertIn("/admin/uploads/branding", src)
        # Must protect packaged defaults from deletion/overwrite
        self.assertIn("BrandingAssetService::isPackagedPath(", src)
        # Must detect post_max_size empty-POST overflow before CSRF
        self.assertIn("CONTENT_LENGTH", src)
        self.assertIn("post_max_size", src)
        # Must auto-upsert built-in keys if system_branding is partially seeded
        self.assertIn("INSERT IGNORE INTO system_branding", src)
        # Security invariants pinned alongside test_private_member_files.py
        for needle in (
            "is_uploaded_file(",
            "filesize(",
            "getimagesize($tmpPath)",
            "begin_transaction()",
            ".rollback-",
        ):
            self.assertIn(needle, src)
        # Must return both file_exists (legacy UI) and web_url with cache-buster
        self.assertIn("$row['file_exists'] = true;", src)
        self.assertIn("'?v=' . $mtime", src)
        # CSRF helper MUST be config.php's validateCsrf(), never undefined verifyCsrfToken()
        self.assertIn("validateCsrf(", src)
        self.assertNotIn("verifyCsrfToken(", src)
        # Role gate must match admin/access_control.php (super_admin + school_admin)
        self.assertIn("'super_admin'", src)
        self.assertIn("'school_admin'", src)

    def test_api_branding_sql_matches_system_branding_schema(self):
        schema = (ROOT / "sql/012_runtime_schema_baseline.sql").read_text(encoding="utf-8")
        # Confirm baseline schema columns
        self.assertIn("`uploaded_by`", schema)
        self.assertIn("`uploaded_at`", schema)

        src = (ROOT / "admin/api_branding.php").read_text(encoding="utf-8")
        # Regression guard: system_branding has uploaded_by / uploaded_at,
        # NOT updated_by or description. Referencing either causes SQL failure
        # and rolls back every logo upload / layout save.
        self.assertNotIn("updated_by", src)
        self.assertNotIn("description", src)
        self.assertIn("uploaded_by", src)
        self.assertIn("uploaded_at", src)
        self.assertIn("bind_param('sssiis'", src)

    def test_branding_asset_service_contract_and_no_ddl(self):
        service = ROOT / "admin/backend/services/BrandingAssetService.php"
        self.assertTrue(service.is_file())
        src = service.read_text(encoding="utf-8")
        self.assertIn("dirname(__DIR__, 3)", src)
        self.assertIn("'?v=' . $mtime", src)
        self.assertIn("THEME_BASE_PATH", src)
        for forbidden in ("CREATE TABLE", "ALTER TABLE", "SHOW TABLES", "SHOW COLUMNS"):
            self.assertNotIn(forbidden, src.upper())

    def test_id_card_layout_background_uses_branding_service(self):
        src = (ROOT / "admin/backend/services/IdCardLayout.php").read_text(encoding="utf-8")
        self.assertIn("BrandingAssetService::assetUrl($conn, 'card_bg'", src)
        # Regression check: old dirname(__DIR__, 2) . $path resolved to /admin/admin/...
        self.assertNotIn("dirname(__DIR__, 2) . $path", src)

    def test_config_exposes_central_logo_and_seal_helpers(self):
        src = (ROOT / "config.php").read_text(encoding="utf-8")
        self.assertIn(
            "require_once __DIR__ . '/admin/backend/services/BrandingAssetService.php';",
            src,
        )
        self.assertIn("function ssms_school_logo_url(): string", src)
        self.assertIn("function ssms_school_seal_url(", src)

    def test_site_wide_logo_consumers_use_dynamic_branding_helper(self):
        consumers = [
            "public_site.php",
            "admin/index.php",
            "member.php",
            "admin/dashboards/content_editor.php",
            "frontend/pages/login.php",
            "frontend/layouts/base.php",
            "backend/api/init.php",
            "admin/backend/services/ReportCardService.php",
        ]
        for rel in consumers:
            src = (ROOT / rel).read_text(encoding="utf-8")
            self.assertIn(
                "ssms_school_logo_url",
                src,
                f"{rel} must resolve the uploaded school logo via ssms_school_logo_url()",
            )

        for rel in ("admin/id_cards/preview.php", "admin/id_cards/view_id_card.php"):
            src = (ROOT / rel).read_text(encoding="utf-8")
            self.assertIn("BrandingAssetService::assetUrl(", src, rel)

    def test_super_admin_branding_ui_matches_server_and_reloads_preview(self):
        src = (ROOT / "admin/dashboards/super-admin.php").read_text(encoding="utf-8")
        # File input & client validator must not accept SVG (server rejects SVG for XSS safety)
        self.assertNotIn("image/svg+xml\" style=\"display:none\" onchange=\"brandUpload", src)
        self.assertNotIn("svg\\+xml", src)
        # Must refresh ID Card Designer preview after uploading/removing any branding asset
        self.assertIn("window.reloadIdDesignerPreview(key==='card_bg'", src)
        self.assertIn("URL.revokeObjectURL(localUrl)", src)
        self.assertIn("await loadBranding(true, false)", src)
        self.assertIn("await loadBranding(true, true)", src)

    def test_id_card_template_guards_empty_seal(self):
        src = (ROOT / "admin/id_cards/id_card_template_layout.php").read_text(encoding="utf-8")
        self.assertIn("if (!empty($CONFIG['seal'])):", src)


if __name__ == "__main__":
    unittest.main()
