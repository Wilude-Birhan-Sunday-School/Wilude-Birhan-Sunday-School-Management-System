/// Gallery import for the photo-capture flow (spec §4.5).
///
/// Picks an existing photo and normalizes it into a camera-equivalent
/// `CapturedPhoto`: EXIF orientation is baked into pixels by the native
/// compressor (the in-app camera path never carries EXIF, so the rest of
/// the pipeline — 3:4 center crop, tier resize, 2 MB JPEG cap — can
/// treat both sources identically).
///
/// The picked file itself is never modified or moved.
library;

import 'dart:io';

import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';

import 'photo_capture_service.dart';

class PhotoGalleryImport {
  PhotoGalleryImport._();

  /// Picks an image from the device gallery and writes an
  /// orientation-corrected JPEG copy into the app's temporary directory.
  /// Returns null when the operator cancels the picker.
  static Future<CapturedPhoto?> pickAndNormalize() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 4096,
      imageQuality: 95,
    );
    if (picked == null) return null;

    final bytes = await File(picked.path).readAsBytes();
    if (bytes.isEmpty) {
      throw const FormatException('The selected image is empty.');
    }

    final encoded = await FlutterImageCompress.compressWithList(
      bytes,
      quality: 92,
      autoCorrectionAngle: true, // bake gallery EXIF orientation
      keepExif: false,
      format: CompressFormat.jpeg,
      minWidth: 4096,
      minHeight: 4096,
    );

    final dir = await getTemporaryDirectory();
    final path =
        '${dir.path}/gallery_import_${DateTime.now().millisecondsSinceEpoch}.jpg';
    await File(path).writeAsBytes(encoded, flush: true);
    return CapturedPhoto(path: path, takenAt: DateTime.now().toUtc());
  }
}
