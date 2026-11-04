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
SCHOOL_FULL_NAME_AM = ዊሉደ ብርሃን ሰንበት ትምህርት ቤት
''';

  test('bundled names match an optional rebuild probe', () {
    const expected = String.fromEnvironment('BRAND_TEST_APP_NAME');
    if (expected.isNotEmpty) expect(AppConfig.appName, expected);
  });

  test('parses five independent UTF-8 name variants', () {
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
    expect(find.text(AppConfig.appFullNameAmharic), findsOneWidget);
    expect(find.text(AppConfig.schoolNameEnglish), findsOneWidget);
    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: UseWebsiteNote())));
    expect(find.textContaining('This job stays on the ${AppConfig.appName} website'), findsOneWidget);
  });

  testWidgets('full login name wraps on a narrow screen with larger text', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(const MaterialApp(
      home: MediaQuery(
        data: MediaQueryData(size: Size(320, 640), textScaler: TextScaler.linear(1.5)),
        child: LoginScreen(),
      ),
    ));
    expect(find.text(AppConfig.appFullNameAmharic), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
