<?php
/** Generate or renew one ID card through an authorized, CSRF-protected POST. */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../backend/services/SecurityAuditService.php';
require_once __DIR__ . '/../backend/services/IdentityCodeService.php';
require_once __DIR__ . '/../backend/services/MemberCategory.php';

use App\Services\IdentityCodeService;
use App\Services\MemberCategory;
use App\Services\SecurityAuditService;

$idCardFail = static function (int $statusCode, string $message): never {
    http_response_code($statusCode);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $backUrl = match ((string)($_SESSION['admin_role'] ?? '')) {
        'super_admin' => ssms_app_url('admin/dashboards/super-admin.php') . '?section=idcards',
        'school_admin' => ssms_app_url('admin/dashboards/school_admin.php') . '?section=idcards',
        default => ssms_app_url('admin/dashboards/hr-dept.php') . '?section=idcards',
    };
    $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeBack = htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>ID Card Generation</title>'
        . '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;color:#1e293b;margin:0;padding:2rem}'
        . '.card{max-width:520px;margin:3rem auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.5rem 1.75rem;box-shadow:0 4px 16px rgba(15,23,42,.06)}'
        . 'h1{font-size:1.1rem;margin:0 0 .5rem;color:#991b1b}p{margin:0 0 1.25rem;line-height:1.5}'
        . 'a{display:inline-block;background:#1e293b;color:#fff;text-decoration:none;padding:.55rem 1rem;border-radius:8px;font-size:.9rem;font-weight:600}</style>'
        . '</head><body><div class="card"><h1>ID Card Generation</h1><p>' . $safeMsg . '</p>'
        . '<a href="' . $safeBack . '">&larr; Back to ID Cards</a></div></body></html>';
    exit;
};

