// Unit tests for the adaptive photo-capture feedback engine
// (lib/services/photo_framing_engine.dart): check states, instruction
// priority (spec §8 — one message at a time), smoothing windows,
// ready-stability, and the two structural guarantees:
//   * allowCapture is always true (FR-09),
//   * missing/uncertain detection maps to neutral, never to pass.
import 'package:flutter_test/flutter_test.dart';
import 'package:fkss_app/services/photo_framing_engine.dart';
import 'package:fkss_app/services/photo_framing_geometry.dart';

/// A well-framed observation: face centered, eyes on the line,
/// reliable shoulders inside the safe margins.
FrameObservation goodObservation() => FrameObservation(
      faceCount: 1,
      faceBox: const RectF(left: 0.35, top: 0.06, width: 0.30, height: 0.40),
      leftEye: const OffsetF(0.43, 0.40),
      rightEye: const OffsetF(0.57, 0.40),
      headTiltDegrees: 0,
      pose: const PoseObservation(
        leftShoulder: OffsetF(0.10, 0.78),
        rightShoulder: OffsetF(0.90, 0.78),
        leftConfidence: 0.9,
        rightConfidence: 0.9,
      ),
    );

FrameObservation emptyObservation() => const FrameObservation(
      faceCount: 0,
      faceBox: null,
      leftEye: null,
      rightEye: null,
      headTiltDegrees: null,
      pose: null,
    );

DateTime _at(num seconds) =>
    DateTime.fromMillisecondsSinceEpoch(1700000000000 + (seconds * 1000).round());

