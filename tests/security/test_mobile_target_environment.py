"""
Mobile target-environment guard.

This school’s `main` branch must build the WBSS production-target app:
wbss.felegekidusan.com API and package com.felegekidusan.wbss.
Display names are configurable, but must not use beta branding.
The FKSS Beta build lives ONLY on the `dev` branch of suraman21/SSMS
(owner rule 2026-10-20: beta settings must never sync to the production
repo). If a dev-branch commit is ever merged into main, this test fails
before any APK can be built.
"""
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
M = ROOT / "Mobile/wbws_flutter_app"

PROD_API = "https://wbss.felegekidusan.com/api/v1"
PROD_PACKAGE = "com.felegekidusan.wbss"


def read(rel):
    return (M / rel).read_text(encoding="utf-8")


class MobileTargetEnvironmentGuard(unittest.TestCase):
    def test_production_api_and_identity(self):
        config = read("lib/utils/config.dart")
        self.assertIn(f"static const String apiBaseUrl = '{PROD_API}';", config)
        self.assertIn("static String get appName => SchoolBrand.appName;", config)
        names = read("ios/Flutter/SchoolNames.xcconfig")
        self.assertIn("SCHOOL_APP_NAME = ", names)
        self.assertNotIn("beta", names.lower())
        self.assertNotIn("arkeonethiopia.com/api", config)
        self.assertNotIn("isBeta", config)
        gradle = read("android/app/build.gradle")
        self.assertIn(f'applicationId = "{PROD_PACKAGE}"', gradle)
        self.assertNotIn(".wbss.beta", gradle)
        self.assertIn('PRODUCT_BUNDLE_IDENTIFIER = com.felegekidusan.wbss;', read("ios/Runner.xcodeproj/project.pbxproj"))
        manifest = read("android/app/src/main/AndroidManifest.xml")
        self.assertIn('android:label="${schoolAppName}"', manifest)
        self.assertIn('manifestPlaceholders.schoolAppName = schoolNames["SCHOOL_APP_NAME"]', gradle)
        self.assertIn('../ios/Flutter/SchoolNames.xcconfig', gradle)
        self.assertNotIn("Beta", manifest)
        self.assertNotIn("isBeta", read("lib/screens/auth/login_screen.dart"))


if __name__ == "__main__":
    unittest.main()
