import 'dart:convert';

/// State machine for a photo outbox row.
///
/// Storage values MUST match the CHECK/valid values in the SQL schema and
/// are kept as short lowercase strings (mirrors OutboxState in
/// outbox_policy.dart but without the in-flight/blocked substates that
/// don't apply to the photo worker).
enum PhotoOutboxState {
  queued('queued'),
  uploading('uploading'),
  retryWait('retry_wait'),
  done('done'),
  conflictPending('conflict_pending'),
  failed('failed'),
  discarded('discarded');

  final String storageValue;
  const PhotoOutboxState(this.storageValue);

  static PhotoOutboxState fromStorage(String s) =>
      PhotoOutboxState.values.firstWhere(
        (v) => v.storageValue == s,
        orElse: () => PhotoOutboxState.queued,
      );
}

/// Image format the queued file is encoded as.
enum PhotoFormat {
  webp('webp', 'image/webp', 'webp'),
  jpeg('jpeg', 'image/jpeg', 'jpg');

  /// Stored in the DB row (`format` column).
  final String storageValue;

  /// Sent as Content-Type for multipart upload and as X-Photo-Format.
  final String mime;

  /// File extension (without dot).
  final String extension;
  const PhotoFormat(this.storageValue, this.mime, this.extension);

  static PhotoFormat fromStorage(String s) =>
      PhotoFormat.values.firstWhere(
        (v) => v.storageValue == s,
        orElse: () => PhotoFormat.jpeg,
      );
}

/// Which member-attached photo field this is.
enum PhotoField {
  student('student_photo'),
  guardian('guardian_photo');

  final String storageValue;
  const PhotoField(this.storageValue);

  static PhotoField fromStorage(String s) => PhotoField.values.firstWhere(
        (v) => v.storageValue == s,
        orElse: () => PhotoField.student,
      );
}

/// A single queued photo capture. Immutable on the UI side; mutations go
/// through PhotoOutboxService so we never have two writers racing.
final class PhotoOutboxEntry {
  final String clientOpId;
  final int memberId;
  final String? memberCode;
  final PhotoField field;
  final String localPath;
  final String sha256;
  final int byteSize;
  final PhotoFormat format;
  final int width;
  final int height;
  final DateTime takenAt;
  final int? operatorAdminId;
  final String deviceId;
  final String? replacedBy;
  final String? retakeOf;
  final PhotoOutboxState state;
  final int attempts;
  final DateTime nextAttemptAt;
  final DateTime? lastAttemptAt;
  final String? lastError;
  final String? lastErrorCode;
  final int? lastHttpCode;
  final String? serverPath;
  final String? serverReceiptId;
  final bool conflict;
  final int? batchExportId;
  final DateTime? exportedAt;
  final DateTime createdAt;
  final DateTime updatedAt;

  const PhotoOutboxEntry({
    required this.clientOpId,
    required this.memberId,
    required this.memberCode,
    required this.field,
    required this.localPath,
    required this.sha256,
    required this.byteSize,
    required this.format,
    required this.width,
    required this.height,
    required this.takenAt,
    required this.operatorAdminId,
    required this.deviceId,
    required this.replacedBy,
    required this.retakeOf,
    required this.state,
    required this.attempts,
    required this.nextAttemptAt,
    required this.lastAttemptAt,
    required this.lastError,
    required this.lastErrorCode,
    required this.lastHttpCode,
    required this.serverPath,
    required this.serverReceiptId,
    required this.conflict,
    required this.batchExportId,
    required this.exportedAt,
    required this.createdAt,
    required this.updatedAt,
  });

  /// "Workable" means eligible for the sync worker (queued or retry_wait,
  /// not discarded/done/conflict_pending, and next_attempt_at is due).
  bool isDueForUpload(DateTime now) =>
      (state == PhotoOutboxState.queued ||
          state == PhotoOutboxState.retryWait) &&
      !nextAttemptAt.isAfter(now);

  bool get isTerminal =>
      state == PhotoOutboxState.done ||
      state == PhotoOutboxState.failed ||
      state == PhotoOutboxState.discarded;

  bool get isVisibleInRoster => state != PhotoOutboxState.discarded;

