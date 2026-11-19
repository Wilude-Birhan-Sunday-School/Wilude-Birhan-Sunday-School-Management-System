/// On-device ML Kit detection for the adaptive photo-capture guide.
///
/// Runs face detection (landmarks + head Euler angles) and single-person
/// pose detection (shoulders with confidence) on camera frames supplied
/// by the `camera` plugin's image stream. All processing is on-device;
/// frames are held only until processed and are never persisted, cached
/// to disk, or transmitted (spec §12).
///
/// Performance contract (spec §9.3):
/// * only the most recent frame is processed — an in-flight detection
///   causes newer frames to be dropped, never queued;
/// * detector instances are created once per capture session and closed
///   in [dispose];
/// * all failures degrade to "no observation" rather than throwing into
///   the UI — the guide falls back to its static/manual form.
library;

import 'dart:async';
import 'dart:typed_data';
import 'dart:ui' show Size;

import 'package:camera/camera.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:google_mlkit_pose_detection/google_mlkit_pose_detection.dart';

import 'photo_framing_geometry.dart';

/// A face detected in the upright camera frame (pixels, unmirrored).
class DetectedFace {
  final RectF box;
  final OffsetF? leftEye;
  final OffsetF? rightEye;
  final double? headTiltDegrees;

  const DetectedFace({
    required this.box,
    required this.leftEye,
    required this.rightEye,
    required this.headTiltDegrees,
  });
}

/// A pose detected in the upright camera frame (pixels, unmirrored).
class DetectedPose {
  final OffsetF? leftShoulder;
  final OffsetF? rightShoulder;
  final double leftConfidence;
  final double rightConfidence;

  const DetectedPose({
    required this.leftShoulder,
    required this.rightShoulder,
    required this.leftConfidence,
    required this.rightConfidence,
  });
}

/// One detection result in upright-image pixel space.
class RawDetection {
  final List<DetectedFace> faces;
  final DetectedPose? pose;

  /// Size of the upright detection image the landmarks refer to.
  final SizeF uprightImageSize;

  const RawDetection({
    required this.faces,
    required this.pose,
    required this.uprightImageSize,
  });
}

class PhotoFramingDetector {
  FaceDetector? _faceDetector;
  PoseDetector? _poseDetector;
  CameraController? _attachedController;
  RawDetection? _latest;
  bool _processing = false;
  bool _disposed = false;
  String? _lastError;

  /// Most recent completed detection, or null before the first result.
  RawDetection? get latest => _latest;

  /// True once the detector instances have been created successfully.
  bool get isReady => _faceDetector != null;

  /// Last non-fatal error, for surfacing "detection unavailable" in the
  /// UI instead of a silently dead guide.
  String? get lastError => _lastError;

  /// Starts consuming frames from [controller]. Safe to call again with
  /// a new controller (e.g. after switching cameras).
  Future<void> attach(CameraController controller) async {
    if (_disposed) return;
    await detach();
    _faceDetector ??= FaceDetector(
      options: FaceDetectorOptions(
        performanceMode: FaceDetectorMode.fast,
        enableLandmarks: true,
        enableClassification: false,
        enableTracking: false,
        minFaceSize: 0.15,
      ),
    );
    _poseDetector ??= PoseDetector(options: PoseDetectorOptions());
    _attachedController = controller;
    try {
      await controller.startImageStream(_onImage);
    } on CameraException catch (e) {
      _lastError = 'Frame stream unavailable: ${e.description}';
      // The guide keeps working in static/manual form.
    }
  }

  /// Stops consuming frames (controller is about to be reconfigured or
  /// released). Idempotent.
  Future<void> detach() async {
    final controller = _attachedController;
    _attachedController = null;
    if (controller == null) return;
    try {
      if (controller.value.isStreamingImages) {
        await controller.stopImageStream();
      }
    } on CameraException catch (e) {
      _lastError = 'Could not stop frame stream: ${e.description}';
    }
  }

  Future<void> dispose() async {
    _disposed = true;
    await detach();
    try {
      await _faceDetector?.close();
    } catch (e) {
      _lastError = 'Face detector close failed: $e';
    }
    try {
      await _poseDetector?.close();
    } catch (e) {
      _lastError = 'Pose detector close failed: $e';
    }
    _faceDetector = null;
    _poseDetector = null;
    _latest = null;
  }

