# Mobile display names

The single source for the Android and iOS app's human-facing names is:

`ios/Flutter/SchoolNames.xcconfig`

Edit all four values in that file, save it as UTF-8, then rebuild and distribute the mobile app. No code-generation or manual synchronization step is required. The existing defaults have deliberately not been renamed; obtain the school's approved English and Amharic wording before changing them.

| Setting | Purpose |
| --- | --- |
| `SCHOOL_APP_NAME` | Short app/launcher name, home headers, updates, audio and diagnostic text |
| `SCHOOL_FULL_NAME_EN` | Full English school name on the sign-in screen and its logo's accessibility label |
| `SCHOOL_SHORT_NAME_EN` | English name used in lock-screen and biometric messages |
| `SCHOOL_NAME_AM` | Amharic name on the sign-in screen |

Keep exactly the four existing keys, one per line, with a nonempty single-line value after `=`. Do not add comments, quotes, Xcode substitutions or escaping. Values must not contain `//`, `/*`, `*/`, `$`, `#`, `=`, backslash, double quote, `<`, `>` or `&`, or control characters. This restricted common syntax prevents the Dart, Android and Xcode readers from interpreting the same value differently. Ordinary spaces, Unicode letters, single apostrophes, hyphens and periods are supported. Android and Flutter reject invalid input; if Flutter cannot load it at startup, the app shows a configuration error instead of opening data. The production target-environment test also rejects beta branding.

For a release, run the Flutter test suite and `python -m unittest tests.security.test_mobile_target_environment` from the repository root, then build and inspect Android and iOS artifacts on their supported toolchains. Check the launcher, login, lock/biometric prompt, update screen and audio notification on devices. Existing notification-channel settings and OS caches may affect when a changed channel label is visible. Builds must include the configuration file as a Flutter asset. Back up offline data and follow the established signing/update procedure before deploying.

## Boundaries

This changes display text, not the school logo, launcher icons or splash images. Replace and regenerate those separately if required. The existing login emblem is an image; the name config cannot change any text embedded in artwork. Web and desktop runner titles/manifests in this Flutter project are not covered by this mobile-only change and retain their existing values.

It intentionally does **not** change the API/domain, server content, Android application ID, iOS bundle ID, signing keys, Flutter package name, native/method-channel identifiers, audio channel ID, QR format, local database, preference/secure-storage keys, telemetry user agent, or update artifact paths. These are compatibility, backend, or persistence contracts, not display names. In particular, this configuration does not point the app to a new school's backend and does not create a separately installable app. Those changes require a separate security, migration and deployment plan.