  Map<String, Object?> toInsertMap() {
    final now = DateTime.now().toUtc().toIso8601String();
    return {
      'client_op_id': clientOpId,
      'member_id': memberId,
      'member_code': memberCode,
      'field': field.storageValue,
      'local_path': localPath,
      'sha256': sha256,
      'byte_size': byteSize,
      'format': format.storageValue,
      'width': width,
      'height': height,
      'taken_at': takenAt.toUtc().toIso8601String(),
      'operator_admin_id': operatorAdminId,
      'device_id': deviceId,
      'replaced_by': replacedBy,
      'retake_of': retakeOf,
      'state': state.storageValue,
      'attempts': attempts,
      'next_attempt_at': nextAttemptAt.toUtc().toIso8601String(),
      'last_attempt_at': lastAttemptAt?.toUtc().toIso8601String(),
      'last_error': lastError,
      'last_error_code': lastErrorCode,
      'last_http_code': lastHttpCode,
      'server_path': serverPath,
      'server_receipt_id': serverReceiptId,
      'conflict': conflict ? 1 : 0,
      'batch_export_id': batchExportId,
      'exported_at': exportedAt?.toUtc().toIso8601String(),
      'created_at': createdAt.toUtc().toIso8601String(),
      'updated_at': now,
    };
  }

  static PhotoOutboxEntry fromMap(Map<String, Object?> m) => PhotoOutboxEntry(
        clientOpId: m['client_op_id'] as String,
        memberId: (m['member_id'] as num).toInt(),
        memberCode: m['member_code'] as String?,
        field: PhotoField.fromStorage(m['field'] as String),
        localPath: m['local_path'] as String,
        sha256: m['sha256'] as String,
        byteSize: (m['byte_size'] as num).toInt(),
        format: PhotoFormat.fromStorage(m['format'] as String),
        width: (m['width'] as num).toInt(),
        height: (m['height'] as num).toInt(),
        takenAt: DateTime.parse(m['taken_at'] as String).toUtc(),
        operatorAdminId: (m['operator_admin_id'] as num?)?.toInt(),
        deviceId: m['device_id'] as String,
        replacedBy: m['replaced_by'] as String?,
        retakeOf: m['retake_of'] as String?,
        state: PhotoOutboxState.fromStorage(m['state'] as String),
        attempts: (m['attempts'] as num).toInt(),
        nextAttemptAt:
            DateTime.parse(m['next_attempt_at'] as String).toUtc(),
        lastAttemptAt: m['last_attempt_at'] == null
            ? null
            : DateTime.parse(m['last_attempt_at'] as String).toUtc(),
        lastError: m['last_error'] as String?,
        lastErrorCode: m['last_error_code'] as String?,
        lastHttpCode: (m['last_http_code'] as num?)?.toInt(),
        serverPath: m['server_path'] as String?,
        serverReceiptId: m['server_receipt_id'] as String?,
        conflict: (m['conflict'] as num? ?? 0) != 0,
        batchExportId: (m['batch_export_id'] as num?)?.toInt(),
        exportedAt: m['exported_at'] == null
            ? null
            : DateTime.parse(m['exported_at'] as String).toUtc(),
        createdAt: DateTime.parse(m['created_at'] as String).toUtc(),
        updatedAt: DateTime.parse(m['updated_at'] as String).toUtc(),
      );

  /// Human-readable byte size, for status chips.
  String get humanSize {
    if (byteSize < 1024) return '$byteSize B';
    if (byteSize < 1024 * 1024) {
      return '${(byteSize / 1024).toStringAsFixed(1)} KB';
    }
    return '${(byteSize / (1024 * 1024)).toStringAsFixed(2)} MB';
  }

  @override
  String toString() =>
      'PhotoOutboxEntry($clientOpId member=$memberId field=${field.storageValue} '
      'state=${state.storageValue} format=${format.storageValue} '
      '${width}x$height $humanSize)';
}

/// Summary counters used by the Sync Panel and roster badge.
final class PhotoOutboxCounters {
  final int queued;
  final int uploading;
  final int retryWait;
  final int done;
  final int conflictPending;
  final int failed;
  final int discarded;
  final int totalBytesQueued;

  const PhotoOutboxCounters({
    required this.queued,
    required this.uploading,
    required this.retryWait,
    required this.done,
    required this.conflictPending,
    required this.failed,
    required this.discarded,
    required this.totalBytesQueued,
  });

  int get unsynced => queued + uploading + retryWait + conflictPending;
  int get terminalIssues => failed;
  int get total =>
      queued + uploading + retryWait + done + conflictPending + failed;

  static const zero = PhotoOutboxCounters(
    queued: 0,
    uploading: 0,
    retryWait: 0,
    done: 0,
    conflictPending: 0,
    failed: 0,
    discarded: 0,
    totalBytesQueued: 0,
  );

