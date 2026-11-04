import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:fkss_app/utils/brand_palette.dart';
import 'package:fkss_app/utils/cover_palette.dart';
import 'package:fkss_app/utils/theme.dart';
import 'package:fkss_app/screens/mezmur/parchment_style.dart';

void main() {
  test('configured brand propagates to the application theme and headers', () {
    expect(BrandPalette.primaryRgb, inInclusiveRange(0, 0xFFFFFF));
    expect(BrandPalette.secondaryRgb, inInclusiveRange(0, 0xFFFFFF));
    expect(AppTheme.primary, BrandPalette.primary);
    expect(AppTheme.accent, BrandPalette.secondary);
    expect(AppTheme.accentOnLight, BrandPalette.secondaryInk);
    expect(AppTheme.lightTheme.colorScheme.primary, BrandPalette.primary);
    expect(AppTheme.lightTheme.colorScheme.secondary, BrandPalette.secondary);
    expect(AppTheme.lightTheme.colorScheme.onPrimary, Colors.white);
    expect(
        AppTheme.lightTheme.colorScheme.onSecondary, BrandPalette.primaryDark);
    expect(BrandPalette.headerGradient.colors, [
      BrandPalette.primaryDark,
      BrandPalette.primary,
      BrandPalette.primaryLight
    ]);
    expect(BrandPalette.primaryScrollbar.toARGB32(),
        0x59000000 | BrandPalette.primaryRgb);
    expect(BrandPalette.primaryAvatar.toARGB32(),
        0x26000000 | BrandPalette.primaryRgb);
    expect(Parchment.gold, BrandPalette.secondary);
  });

  test('the logo green derives the expected header shades', () {
    if (BrandPalette.primaryRgb == 0x004820) {
      expect(BrandPalette.primaryLight, const Color(0xFF07632F));
      expect(BrandPalette.primaryDark, const Color(0xFF012511));
    }
  });

  test('cover branding uses configured colors without overriding saved colors',
      () {
    expect(BrandPalette.coverPalettes.first,
        [BrandPalette.primary, BrandPalette.secondary]);
    expect(coverColors(null, 'አዳም'), BrandPalette.coverPalettes.first);
    expect(
        coverColors(
            {'gradient_start': '#123456', 'gradient_end': '#abcdef'}, 'አዳም'),
        [const Color(0xFF123456), const Color(0xFFABCDEF)]);
    expect(hymnCoverColors({'art_color': '#123456'}, 'አዳም').first,
        const Color(0xFF123456));
  });

  test('configured headers and accent buttons retain readable contrast', () {
    double contrast(Color a, Color b) {
      final l1 = a.computeLuminance();
      final l2 = b.computeLuminance();
      final light = l1 > l2 ? l1 : l2;
      final dark = l1 > l2 ? l2 : l1;
      return (light + 0.05) / (dark + 0.05);
    }

    for (final stop in BrandPalette.headerGradient.colors) {
      expect(contrast(Colors.white, stop), greaterThanOrEqualTo(4.5),
          reason:
              'White header text must remain readable on each gradient stop');
    }
    for (final stop in BrandPalette.headerGradient.colors) {
      final badge = Color.alphaBlend(
          BrandPalette.secondary.withValues(alpha: 0.15), stop);
      expect(contrast(Colors.white, badge), greaterThanOrEqualTo(4.5),
          reason:
              'White badge labels must remain readable on the tinted header');
      expect(contrast(BrandPalette.secondary, badge), greaterThanOrEqualTo(3),
          reason: 'Gold badge icons must remain visible on the tinted header');
      final calendarButton = Color.alphaBlend(
          BrandPalette.secondary.withValues(alpha: 0.18), stop);
      expect(contrast(BrandPalette.secondary, calendarButton),
          greaterThanOrEqualTo(3),
          reason: 'The calendar icon must remain visible on its tinted header');
    }
    final lightAccentSurface = Color.alphaBlend(
        BrandPalette.secondaryInk.withValues(alpha: 0.12), Colors.white);
    expect(contrast(BrandPalette.secondaryInk, lightAccentSurface),
        greaterThanOrEqualTo(4.5),
        reason: 'Gold labels and icons on light surfaces need a darker ink');
    expect(contrast(BrandPalette.primary, BrandPalette.secondary),
        greaterThanOrEqualTo(4.5),
        reason: 'Gold labels on the primary green must remain readable');
    expect(contrast(Parchment.buttonInk, Parchment.gold),
        greaterThanOrEqualTo(4.5),
        reason: 'Parchment controls must remain readable on gold');
    expect(contrast(BrandPalette.primaryDark, BrandPalette.secondary),
        greaterThanOrEqualTo(4.5),
        reason:
            'The accent button foreground and background must remain readable');
    for (final background in [Colors.white, Colors.black]) {
      final chip =
          Color.alphaBlend(BrandPalette.parchmentControlSurface, background);
      expect(contrast(BrandPalette.parchmentControlInk, chip),
          greaterThanOrEqualTo(4.5),
          reason:
              'The back-arrow chip must remain readable over light and dark artwork');
    }
  });

  test('optional rebuild probe confirms both configured base colors', () {
    const primary = int.fromEnvironment('BRAND_TEST_PRIMARY', defaultValue: -1);
    const secondary =
        int.fromEnvironment('BRAND_TEST_SECONDARY', defaultValue: -1);
    if (primary >= 0) expect(BrandPalette.primaryRgb, primary);
    if (secondary >= 0) expect(BrandPalette.secondaryRgb, secondary);
  });
}
