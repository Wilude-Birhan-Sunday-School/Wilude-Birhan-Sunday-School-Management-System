<?php
// FILE: /admin/id_cards/view_id_card.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/libs/eth_date_helper.php';
require_once __DIR__ . '/../backend/services/IdCardLayout.php';

// SECURITY (IDOR fix): this page renders member PII (photo, name, phone,
// address, guardian). It used to rely on nothing but obscurity — anyone
// could sweep ?member_id=1..N. Authenticated admin session is mandatory,
// mirroring the dashboard router convention.
if (!isLoggedIn()) {
    header('Location: ' . ssms_app_url('admin/index.php'));
    exit;
}

$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$backDashboard = match ((string)($_SESSION['admin_role'] ?? '')) {
    'super_admin' => ssms_app_url('admin/dashboards/super-admin.php'),
    'school_admin' => ssms_app_url('admin/dashboards/school_admin.php'),
    default => ssms_app_url('admin/dashboards/hr-dept.php'),
};
$stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if (!$member) die("Member not found");

// ASSETS — Load from system_branding table (managed via Super Admin > Branding)
// with cache-busting ?v=mtime and theme-aware packaged fallbacks.
$CONFIG = [
    'logo'      => \App\Services\BrandingAssetService::assetUrl($conn, 'logo', '', true),
    'seal'      => \App\Services\BrandingAssetService::assetUrl($conn, 'seal', '', true),
    'sig_head'  => \App\Services\BrandingAssetService::assetUrl($conn, 'sig_head', '', true),
    'sig_admin' => \App\Services\BrandingAssetService::assetUrl($conn, 'sig_admin', '', true),
];
$DISPLAY = [
    'logo_size' => 100, 'logo_opacity' => 100,
    'seal_size' => 150, 'seal_opacity' => 85,
    'sig_head_size' => 140, 'sig_head_opacity' => 90,
    'sig_admin_size' => 140, 'sig_admin_opacity' => 90,
];

$layout = \App\Services\IdCardLayout::load($conn);
$idCardBg = \App\Services\IdCardLayout::background($conn);
$ID_CARD_STYLE = \App\Services\IdCardLayout::cssVars($layout, $idCardBg);
$ID_CARD_LAYOUT = $layout;
$DISPLAY['logo_size'] = (int)$layout['logo_size'];
$DISPLAY['logo_opacity'] = (int)$layout['logo_opacity'];
$DISPLAY['seal_size'] = (int)$layout['seal_size'];
$DISPLAY['seal_opacity'] = (int)$layout['seal_opacity'];
$DISPLAY['sig_head_size'] = (int)$layout['sig_head_size'];
$DISPLAY['sig_head_opacity'] = (int)$layout['sig_head_opacity'];
$DISPLAY['sig_admin_size'] = (int)$layout['sig_admin_size'];
$DISPLAY['sig_admin_opacity'] = (int)$layout['sig_admin_opacity'];

// --- ETHIOPIAN DATES ---
$issueDateGregorian = $member['id_card_generated_at'] ?? date('Y-m-d');
$issueDateEth = toEthiopianDate($issueDateGregorian);
$expiryDateGregorian = date('Y-m-d', strtotime($issueDateGregorian . ' + 4 years'));
$expiryDateEth = toEthiopianDate($expiryDateGregorian);
$isExpired = isExpired($member['id_card_generated_at']);

// DATA PREP
$full_name = ($member['student_name'] ?? '') . ' ' . ($member['father_name'] ?? '') . ' ' . ($member['grandfather_name'] ?? '');
$christian_name = !empty($member['baptismal_name']) ? $member['baptismal_name'] : '-----------';

// Age Calculation
// Calculate current Ethiopian year dynamically
// Ethiopian new year (Meskerem 1) falls on Sep 11 (or Sep 12 in leap years)
$gcYear = (int)date('Y');
$gcMonth = (int)date('n');
$gcDay = (int)date('j');
// Before Sep 11, Ethiopian year = Gregorian year - 8; after Sep 11, it's Gregorian year - 7
if ($gcMonth < 9 || ($gcMonth === 9 && $gcDay < 11)) {
    $currentYearEth = $gcYear - 8;
} else {
    $currentYearEth = $gcYear - 7;
}
if (!empty($member['dob_ec_year'])) {
    $age = $currentYearEth - $member['dob_ec_year'];
} elseif (!empty($member['date_of_birth'])) {
    $age = date('Y') - date('Y', strtotime($member['date_of_birth']));
} else {
    $age = '--';
}

