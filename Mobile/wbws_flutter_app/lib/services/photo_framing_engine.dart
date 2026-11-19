/// Pure decision engine for the adaptive photo-capture guide.
///
/// Consumes frame-normalized observations (see
/// [photo_framing_geometry]) and produces the verdict the camera screen
/// renders: per-check states (green/amber/neutral), the single most
/// useful instruction (spec §8 — never competing messages), and the
/// "ready to capture" signal, which requires the applicable checks to
/// have stayed stable for a short window.
///
/// Two hard rules from the specification are enforced structurally:
///
/// * `allowCapture` is always true — detection assists the operator,
///   it never prevents capture (FR-09, principle 7).
/// * A missing or low-confidence detection maps to `neutral`, never to
///   `pass` (principle: uncertainty is never communicated as success).
library;

import 'dart:math' as math;

import 'photo_framing_geometry.dart';

/// Three-state visual result for one positioning check.
enum CheckState { pass, warn, neutral }

/// One landmark observation mapped into 3:4 frame space.
///
/// Built by the camera screen from raw ML Kit output; the engine never
/// touches pixel coordinates or plugin types.
class FrameObservation {
  final int faceCount;

  /// Face bounding box, frame-normalized. Null when no face.
  final RectF? faceBox;

  /// Eye landmarks, frame-normalized. Either eye may be null when the
  /// detector did not report it.
  final OffsetF? leftEye;
  final OffsetF? rightEye;

  /// Head rotation (Euler Z, degrees) from ML Kit, or null.
  final double? headTiltDegrees;

  /// Shoulder landmarks, frame-normalized, with per-landmark
  /// confidence (0..1). Null when pose detection is unavailable or
  /// returned no person.
  final PoseObservation? pose;

  const FrameObservation({
    required this.faceCount,
    required this.faceBox,
    required this.leftEye,
    required this.rightEye,
    required this.headTiltDegrees,
    required this.pose,
  });
}

class PoseObservation {
  final OffsetF? leftShoulder;
  final OffsetF? rightShoulder;
  final double leftConfidence;
  final double rightConfidence;

  const PoseObservation({
    required this.leftShoulder,
    required this.rightShoulder,
    required this.leftConfidence,
    required this.rightConfidence,
  });

  bool get hasReliableShoulders =>
      leftShoulder != null &&
      rightShoulder != null &&
      leftConfidence >= kShoulderMinConfidence &&
      rightConfidence >= kShoulderMinConfidence;
}

/// Verdict rendered by the camera screen.
class FramingVerdict {
  final CheckState faceCentered;
  final CheckState eyesAligned;
  final CheckState shouldersVisible;

  /// The single most useful instruction right now (null when ready).
  final String? message;

  /// True only when the applicable checks have been stable for
  /// [kReadyStabilityWindow] — "ready" is a sustained state, not a
  /// single good frame.
  final bool ready;

  final bool personDetected;

  /// Always true. Capture is never blocked by detection (FR-09).
  final bool allowCapture;

  const FramingVerdict({
    required this.faceCentered,
    required this.eyesAligned,
    required this.shouldersVisible,
    required this.message,
    required this.ready,
    required this.personDetected,
  }) : allowCapture = true;
}

// ---------------------------------------------------------------------------
// Target regions — spec §7.3 starting values, to be calibrated in Stage D
// against real photographs across the FKSS age range. Do not tighten a
// threshold into a rejection gate: these drive guidance only.
// ---------------------------------------------------------------------------

/// Face center horizontal target with tolerance.
const double kFaceCenterXTarget = 0.50;
const double kFaceCenterXTolerance = 0.08;

/// Eye-line vertical band (soft edges for warn, hard for pass).
const double kEyeLinePassMin = 0.38;
const double kEyeLinePassMax = 0.45;
const double kEyeLineWarnMin = 0.33;
const double kEyeLineWarnMax = 0.50;

/// Top-of-head margin (fraction of frame height).
const double kHeadTopMarginMin = 0.02;
const double kHeadTopMarginPreferredMin = 0.05;
const double kHeadTopMarginPreferredMax = 0.10;
const double kHeadTopMarginMax = 0.18;

/// Face width (fraction of frame width) — distance guidance band.
const double kFaceWidthTooSmall = 0.24;
const double kFaceWidthWarnMin = 0.28;
const double kFaceWidthWarnMax = 0.42;
const double kFaceWidthTooLarge = 0.48;

/// Shoulder landmark band and horizontal safe margins.
const double kShoulderYMin = 0.70;
const double kShoulderYMax = 0.88;
const double kShoulderMarginPreferred = 0.06;
const double kShoulderMarginWarn = 0.03;

/// Orientation tolerances (degrees).
const double kHeadTiltPass = 10.0;
const double kHeadTiltWarn = 15.0;
const double kShoulderTiltPass = 12.0;
const double kShoulderTiltWarn = 18.0;

/// Pose landmark confidence floor for treating a shoulder as reliable.
const double kShoulderMinConfidence = 0.55;

/// Stability windows (seconds, engine-side, injectable clock).
const double kMessageSwitchDelay = 0.6;
const double kReadyStabilityWindow = 1.2;

