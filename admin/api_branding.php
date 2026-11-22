<?php
/**
 * Branding & Assets Management API — Super Admin & School Admin
 *
 * Manages:
 * - logo:      School logo (used across landing page, login, QR verification, reports, ID cards)
 * - seal:      Official church/school seal (used on ID cards & certificates)
 * - sig_head:  Head of Sunday School signature (used on ID cards & reports)
 * - sig_admin: Parish administration signature (used on ID cards & reports)
 * - card_bg:   ID card background artwork
 *
 * Uploaded files are stored in /admin/uploads/branding/ (untracked in Git and
 * script-hardened via .htaccess) so custom uploads never overwrite packaged
 * git-tracked defaults or cause git-pull conflicts on production.
 *
 * Schema contract (sql/012_runtime_schema_baseline.sql `system_branding`):
 *   id, asset_key, asset_label, file_path, original_name, mime_type,
 *   file_size, uploaded_by, uploaded_at, updated_at
 */

if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/backend/services/IdCardLayout.php';
require_once __DIR__ . '/backend/services/BrandingAssetService.php';

function brandRespond(array $payload, int $httpStatus = 200): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Built-in branding slots seeded by sql/012_runtime_schema_baseline.sql and
 * sql/013_application_schema_completion.sql.
 *
 * @return array<string, array{asset_label:string, file_stem:string}>
 */
function brandBuiltinCatalog(): array {
    return [
        'logo' => [
            'asset_label' => 'School Logo',
            'file_stem'   => 'school_logo',
        ],
        'seal' => [
            'asset_label' => 'School Seal / Stamp',
            'file_stem'   => 'school_seal',
        ],
        'sig_head' => [
            'asset_label' => 'Head Teacher Signature',
            'file_stem'   => 'head_signature',
        ],
        'sig_admin' => [
            'asset_label' => 'Director / Admin Signature',
            'file_stem'   => 'director_signature',
        ],
        'card_bg' => [
            'asset_label' => 'ID Card Background',
            'file_stem'   => 'id_card_bg',
        ],
    ];
}

// Verify DB connection is alive
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    brandRespond(['status' => 'error', 'message' => 'Database connection failed. Please try again.'], 503);
}

// Detect POST body truncation (upload larger than PHP post_max_size) BEFORE CSRF
// so the user gets an accurate size error instead of a misleading CSRF error.
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && empty($_POST)
    && empty($_FILES)
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
    $postMax = ini_get('post_max_size') ?: '8M';
    brandRespond([
        'status'  => 'error',
        'message' => "The uploaded file exceeds the server POST size limit ({$postMax}). Please choose an image under 5 MB.",
    ], 413);
}

// Auth check — matches admin/access_control.php ('super_admin', 'school_admin')
if (empty($_SESSION['admin_id'])) {
    brandRespond(['status' => 'error', 'message' => 'Unauthorized. Please log in again.'], 401);
}

$userRole = (string)($_SESSION['admin_role'] ?? '');
if (!in_array($userRole, ['super_admin', 'school_admin'], true)) {
    brandRespond(['status' => 'error', 'message' => 'Access denied — only Super Admin and School Admin can manage branding.'], 403);
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

// CSRF check on POST (uses config.php's validateCsrf())
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validateCsrf($csrf)) {
        brandRespond(['status' => 'error', 'message' => 'Security token expired. Please reload the page and try again.'], 403);
    }
}

// Schema is deployment-managed by sql/012_runtime_schema_baseline.sql.
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)), '/');

/**
 * Resolve a branding file_path (web path like /admin/uploads/branding/school_logo.png)
 * to a real disk path. Rejects path traversal.
 */
function brandResolveAssetFile(string $webPath, string $docRoot): ?string {
    $clean = \App\Services\BrandingAssetService::stripQuery($webPath);
    if ($clean === '' || $clean[0] !== '/' || strpos($clean, '..') !== false || strpos($clean, "\0") !== false) {
        return null;
    }
    foreach ([dirname(__DIR__) . $clean, $docRoot . $clean] as $disk) {
        if (is_file($disk) && @filesize($disk) > 32) {
            return $disk;
        }
    }
    return null;
}

