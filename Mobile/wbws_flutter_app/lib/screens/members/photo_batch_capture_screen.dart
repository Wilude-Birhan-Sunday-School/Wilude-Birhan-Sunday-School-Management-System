import 'dart:io';

import 'package:flutter/material.dart';

import '../../services/local_db.dart';
import '../../services/photo_capture_service.dart';
import '../../services/photo_outbox_models.dart';
import '../../services/photo_outbox_service.dart';
import '../../utils/brand_palette.dart';
import 'member_detail_screen.dart';
import 'photo_camera_screen.dart';

/// Brand palette for photo workflows (aligned with BrandPalette).
class _PhotoBrand {
  static const maroon = BrandPalette.primary;
  static const gold = BrandPalette.secondary;
  static const ink = Color(0xFF1e1b19);
  static const muted = Color(0xFF6b6360);
  static const surface = Color(0xFFfaf6f2);
}

/// P6 — Batch roster photo capture.
///
/// Walks the locally-cached member list in order, skips members who
/// already have a queued/captured photo, shows progress dots and
/// automatically advances to the next missing member after a capture
/// is accepted. Field operators can jump to any member manually, mark
/// someone as "skip for later," or jump back to the detail screen.
///
/// Route args:
///   arguments = {'startFromMemberId': int?} — optional member id to
///   jump the cursor to (used from the detail screen's "Start batch
///   from here" action).
class PhotoBatchCaptureScreen extends StatefulWidget {
  const PhotoBatchCaptureScreen({super.key});

  @override
  State<PhotoBatchCaptureScreen> createState() =>
      _PhotoBatchCaptureScreenState();
}

class _PhotoBatchCaptureScreenState extends State<PhotoBatchCaptureScreen> {
  final _db = LocalDb();
  List<_RosterRow> _roster = [];
  bool _loading = true;
  String? _error;
  int _cursor = 0; // index into _roster of the member currently "up"
  bool _capturing = false;
  bool _processing = false;
  PhotoOutboxEntry? _reviewEntry; // preview for current member after capture

