"""Check the source logo copies and native asset dimensions remain consistent."""
import hashlib
import json
import struct
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MOBILE = ROOT / 'Mobile/wbws_flutter_app'


def png_size(path):
    data = path.read_bytes()[:24]
    if data[:8] != b'\x89PNG\r\n\x1a\n' or data[12:16] != b'IHDR':
        raise ValueError(f'Not a PNG: {path}')
    return struct.unpack('>II', data[16:24])


class LogoAssetTests(unittest.TestCase):
    def test_primary_logo_copies_match(self):
        files = [ROOT / 'themes/fkss/assets/logos/school_logo.png',
                 ROOT / 'admin/id_cards/assets/logos/school_logo.png',
                 MOBILE / 'assets/logo_school.png', ROOT / 'Mobile/splash_logo.png']
        hashes = {hashlib.sha256(path.read_bytes()).digest() for path in files}
        self.assertEqual(len(hashes), 1)
        self.assertEqual(png_size(files[0]), (1536, 1536))

    def test_ios_icon_catalog_dimensions(self):
        catalog = MOBILE / 'ios/Runner/Assets.xcassets/AppIcon.appiconset'
        entries = json.loads((catalog / 'Contents.json').read_text())['images']
        for entry in entries:
            if 'filename' not in entry:
                continue
            size = float(entry['size'].split('x')[0]) * int(entry['scale'][:-1])
            self.assertEqual(png_size(catalog / entry['filename']), (round(size), round(size)))

    def test_android_launcher_densities(self):
        res = MOBILE / 'android/app/src/main/res'
        for density, size in [('mdpi', 48), ('hdpi', 72), ('xhdpi', 96),
                              ('xxhdpi', 144), ('xxxhdpi', 192)]:
            self.assertEqual(png_size(res / f'mipmap-{density}/launcher_icon.png'), (size, size))


if __name__ == '__main__':
    unittest.main()