/**
 * Find the first packaged (git-tracked) fallback asset on disk for a built-in key.
 * Theme-aware via BrandingAssetService::packagedCandidates() (supports FKSS, WBSS, etc.).
 *
 * @return array{disk:string, web:string}|null
 */
function brandPackagedFallback(string $assetKey, string $docRoot): ?array {
    foreach (\App\Services\BrandingAssetService::packagedCandidates($assetKey) as $web) {
        $disk = brandResolveAssetFile($web, $docRoot);
        if ($disk !== null) {
            return ['disk' => $disk, 'web' => $web];
        }
    }
    return null;
}

/**
 * Only files inside our runtime upload directory (or non-packaged legacy custom
 * subdirectories) may ever be unlinked/replaced by upload_asset or delete_asset.
 * Packaged defaults under /themes/ or /admin/id_cards/assets/{logos,backgrounds}/
 * are never touched on disk.
 */
function brandIsDeletableUpload(string $webPath): bool {
    $clean = \App\Services\BrandingAssetService::stripQuery($webPath);
    if ($clean === '' || $clean[0] !== '/' || strpos($clean, '..') !== false || strpos($clean, "\0") !== false) {
        return false;
    }
    if (\App\Services\BrandingAssetService::isPackagedPath($clean)) {
        return false;
    }
    foreach ([
        '/admin/uploads/branding/',
        '/admin/id_cards/assets/seals/',
        '/admin/id_cards/assets/signatures/',
        '/admin/id_cards/assets/custom/',
    ] as $prefix) {
        if (strpos($clean, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

// ============================================================
// ACTION: GET ALL BRANDING ASSETS
// ============================================================
if ($action === 'get_assets') {
    $catalog = brandBuiltinCatalog();
    $rowsByKey = [];

    try {
        $res = $conn->query("SELECT * FROM system_branding WHERE asset_key NOT LIKE '\\_%' ORDER BY id ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rowsByKey[(string)$row['asset_key']] = $row;
            }
            $res->free();
        }
    } catch (Throwable $e) {
        reportInternalError('branding_get_assets', $e);
        brandRespond(['status' => 'error', 'message' => 'Could not read branding assets from the database.'], 500);
    }

    // Ensure every built-in slot is always represented in the UI, even on a
    // partially seeded database, keeping built-ins in canonical order first.
    $orderedRows = [];
    foreach ($catalog as $k => $meta) {
        if (isset($rowsByKey[$k])) {
            $orderedRows[] = $rowsByKey[$k];
            unset($rowsByKey[$k]);
        } else {
            $orderedRows[] = [
                'id'            => 0,
                'asset_key'     => $k,
                'asset_label'   => $meta['asset_label'],
                'file_path'     => null,
                'original_name' => null,
                'file_size'     => 0,
                'mime_type'     => null,
                'uploaded_by'   => null,
                'uploaded_at'   => null,
                'updated_at'    => null,
            ];
        }
    }
    foreach ($rowsByKey as $extraRow) {
        $orderedRows[] = $extraRow;
    }

    $assets = [];
    foreach ($orderedRows as $row) {
        $key = (string)$row['asset_key'];
        $dbPath = \App\Services\BrandingAssetService::stripQuery((string)($row['file_path'] ?? ''));
        $disk = $dbPath !== '' ? brandResolveAssetFile($dbPath, $docRoot) : null;

        $row['file_exists'] = false;
        $row['has_file'] = false;
        $row['is_packaged'] = false;
        $row['web_url'] = null;
        $row['size_kb'] = 0;

        if ($disk !== null) {
            $size = (int)@filesize($disk);
            $mtime = (int)@filemtime($disk);
            $row['file_exists'] = true;
            $row['has_file'] = true;
            $row['is_packaged'] = \App\Services\BrandingAssetService::isPackagedPath($dbPath);
            $row['file_size'] = $size;
            $row['size_kb'] = round($size / 1024, 1);
            if (empty($row['original_name'])) {
                $row['original_name'] = $row['is_packaged'] ? 'Default packaged image' : basename($disk);
            }
            $row['web_url'] = $dbPath . ($mtime > 0 ? ('?v=' . $mtime) : '');
        } else {
            $fallback = brandPackagedFallback($key, $docRoot);
            if ($fallback !== null) {
                $size = (int)@filesize($fallback['disk']);
                $mtime = (int)@filemtime($fallback['disk']);
                $row['file_exists'] = true;
                $row['has_file'] = true;
                $row['is_packaged'] = true;
                $row['file_size'] = $size;
                $row['size_kb'] = round($size / 1024, 1);
                if (empty($row['original_name'])) {
                    $row['original_name'] = 'Default packaged image';
                }
                $row['web_url'] = $fallback['web'] . ($mtime > 0 ? ('?v=' . $mtime) : '');
            }
        }
        $assets[] = $row;
    }

    $settings = \App\Services\IdCardLayout::load($conn);

    brandRespond([
        'status'   => 'success',
        'assets'   => $assets,
        'settings' => $settings,
        'schema'   => \App\Services\IdCardLayout::designerSchema(),
    ]);
}

// ============================================================
// ACTION: UPLOAD / REPLACE AN ASSET
// ============================================================
if ($action === 'upload_asset' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $assetKey = trim((string)($_POST['asset_key'] ?? ''));
    if ($assetKey === '' || $assetKey[0] === '_' || !preg_match('/^[a-z0-9_]{2,50}$/', $assetKey)) {
        brandRespond(['status' => 'error', 'message' => 'Missing or invalid asset key.'], 400);
    }

    $catalog = brandBuiltinCatalog();

    // Look up existing row (built-in keys are auto-created if not yet seeded).
    $stmt = $conn->prepare("SELECT * FROM system_branding WHERE asset_key = ? LIMIT 1");
    if (!$stmt) {
        reportInternalError('branding_upload_lookup', $conn->error);
        brandRespond(['status' => 'error', 'message' => 'Database error while checking branding slot.'], 500);
    }
    $stmt->bind_param('s', $assetKey);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$asset) {
        if (!isset($catalog[$assetKey])) {
            brandRespond(['status' => 'error', 'message' => 'Unknown asset key: ' . $assetKey], 404);
        }
        $ins = $conn->prepare(
            "INSERT IGNORE INTO system_branding (asset_key, asset_label) VALUES (?, ?)"
        );
        if ($ins) {
            $lbl = $catalog[$assetKey]['asset_label'];
            $ins->bind_param('ss', $assetKey, $lbl);
            $ins->execute();
            $ins->close();
        }
        $asset = [
            'asset_key'   => $assetKey,
            'asset_label' => $catalog[$assetKey]['asset_label'],
            'file_path'   => null,
        ];
    }

    // Check file upload presence & PHP upload error codes
    if (!isset($_FILES['file'])) {
        brandRespond(['status' => 'error', 'message' => 'No image file was received. Please select a PNG, JPG, GIF, or WebP file.'], 400);
    }
    $file = $_FILES['file'];
    $errCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errCode !== UPLOAD_ERR_OK) {
        $errMsgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit (' . (ini_get('upload_max_filesize') ?: '2M') . '). Please use a smaller image.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the 5 MB form size limit.',
            UPLOAD_ERR_PARTIAL    => 'Upload was interrupted before completion. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'Please select an image file to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload folder is missing. Contact hosting support.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file to temporary storage.',
            UPLOAD_ERR_EXTENSION  => 'Upload was blocked by a server PHP extension.',
        ];
        brandRespond(['status' => 'error', 'message' => $errMsgs[$errCode] ?? ('Upload failed (code ' . $errCode . ').')], 400);
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        brandRespond(['status' => 'error', 'message' => 'Invalid file upload. Please try again.'], 400);
    }

    $actualSize = (int)@filesize($tmpPath);
    if ($actualSize <= 0 || $actualSize > 5 * 1024 * 1024) {
        brandRespond(['status' => 'error', 'message' => 'File must be between 1 byte and 5 MB.'], 400);
    }

    // Validate real image bytes and MIME type (no SVG — prevents stored XSS).
    // Note: PHP 8.5 deprecates explicit finfo closing (it has been a no-op since
    // PHP 8.1), so letting $finfo go out of scope via unset() releases it cleanly.
    $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    $extMap = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfoMime = '';
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = @finfo_file($finfo, $tmpPath);
            if (is_string($detected)) {
                $finfoMime = strtolower(trim($detected));
            }
            unset($finfo);
        }
    }

    $imgInfo = @getimagesize($tmpPath);
    if ($imgInfo === false || empty($imgInfo['mime'])) {
        brandRespond(['status' => 'error', 'message' => 'Invalid or corrupted image file. Use a valid PNG, JPG, GIF, or WebP image.'], 400);
    }

    $imgMime = strtolower(trim((string)$imgInfo['mime']));
    if ($imgMime === 'image/jpg') {
        $imgMime = 'image/jpeg';
    }
    if ($finfoMime === 'image/jpg') {
        $finfoMime = 'image/jpeg';
    }

    $mimeType = $finfoMime !== '' ? $finfoMime : $imgMime;
    if (!in_array($mimeType, $allowedTypes, true) || $imgMime !== $mimeType) {
        brandRespond(['status' => 'error', 'message' => 'Use a valid PNG, JPG, GIF, or WebP image. SVG files are not permitted for security.'], 400);
    }

    $width = (int)($imgInfo[0] ?? 0);
    $height = (int)($imgInfo[1] ?? 0);
    if ($width <= 0 || $height <= 0 || $width > 8000 || $height > 8000) {
        brandRespond(['status' => 'error', 'message' => 'Image dimensions must be between 1x1 and 8000x8000 pixels.'], 400);
    }

    $ext = $extMap[$mimeType];
    $fileStem = isset($catalog[$assetKey])
        ? $catalog[$assetKey]['file_stem']
        : preg_replace('/[^a-z0-9_]/', '', $assetKey);
    if ($fileStem === '') {
        brandRespond(['status' => 'error', 'message' => 'Invalid asset filename stem.'], 400);
    }

    // Store custom uploads in dedicated untracked directory /admin/uploads/branding/
    // so packaged git-tracked defaults are never overwritten or lost on git pull.
    $brandingBase = __DIR__ . '/uploads/branding';
    $brandingWebPrefix = '/admin/uploads/branding';

    if (!is_dir($brandingBase) && !@mkdir($brandingBase, 0755, true) && !is_dir($brandingBase)) {
        $ref = reportInternalError('branding_upload_mkdir', 'Failed to create ' . $brandingBase);
        brandRespond([
            'status'    => 'error',
            'error_ref' => 'SSMS:' . $ref,
            'message'   => 'Upload storage folder could not be created. Please check directory permissions on admin/uploads/branding/.',
        ], 500);
    }

    $realBase = realpath($brandingBase);
    if ($realBase !== false && !is_writable($realBase)) {
        @chmod($realBase, 0755);
    }
    if ($realBase === false || !is_writable($realBase)) {
        $ref = reportInternalError('branding_upload_unwritable', 'Unwritable dir ' . $brandingBase);
        brandRespond([
            'status'    => 'error',
            'error_ref' => 'SSMS:' . $ref,
            'message'   => 'Upload storage folder is not writable by the web server. Please check permissions on admin/uploads/branding/.',
        ], 500);
    }

    $newFilename = $fileStem . '.' . $ext;
    $destPath = $realBase . '/' . $newFilename;
    $webPath = $brandingWebPrefix . '/' . $newFilename;
    $origName = basename((string)($file['name'] ?? $newFilename));
    if (strlen($origName) > 250) {
        $origName = substr($origName, 0, 250);
    }

    // Collect any previous *uploaded* files for this slot (e.g., an older extension
    // or the same filename being replaced) so we swap atomically with rollback
    // safety — while NEVER touching packaged git-tracked defaults.
    $replaceFiles = [];
    foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $oldExt) {
        $candidate = $realBase . '/' . $fileStem . '.' . $oldExt;
        if (is_file($candidate)) {
            $replaceFiles[$candidate] = $candidate;
        }
    }
    $prevDbWeb = \App\Services\BrandingAssetService::stripQuery((string)($asset['file_path'] ?? ''));
    if ($prevDbWeb !== '' && brandIsDeletableUpload($prevDbWeb)) {
        $prevDisk = brandResolveAssetFile($prevDbWeb, $docRoot);
        if ($prevDisk !== null && is_file($prevDisk)) {
            $replaceFiles[$prevDisk] = $prevDisk;
        }
    }

    $rollbackToken = bin2hex(random_bytes(6));
    $stagedBackups = [];
    $movedNew = false;

    $restoreFiles = static function () use (&$movedNew, $destPath, &$stagedBackups): void {
        if ($movedNew && is_file($destPath)) {
            @unlink($destPath);
            $movedNew = false;
        }
        foreach (array_reverse($stagedBackups) as $pair) {
            if (is_file($pair['backup'])) {
                @rename($pair['backup'], $pair['orig']);
            }
        }
        $stagedBackups = [];
    };

    try {
        foreach (array_values($replaceFiles) as $idx => $oldPath) {
            $backupPath = $oldPath . '.rollback-' . $rollbackToken . '-' . $idx;
            if (!@rename($oldPath, $backupPath)) {
                throw new RuntimeException('Failed to stage previous branding file for atomic swap.');
            }
            $stagedBackups[] = ['orig' => $oldPath, 'backup' => $backupPath];
        }

        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new RuntimeException('Failed to move uploaded branding file into place.');
        }
        @chmod($destPath, 0644);
        $movedNew = true;

        $conn->begin_transaction();
        $stmt = $conn->prepare("
            UPDATE system_branding
            SET file_path = ?, original_name = ?, mime_type = ?, file_size = ?, uploaded_by = ?, uploaded_at = NOW()
            WHERE asset_key = ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare system_branding update: ' . $conn->error);
        }
        $stmt->bind_param('sssiis', $webPath, $origName, $mimeType, $actualSize, $adminId, $assetKey);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Failed to execute system_branding update: ' . $err);
        }
        $stmt->close();
        $conn->commit();
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rbErr) {
            // No active transaction to roll back.
        }
        $restoreFiles();
        $ref = reportInternalError('branding_upload_failed', $e);
        brandRespond([
            'status'    => 'error',
            'error_ref' => 'SSMS:' . $ref,
            'message'   => 'Could not save the uploaded image (ref SSMS:' . $ref . '). Please try again.',
        ], 500);
    }

    // Commit succeeded — remove staged rollback backups.
    foreach ($stagedBackups as $pair) {
        if (is_file($pair['backup'])) {
            @unlink($pair['backup']);
        }
    }

    \App\Services\BrandingAssetService::clearCache();

    $mtime = (int)(@filemtime($destPath) ?: time());
    brandRespond([
        'status'      => 'success',
        'message'     => ($asset['asset_label'] ?: 'Branding asset') . ' uploaded successfully!',
        'asset_key'   => $assetKey,
        'file_path'   => $webPath,
        'web_url'     => $webPath . '?v=' . $mtime,
        'size_kb'     => round($actualSize / 1024, 1),
        'is_packaged' => false,
    ]);
}