  int? get _startFromId => (ModalRoute.of(context)?.settings.arguments
      as Map<String, dynamic>?)?['startFromMemberId'] as int?;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      // Page through the cached member list in one shot — roster is
      // typically 300-400 members, well within a single query.
      final all = <Map<String, dynamic>>[];
      const pageSize = 200;
      int offset = 0;
      while (true) {
        final page = await _db.getCachedMembers(
            status: 'active', limit: pageSize, offset: offset);
        all.addAll(page);
        if (page.length < pageSize) break;
        offset += pageSize;
      }
      final withPhoto = await PhotoOutboxService.instance.memberIdsWithPhoto();
      final roster = all.map((m) {
        final id = (m['id'] as num?)?.toInt() ?? 0;
        return _RosterRow(
          memberId: id,
          code: m['member_code'] as String?,
          firstName: (m['student_name'] as String?) ?? '',
          fatherName: (m['father_name'] as String?) ?? '',
          section: m['current_section'] as String?,
          hasPhoto: withPhoto.contains(id),
        );
      }).toList();
      // Sort by member_code (numeric when possible, string otherwise)
      // so the walk matches printed rosters.
      roster.sort((a, b) => (a.code ?? '').compareTo(b.code ?? ''));
      int cursor = 0;
      final startId = _startFromId;
      if (startId != null) {
        final idx = roster.indexWhere((r) => r.memberId == startId);
        if (idx >= 0) cursor = idx;
      } else {
        // Default: start from first missing photo (the "start from
        // first missing" spec requirement).
        final firstMissing = roster.indexWhere((r) => !r.hasPhoto);
        if (firstMissing >= 0) cursor = firstMissing;
      }
      if (!mounted) return;
      setState(() {
        _roster = roster;
        _cursor = cursor;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not load roster: $e';
        _loading = false;
      });
    }
  }

  _RosterRow get _current => _roster[_cursor];
  int get _missing => _roster.where((r) => !r.hasPhoto).length;
  int get _done => _roster.where((r) => r.hasPhoto).length;

  Future<void> _capture() async {
    if (_capturing) return;
    setState(() {
      _capturing = true;
      _processing = false;
      _error = null;
      _reviewEntry = null;
    });
    try {
      final capture = await Navigator.of(context).push<CapturedPhoto>(
        MaterialPageRoute(
          fullscreenDialog: true,
          builder: (_) => PhotoCameraScreen(
            memberName: '${_current.firstName} ${_current.fatherName}'.trim(),
          ),
        ),
      );
      if (!mounted) return;
      if (capture == null) {
        setState(() {
          _capturing = false;
          _processing = false;
        });
        return;
      }
      setState(() => _processing = true);
      final entry = await PhotoCaptureService.instance.processAndEnqueue(
        capture: capture,
        memberId: _current.memberId,
        memberCode: _current.code,
        field: PhotoField.student,
      );
      if (!mounted) return;
      // Mark roster row as captured; clear the review preview and
      // auto-advance to next missing member after a brief pause so the
      // operator sees the success flash.
      setState(() {
        _reviewEntry = entry;
        _roster[_cursor] = _current.withPhoto(true);
        _capturing = false;
        _processing = false;
      });
      await Future.delayed(const Duration(milliseconds: 650));
      if (!mounted) return;
      setState(() => _reviewEntry = null);
      _advanceToNextMissing();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _capturing = false;
        _processing = false;
        _error = '$e';
      });
    }
  }

  void _advanceToNextMissing() {
    for (int i = _cursor + 1; i < _roster.length; i++) {
      if (!_roster[i].hasPhoto) {
        setState(() => _cursor = i);
        return;
      }
    }
    // Wrap: if none remaining after cursor, look from the top.
    for (int i = 0; i < _cursor; i++) {
      if (!_roster[i].hasPhoto) {
        setState(() => _cursor = i);
        return;
      }
    }
    // All done!
    _showDoneDialog();
  }

  void _showDoneDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        title: const Text('All photos captured'),
        content: Text(
            'All ${_roster.length} active members now have a photo on this device. '
            'Return to the Members screen and open Photo Sync to upload them when you have Wi-Fi.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Stay here')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
                backgroundColor: _PhotoBrand.maroon,
                foregroundColor: Colors.white),
            onPressed: () {
              Navigator.pop(ctx);
              Navigator.pop(context);
            },
            child: const Text('Done'),
          ),
        ],
      ),
    );
  }

  void _skip() {
    _advanceToNextMissing();
  }

  void _jumpTo(int idx) {
    setState(() {
      _cursor = idx;
      _reviewEntry = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _PhotoBrand.surface,
      appBar: AppBar(
        backgroundColor: _PhotoBrand.maroon,
        foregroundColor: Colors.white,
        title: const Text('Batch Photo Capture'),
        actions: [
          IconButton(
            tooltip: 'Done',
            icon: const Icon(Icons.check),
            onPressed: () => Navigator.pop(context),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _roster.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline,
                            color: _PhotoBrand.maroon, size: 48),
                        const SizedBox(height: 12),
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        ElevatedButton(
                            onPressed: _load, child: const Text('Retry')),
                      ],
                    ),
                  ),
                )
              : _roster.isEmpty
                  ? const Center(
                      child: Padding(
                        padding: EdgeInsets.all(24),
                        child: Text(
                            'No active members cached on this device yet. '
                            'Go online once to sync the roster before capturing photos.',
                            textAlign: TextAlign.center),
                      ),
                    )
                  : Column(
                      children: [
                        _progressStrip(),
                        Expanded(child: _currentMemberCard()),
                        _rosterStrip(),
                      ],
                    ),
    );
  }

  Widget _progressStrip() {
    final total = _roster.length;
    final pct = total == 0 ? 0.0 : _done / total;
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
      color: Colors.white,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Text('Active roster',
                style: TextStyle(
                    fontSize: 11,
                    color: _PhotoBrand.muted,
                    fontWeight: FontWeight.w600,
                    letterSpacing: .06)),
            const Spacer(),
            Text('$_done / $total done',
                style:
                    const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
            const SizedBox(width: 10),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: BrandPalette.primary.withOpacity(.08),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text('$_missing missing',
                  style: const TextStyle(
                      color: BrandPalette.primary,
                      fontSize: 11,
                      fontWeight: FontWeight.w700)),
            ),
          ]),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: pct,
              minHeight: 6,
              backgroundColor: _PhotoBrand.maroon.withOpacity(.08),
              valueColor: const AlwaysStoppedAnimation<Color>(_PhotoBrand.gold),
            ),
          ),
        ],
      ),
    );
  }

  Widget _currentMemberCard() {
    final row = _current;
    final fullName = '${row.firstName} ${row.fatherName}'.trim().isEmpty
        ? '(no name)'
        : '${row.firstName} ${row.fatherName}';
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: _PhotoBrand.maroon.withOpacity(.12)),
              boxShadow: [
                BoxShadow(
                    color: _PhotoBrand.maroon.withOpacity(.06),
                    blurRadius: 12,
                    offset: const Offset(0, 4)),
              ],
            ),
            child: Column(children: [
              // Preview / portrait frame
              AspectRatio(
                aspectRatio: 3 / 4,
                child: Container(
                  decoration: BoxDecoration(
                    color: Colors.black,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: _reviewEntry != null &&
                          File(_reviewEntry!.localPath).existsSync()
                      ? Stack(fit: StackFit.expand, children: [
                          Image.file(File(_reviewEntry!.localPath),
                              fit: BoxFit.cover),
                          const Positioned(
                            top: 12,
                            right: 12,
                            child: Icon(Icons.check_circle,
                                color: _PhotoBrand.gold, size: 36),
                          ),
                        ])
                      : row.hasPhoto
                          ? Container(
                              color: const Color(0xFF2a2520),
                              child: const Center(
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(Icons.check_circle_outline,
                                        color: _PhotoBrand.gold, size: 56),
                                    SizedBox(height: 8),
                                    Text('Photo already queued',
                                        style: TextStyle(
                                            color: Colors.white70,
                                            fontSize: 13)),
                                  ],
                                ),
                              ),
                            )
                          : Container(
                              color: const Color(0xFF2a2520),
                              child: Center(
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(Icons.person,
                                        color: Colors.white24, size: 80),
                                    const SizedBox(height: 8),
                                    Text(fullName,
                                        style: const TextStyle(
                                            color: Colors.white70,
                                            fontSize: 16,
                                            fontWeight: FontWeight.w600)),
                                  ],
                                ),
                              ),
                            ),
                ),
              ),
              const SizedBox(height: 16),
              Text(fullName,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w700,
                      color: _PhotoBrand.ink)),
              const SizedBox(height: 6),
              Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                  decoration: BoxDecoration(
                    color: _PhotoBrand.gold.withOpacity(.22),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(row.code ?? '—',
                      style: const TextStyle(
                          color: Color(0xFF7a5a00),
                          fontWeight: FontWeight.w800,
                          fontSize: 12)),
                ),
                if (row.section != null && row.section!.isNotEmpty) ...[
                  const SizedBox(width: 8),
                  Text(row.section!,
                      style: TextStyle(
                          color: _PhotoBrand.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w500)),
                ],
              ]),
              const SizedBox(height: 20),
              if (_error != null)
                Container(
                  padding: const EdgeInsets.all(10),
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(
                    color: Colors.red.withOpacity(.08),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(children: [
                    const Icon(Icons.error_outline,
                        color: Colors.red, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                        child: Text(_error!,
                            style: const TextStyle(
                                color: Colors.red, fontSize: 12))),
                  ]),
                ),
              Row(children: [
                Expanded(
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _PhotoBrand.maroon,
                      side:
                          BorderSide(color: _PhotoBrand.maroon.withOpacity(.3)),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10)),
                    ),
                    onPressed: _capturing ? null : _skip,
                    icon: const Icon(Icons.skip_next),
                    label: const Text('Skip'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  flex: 2,
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _PhotoBrand.maroon,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10)),
                    ),
                    onPressed: _capturing ? null : _capture,
                    icon: _capturing
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.camera_alt),
                    label: Text(
                        _capturing
                            ? (_processing
                                ? 'Preparing photo…'
                                : 'Opening camera…')
                            : (row.hasPhoto ? 'Retake' : 'Take photo'),
                        style: const TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 14)),
                  ),
                ),
              ]),
              const SizedBox(height: 8),
              TextButton.icon(
                onPressed: () {
                  Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) =>
                          MemberDetailScreen(memberId: row.memberId)));
                },
                icon: const Icon(Icons.info_outline, size: 16),
                label: const Text('View member details',
                    style: TextStyle(fontSize: 12)),
              ),
            ]),
          ),
        ],
      ),
    );
  }

  Widget _rosterStrip() {
    // Horizontal scrollable strip of upcoming members with status dots.
    // Shows 15 members centered on the cursor for easy tap-to-jump.
    final window = 15;
    final start = (_cursor - window ~/ 2).clamp(0, _roster.length - 1);
    final end = (start + window).clamp(0, _roster.length);
    final slice = _roster.sublist(start, end);
    return Container(
      height: 84,
      color: Colors.white,
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: slice.length,
        separatorBuilder: (_, __) => const SizedBox(width: 6),
        itemBuilder: (ctx, i) {
          final idx = start + i;
          final r = slice[i];
          final active = idx == _cursor;
          final initial =
              r.firstName.isEmpty ? '?' : r.firstName[0].toUpperCase();
          return GestureDetector(
            onTap: () => _jumpTo(idx),
            child: Container(
              width: 60,
              decoration: BoxDecoration(
                color: active
                    ? _PhotoBrand.maroon
                    : r.hasPhoto
                        ? _PhotoBrand.gold.withOpacity(.25)
                        : Colors.grey.withOpacity(.08),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                    color: active
                        ? _PhotoBrand.maroon
                        : Colors.black.withOpacity(.08)),
              ),
              padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 4),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircleAvatar(
                    radius: 16,
                    backgroundColor: active
                        ? _PhotoBrand.gold
                        : r.hasPhoto
                            ? _PhotoBrand.gold.withOpacity(.5)
                            : Colors.grey.withOpacity(.25),
                    child: Text(initial,
                        style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: active
                                ? _PhotoBrand.maroon
                                : r.hasPhoto
                                    ? const Color(0xFF7a5a00)
                                    : _PhotoBrand.muted)),
                  ),
                  const SizedBox(height: 4),
                  Text(r.code ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                          color: active
                              ? Colors.white
                              : r.hasPhoto
                                  ? const Color(0xFF7a5a00)
                                  : _PhotoBrand.muted)),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _RosterRow {
  final int memberId;
  final String? code;
  final String firstName;
  final String fatherName;
  final String? section;
  final bool hasPhoto;
  _RosterRow({
    required this.memberId,
    required this.code,
    required this.firstName,
    required this.fatherName,
    required this.section,
    required this.hasPhoto,
  });
  _RosterRow withPhoto(bool v) => _RosterRow(
        memberId: memberId,
        code: code,
        firstName: firstName,
        fatherName: fatherName,
        section: section,
        hasPhoto: v,
      );
}
