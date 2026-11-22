<?php
/**
 * QR library loader for the ID-card subsystem.
 *
 * The upstream phpqrcode distribution ships both as a self-contained
 * single-file build (phpqrcode.php, with cache and file-logging disabled
 * so it never tries to write into the code tree or read zlib cache files)
 * and as a multi-file set (qrlib.php + parts).
 *
 * This loader prefers phpqrcode.php and falls back to qrlib.php, breaking
 * only once QRcode is genuinely defined so a partial/broken candidate
 * still falls through to the backup build.
 */

if (!class_exists('QRcode', false)) {
    $__qrCandidates = [
        __DIR__ . '/phpqrcode/phpqrcode.php',
        __DIR__ . '/phpqrcode/qrlib.php',
    ];
    foreach ($__qrCandidates as $__qrFile) {
        if (is_file($__qrFile)) {
            require_once $__qrFile;
            if (class_exists('QRcode', false)) {
                break;
            }
        }
    }
    unset($__qrCandidates, $__qrFile);
}