// ============================================================
// ACTION: DELETE AN ASSET FILE (RESTORES PACKAGED DEFAULT IF ANY)
// ============================================================
if ($action === 'delete_asset' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $assetKey = trim((string)($_POST['asset_key'] ?? ''));
    if ($assetKey === '' || $assetKey[0] === '_' || !preg_match('/^[a-z0-9_]{2,50}$/', $assetKey)) {
        brandRespond(['status' => 'error', 'message' => 'Missing or invalid asset key.'], 400);
    }

    $stmt = $conn->prepare("SELECT * FROM system_branding WHERE asset_key = ? LIMIT 1");
    if (!$stmt) {
        reportInternalError('branding_delete_lookup', $conn->error);
        brandRespond(['status' => 'error', 'message' => 'Database error while looking up asset.'], 500);
    }
    $stmt->bind_param('s', $assetKey);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$asset) {
        brandRespond(['status' => 'error', 'message' => 'Asset not found.'], 404);
    }

    $oldPath = \App\Services\BrandingAssetService::stripQuery((string)($asset['file_path'] ?? ''));
    if ($oldPath !== '' && brandIsDeletableUpload($oldPath)) {
        $oldDisk = brandResolveAssetFile($oldPath, $docRoot);
        if ($oldDisk !== null && is_file($oldDisk)) {
            @unlink($oldDisk);
        }
    }

    // Also clean any leftover alternate-extension custom upload for built-in stems
    // inside admin/uploads/branding/.
    $catalog = brandBuiltinCatalog();
    $brandingBase = realpath(__DIR__ . '/uploads/branding');
    if ($brandingBase !== false) {
        $stem = isset($catalog[$assetKey])
            ? $catalog[$assetKey]['file_stem']
            : preg_replace('/[^a-z0-9_]/', '', $assetKey);
        if ($stem !== '') {
            foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $ext) {
                $candidate = $brandingBase . '/' . $stem . '.' . $ext;
                if (is_file($candidate)) {
                    @unlink($candidate);
                }
            }
        }
    }

    $stmt = $conn->prepare("
        UPDATE system_branding
        SET file_path = NULL, original_name = NULL, mime_type = NULL, file_size = 0, uploaded_by = NULL
        WHERE asset_key = ?
    ");
    if (!$stmt) {
        reportInternalError('branding_delete_update', $conn->error);
        brandRespond(['status' => 'error', 'message' => 'Database error while removing asset.'], 500);
    }
    $stmt->bind_param('s', $assetKey);
    $stmt->execute();
    $stmt->close();

    \App\Services\BrandingAssetService::clearCache();

    $fallback = brandPackagedFallback($assetKey, $docRoot);
    $restoredUrl = null;
    if ($fallback !== null) {
        $mtime = (int)@filemtime($fallback['disk']);
        $restoredUrl = $fallback['web'] . ($mtime > 0 ? ('?v=' . $mtime) : '');
    }

    $msg = $restoredUrl !== null
        ? ($asset['asset_label'] . ' reset to the default packaged image.')
        : ($asset['asset_label'] . ' removed.');

    brandRespond([
        'status'       => 'success',
        'message'      => $msg,
        'asset_key'    => $assetKey,
        'restored_url' => $restoredUrl,
    ]);
}