/// Feedback engine. Feed one [FrameObservation] per detection result and
/// read the returned verdict. Keep a single engine instance per capture
/// session — smoothing state lives here.
class PhotoFramingEngine {
  String? _currentMessage;
  String? _candidateMessage;
  DateTime? _candidateSince;
  DateTime? _allPassSince;
  DateTime _lastTick = DateTime.fromMillisecondsSinceEpoch(0);

  FramingVerdict evaluate(FrameObservation obs, DateTime now) {
    final dt = now.isAfter(_lastTick)
        ? now.difference(_lastTick).inMilliseconds / 1000.0
        : 0.0;
    _lastTick = now;

    final checks = _Checks.from(obs);
    final message = _highestPriorityMessage(checks, obs);

    // Ready requires sustained success, not one good frame.
    final allPass = checks.isReadyQuality;
    if (allPass) {
      _allPassSince ??= now;
    } else {
      _allPassSince = null;
    }
    final allPassSince = allPass ? (_allPassSince ??= now) : (_allPassSince = null);
    final ready = allPassSince != null &&
        now.difference(allPassSince).inMilliseconds / 1000.0 >=
            kReadyStabilityWindow;

    // Messages must persist briefly before switching, so the panel does
    // not flash between states on noisy detections.
    String? shown;
    if (message == null) {
      shown = ready ? null : _currentMessage;
    } else if (message == _currentMessage) {
      shown = message;
      _candidateMessage = null;
      _candidateSince = null;
    } else {
      if (message == _candidateMessage) {
        final since = _candidateSince ?? now;
        if (now.difference(since).inMilliseconds / 1000.0 >=
                kMessageSwitchDelay ||
            dt == 0) {
          shown = message;
        } else {
          shown = _currentMessage;
        }
      } else if (_currentMessage == null) {
        // First instruction of the session shows immediately — the
        // switch delay protects against flashing BETWEEN messages.
        shown = message;
      } else {
        _candidateMessage = message;
        _candidateSince = now;
        shown = _currentMessage;
      }
      if (shown == message) {
        _currentMessage = message;
        _candidateMessage = null;
        _candidateSince = null;
      }
    }

    return FramingVerdict(
      faceCentered: checks.faceCentered,
      eyesAligned: checks.eyesAligned,
      shouldersVisible: checks.shouldersVisible,
      message: ready ? null : shown,
      ready: ready,
      personDetected: obs.faceCount > 0 || obs.pose != null,
    );
  }

  /// Reset smoothing state (e.g. when returning to the screen after
  /// backgrounding, or switching cameras).
  void reset() {
    _currentMessage = null;
    _candidateMessage = null;
    _candidateSince = null;
    _allPassSince = null;
  }
}

class _Checks {
  final CheckState faceCentered;
  final CheckState eyesAligned;
  final CheckState shouldersVisible;
  final List<String> failures; // ordered by priority

  const _Checks({
    required this.faceCentered,
    required this.eyesAligned,
    required this.shouldersVisible,
    required this.failures,
  });

  bool get isReadyQuality =>
      faceCentered == CheckState.pass &&
      eyesAligned != CheckState.warn &&
      (shouldersVisible == CheckState.pass ||
          shouldersVisible == CheckState.neutral) &&
      failures.isEmpty;