// SECURITY: ID-card generation mutates member rows and writes assets —
// an authenticated admin session is mandatory (CSRF alone is not enough,
// anonymous sessions also receive CSRF tokens).
if (!isLoggedIn()) {
    $idCardFail(401, 'Authentication required.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    $idCardFail(405, 'Method not allowed.');
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    $idCardFail(403, 'Invalid security token. Refresh the page and try again.');
}

$memberId = filter_var($_POST['member_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$action = (string)($_POST['action'] ?? 'generate');
if ($memberId === false || !in_array($action, ['generate', 'renew'], true)) {
    $idCardFail(422, 'Invalid ID-card request.');
}

require_once __DIR__ . '/libs/qr_loader.php';
if (!class_exists('QRcode')) {
    error_log('ID-card QR library is unavailable (QRcode class not loaded).');
    $idCardFail(503, 'ID-card generation is temporarily unavailable.');
}

if (!extension_loaded('gd') || !function_exists('imagepng')) {
    error_log('ID-card generation unavailable: PHP GD extension with PNG support is not loaded.');
    $idCardFail(503, 'ID-card generation is temporarily unavailable.');
}

$qrDirectory = __DIR__ . '/assets/qr';
if (!is_dir($qrDirectory)) {
    error_log('The deployment-managed ID-card QR directory is unavailable (missing directory: ' . $qrDirectory . ').');
    $idCardFail(503, 'ID-card generation is temporarily unavailable.');
}
if (!is_writable($qrDirectory)) {
    error_log('The deployment-managed ID-card QR directory is unavailable (directory not writable: ' . $qrDirectory . ').');
    $idCardFail(503, 'ID-card generation is temporarily unavailable.');
}

if (!($pdo instanceof PDO)) {
    error_log('ID-card generation unavailable: PDO database connection is not initialized.');
    $idCardFail(503, 'ID-card generation is temporarily unavailable.');
}

$tempPath = null;
$finalPath = null;
$backupPath = null;
$installedNewFile = false;

try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare(
        'SELECT id, member_code, age_group, registration_type, member_type, membership_tier, status, id_card_status
         FROM members WHERE id = ? FOR UPDATE'
    );
    $statement->execute([(int)$memberId]);
    $member = $statement->fetch(PDO::FETCH_ASSOC);
    // H4 (updated 2026-11-18): promotions now flip waiting→direct, but
    // legacy permanent+waiting rows were never backfilled by decision —
    // the tier=permanent clause covers them. Keep in sync with
    // MemberDirectoryService eligible_for_id_card.
    $eligible = $member
        && $member['status'] === 'active'
        && (
            in_array($member['registration_type'], ['direct', 'transfer'], true)
            || $member['member_type'] === 'honorary'
            || ($member['membership_tier'] ?? '') === 'permanent'
        );
    if (!$eligible) {
        throw new DomainException('The member is not eligible for an ID card.');
    }

    $memberCode = trim((string)($member['member_code'] ?? ''));
    if ($memberCode === '') {
        if ($conn instanceof mysqli && !$conn->connect_error) {
            $segments = IdentityCodeService::resolveStaffSegments($conn, (int)$memberId);
            if ($segments !== null && $segments !== []) {
                $memberCode = IdentityCodeService::allocateStaff($conn, $segments);
            } else {
                $letter = MemberCategory::letterFor((string)($member['age_group'] ?? ''));
                if ($letter !== null) {
                    $memberCode = IdentityCodeService::allocateStudent($conn, $letter);
                }
            }
        }
        if ($memberCode === '') {
            throw new DomainException('This member does not have an identity code yet. Assign an age group or position first.');
        }
        $statement = $pdo->prepare('UPDATE members SET member_code = ? WHERE id = ?');
        $statement->execute([$memberCode, (int)$memberId]);
    }

    $baseUrl = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '';
    $qrContent = $baseUrl . '/member.php?code=' . rawurlencode($memberCode);
    $filename = 'qr_member_' . (int)$memberId . '.png';
    $finalPath = $qrDirectory . '/' . $filename;
    $webPath = '/admin/id_cards/assets/qr/' . $filename;
    $tempPath = tempnam($qrDirectory, '.qr-');
    if ($tempPath === false) {
        throw new RuntimeException('Could not allocate a QR output file.');
    }

    QRcode::png($qrContent, $tempPath, QR_ECLEVEL_L, 4, 2);
    if (!is_file($tempPath) || filesize($tempPath) < 32) {
        throw new RuntimeException('QR generation did not produce a valid image.');
    }
    @chmod($tempPath, 0644);

    if (!SecurityAuditService::record(
        $pdo,
        'Member ID Card ' . ($action === 'renew' ? 'Renewed' : 'Generated'),
        ['action' => $action],
        'member',
        (int)$memberId
    )) {
        throw new RuntimeException('ID-card audit recording failed.');
    }

    $refreshIssueDate = $action === 'renew' || ($member['id_card_status'] ?? 'none') !== 'generated';
    $statement = $pdo->prepare(
        $refreshIssueDate
            ? "UPDATE members
               SET qr_code_path = ?, id_card_status = 'generated', id_card_generated_at = CURRENT_TIMESTAMP
               WHERE id = ?"
            : 'UPDATE members SET qr_code_path = ? WHERE id = ?'
    );
    $statement->execute([$webPath, (int)$memberId]);

    // Replace the generated asset atomically and keep the old image available
    // for restoration if the database commit fails.
    if (is_file($finalPath)) {
        $backupPath = $finalPath . '.bak-' . bin2hex(random_bytes(6));
        if (!rename($finalPath, $backupPath)) {
            throw new RuntimeException('Could not preserve the prior QR image.');
        }
    }
    if (!rename($tempPath, $finalPath)) {
        throw new RuntimeException('Could not install the generated QR image.');
    }
    $tempPath = null;
    $installedNewFile = true;

    $pdo->commit();
    if ($backupPath !== null && is_file($backupPath)) {
        @unlink($backupPath);
    }

    header('Location: ' . ssms_app_url('admin/id_cards/view_id_card.php') . '?member_id=' . (int)$memberId, true, 303);
    exit;
} catch (DomainException $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($tempPath !== null && is_file($tempPath)) {
        @unlink($tempPath);
    }
    $idCardFail(422, $error->getMessage());
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($tempPath !== null && is_file($tempPath)) {
        @unlink($tempPath);
    }
    if ($installedNewFile && $finalPath !== null && is_file($finalPath)) {
        @unlink($finalPath);
    }
    if ($backupPath !== null && is_file($backupPath) && $finalPath !== null) {
        @rename($backupPath, $finalPath);
    }
    error_log(sprintf('ID-card generation failed (%s): %s', get_class($error), $error->getMessage()));
    $idCardFail(500, 'ID-card generation failed. Please try again.');
}