$filename = "ID_" . $member['member_code'];

// Address Construction
$address_parts = [];
if (!empty($member['city'])) $address_parts[] = $member['city'];
if (!empty($member['sub_city'])) $address_parts[] = $member['sub_city'];
if (!empty($member['woreda'])) $address_parts[] = 'Wor. ' . $member['woreda'];
if (!empty($member['house_number'])) $address_parts[] = 'H.No ' . $member['house_number'];

$member['address'] = !empty($address_parts) ? implode(', ', $address_parts) : ($member['address'] ?? '---');

// Fix Image Path for ID Card (needs to be relative to this file or absolute)
// This file is in admin/id_cards/
// Images are in admin/uploads/members/photos/
// DB path is usually uploads/members/photos/...
// So we need ../uploads/members/photos/...

if (!empty($member['student_photo_path'])) {
    // 1. Normalize: remove leading slash
    $p = ltrim($member['student_photo_path'], '/');
    
    // 2. Remove 'admin/' prefix if present (to get clean 'uploads/...')
    if (strpos($p, 'admin/') === 0) {
        $p = substr($p, 6);
    }
    
    // 3. Build BOTH a relative path (for file_exists check) and an absolute URL
    // Relative from id_cards/ to admin/: ../uploads/...
    $relativePath = '../' . $p;
    
    // 4. Check if file actually exists on disk
    $absCheck = __DIR__ . '/' . $relativePath;
    if (file_exists($absCheck)) {
        // Use absolute web URL for background-image (html2canvas resolves bg-images
        // from the page URL context, so absolute URLs are most reliable)
        $member['student_photo_path'] = '/admin/' . $p;
    } else {
        // File missing — clear the path so template shows empty placeholder
        $member['student_photo_path'] = '';
    }
}

// Fix QR Code Path and self-heal using the canonical qr_member_{id}.png filename
// shared with generate_id_card.php and IdentityCodeService::regenerateQr().
$canonicalQrFile = 'qr_member_' . (int)$member_id . '.png';
$canonicalQrRel = 'assets/qr/' . $canonicalQrFile;
$canonicalQrWeb = '/admin/id_cards/' . $canonicalQrRel;
$canonicalQrDisk = __DIR__ . '/' . $canonicalQrRel;
$resolvedQrDisk = null;
$resolvedQrRel = '';

if (!empty($member['qr_code_path'])) {
    $qp = ltrim((string)$member['qr_code_path'], '/');
    if (strpos($qp, 'admin/id_cards/') === 0) {
        $qp = substr($qp, strlen('admin/id_cards/'));
    } elseif (strpos($qp, 'admin/') === 0) {
        $qp = '../' . substr($qp, 6);
    } else {
        $qp = '../' . $qp;
    }
    $candidateDisk = __DIR__ . '/' . $qp;
    if (is_file($candidateDisk) && filesize($candidateDisk) >= 32) {
        $resolvedQrDisk = $candidateDisk;
        $resolvedQrRel = $qp;
    }
}

if ($resolvedQrDisk === null && is_file($canonicalQrDisk) && filesize($canonicalQrDisk) >= 32) {
    $resolvedQrDisk = $canonicalQrDisk;
    $resolvedQrRel = $canonicalQrRel;
    if (($member['qr_code_path'] ?? '') !== $canonicalQrWeb) {
        $syncStmt = $conn->prepare('UPDATE members SET qr_code_path = ? WHERE id = ?');
        if ($syncStmt) {
            $syncStmt->bind_param('si', $canonicalQrWeb, $member_id);
            $syncStmt->execute();
            $syncStmt->close();
        }
    }
}