// ============================================================
// ACTION: UPDATE ASSET LABEL
// ============================================================
if ($action === 'update_label' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $assetKey = trim((string)($_POST['asset_key'] ?? ''));
    $label = trim((string)($_POST['label'] ?? $_POST['asset_label'] ?? ''));

    if ($assetKey === '' || $label === '') {
        brandRespond(['status' => 'error', 'message' => 'Asset key and label are required.'], 400);
    }

    $label = mb_substr(strip_tags($label), 0, 100);
    $stmt = $conn->prepare("UPDATE system_branding SET asset_label = ? WHERE asset_key = ?");
    if (!$stmt) {
        reportInternalError('branding_update_label', $conn->error);
        brandRespond(['status' => 'error', 'message' => 'Database error while updating label.'], 500);
    }
    $stmt->bind_param('ss', $label, $assetKey);
    $stmt->execute();
    $stmt->close();

    brandRespond(['status' => 'success', 'message' => 'Label updated.']);
}

// ============================================================
// ACTION: ADD CUSTOM ASSET SLOT
// ============================================================
if ($action === 'add_asset' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $label = mb_substr(strip_tags(trim((string)($_POST['asset_label'] ?? ''))), 0, 100);
    $rawKey = trim((string)($_POST['asset_key'] ?? ''));
    $key = $rawKey !== ''
        ? preg_replace('/[^a-z0-9_]/', '', strtolower($rawKey))
        : trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label)), '_');

    if ($label === '' || strlen($key) < 2) {
        brandRespond(['status' => 'error', 'message' => 'Please enter a valid asset name.'], 400);
    }
    if (strlen($key) > 50) {
        $key = substr($key, 0, 50);
    }

    $reserved = ['logo', 'seal', 'sig_head', 'sig_admin', 'card_bg', '_id_card_settings'];
    if (in_array($key, $reserved, true) || $key[0] === '_') {
        brandRespond(['status' => 'error', 'message' => 'That asset key is reserved.'], 400);
    }

    $chk = $conn->prepare("SELECT id FROM system_branding WHERE asset_key = ? LIMIT 1");
    $chk->bind_param('s', $key);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        brandRespond(['status' => 'error', 'message' => 'An asset with this key already exists.'], 409);
    }
    $chk->close();

    $stmt = $conn->prepare("INSERT INTO system_branding (asset_key, asset_label) VALUES (?, ?)");
    if (!$stmt) {
        reportInternalError('branding_add_asset', $conn->error);
        brandRespond(['status' => 'error', 'message' => 'Database error while creating asset slot.'], 500);
    }
    $stmt->bind_param('ss', $key, $label);
    $stmt->execute();
    $stmt->close();

    brandRespond(['status' => 'success', 'message' => 'Asset slot created.', 'asset_key' => $key]);
}