  Map<String, dynamic> toJson() => {
        'queued': queued,
        'uploading': uploading,
        'retryWait': retryWait,
        'done': done,
        'conflictPending': conflictPending,
        'failed': failed,
        'discarded': discarded,
        'totalBytesQueued': totalBytesQueued,
        'unsynced': unsynced,
      };

  @override
  String toString() => const JsonEncoder.withIndent('  ').convert(toJson());
}

/// The sync policy row. All three booleans start at "no traffic" defaults.
final class PhotoSyncPolicyState {
  final bool syncEnabled;
  final bool allowCellular;
  final int maxConcurrency;
  final DateTime updatedAt;

  const PhotoSyncPolicyState({
    required this.syncEnabled,
    required this.allowCellular,
    required this.maxConcurrency,
    required this.updatedAt,
  });

  /// Hard-coded defaults used to seed the policy row on first open.
  /// `updatedAt` is set at insert time by [toUpsertMap].
  static PhotoSyncPolicyState seeded() => PhotoSyncPolicyState(
        syncEnabled: false,
        allowCellular: false,
        maxConcurrency: 2,
        updatedAt: DateTime.fromMillisecondsSinceEpoch(0, isUtc: true),
      );

  PhotoSyncPolicyState copyWith({
    bool? syncEnabled,
    bool? allowCellular,
    int? maxConcurrency,
  }) =>
      PhotoSyncPolicyState(
        syncEnabled: syncEnabled ?? this.syncEnabled,
        allowCellular: allowCellular ?? this.allowCellular,
        maxConcurrency: maxConcurrency ?? this.maxConcurrency,
        updatedAt: DateTime.now().toUtc(),
      );

  static PhotoSyncPolicyState fromMap(Map<String, Object?> m) =>
      PhotoSyncPolicyState(
        syncEnabled: (m['sync_enabled'] as num? ?? 0) != 0,
        allowCellular: (m['allow_cellular'] as num? ?? 0) != 0,
        maxConcurrency: (m['max_concurrency'] as num? ?? 2).toInt(),
        updatedAt: DateTime.parse(m['updated_at'] as String).toUtc(),
      );

  Map<String, Object?> toUpsertMap() => {
        'id': 1,
        'sync_enabled': syncEnabled ? 1 : 0,
        'allow_cellular': allowCellular ? 1 : 0,
        'max_concurrency': maxConcurrency,
        'updated_at': DateTime.now().toUtc().toIso8601String(),
      };
}

/// A recorded ZIP export event.
final class PhotoExportBatch {
  final int id;
  final String batchUuid;
  final DateTime exportedAt;
  final int? operatorAdminId;
  final String deviceId;
  final String localZipPath;
  final String zipSha256;
  final int zipByteSize;
  final int photoCount;
  final String? note;
  final DateTime? closedAt;
  final int? serverBatchId;
  final DateTime createdAt;
  final DateTime updatedAt;

  const PhotoExportBatch({
    required this.id,
    required this.batchUuid,
    required this.exportedAt,
    required this.operatorAdminId,
    required this.deviceId,
    required this.localZipPath,
    required this.zipSha256,
    required this.zipByteSize,
    required this.photoCount,
    required this.note,
    required this.closedAt,
    required this.serverBatchId,
    required this.createdAt,
    required this.updatedAt,
  });

  bool get isClosed => closedAt != null;

  static PhotoExportBatch fromMap(Map<String, Object?> m) => PhotoExportBatch(
        id: (m['id'] as num).toInt(),
        batchUuid: m['batch_uuid'] as String,
        exportedAt: DateTime.parse(m['exported_at'] as String).toUtc(),
        operatorAdminId: (m['operator_admin_id'] as num?)?.toInt(),
        deviceId: m['device_id'] as String,
        localZipPath: m['local_zip_path'] as String,
        zipSha256: m['zip_sha256'] as String,
        zipByteSize: (m['zip_byte_size'] as num).toInt(),
        photoCount: (m['photo_count'] as num).toInt(),
        note: m['note'] as String?,
        closedAt: m['closed_at'] == null
            ? null
            : DateTime.parse(m['closed_at'] as String).toUtc(),
        serverBatchId: (m['server_batch_id'] as num?)?.toInt(),
        createdAt: DateTime.parse(m['created_at'] as String).toUtc(),
        updatedAt: DateTime.parse(m['updated_at'] as String).toUtc(),
      );
}