  static _Checks from(FrameObservation obs) {
    final failures = <String>[];

    // --- Face ---------------------------------------------------------
    final box = obs.faceBox;
    CheckState faceCentered = CheckState.neutral;
    CheckState eyesAligned = CheckState.neutral;

    if (obs.faceCount > 1) {
      failures.add('multiple');
    }
    if (obs.faceCount == 0) {
      failures.add('noFace');
    } else if (box != null) {
      final center = OffsetF(
        box.left + box.width / 2,
        box.top + box.height / 2,
      );
      final dx = (center.x - kFaceCenterXTarget).abs();

      // Distance first — it changes what every other instruction means.
      if (box.width < kFaceWidthTooSmall || box.height < 0.26) {
        failures.add('tooSmall');
      } else if (box.width > kFaceWidthTooLarge) {
        failures.add('tooLarge');
      } else if (box.width < kFaceWidthWarnMin) {
        failures.add('tooSmall');
      } else if (box.width > kFaceWidthWarnMax) {
        failures.add('tooLarge');
      }

      final headTopMargin = box.top;
      if (headTopMargin < kHeadTopMarginMin) {
        failures.add('tooLarge'); // head touches the top boundary
      } else if (headTopMargin > kHeadTopMarginMax) {
        failures.add('tooLow');
      }

      if (dx > kFaceCenterXTolerance) {
        failures.add('offCenter');
        faceCentered = CheckState.warn;
      } else if (!frameContainsMostly(box)) {
        failures.add('offCenter');
        faceCentered = CheckState.warn;
      } else {
        faceCentered = CheckState.pass;
      }

      // --- Eyes ---------------------------------------------------------
      final eyeTilt = _eyeTiltDegrees(obs.leftEye, obs.rightEye);
      final eyeY = _eyeLineY(obs.leftEye, obs.rightEye);
      final tilt = obs.headTiltDegrees?.abs() ?? 0;
      final tooTilted =
          (obs.headTiltDegrees != null && tilt > kHeadTiltWarn) ||
              (eyeTilt != null && eyeTilt.abs() > kHeadTiltWarn);
      final warnTilted =
          (obs.headTiltDegrees != null && tilt > kHeadTiltPass) ||
              (eyeTilt != null && eyeTilt.abs() > kHeadTiltPass);

      if (tooTilted) {
        failures.add('headTilt');
        eyesAligned = CheckState.warn;
      } else if (eyeY == null) {
        // Landmarks unavailable — neutral, never a false pass.
        eyesAligned = CheckState.neutral;
      } else if (eyeY < kEyeLinePassMin || eyeY > kEyeLinePassMax) {
        failures.add('eyesMisaligned');
        eyesAligned = CheckState.warn;
      } else if (eyeY < kEyeLineWarnMin || eyeY > kEyeLineWarnMax) {
        eyesAligned = CheckState.warn;
      } else if (warnTilted) {
        failures.add('headTilt');
        eyesAligned = CheckState.warn;
      } else {
        eyesAligned = CheckState.pass;
      }
    }

    // --- Shoulders ----------------------------------------------------
    CheckState shoulders = CheckState.neutral;
    final pose = obs.pose;
    if (pose != null && pose.hasReliableShoulders) {
      final l = pose.leftShoulder!;
      final r = pose.rightShoulder!;
      final outside =
          l.x < kShoulderMarginWarn || r.x > 1 - kShoulderMarginWarn;
      final tight = l.x < kShoulderMarginPreferred ||
          r.x > 1 - kShoulderMarginPreferred;
      final tooLow = l.y > kShoulderYMax || r.y > kShoulderYMax;
      final tooHigh = l.y < kShoulderYMin || r.y < kShoulderYMin;
      final tilt =
          ((r.y - l.y) / ((r.x - l.x) == 0 ? 0.0001 : (r.x - l.x))).abs();
      final tiltDeg =
          (math.atan2(tilt, 1) * 180 / math.pi).abs();

      if (outside) {
        failures.add('shouldersOutside');
        shoulders = CheckState.warn;
      } else if (tooHigh) {
        // Shoulders near the eye line usually means the frame is too
        // tight; distance guidance covers it.
        failures.add('shouldersOutside');
        shoulders = CheckState.warn;
      } else if (tooLow) {
        failures.add('tooLow');
        shoulders = CheckState.warn;
      } else if (tiltDeg > kShoulderTiltWarn) {
        failures.add('shoulderTilt');
        shoulders = CheckState.warn;
      } else if (tight) {
        shoulders = CheckState.pass;
      } else if (tiltDeg > kShoulderTiltPass) {
        shoulders = CheckState.pass; // slight natural asymmetry is fine
      } else {
        shoulders = CheckState.pass;
      }
    } else if (pose != null) {
      // Pose present but shoulders unreliable → neutral guidance.
    }

    return _Checks(
      faceCentered: faceCentered,
      eyesAligned: eyesAligned,
      shouldersVisible: shoulders,
      failures: failures,
    );
  }

  static bool frameContainsMostly(RectF box) =>
      box.left >= -0.02 &&
      box.right <= 1.02 &&
      box.top >= -0.02 &&
      box.bottom <= 1.02;

  static double? _eyeLineY(OffsetF? l, OffsetF? r) {
    if (l != null && r != null) return (l.y + r.y) / 2;
    return l?.y ?? r?.y;
  }

  static double? _eyeTiltDegrees(OffsetF? l, OffsetF? r) {
    if (l == null || r == null) return null;
    final dx = r.x - l.x;
    final dy = r.y - l.y;
    if (dx.abs() < 0.005) return null;
    return (math.atan2(dy.abs(), dx.abs()) * 180 / math.pi).abs();
  }
}

String? _highestPriorityMessage(_Checks checks, FrameObservation obs) {
  String? messageFor(String failure) {
    switch (failure) {
      case 'multiple':
        return 'Keep only one person in the frame';
      case 'noFace':
        // A pose detection (body seen, face hidden) still deserves the
        // more specific "Face the camera"; with nothing detected at all
        // return null so the generic fallback below applies.
        return obs.pose != null ? 'Face the camera' : null;
      case 'tooLarge':
        return 'Move a little farther away';
      case 'tooSmall':
        return 'Move slightly closer';
      case 'offCenter':
        return 'Move toward the center';
      case 'tooLow':
        return 'Raise the camera a little';
      case 'headTilt':
        return 'Keep your head straight';
      case 'eyesMisaligned':
        return 'Look straight toward the camera';
      case 'shouldersOutside':
        return 'Adjust distance so both shoulders fit';
      case 'shoulderTilt':
        return 'Keep your head straight';
    }
    return null;
  }

  for (final f in checks.failures) {
    final message = messageFor(f);
    if (message != null) return message;
  }
  if (obs.faceCount == 0 && obs.pose == null) {
    return 'Position the person in front of the camera';
  }
  return null;
}