// ============================================================
// ACTION: DELETE CUSTOM ASSET SLOT (cannot delete built-in 5)
// ============================================================
if ($action === 'remove_asset_slot' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $assetKey = trim((string)($_POST['asset_key'] ?? ''));
    $builtIn = array_merge(array_keys(brandBuiltinCatalog()), ['_id_card_settings']);
    if ($assetKey === '' || in_array($assetKey, $builtIn, true)) {
        brandRespond(['status' => 'error', 'message' => 'Cannot remove built-in system assets.'], 400);
    }

    $stmt = $conn->prepare("SELECT file_path FROM system_branding WHERE asset_key = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $assetKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['file_path']) && brandIsDeletableUpload((string)$row['file_path'])) {
            $disk = brandResolveAssetFile((string)$row['file_path'], $docRoot);
            if ($disk !== null && is_file($disk)) {
                @unlink($disk);
            }
        }
    }

    $stmt = $conn->prepare("DELETE FROM system_branding WHERE asset_key = ? AND asset_key NOT IN ('logo','seal','sig_head','sig_admin','card_bg','_id_card_settings')");
    if ($stmt) {
        $stmt->bind_param('s', $assetKey);
        $stmt->execute();
        $stmt->close();
    }

    brandRespond(['status' => 'success', 'message' => 'Custom asset removed.']);
}

