import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:fkss_app/screens/auth/login_screen.dart';
import 'package:fkss_app/utils/config.dart';
import 'package:fkss_app/utils/school_brand.dart';
import 'package:fkss_app/widgets/use_website_note.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(SchoolBrand.load);

  const alternate = '''
SCHOOL_APP_NAME = WBWS
SCHOOL_FULL_NAME_EN = Wilude Birhan Sunday School
SCHOOL_SHORT_NAME_EN = Wilude Birhan
SCHOOL_NAME_AM = ዊሉደ ብርሃን
''';

  test('bundled names match an optional rebuild probe', () {
    const expected = String.fromEnvironment('BRAND_TEST_APP_NAME');
    if (expected.isNotEmpty) expect(AppConfig.appName, expected);
  });

  test('parses four independent UTF-8 name variants', () {
    final names = SchoolBrand.parse(alternate);
    expect(names['SCHOOL_APP_NAME'], 'WBWS');
    expect(names['SCHOOL_NAME_AM'], 'ዊሉደ ብርሃን');
    expect(() => names['SCHOOL_APP_NAME'] = 'changed', throwsUnsupportedError);
  });

  test('rejects incomplete, duplicate and unsafe configuration', () {
    expect(() => SchoolBrand.parse('SCHOOL_APP_NAME = A'), throwsFormatException);
    expect(() => SchoolBrand.parse('$alternate\nSCHOOL_APP_NAME = Other'), throwsFormatException);
    expect(() => SchoolBrand.parse(alternate.replaceFirst('WBWS', r'$(OTHER)')), throwsFormatException);
    expect(() => SchoolBrand.parse(alternate.replaceFirst('WBWS', 'a//b')), throwsFormatException);
    expect(() => SchoolBrand.parse(alternate.replaceFirst('WBWS', '')), throwsFormatException);
    expect(() => SchoolBrand.parse('$alternate\nUNKNOWN = X'), throwsFormatException);
  });

  testWidgets('login and website note use the bundled configuration', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: LoginScreen()));
    expect(find.text(AppConfig.appNameAmharic), findsOneWidget);
    expect(find.text(AppConfig.schoolNameEnglish), findsOneWidget);
    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: UseWebsiteNote())));
    expect(find.textContaining('This job stays on the ${AppConfig.appName} website'), findsOneWidget);
  });
}
