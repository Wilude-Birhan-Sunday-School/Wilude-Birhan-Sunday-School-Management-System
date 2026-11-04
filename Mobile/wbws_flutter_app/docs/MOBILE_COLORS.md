# Mobile brand colors

The single editable mobile branding palette is **`lib/utils/brand_palette.dart`**. Change `primaryRgb` and `secondaryRgb` at the top (24-bit RGB hexadecimal, no alpha), then rebuild the app. Defaults are maroon `0x5A1212` and gold `0xD4AF37`. The existing theme, role-header gradient, member-list tints, default branded cover pair, picker and Mezmur gold accent use those values. No color-generation step is required.

`primaryLight` and `primaryDark` are computed at compile time from the primary color; the current defaults reproduce the previous shades exactly. `headerGradient` controls the shared header colors, order and direction. `coverPalettes` controls the automatic Mezmur cover pairs and picker presets, including five other decorative combinations. You can edit them in this same file. The specialized parchment tokens are also here; they are calibrated to the painted scroll artwork rather than automatically recolored from the main brand. The gold token defaults to the secondary color and may be set separately if the new color loses contrast against the artwork.

**Before releasing a new palette:** check text/icon contrast and selected/disabled states on real devices (automated tests also check white header text, accent-button and back-control contrast), including white text on every header gradient stop, text on buttons, and parchment controls. Arbitrary colors are not guaranteed to be accessible. Test the app's roles, login, timetable, member lists, cover picker and audio/lyrics screens. Run Flutter tests and the mobile timetable guard. The app is light-theme only.

## Scope and compatibility

- Functional error, warning, success, info and neutral colors intentionally remain separate; don't blindly replace them with brand colors.
- Category colors selected by an administrator and colors extracted from artwork remain authoritative and are not overwritten. Changing this config changes only unpinned automatic colors and available presets. New preset selections use a six-digit `#RRGGBB` value; existing saved colors are not migrated or rewritten.
- The mobile automatic cover hash matches the web manager, and the **default** pairs match it. If you change a cover pair, the unchanged web application may show a different automatic cover; update its palette separately if cross-platform visual parity is required.
- Logos, launcher icons, splash artwork and native launch resources are not dynamically recolored. Their embedded pixels or native resource files and generators must be updated separately if desired. Server-provided artwork and web/desktop themes are outside this mobile palette.
- This color change does not modify authentication, permissions, API endpoints, databases, app IDs, signing or offline data.
