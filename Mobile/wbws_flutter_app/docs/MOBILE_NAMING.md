# Mobile display names

The single source for the Android and iOS app's human-facing names is:

`ios/Flutter/SchoolNames.xcconfig`

Edit the five values in that file, save it as UTF-8, then rebuild the mobile app. No code-generation or manual synchronization step is required. The website uses its separate `school_config.php`; update both configurations together and verify they agree. Do not distribute until the intended backend/domain is configured and verified.

| Setting | Purpose |
| --- | --- |
| `SCHOOL_APP_NAME` | Short app/launcher name, home headers, updates, audio and diagnostic text |
| `SCHOOL_FULL_NAME_EN` | Full English school name on the sign-in screen and its logo's accessibility label |
| `SCHOOL_SHORT_NAME_EN` | English name used in lock-screen and biometric messages |
| `SCHOOL_NAME_AM` | Amharic school-only name |
| `SCHOOL_FULL_NAME_AM` | Full church and school name on the sign-in screen |

Keep exactly the five existing keys, one per line, with a nonempty single-line value after `=`. Do not add comments, quotes, Xcode substitutions or escaping. Values must not contain `//`, `/*`, `*/`, `$`, `#`, `=`, backslash, double quote, `<`, `>` or `&`, or control characters. This restricted common syntax prevents the Dart, Android and Xcode readers from interpreting the same value differently. Ordinary spaces, Unicode letters, single apostrophes, hyphens and periods are supported. Android and Flutter reject invalid input; if Flutter cannot load it at startup, the app shows a configuration error instead of opening data. The production target-environment test also rejects beta branding.

For a release, run the Flutter test suite and `python -m unittest tests.security.test_mobile_target_environment` from the repository root, then build and inspect Android and iOS artifacts on their supported toolchains. Check the launcher, login, lock/biometric prompt, update screen and audio notification on devices. Existing notification-channel settings and OS caches may affect when a changed channel label is visible. Builds must include the configuration file as a Flutter asset. Back up offline data and follow the established signing/update procedure before deploying.

## Boundaries

The name configuration changes text, not artwork. The owner-supplied logo and regenerated launcher/splash assets are documented in `MOBILE_LOGO.md`; name changes alone cannot alter text embedded in those images. The Flutter web metadata and retired website PWA metadata are static and must be updated separately if names change. Desktop runner product identifiers and labels are not covered by this update.

It intentionally does **not** change the API/domain, server content, Android application ID, iOS bundle ID, signing keys, Flutter package name, native/method-channel identifiers, audio channel ID, QR format, local database, preference/secure-storage keys, telemetry user agent, or update artifact paths. These are compatibility, backend, or persistence contracts, not display names. In particular, this configuration does not point the app to a new school's backend and does not create a separately installable app. Those changes require a separate security, migration and deployment plan.
