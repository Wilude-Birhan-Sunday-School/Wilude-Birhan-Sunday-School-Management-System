# Offline Photo Export → Desktop Import (ZIP Airlock) — Design

**Layer:** Fallback / safety net for the online photo sync described in `OFFLINE_PHOTO_SYNC_DESIGN.md`.
**Principle:** When the mobile internet cannot be trusted, the field staff exports everything captured to a single signed ZIP file, transfers it to a PC that has reliable internet (USB cable, local Wi-Fi hotspot, SD card, AirDrop, SHAREit, Telegram-to-self), and uploads that one file through a dedicated web dashboard. The PC upload runs once over reliable internet; the server matches every photo to its member and finalizes records in one atomic batch.

This mirrors the field-data "airlock" pattern used by ODK Collect / CommCare / KoBoToolbox, and is the standard approach when cellular is unreliable but a back-office PC has stable internet.

---

## 1. Why this is a critical safety net

Even with the online sync + retry/backoff from the previous design, real field conditions in Ethiopia include:

1. **Multi-day photo drives in rural areas with zero signal.** Staff finish photographing 400 members before returning to an office. They cannot wait hours for sync.
2. **Data-plan exhaustion.** After a few MB of upload the SIM runs out of balance; staff won't discover this until they return to Wi-Fi.
3. **Restricted devices.** Some staff phones are organization-issued with no active data plan; Wi-Fi-only.
4. **Battery priority.** Capturing photos for 3 hours is the primary task; running a foreground upload for 30 minutes drains the battery before the day is done.
5. **Verification step.** A supervisor wants to review all photos on a big screen before they are committed to the database — blurry shots, wrong member, sideways — and fix them on a PC, not a phone.

The ZIP airlock provides: **zero dependency on mobile internet at capture time**, **visual QA on a big screen**, and a **single atomic batch commit** from a PC with reliable connectivity.

## 2. It does NOT replace the online sync — both coexist

| Flow | When used | Pros | Cons |
|---|---|---|---|
| **Online sync** (background/foreground in-app) | Staff is on office Wi-Fi, or has strong cellular; wants real-time dashboard updates | Fully automatic; zero manual steps; photos appear on dashboard within minutes | Fails or degrades on bad internet; battery cost |
| **ZIP export + web import** | After field day, before staff leaves the site; or as a fallback when online sync stalls | Works on zero connectivity; review on PC before commit; one reliable upload from a known-good connection; perfect audit trail | Requires a manual step; supervisor must trigger import |

The two flows must be **mutually aware of each other** — if a photo was already imported via ZIP, the online sync must recognize it (by sha256) and not double-upload or overwrite; conversely if a photo was already synced online, the ZIP import skips it with "already synced" status.

---

## 3. New role: Photo Importer (or "Field Photo Supervisor")

### 3.1 Role definition

Add a new admin role **`photo_importer`** (or fold into existing `hr_dept` with a permission flag, but a dedicated role is cleaner because many parishes may have a technical volunteer who handles imports but should NOT see all member data, salaries, finances, etc.).

**Permissions for `photo_importer`:**
- ✅ Can log into the admin dashboard (limited view)
- ✅ Can access a new section **`section=photo_import`** (the ZIP upload dashboard)
- ✅ Can upload a ZIP, preview matched photos, confirm/override mismatches, commit the batch
- ✅ Can view member names + codes + existing thumbnails for the purpose of matching
- ❌ Cannot edit member fields beyond the photo
- ❌ Cannot access finance, attendance reports, HR records, Telegram bot, Telegram registrations, etc.
- ❌ Cannot delete members
- ❌ Cannot see phone numbers / guardian details (can be configured — for pure importer volunteers, hide PII beyond name/code)

**Who should get it:** IT volunteers, office admins, school media team. HR staff already have full access and will get this permission automatically.

### 3.2 Why a new role instead of letting everyone import

- Separation of duties: the person taking photos in the field should not be the only person who commits them (supervisor review step).
- A volunteer who is only trusted to drag-and-drop a ZIP into a web page should not have keys to the entire member database.
- Audit trail: every imported batch records who uploaded it, from which device fingerprint, when.

---

## 4. ZIP file format (the contract)

