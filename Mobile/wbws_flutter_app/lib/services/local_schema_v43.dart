/// Declarative SQLite v43 schema contract — offline photo outbox.
///
/// Version 43 introduces the `photo_outbox` table, the `photo_export_batches`
/// table (ZIP-export lineage for the airlock workflow), and the
/// `photo_device_identity` one-row table holding the per-install UUID used to
/// tag every capture with which device took it (for multi-photographer
/// conflict resolution on the server).
///
/// These additions are referenced from `local_db.dart`'s `onCreate`/`onUpgrade`
/// harness and from `PhotoOutboxService` (which owns all read/write access to
/// `photo_outbox`).
///
/// Design doc: docs/OFFLINE_PHOTO_SYNC_DESIGN.md §6 (Client Data Model).
const localDatabaseSchemaVersionV43 = 43;

/// Per-install device identity row. Exactly one row (id=1) exists after the
/// v43 migration; `device_id` is a UUID v4 generated once at first launch
/// (or lazily on first access to PhotoOutboxService). It is sent to the
/// server as `X-Photo-Device-Id` on every upload and recorded in
/// `photo_upload_receipts`/`member_photo_candidates` so the admin UI can
/// attribute photos to the specific device that captured them.
const photoDeviceIdentityV43Sql = '''
  CREATE TABLE IF NOT EXISTS photo_device_identity (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    device_id TEXT NOT NULL,
    photo_hmac_key TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
''';

/// Durable outbox queue for member photos captured offline.
///
/// Every photograph the field staff takes lands here as a row BEFORE the
/// shutter animation completes. Workers (sync worker, ZIP exporter) never
/// read camera output directly — they only ever see committed rows + files.
///
/// State machine mirrors `comm_outbox` and `pending_attendance` (queued →
/// uploading → done/retry_wait/failed/conflict_pending/discarded). State
/// values are the `PhotoOutboxState.storageValue` constants so the SQL and
/// Dart enum stay in lockstep.
///
/// `replaced_by` enables retake-undo (§3.6 of the design doc): when staff
/// taps Retake, the prior capture is transitioned to `discarded` and its
/// `replaced_by` is set to the new op's client_op_id; an undo is just
/// "flip the new row to discarded and set the old row back to queued"
/// for ~30 seconds after capture.
const photoOutboxV43Sql = '''
  CREATE TABLE IF NOT EXISTS photo_outbox (
    client_op_id      TEXT PRIMARY KEY,
    member_id         INTEGER NOT NULL,
    member_code       TEXT,
    field             TEXT NOT NULL,             -- 'student_photo' | 'guardian_photo'
    local_path        TEXT NOT NULL,             -- absolute path in app-private dir
    sha256            TEXT NOT NULL,             -- lowercase hex sha256 of file bytes
    byte_size         INTEGER NOT NULL,
    format            TEXT NOT NULL,             -- 'webp' | 'jpeg'
    width             INTEGER NOT NULL,
    height            INTEGER NOT NULL,
    taken_at          TEXT NOT NULL,             -- ISO 8601 UTC, when shutter pressed
    operator_admin_id INTEGER,                   -- filled in when session is known
    device_id         TEXT NOT NULL,
    replaced_by       TEXT,                      -- client_op_id of the retake that replaced this
    retake_of         TEXT,                      -- client_op_id of the capture this one retakes
    state             TEXT NOT NULL DEFAULT 'queued',
                                                  -- queued | uploading | done | failed
                                                  -- | retry_wait | conflict_pending
                                                  -- | discarded
    attempts          INTEGER NOT NULL DEFAULT 0,
    next_attempt_at   TEXT NOT NULL,             -- ISO 8601 UTC
    last_attempt_at   TEXT,
    last_error        TEXT,
    last_error_code   TEXT,
    last_http_code    INTEGER,
    server_path       TEXT,
    server_receipt_id TEXT,
    conflict          INTEGER NOT NULL DEFAULT 0,-- boolean: server accepted but admin must pick
    batch_export_id   INTEGER,                   -- FK into photo_export_batches once exported
    exported_at       TEXT,
    created_at        TEXT NOT NULL,
    updated_at        TEXT NOT NULL
  )
''';

const photoOutboxIndexesV43Sql = <String>[
  '''CREATE INDEX IF NOT EXISTS idx_photo_outbox_state_due
     ON photo_outbox(state, next_attempt_at, created_at)''',
  '''CREATE INDEX IF NOT EXISTS idx_photo_outbox_member_field
     ON photo_outbox(member_id, field, state)''',
  '''CREATE INDEX IF NOT EXISTS idx_photo_outbox_device
     ON photo_outbox(device_id, state)''',
  '''CREATE INDEX IF NOT EXISTS idx_photo_outbox_active_per_member
     ON photo_outbox(member_id, field)
     WHERE state NOT IN ('done', 'discarded')''',
  '''CREATE INDEX IF NOT EXISTS idx_photo_outbox_batch
     ON photo_outbox(batch_export_id)''',
];

/// ZIP-export lineage. Every time staff taps "Export ZIP", one row lands
/// here with the file path and byte count so we can later reconcile
/// against a server-side `photo_import_batches` receipt and automatically
/// clear queued photos when the office imports the zip.
const photoExportBatchesV43Sql = '''
  CREATE TABLE IF NOT EXISTS photo_export_batches (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_uuid        TEXT NOT NULL UNIQUE,
    exported_at       TEXT NOT NULL,
    operator_admin_id INTEGER,
    device_id         TEXT NOT NULL,
    local_zip_path    TEXT NOT NULL,
    zip_sha256        TEXT NOT NULL,
    zip_byte_size     INTEGER NOT NULL,
    photo_count       INTEGER NOT NULL,
    note              TEXT,
    closed_at         TEXT,                      -- set once server confirms import
    server_batch_id   INTEGER,
    created_at        TEXT NOT NULL,
    updated_at        TEXT NOT NULL
  )
''';

const photoExportBatchesIndexV43Sql = <String>[
  '''CREATE UNIQUE INDEX IF NOT EXISTS uq_photo_export_batches_uuid
     ON photo_export_batches(batch_uuid)''',
  '''CREATE INDEX IF NOT EXISTS idx_photo_export_batches_device_open
     ON photo_export_batches(device_id, closed_at)''',
];

/// Sync-policy knobs for photo uploads. All three flags default to safe/
/// no-traffic values: the app is shipped with sync effectively disabled
/// until the user explicitly turns it on (per the user-mandated "sync OFF
/// by default" rule). There is exactly one row (id=1); PhotoOutboxService
/// seeds it on first open.
const photoSyncPolicyV43Sql = '''
  CREATE TABLE IF NOT EXISTS photo_sync_policy (
    id                  INTEGER PRIMARY KEY CHECK (id = 1),
    sync_enabled        INTEGER NOT NULL DEFAULT 0,
    allow_cellular      INTEGER NOT NULL DEFAULT 0,
    max_concurrency     INTEGER NOT NULL DEFAULT 2,
    updated_at          TEXT NOT NULL
  )
''';