// ============================================================
// ACTION: SAVE ID CARD LAYOUT & DISPLAY SETTINGS
// ============================================================
if ($action === 'save_settings' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $raw = $_POST['settings'] ?? '';
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        $decoded = $_POST;
    }

    $merged = \App\Services\IdCardLayout::sanitize(
        array_merge(\App\Services\IdCardLayout::load($conn), $decoded)
    );
    $json = json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $conn->query("INSERT IGNORE INTO system_branding (asset_key, asset_label) VALUES ('_id_card_settings', 'ID Card Display Settings')");
    $stmt = $conn->prepare("UPDATE system_branding SET original_name = ? WHERE asset_key = '_id_card_settings'");
    if (!$stmt) {
        reportInternalError('branding_save_settings_prepare', $conn->error);
        brandRespond(['status' => 'error', 'message' => 'Unable to save ID card layout.'], 500);
    }
    $stmt->bind_param('s', $json);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        reportInternalError('branding_save_settings_exec', $err);
        brandRespond(['status' => 'error', 'message' => 'Unable to save ID card layout.'], 500);
    }
    $stmt->close();

    brandRespond([
        'status'   => 'success',
        'message'  => 'Saved. Every member ID card will use this layout.',
        'settings' => $merged,
    ]);
}

brandRespond(['status' => 'error', 'message' => 'Unknown action.'], 400);
