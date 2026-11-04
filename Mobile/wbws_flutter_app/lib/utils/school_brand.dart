import 'package:flutter/services.dart';

/// Human-facing names shared by Flutter, Android and iOS. Load before use.
class SchoolBrand {
  static const assetPath = 'ios/Flutter/SchoolNames.xcconfig';
  static const keys = {
    'SCHOOL_APP_NAME', 'SCHOOL_FULL_NAME_EN',
    'SCHOOL_SHORT_NAME_EN', 'SCHOOL_NAME_AM',
  };
  static Map<String, String>? _names;

  /// Parse the deliberately small common subset of xcconfig and properties.
  /// Disallow substitutions/comments and escapes, which have different
  /// meanings across Xcode, Gradle and the Dart asset parser.
  static Map<String, String> parse(String source) {
    final values = <String, String>{};
    for (final rawLine in source.split('\n')) {
      final line = rawLine.trim();
      if (line.isEmpty) continue;
      final separator = line.indexOf('=');
      if (separator < 1) throw const FormatException('Invalid school name line');
      final key = line.substring(0, separator).trim();
      final value = line.substring(separator + 1).trim();
      if (!keys.contains(key) || values.containsKey(key) || value.isEmpty ||
          value.contains('//') || value.contains(r'$') || value.contains('#') ||
          value.contains('=') || value.contains(r'\') ||
          value.contains('/*') || value.contains('*/') ||
          value.contains('\"') || value.contains('<') || value.contains('>') || value.contains('&') ||
          value.codeUnits.any((c) => c < 32 || (c >= 127 && c <= 159))) {
        throw FormatException('Invalid school name configuration: $key');
      }
      values[key] = value;
    }
    if (values.length != keys.length) {
      throw const FormatException('Missing school name configuration');
    }
    return Map.unmodifiable(values);
  }

  static Future<void> load() async {
    _names = parse(await rootBundle.loadString(assetPath));
  }

  static String _get(String key) {
    final names = _names;
    if (names == null) throw StateError('SchoolBrand.load() must run before use');
    return names[key]!;
  }

  static String get appName => _get('SCHOOL_APP_NAME');
  static String get fullNameEnglish => _get('SCHOOL_FULL_NAME_EN');
  static String get shortNameEnglish => _get('SCHOOL_SHORT_NAME_EN');
  static String get nameAmharic => _get('SCHOOL_NAME_AM');
}