void main() {
  group('allowCapture (FR-09)', () {
    test('is true even when nothing is detected', () {
      final engine = PhotoFramingEngine();
      final verdict = engine.evaluate(emptyObservation(), _at(0));
      expect(verdict.allowCapture, isTrue);
    });

    test('is true on every state', () {
      final engine = PhotoFramingEngine();
      // Multiple faces, worst case — capture is still allowed.
      final verdict = engine.evaluate(
        goodObservation().copyWithFaceCount(2),
        _at(0),
      );
      expect(verdict.allowCapture, isTrue);
    });
  });

  group('missing detection', () {
    test('maps every chip to neutral, never pass', () {
      final engine = PhotoFramingEngine();
      final verdict = engine.evaluate(emptyObservation(), _at(0));
      expect(verdict.faceCentered, CheckState.neutral);
      expect(verdict.eyesAligned, CheckState.neutral);
      expect(verdict.shouldersVisible, CheckState.neutral);
      expect(verdict.personDetected, isFalse);
      expect(verdict.ready, isFalse);
    });

    test('asks the operator to position the person', () {
      final engine = PhotoFramingEngine();
      final verdict = engine.evaluate(emptyObservation(), _at(0));
      expect(verdict.message, 'Position the person in front of the camera');
    });
  });

  group('message priority (spec §8 — most useful correction first)', () {
    test('multiple faces outrank every other problem', () {
      final engine = PhotoFramingEngine();
      final verdict = engine.evaluate(
        goodObservation().copyWithFaceCount(2),
        _at(0),
      );
      expect(verdict.personDetected, isTrue);
      // First evaluation of a condition shows only after the switch
      // delay; sample at a time past the delay.
      final later = engine.evaluate(
        goodObservation().copyWithFaceCount(2),
        _at(2),
      );
      expect(later.message, 'Keep only one person in the frame');
    });

    test('distance problems outrank centering', () {
      // Face far too small AND off-center → "move closer" wins.
      final engine = PhotoFramingEngine();
      final obs = FrameObservation(
        faceCount: 1,
        faceBox: const RectF(left: 0.60, top: 0.30, width: 0.12, height: 0.16),
        leftEye: const OffsetF(0.64, 0.36),
        rightEye: const OffsetF(0.68, 0.36),
        headTiltDegrees: 0,
        pose: null,
      );
      engine.evaluate(obs, _at(0));
      final verdict = engine.evaluate(obs, _at(2));
      expect(verdict.message, 'Move slightly closer');
    });

    test('face too large asks to move farther away', () {
      final engine = PhotoFramingEngine();
      final obs = FrameObservation(
        faceCount: 1,
        faceBox: const RectF(left: 0.05, top: 0.01, width: 0.90, height: 0.95),
        leftEye: const OffsetF(0.30, 0.40),
        rightEye: const OffsetF(0.70, 0.40),
        headTiltDegrees: 0,
        pose: null,
      );
      engine.evaluate(obs, _at(0));
      final verdict = engine.evaluate(obs, _at(2));
      expect(verdict.message, 'Move a little farther away');
    });

    test('head tilt asks to keep the head straight', () {
      final engine = PhotoFramingEngine();
      final obs = goodObservation().copyWithTilt(25);
      engine.evaluate(obs, _at(0));
      final verdict = engine.evaluate(obs, _at(2));
      expect(verdict.message, 'Keep your head straight');
      expect(verdict.eyesAligned, CheckState.warn);
    });

    test('shoulders outside the safe frame ask for distance adjustment',
        () {
      final engine = PhotoFramingEngine();
      final obs = goodObservation().copyWithShoulders(
        const OffsetF(0.01, 0.78),
        const OffsetF(0.99, 0.78),
      );
      engine.evaluate(obs, _at(0));
      final verdict = engine.evaluate(obs, _at(2));
      expect(verdict.message, 'Adjust distance so both shoulders fit');
      expect(verdict.shouldersVisible, CheckState.warn);
    });
  });

  group('smoothing (message switch delay)', () {
    test('a new condition must persist before the message changes', () {
      final engine = PhotoFramingEngine();
      // Establish "move closer" as the current message.
      final tooSmall = goodObservation().copyWithFaceBox(
        const RectF(left: 0.40, top: 0.30, width: 0.15, height: 0.20),
      );
      engine.evaluate(tooSmall, _at(0));
      engine.evaluate(tooSmall, _at(1));
      final established = engine.evaluate(tooSmall, _at(2));
      expect(established.message, 'Move slightly closer');

      // Condition changes to off-center: the old message holds first.
      final offCenter = goodObservation().copyWithFaceBox(
        const RectF(left: 0.80, top: 0.06, width: 0.30, height: 0.40),
      );
      final held = engine.evaluate(offCenter, _at(2.4));
      expect(held.message, 'Move slightly closer');

      // After 0.6 s of the new condition, the message switches.
      final switched = engine.evaluate(offCenter, _at(3.1));
      expect(switched.message, 'Move toward the center');
    });

    test('a flickering condition never replaces a stable message', () {
      final engine = PhotoFramingEngine();
      final tooSmall = goodObservation().copyWithFaceBox(
        const RectF(left: 0.40, top: 0.30, width: 0.15, height: 0.20),
      );
      final offCenter = goodObservation().copyWithFaceBox(
        const RectF(left: 0.80, top: 0.06, width: 0.30, height: 0.40),
      );
      engine.evaluate(tooSmall, _at(0));
      engine.evaluate(tooSmall, _at(1));
      engine.evaluate(tooSmall, _at(2));

      // Off-center flickers in and out faster than the switch delay.
      engine.evaluate(offCenter, _at(2.2));
      engine.evaluate(tooSmall, _at(2.4));
      engine.evaluate(offCenter, _at(2.6));
      final verdict = engine.evaluate(tooSmall, _at(2.8));
      expect(verdict.message, 'Move slightly closer');
    });
  });

  group('ready stability window', () {
    test('ready only after 1.2 s of sustained good framing', () {
      final engine = PhotoFramingEngine();
      final good = goodObservation();
      expect(engine.evaluate(good, _at(0)).ready, isFalse);
      expect(engine.evaluate(good, _at(0.6)).ready, isFalse);
      expect(engine.evaluate(good, _at(1.19)).ready, isFalse);
      expect(engine.evaluate(good, _at(1.21)).ready, isTrue);
    });

    test('a single bad frame resets the stability window', () {
      final engine = PhotoFramingEngine();
      final good = goodObservation();
      final tilted = good.copyWithTilt(25);
      engine.evaluate(good, _at(0));
      engine.evaluate(good, _at(0.8));
      engine.evaluate(tilted, _at(1.0)); // window resets
      engine.evaluate(good, _at(1.2));
      expect(engine.evaluate(good, _at(2.0)).ready, isFalse);
      expect(engine.evaluate(good, _at(2.5)).ready, isTrue);
    });

    test('a ready verdict carries no correction message', () {
      final engine = PhotoFramingEngine();
      final good = goodObservation();
      engine.evaluate(good, _at(0));
      engine.evaluate(good, _at(1.3));
      final verdict = engine.evaluate(good, _at(1.4));
      expect(verdict.ready, isTrue);
      expect(verdict.message, isNull);
    });
  });

  group('chip states', () {
    test('centered face passes, off-center face warns', () {
      final engine = PhotoFramingEngine();
      final centered = engine.evaluate(goodObservation(), _at(0));
      expect(centered.faceCentered, CheckState.pass);
      expect(centered.eyesAligned, CheckState.pass);

      final offCenter = engine.evaluate(
        goodObservation().copyWithFaceBox(
          const RectF(left: 0.80, top: 0.06, width: 0.30, height: 0.40),
        ),
        _at(1),
      );
      expect(offCenter.faceCentered, CheckState.warn);
    });

    test('eyes outside the target band warn, not pass', () {
      final engine = PhotoFramingEngine();
      final obs = goodObservation().copyWithEyes(
        const OffsetF(0.43, 0.60),
        const OffsetF(0.57, 0.60),
      );
      final verdict = engine.evaluate(obs, _at(0));
      expect(verdict.eyesAligned, CheckState.warn);
    });

    test('missing eye landmarks are neutral, never a false pass', () {
      final engine = PhotoFramingEngine();
      final obs = goodObservation().copyWithEyes(null, null);
      final verdict = engine.evaluate(obs, _at(0));
      expect(verdict.eyesAligned, CheckState.neutral);
    });
  });

  group('pose confidence gate', () {
    test('low-confidence shoulders are neutral and never fail the frame',
        () {
      final engine = PhotoFramingEngine();
      final obs = goodObservation().copyWithShoulders(
        const OffsetF(0.01, 0.78),
        const OffsetF(0.99, 0.78),
        leftConfidence: 0.3,
        rightConfidence: 0.3,
      );
      final verdict = engine.evaluate(obs, _at(0));
      expect(verdict.shouldersVisible, CheckState.neutral);
      expect(verdict.message, isNull); // face/eyes still fine
    });

    test('missing pose landmarks keep shoulders neutral', () {
      final engine = PhotoFramingEngine();
      final obs = goodObservation().copyWithShoulders(null, null);
      final verdict = engine.evaluate(obs, _at(0));
      expect(verdict.shouldersVisible, CheckState.neutral);
    });
  });

  group('reset', () {
    test('clears smoothing and ready state', () {
      final engine = PhotoFramingEngine();
      final good = goodObservation();
      engine.evaluate(good, _at(0));
      engine.evaluate(good, _at(1.3));
      expect(engine.evaluate(good, _at(1.4)).ready, isTrue);

      engine.reset();
      expect(engine.evaluate(good, _at(2)).ready, isFalse);
    });
  });
}