  Future<void> _onImage(CameraImage image) async {
    if (_disposed || _processing) return; // drop stale frames, never queue
    _processing = true;
    try {
      final rotation = _rotationDegrees(image);
      if (rotation == null) return;
      final input = _toInputImage(image, rotation);
      final faces = await _detectFaces(input);
      final pose = await _detectPose(input);
      _latest = RawDetection(
        faces: faces,
        pose: pose,
        uprightImageSize:
            uprightImageSize(SizeF(image.width.toDouble(), image.height.toDouble()), rotation),
      );
      _lastError = null;
    } catch (e) {
      // Detection is advisory; never let a bad frame break the screen.
      _lastError = 'Detection error: $e';
    } finally {
      _processing = false;
    }
  }

  /// Rotation to apply so ML Kit sees an upright portrait image.
  ///
  /// The app captures in portrait; the plugin's picture path bakes the
  /// same orientation, so the detection rotation is the camera's sensor
  /// orientation (90 back / 270 front on the tested devices). Confirmed
  /// per-device during Stage D acceptance — a wrong value shows up
  /// immediately as a sideways or flipped outline.
  int? _rotationDegrees(CameraImage image) {
    final controller = _attachedController;
    if (controller == null) return null;
    final sensor = controller.description.sensorOrientation;
    final lensFacing = controller.description.lensDirection;
    if (lensFacing == CameraLensDirection.back) return sensor;
    if (lensFacing == CameraLensDirection.front) {
      // Front sensors are typically mounted at 270; keep the sensor
      // value so mirrored previews and unmirrored captures agree.
      return sensor;
    }
    return sensor;
  }

  InputImage _toInputImage(CameraImage image, int rotation) {
    final bytes = BytesBuilder();
    for (final plane in image.planes) {
      bytes.add(plane.bytes);
    }
    return InputImage.fromBytes(
      bytes: bytes.toBytes(),
      metadata: InputImageMetadata(
        size: Size(image.width.toDouble(), image.height.toDouble()),
        rotation: InputImageRotation.values.firstWhere(
          (r) => r.rawValue == rotation,
          orElse: () => InputImageRotation.rotation0deg,
        ),
        format: InputImageFormat.yuv420,
        bytesPerRow: image.planes.first.bytesPerRow,
      ),
    );
  }

  Future<List<DetectedFace>> _detectFaces(InputImage input) async {
    final detector = _faceDetector;
    if (detector == null) return const [];
    final results = await detector.processImage(input);
    return [
      for (final face in results)
        DetectedFace(
          box: RectF(
            left: face.boundingBox.left.toDouble(),
            top: face.boundingBox.top.toDouble(),
            width: face.boundingBox.width.toDouble(),
            height: face.boundingBox.height.toDouble(),
          ),
          leftEye: _landmark(face, FaceLandmarkType.leftEye),
          rightEye: _landmark(face, FaceLandmarkType.rightEye),
          headTiltDegrees: face.headEulerAngleZ,
        ),
    ];
  }

  OffsetF? _landmark(Face face, FaceLandmarkType type) {
    final lm = face.landmarks[type];
    if (lm == null) return null;
    return OffsetF(
      lm.position.x.toDouble(),
      lm.position.y.toDouble(),
    );
  }

  Future<DetectedPose?> _detectPose(InputImage input) async {
    final detector = _poseDetector;
    if (detector == null) return null;
    final results = await detector.processImage(input);
    if (results.isEmpty) return null;
    final pose = results.first;
    PoseLandmark? landmark(PoseLandmarkType type) => pose.landmarks[type];

    final left = landmark(PoseLandmarkType.leftShoulder);
    final right = landmark(PoseLandmarkType.rightShoulder);
    return DetectedPose(
      leftShoulder: left == null ? null : OffsetF(left.x, left.y),
      rightShoulder: right == null ? null : OffsetF(right.x, right.y),
      leftConfidence: left?.likelihood ?? 0,
      rightConfidence: right?.likelihood ?? 0,
    );
  }
}
