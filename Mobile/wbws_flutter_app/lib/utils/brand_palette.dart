import 'package:flutter/material.dart';

/// Single configuration for the mobile application's brand colors.
/// Use 24-bit RGB hex values, e.g. 0x004820. Rebuild after editing.
abstract final class BrandPalette {
  // Change these two values to update the principal brand and its gradients.
  static const int primaryRgb = 0x004820;
  static const int secondaryRgb = 0xF0B818;

  static const Color primary = Color(0xFF000000 | primaryRgb);
  static const Color secondary = Color(0xFF000000 | secondaryRgb);

  // Darker gold for text and icons on light surfaces; bright logo gold is
  // reserved for dark backgrounds and filled accents where it is readable.
  static const Color secondaryInk = Color(0xFF000000 |
      (((secondaryRgb >> 16 & 0xFF) * 55 ~/ 100) << 16) |
      (((secondaryRgb >> 8 & 0xFF) * 55 ~/ 100) << 8) |
      ((secondaryRgb & 0xFF) * 55 ~/ 100));

  // Derive shades at compile time so const widgets respond to palette changes.
  static const int _r = (primaryRgb >> 16) & 0xFF;
  static const int _g = (primaryRgb >> 8) & 0xFF;
  static const int _b = primaryRgb & 0xFF;
  static const int _lightR = _r * 23 ~/ 18 + 7;
  static const int _lightG = _g * 23 ~/ 18 + 7;
  static const int _lightB = _b * 23 ~/ 18 + 7;
  static const Color primaryLight = Color(0xFF000000 |
      ((_lightR > 255 ? 255 : _lightR) << 16) |
      ((_lightG > 255 ? 255 : _lightG) << 8) |
      (_lightB > 255 ? 255 : _lightB));
  static const Color primaryDark = Color(0xFF000000 |
      ((_r ~/ 2 + 1) << 16) |
      ((_g ~/ 2 + 1) << 8) |
      (_b ~/ 2 + 1));
  static const Color primaryScrollbar = Color(0x59000000 | primaryRgb);
  static const Color primaryAvatar = Color(0x26000000 | primaryRgb);

  // Shared role-home/timetable banner combination. Adjust stops or direction
  // here if the visual design requires a different combination.
  static const LinearGradient headerGradient = LinearGradient(
    colors: [primaryDark, primary, primaryLight],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  // Automatic and selectable Mezmur cover pairs. A stored admin selection or
  // server artwork color takes precedence and is never rewritten by branding.
  static const List<List<Color>> coverPalettes = [
    [primary, secondary],
    [Color(0xFF4F46E5), Color(0xFF7C3AED)],
    [Color(0xFF0EA5E9), Color(0xFF2563EB)],
    [Color(0xFF059669), Color(0xFF0D9488)],
    [Color(0xFFD97706), Color(0xFFDC2626)],
    [Color(0xFFDB2777), Color(0xFF9333EA)],
  ];

  // Mezmur parchment colors are separately configurable because they were
  // calibrated for contrast against the fixed painted scroll artwork.
  static const Color parchmentInk = Color(0xFF3B2417);
  static const Color parchmentInkStrong = Color(0xFF2A150A);
  static const Color parchmentInkFaint = Color(0xFF7A4E12);
  static const Color parchmentBronze = Color(0xFF8A5A1B);
  static const Color parchmentBronzeSoft = Color(0xFFA9783A);
  static const Color parchmentGold = secondary;
  static const Color parchmentHoney = Color(0xFFD8C078);
  static const Color parchmentCream = Color(0xFFF1DFAE);
  static const Color parchmentCreamBright = Color(0xFFF8EBCB);
  static const Color parchmentLeather = Color(0xB83E230C);
  static const Color parchmentLeatherSoft = Color(0x993E230C);
  static const Color parchmentSurface = Color(0xFFF3E4C4);
  static const Color parchmentToolbar = Color(0xFFEAD8B2);
  static const Color parchmentButtonInk = Color(0xFF4A2C0C);
  static const Color parchmentNavigationBar = Color(0xE62A150A);
  static const Color parchmentPanelOverlay = Color(0x99F6E7C8);
  static const Color parchmentControlSurface = Color(0xF5F8EBCB);
  static const Color parchmentControlInk = Color(0xFF1A0C06);
  static const Color parchmentAccentShadow = Color(0xFF5A3A12);
}