if ($resolvedQrDisk === null && !empty($member['member_code'])) {
    require_once __DIR__ . '/libs/qr_loader.php';
    if (class_exists('QRcode') && extension_loaded('gd') && function_exists('imagepng')) {
        $qr_dir = __DIR__ . '/assets/qr';
        if (!is_dir($qr_dir)) {
            @mkdir($qr_dir, 0755, true); // 0755, never world-writable
        }
        if (is_dir($qr_dir) && is_writable($qr_dir)) {
            $qr_content = rtrim((string)SITE_URL, '/') . '/member.php?code=' . rawurlencode((string)$member['member_code']);
            $tmpQr = tempnam($qr_dir, '.qr-');
            if ($tmpQr !== false) {
                try {
                    QRcode::png($qr_content, $tmpQr, QR_ECLEVEL_L, 4, 2);
                    if (is_file($tmpQr) && filesize($tmpQr) >= 32) {
                        @chmod($tmpQr, 0644);
                        if (@rename($tmpQr, $canonicalQrDisk) || @copy($tmpQr, $canonicalQrDisk)) {
                            $resolvedQrDisk = $canonicalQrDisk;
                            $resolvedQrRel = $canonicalQrRel;
                            $update_stmt = $conn->prepare('UPDATE members SET qr_code_path = ? WHERE id = ?');
                            if ($update_stmt) {
                                $update_stmt->bind_param('si', $canonicalQrWeb, $member_id);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                        }
                    }
                } catch (Throwable $qrError) {
                    error_log('ID-card viewer QR self-heal failed: ' . $qrError->getMessage());
                } finally {
                    if (is_file($tmpQr)) {
                        @unlink($tmpQr);
                    }
                }
            }
        } else {
            error_log('ID-card viewer QR directory unavailable: ' . $qr_dir);
        }
    }
}

if ($resolvedQrDisk !== null && $resolvedQrRel !== '') {
    $qrVer = (int)@filemtime($resolvedQrDisk);
    $qrWebUrl = str_starts_with($resolvedQrRel, '../')
        ? ssms_app_url('admin/' . ltrim(substr($resolvedQrRel, 3), '/'))
        : ssms_app_url('admin/id_cards/' . ltrim($resolvedQrRel, '/'));
    $member['qr_code_path'] = $qrWebUrl . ($qrVer > 0 ? ('?v=' . $qrVer) : '');
} else {
    $member['qr_code_path'] = '';
}

// Map DB columns to Template variables
$member['emergency_name'] = $member['guardian_name'] ?? '---';
$member['emergency_phone'] = $member['guardian_phone1'] ?? '---';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card - <?php echo htmlspecialchars((string)$member['member_code'], ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha384-ZZ1pncU3bQe8y31yfZdMFdSpttDoPmOZg2wguVK9almUodir1PghgT0eY7Mrty8H" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha384-JcnsjUPPylna1s1fvi1u12X5qjY5OL56iySh75FdtrwhO/SWXgMjoVqcKyIIWOLk" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700;900&display=swap">
    <link rel="stylesheet" href="/admin/css/id_card.css?v=20260823c">
    <style>
        /* page chrome only — card look lives in id_card.css */
        
        body { background: #374151; font-family: 'Noto Sans Ethiopic', 'Noto Serif Ethiopic', sans-serif; }
        /* PREVIEW ON SCREEN (Scaled Down CSS) */
        .preview-wrapper {
            transform: scale(0.6);
            transform-origin: top center;
            margin-bottom: -180px;
        }
        .preview-wrapper .id-card-template { margin-bottom: 28px; }
        #export-container .id-card-template { margin-bottom: 0; }
        
        /* EXPORT CONTAINER (Hidden from user, but visible to code) */
        #export-container {
            position: fixed;
            left: -9999px; /* Move off screen */
            top: 0;
        }
    </style>
<link rel="stylesheet" href="/admin/css/mobile.css?v=<?= SSMS_ASSET_VER ?>">
</head>
<body class="flex flex-col items-center p-6">

    <!-- TOP MENU -->
    <div class="fixed top-0 left-0 w-full bg-white shadow-lg p-3 flex justify-between items-center z-50">
        <div class="flex items-center gap-4">
            <a href="<?= e($backDashboard) ?>?section=idcards" class="text-gray-600 hover:text-black font-bold flex items-center gap-2">&larr; Back</a>
            <?php if($isExpired): ?>
                <div class="bg-red-100 text-red-700 px-3 py-1 rounded font-bold text-sm flex items-center gap-2">
                    <span class="w-2 h-2 bg-red-600 rounded-full animate-pulse"></span> EXPIRED
                </div>
            <?php else: ?>
                <div class="bg-green-100 text-green-700 px-3 py-1 rounded font-bold text-sm">
                    Active (Exp: <?php echo $expiryDateEth; ?>)
                </div>
            <?php endif; ?>
        </div>
        <div class="flex gap-2">
            <!-- RENEW BUTTON -->
            <form method="post" action="generate_id_card.php" id="dcRenewCardForm">
                <?= csrf_field() ?>
                <input type="hidden" name="member_id" value="<?= (int)$member_id ?>">
                <input type="hidden" name="action" value="renew">
                <button type="submit"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded shadow font-semibold flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-rotate"></i> Update Date
                </button>
            </form>
            <button onclick="downloadPNG('front')" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded shadow font-semibold text-sm">PNG Front</button>
            <button onclick="downloadPNG('back')" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded shadow font-semibold text-sm">PNG Back</button>
            <button onclick="downloadPDF()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded shadow font-semibold text-sm">Download PDF</button>
        </div>
    </div>

    <div class="h-24"></div>

    <!-- PREVIEW AREA (What you see on screen) -->
    <div class="text-white font-bold mb-2">Card Preview (Front & Back)</div>
    <div class="preview-wrapper">
        <!-- We clone the layout here just for viewing -->
        <?php include 'id_card_template_layout.php'; ?>
    </div>


    <!-- EXPORT AREA (Hidden off-screen, Full Size for High Quality) -->
    <div id="export-container">
        <!-- This includes the exact same HTML but 100% scale -->
        <?php include 'id_card_template_layout.php'; ?>
    </div>

    <script>
        const memberCode = "<?php echo $filename; ?>";
        
        // Helper to get the correct element from the HIDDEN container
        function getExportElement(side) {
            const container = document.getElementById('export-container');
            const cards = container.querySelectorAll('.id-card-template');
            return side === 'front' ? cards[0] : cards[1];
        }

        function downloadPNG(side) {
            const element = getExportElement(side);
            html2canvas(element, {
                scale: 2,
                useCORS: true,
                allowTaint: false,
                backgroundColor: "#f3e6c4",
                logging: false,
                // Ensure the off-screen container is rendered at correct size
                width: 1011,
                height: 638,
                onclone: function(clonedDoc) {
                    // Ensure the cloned export container is visible for rendering
                    const container = clonedDoc.getElementById('export-container');
                    if (container) {
                        container.style.position = 'absolute';
                        container.style.left = '0';
                        container.style.top = '0';
                    }
                }
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = `${memberCode}_${side}.png`;
                link.href = canvas.toDataURL("image/png");
                link.click();
            }).catch(err => {
                console.error('Export error:', err);
                alert('Download failed. If images are missing, ensure they are uploaded to the server.');
            });
        }

        async function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('l', 'mm', [85.6, 54]); 
            
            const frontEl = getExportElement('front');
            const backEl = getExportElement('back');

            try {
                const opts = { scale: 2, useCORS: true, allowTaint: false, backgroundColor: "#f3e6c4", width: 1011, height: 638, logging: false };

                const frontCanvas = await html2canvas(frontEl, opts);
                pdf.addImage(frontCanvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, 85.6, 54);
                
                pdf.addPage();
                const backCanvas = await html2canvas(backEl, opts);
                pdf.addImage(backCanvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, 85.6, 54);
                
                pdf.save(`${memberCode}_printable.pdf`);
            } catch(err) {
                console.error('PDF export error:', err);
                alert('PDF download failed.');
            }
        }
    </script>
<script src="/admin/js/danger_confirm.js?v=20261022dc4"></script>
<script>
(function () {
    var f = document.getElementById('dcRenewCardForm');
    if (f && window.DangerConfirm && window.DangerConfirm.guardForm) {
        window.DangerConfirm.guardForm(f, { tier: 'medium', title: 'Renew this ID card?', summary: 'A renewed card replaces the current one.', intent: 'I want to renew this card', consequences: ['The issue date is reset to TODAY and a new expiry date is calculated.', 'The card that was printed before shows old dates; print and hand out the new card.'], keeps: ['The member code and QR code stay the same.', 'Member data is not changed.'], phrase: 'RENEW CARD', confirmLabel: 'Renew card' });
    } else if (f) {
        f.addEventListener('submit', function (e) { e.preventDefault(); alert('The confirmation dialog did not load. Reload the page and try again.'); });
    }
})();
</script>
</body>
</html>