This format must be **stable, versioned, self-describing, and tamper-evident** so future app versions can still import old files and the server can reject malformed/corrupted uploads before doing any work.

### 4.1 Directory layout

```
fkss_photos_2026-11-16_bole-bulbula_01.zip
│
├── manifest.json                 ← REQUIRED, first file, signed
├── manifest.json.sig             ← REQUIRED, ECDSA or HMAC signature of manifest
├── photos/
│   ├── 0001_m471_student.jpg
│   ├── 0002_m472_student.jpg
│   ├── 0003_m473_student.jpg
│   ├── 0004_m473_guardian.jpg    ← optional guardian photo
│   └── ...
└── readme.txt                    ← OPTIONAL, human-readable summary
```

### 4.2 `manifest.json`

```json
{
  "export_version": 1,
  "exported_at": "2026-11-16T17:42:00+03:00",
  "device_id": "a1b2c3d4e5f6",
  "app_version": "1.8.0+43",
  "operator_admin_id": 27,
  "operator_name": "Abebe Kebede",
  "school_scope": "all",
  "batch_note": "Bole Bulbula Saturday photo day — 403 members",
  "default_field": "student_photo",
  "photos": [
    {
      "seq": 1,
      "file": "photos/0001_m471_student.webp",
      "member_code": "M-0471",
      "member_id": 471,
      "field": "student_photo",
      "taken_at": "2026-11-16T09:14:22+03:00",
      "sha256": "e3b0c442...hex...9af",
      "byte_size": 1482391,
      "format": "webp",
      "width": 1200,
      "height": 1500,
      "capture_device": "SM-A546E",
      "operator_admin_id": 27,
      "device_id": "a1b2c3d4e5f6",
      "retakes": 0
    }
    /* ...one entry per photo... */
  ],
  "counters": {
    "total_photos": 403,
    "student_photos": 398,
    "guardian_photos": 5,
    "webp_count": 403,
    "jpeg_count": 0,
    "total_bytes": 612847302
  }
}
```