// --- Test helpers ------------------------------------------------------------

extension on FrameObservation {
  FrameObservation copyWithFaceCount(int count) => FrameObservation(
        faceCount: count,
        faceBox: faceBox,
        leftEye: leftEye,
        rightEye: rightEye,
        headTiltDegrees: headTiltDegrees,
        pose: pose,
      );

  FrameObservation copyWithFaceBox(RectF box) => FrameObservation(
        faceCount: faceCount,
        faceBox: box,
        leftEye: leftEye,
        rightEye: rightEye,
        headTiltDegrees: headTiltDegrees,
        pose: pose,
      );

  FrameObservation copyWithEyes(OffsetF? left, OffsetF? right) =>
      FrameObservation(
        faceCount: faceCount,
        faceBox: faceBox,
        leftEye: left,
        rightEye: right,
        headTiltDegrees: headTiltDegrees,
        pose: pose,
      );

  FrameObservation copyWithTilt(double degrees) => FrameObservation(
        faceCount: faceCount,
        faceBox: faceBox,
        leftEye: leftEye,
        rightEye: rightEye,
        headTiltDegrees: degrees,
        pose: pose,
      );

  FrameObservation copyWithShoulders(
    OffsetF? left,
    OffsetF? right, {
    double leftConfidence = 0.9,
    double rightConfidence = 0.9,
  }) =>
      FrameObservation(
        faceCount: faceCount,
        faceBox: faceBox,
        leftEye: leftEye,
        rightEye: rightEye,
        headTiltDegrees: headTiltDegrees,
        pose: (left == null && right == null)
            ? null
            : PoseObservation(
                leftShoulder: left,
                rightShoulder: right,
                leftConfidence: leftConfidence,
                rightConfidence: rightConfidence,
              ),
      );
}