Rules:
- `member_id` is the FKSS database member ID (known offline from the last sync of the roster to the phone).
- `member_code` is the human-readable code; included for redundancy so an import can match by code even if IDs shifted (they won't, but defense in depth).
- `seq` is a stable ordering number for the preview screen.
- `file` path is relative to zip root; **no parent directory traversal** (`../` rejected by importer).
- `sha256` is lowercase hex of the file bytes; server verifies before processing.
- `format` is `"webp"` or `"jpeg"`; server validates MIME matches.
- `width`/`height` are the encoded dimensions (typically 1200×1600 portrait, 3:4 passport ratio).
- `operator_admin_id` + `device_id` identify who captured the photo and on which device, used for multi-photographer conflict attribution.
- Photos are named `{seq}_{memberCodeOrId}_{field}.webp` (or `.jpg` on JPEG fallback) for human readability if someone unzips.

### 4.3 Signature (`manifest.json.sig`)

The mobile app signs the manifest with a device-specific HMAC key issued at login (stored in `flutter_secure_storage`), using SHA-256 HMAC. The server verifies:
1. That the export was produced by a currently-authorized logged-in device.
2. That the manifest has not been tampered with after export (e.g. someone swapping a photo in the zip).
3. That photos inside the zip match their declared sha256 values.

A failed signature rejects the whole zip with a clear error. This prevents a malicious zip from being crafted and uploaded.

### 4.4 Compression settings

- ZIP **store** (no compression) — WebP and JPEG are already compressed; deflating them wastes CPU on phone and PC for ~2% size gain.
- Each file is encoded as **WebP quality 92 at 1200×1600 px (primary)**, or **JPEG quality 92 at 1200×1600 px (fallback)**, per the encode loop in the sync design. Target 1.0–2.0 MB per photo; hard cap 2 MB.
- Total ZIP size budget: **~600–800 MB for 400 photos** (at ~1.5 MB average) — fits easily on any USB transfer, SD card, or SHAREit (local hotspot, no data cost); too big for email, which is fine because USB is the expected delivery.
- Split into volumes of 200 MB if zip exceeds 600 MB (ZIP64 multi-part `z01, z02, ... zip`) — optional v2. v1 keeps single-file; 800 MB is well under FAT32's 4 GB limit and under SHAREit's file size limits.

### 4.5 Generation on phone

- User taps **Export ZIP** on the queue/sync screen.
- App creates a temp directory, writes `manifest.json` from the outbox rows (both **queued AND synced** photos can be included — synced photos get `"already_synced": true`), copies each photo file into `photos/`, generates signature, zips with `archive` package (Dart), computes the final zip sha256, saves to app exports directory.
- User picks a share destination: **Save to Files** (iOS), **Share via USB/SD**, **SHAREit / Telegram "Saved Messages"**, **Email (if zip < 25 MB)**.
- After a successful import (detected next time the phone is online), the app marks the exported batch as "closed" and can auto-cleanup the outbox.

---

## 5. Web dashboard — Photo Import section

New admin section `section=photo_import` (available to `photo_importer`, `hr_dept`, `info_dept`, `school_admin`, `super_admin`).

### 5.1 Screens

1. **Upload screen**
   - Drop zone for ZIP file (or click-to-browse)
   - Live progress bar during upload (large file, can be 100 MB)
   - Server-side streaming validation (signature, sha256 of every photo) — no DB writes yet
   - Shows operator name, device, capture date, photo count immediately after parsing manifest

2. **Review screen** (the critical human step — includes multi-photographer conflict resolution)
   - Grid of thumbnails, each card showing:
     - Photo preview (server-generated, 240 px; click to open full-size viewer)
     - Matched member (name + code + section)
     - Photographer name + device (from manifest `operator_admin_id`, `device_id`) + taken_at timestamp
     - Match/confidence chip:
       - 🟢 **New** — member has no current photo; auto-accept ready
       - 🟡 **Matched by code fallback** (ID miss but code found)
       - 🔴 **Unmatched** (neither ID nor code found — search picker to assign)
       - ⚔️ **Conflict** — this member already has a photo (from online sync or another photographer's ZIP); card shows BOTH thumbnails side-by-side: existing current vs new, with photographer + taken_at for each; admin chooses one
       - ⚠️ **Corrupt / sha256 mismatch / unsupported format**
       - ♻️ **Duplicate** — sha256 already in receipts (already imported); auto-skip
     - Action buttons: **Accept**, **Use this one (resolve conflict)**, **Keep existing**, **Skip**, **Assign to different member** (searchable picker)
   - Global filters: "Show only conflicts", "Show only unmatched", "Show only new", "Hide duplicates"
   - "Auto-accept all 🟢 new photos" bulk action (confirms everything without a conflict in one tap)
   - "Auto-resolve ♻️ duplicates" (bulk-skip already-imported items)
   - Blurry/too-dark/low-quality warnings (simple server-side heuristic, optional v2)
   - Summary bar: **387 new (auto-ready) · 8 conflicts · 5 unmatched · 3 duplicates · total 403**

3. **Commit screen**
   - Lists what will happen: **387 new photos added · 5 conflicts resolved in favor of new photo · 3 conflicts keeping existing · 5 unmatched manually assigned · 3 duplicates skipped**
   - Shows batch summary: operator(s), device(s), captured date range, total size, format breakdown (WebP/JPEG count)
   - Big **"Commit import"** button — writes everything in ONE database transaction:
     - Copies validated photo files into permanent storage (preserving `.webp`/`.jpg` extension)
     - For 🟢 new photos: inserts `photo_upload_receipts` rows AND `member_photo_candidates` rows, flips `is_current` on the winners
     - For ⚔️ conflict resolutions: inserts candidates for BOTH existing and new (preserving history), flips `is_current` to admin's chosen winner
     - Updates `members.student_photo_path` (or `guardian_photo_path`) as a denormalized pointer to the winning candidate
     - Inserts `photo_import_batches` + `photo_import_items` audit rows
     - Returns a batch receipt number

4. **Batch history screen**
   - Past imports: date, operator, device, count, status
   - Can re-download the original ZIP for audit, can view commit diff
   - Cannot "undo" a commit (safety: corrections must be done member-by-member through the normal edit flow)

### 5.2 Server components

| Component | Purpose |
|---|---|
| `admin/dashboards/sections/photo_import_section.php` | UI for upload/review/commit |
| `admin/api_photo_import.php` | Endpoints: `upload_chunk`, `parse_manifest`, `preview_batch`, `commit_batch`, `list_batches` |
| `admin/backend/services/PhotoImportService.php` | Validation, match logic, transactional commit, receipts |
| SQL table `photo_import_batches` | Audit log: id, operator_admin_id, device_id, manifest_sig_valid, total_photos, committed_photos, status (uploaded/parsed/reviewed/committed/failed), created_at, committed_at, zip_path, manifest_json |
| SQL table `photo_import_items` | Per-photo decisions: batch_id, seq, member_id, field, sha256, decision (accepted/replaced/skipped/reassigned), resolved_member_id |
| Add permission in `access_control.php` for new role `photo_importer` → `section=photo_import` |

### 5.3 Upload protocol

For 600–800 MB ZIPs from office internet:
- **v1: single POST** with PHP `post_max_size = 1G, upload_max_filesize = 1G` and HTML5 upload progress. Office Wi-Fi (5 Mbps up = ~600 KB/s) handles 800 MB in ~22 minutes; wired LAN handles it in 2–3 minutes.
- **v2 (if offices prove slower):** add HTML5 chunked/resumable upload mirroring the `Range` pattern from the hymn downloader (reversed for upload).
- Validate ZIP structure in a streaming fashion (don't load entire zip into memory — iterate entries with `ZipArchive` and extract one at a time).
- Extract to a temp directory outside web root; compute each photo's sha256 while extracting; verify declared MIME matches actual file magic bytes (`RIFF....WEBP` or `FFD8FF` JPEG header); compare to manifest.
- WebP/JPEG decode-verify server-side using GD/Imagick before accepting a photo (reject corrupt/truncated files).

### 5.4 Match resolution logic (updated with multi-photographer conflict handling)

```
For each photo in manifest:
  1. Verify sha256 matches actual file bytes; if mismatch → CORRUPT
  2. Verify file is a decodable WebP/JPEG; if not → INVALID_FORMAT
  3. If sha256 already in photo_upload_receipts → DUPLICATE (auto-skip)
  4. Resolve member: by member_id first; if not found, try member_code; if still not found → UNMATCHED (manual assignment)
  5. If resolved member has NO current photo → NEW (auto-accept ready)
  6. If resolved member HAS a current photo:
     - If existing photo's sha256 equals this file → DUPLICATE (auto-skip)
     - If existing photo's operator_admin_id == manifest.operator_admin_id
       AND taken_at of new > taken_at of existing → SAME-PHOTOGRAPHER RETAKE (auto-accept; replace)
     - Otherwise → CONFLICT (different photographer or uncertain); queue both thumbnails for admin side-by-side choice
```

**Key rule:** photos from different photographers NEVER auto-overwrite each other. Same-photographer retakes (identified by same `operator_admin_id` from the same or different batches) auto-win by newer `taken_at`, mirroring the mobile retake semantics.

A supervisor can override any decision. Defaults lean conservative (flag anything uncertain rather than silently overwrite).

---

## 6. Integration with the online photo sync

Both pathways converge on the same server primitives:

- `photo_upload_receipts` table records every photo that landed on the server, regardless of path (online sync or ZIP import), keyed by `(client_op_id, sha256)`.
- When the mobile app comes online after a ZIP was imported from the same device:
  - Online sync sends each photo with its `client_op_id`
  - Server returns `200 {replayed:true, already_imported_via:'zip', batch_id:123}`
  - App marks the photo `done` and removes it from the queue automatically
- When a ZIP is exported while some photos already synced online:
  - Manifest marks them `"already_synced": true`
  - Web importer shows them greyed-out ("synced, will skip")

No double-commits. No confusion. The operator always sees a consistent state.

---

## 7. Security rules

1. **ZIP upload is auth-gated** — only `photo_importer` (or higher) roles can upload.
2. **Signature verification is mandatory** — unsigned or bad-signature ZIPs are rejected before parsing.
3. **File type validation** — only `image/webp` and `image/jpeg` (magic-byte verified, GD/Imagick decode-verified); max 2 MB per file; max 1 GB total zip size; max 2000 photos per batch. `image/png` is NOT accepted (unnecessary for face photos, larger, no print benefit at our sizes).
4. **No path traversal** — all extracted paths are resolved against the temp directory; any entry starting with `/` or containing `..` rejects the batch; only accept extensions `.webp` and `.jpg`/`.jpeg`.
5. **Zip bomb protection** — reject entries with absurd compression ratios (ZIP is stored uncompressed per §4.4, so ratio should be ~1.0); cap total extracted size at 1.2 GB; reject zip64 overlapping-offset attacks.
6. **Atomic commit** — database writes happen in one transaction; a failure midway rolls back, leaving zero partial state.
7. **Audit trail is immutable** — committed batches cannot be edited through this UI; corrections go through the normal member edit flow (which has its own SecurityAuditService trail).
8. **The export HMAC key is per-device** — a stolen zip from one device cannot be replayed as if from another.

---

## 8. End-to-end workflow (field day scenario)

**Before the trip:**
- IT/HR creates a `photo_importer` account for the supervisor.
- Field staff sync their phone roster one last time on office Wi-Fi (so member IDs/codes are current).

**In the field (no internet):**
1. Staff opens app → Members → Photo Capture Day. Sync is OFF by default; no data is used.
2. Photographs 403 members one-by-one; each photo is encoded to WebP q=92 at 1200×1600 (~1.5 MB) and queued locally; counter shows "403 captured · 0 synced · sync OFF". Retakes discard the previous shot automatically.
3. At end of day, staff taps **Export ZIP**, chooses **Save to USB storage** (or Share → SHAREit → PC / AirDrop / Telegram Saved Messages).
4. Phone generates `fkss_photos_2026-11-16_bole-bulbula_01.zip` (~600–700 MB) and signs it with device HMAC key.
5. Staff connects phone to PC via USB (or transfers via local hotspot SHAREit, which costs zero data) and copies the zip to the PC's Downloads folder.

**Back at the office (or church with wired/Wi-Fi internet):**
1. Supervisor (with `photo_importer` role) logs into the admin dashboard → **Photo Import**.
2. Drag-and-drops the ZIP; upload progress bar shows ~20 minutes on Wi-Fi, ~3 minutes on wired LAN.
3. Server parses manifest, verifies signature, extracts & hashes photos, validates WebP/JPEG decode, builds preview.
4. Supervisor reviews the grid: "387 new (auto-ready) · 8 conflicts (two photographers captured the same members) · 5 unmatched · 3 duplicates". Clicks "Auto-accept new", resolves the 8 conflicts by side-by-side comparison (picks the better-lit/sharper photo), assigns the 5 unmatched members via search picker, marks 2 blurry to skip.
5. Clicks **Commit import**. Server writes everything in ONE transaction; new photos become current; losing conflict candidates are archived (not deleted).
6. Dashboard now shows all 395 winning photos on member profiles; any online-sync devices learn the decision next time they connect.
7. When the field phone next comes online, the app detects that 398 photos are already committed via ZIP, marks them synced, removes them from the queue, shows "All caught up — 0 photos pending".

If staff later gets a chance to sync online before bringing the USB cable, the online sync proceeds normally; ZIP import is still a safe option that detects duplicates — no race condition.

---

## 9. Effort estimate (incremental, on top of online sync P1-P7)

| Phase | Work | Days |
|---|---|---|
| **Z1 Manifest & ZIP generation on mobile** | Manifest schema with format/width/height/operator/device_id/retakes; HMAC signing; ZIP packaging (store, no compression); Export UI (share sheet target picker, always-available Export ZIP button); mark batches as "exported" with UUID; discard entries excluded from zip; already-synced entries marked `"already_synced":true` | 3 |
| **Z2 Server ingestion primitives** | `photo_import_batches` + `photo_import_items` tables; new role `photo_importer` in access control; streaming ZIP parser; manifest signature verification; sha256 verification; WebP/JPEG magic-byte + GD decode validation; photo extraction to temp dir; probe for server WebP support at deploy time; raise `upload_max_filesize`/`post_max_size` to 1 GB | 4 |
| **Z3 Review web UI** | Upload dropzone with progress; preview grid (server-generated WebP/JPEG thumbnails via GD/Imagick); **multi-photographer conflict side-by-side comparison cards** with photographer/device/taken_at labels; match chips (new/matched/unmatched/conflict/duplicate/corrupt); filters (conflicts-only, unmatched-only, etc.); member picker for reassignment; "Auto-accept new" + "Auto-skip duplicates" bulk actions; full-size photo viewer on click | 7 |
| **Z4 Commit transaction + receipts** | Atomic commit; file promotion from temp to permanent storage (preserving .webp/.jpg extensions); receipt rows AND `member_photo_candidates` rows; conflict flip of `is_current` winner; `members.student_photo_path` denormalized update; batch history page; audit log; shared resolution with online-sync conflict records | 4 |
| **Z5 Device feedback loop** | After import, phone detects committed batches via `/api/v1/photo-imports/by-device/{deviceId}`; clears outbox items for sha256s now in receipts; updates conflict_pending items when admin resolves them; badge updates; "ZIP import committed 395 photos, 3 pending admin review" confirmation | 2 |
| **Z6 Hardening & QA** | Zip-bomb protection; path-traversal tests; corrupt zip tests; wrong signature tests; WebP/JPEG/PNG-rejection tests; simulated multi-photographer conflict scenarios; 800 MB large-file soak test on office Wi-Fi; end-to-end walkthrough with real 400-photo batch; verify GD WebP support in staging/production | 4 |
| **Total incremental** | | **~24 days** |
| **Combined with online sync (P1-P8 updated = 31 days + Z1-Z6 = 24 days)** | | **~55 days total** |
| **MVP cut (default-off sync + ZIP export/import, auto-accept, no conflict grid yet)** | P1+P2+P4+minimal P5 + Z1+Z2+minimal Z3+Z4 | ~30 days (~6 weeks) |

The MVP cut lets you start field work in **~6 weeks** with the core "capture offline → ZIP → web import" workflow and optional online sync on Wi-Fi. Full polished experience (side-by-side conflict resolution, feedback loop, candidate archive) adds another ~5 weeks.

---

## 10. Technology choices (no new heavy dependencies)

- **Image encoding on mobile:** Dart `image` package (pure Dart) for WebP/JPEG encode, EXIF rotation, resize. WebP encode is built into recent versions of the package.
- **ZIP on mobile:** `archive` Dart package (pure Dart). No native code.
- **HMAC signing:** Dart `crypto` package (already in pubspec). Key stored in `flutter_secure_storage`; issued by server at login.
- **ZIP on server:** PHP's `ZipArchive` (built-in). Stream-extracting to avoid memory exhaustion.
- **Image handling on server:** PHP **GD with WebP support** (verify `imagewebp` exists at deploy time; `apt-get install php-gd` on most Debian/Ubuntu stacks) or Imagick as fallback. WebP thumbnails generated on-the-fly for the review grid.
- **Chunked upload (optional v2):** reuse the Range-header pattern from `MezmurDownloadManager` if 800 MB uploads over slow office Wi-Fi prove problematic.
- **Server config:** raise `upload_max_filesize` and `post_max_size` to `1G`; `memory_limit` should not be raised (streaming extract keeps memory flat).

No new third-party services, no subscriptions, no native SDKs.

---

## 11. Summary of user-mandated refinements applied to both documents

| User request | Implementation |
|---|---|
| **High-quality images, 1–2 MB, WebP preferred, preserve quality for ID/report cards/diplomas** | Output 1200×1600 px (3:4 portrait, covers 300 DPI prints up to 4×5.3″ / yearbook 60×80 mm); WebP q=92 primary, JPEG q=92 fallback; quality-control encode loop guarantees 2 MB cap without dropping below q=88 or 1024×1365; WebP gives 30–40% size savings vs same-quality JPEG; server accepts both with GD decode-verify |
| **Sync OFF by default; users block sync to protect mobile data; default workflow is ZIP handoff** | Master sync toggle default OFF; cellular sub-toggle default OFF; sync requires explicit Enable + Start Sync tap; "Export ZIP" button always visible and works fully offline; persistent "sync OFF" badge; no auto-sync on capture/app-open/Wi-Fi-join |
| **Two photographers → admin picks winner; retake discards prior** | Server `member_photo_candidates` table stores every incoming photo; cross-photographer duplicates surface as ⚔️ conflicts in both ZIP review UI and online-sync Photo Review page with side-by-side comparison + "Set as current"; same-device retake (§3.6) marks prior capture as `discarded` before upload so no conflict is generated on server; same-photographer retakes across sessions auto-win by newer `taken_at`; losers are archived for audit, not deleted |
