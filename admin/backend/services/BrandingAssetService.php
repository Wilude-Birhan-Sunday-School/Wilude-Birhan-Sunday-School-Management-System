<?php
/**
 * Single source of truth for resolving school branding assets (logo, seal,
 * signatures, ID card background) across the public website, admin portal,
 * member verification page, report cards, and ID card generator.
 *
 * Read-only, fail-soft, and request-cached: never executes DDL and degrades
 * cleanly to theme/packaged defaults if the database is unavailable.
 */

namespace App\Services;

class BrandingAssetService
{
    /** @var array<string,string>|null */
    private static ?array $dbMap = null;
    private static bool $dbAttempted = false;

    /** Reset the per-request cache (useful after an upload/delete in the same request). */
    public static function clearCache(): void
    {
        self::$dbMap = null;
        self::$dbAttempted = false;
    }

    /** Repo root directory (3 levels up from admin/backend/services). */
    public static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** Normalize a web path by stripping any query string/fragment. */
    public static function stripQuery(string $webPath): string
    {
        $clean = trim($webPath);
        if ($clean === '') {
            return '';
        }
        $qPos = strpos($clean, '?');
        if ($qPos !== false) {
            $clean = substr($clean, 0, $qPos);
        }
        $hPos = strpos($clean, '#');
        if ($hPos !== false) {
            $clean = substr($clean, 0, $hPos);
        }
        return $clean;
    }

    /**
     * Resolve a root-relative web path (e.g. /admin/uploads/branding/school_logo.png)
     * to a real, non-empty file on disk. Rejects path traversal.
     */
    public static function resolveDiskPath(string $webPath): ?string
    {
        $clean = self::stripQuery($webPath);
        if ($clean === '' || $clean[0] !== '/' || strpos($clean, '..') !== false || strpos($clean, "\0") !== false) {
            return null;
        }

        $roots = [self::projectRoot()];
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($docRoot !== '' && $docRoot !== $roots[0]) {
            $roots[] = $docRoot;
        }

        foreach ($roots as $root) {
            $disk = $root . $clean;
            if (is_file($disk) && @filesize($disk) > 32) {
                return $disk;
            }
        }
        return null;
    }

    /** Append a filemtime cache-buster (?v=...) so updated logos refresh immediately. */
    public static function versionedUrl(string $webPath, ?string $diskPath = null): string
    {
        $clean = self::stripQuery($webPath);
        if ($clean === '') {
            return '';
        }
        $disk = $diskPath ?? self::resolveDiskPath($clean);
        if ($disk === null) {
            return $clean;
        }
        $mtime = @filemtime($disk);
        if ($mtime === false || $mtime <= 0) {
            return $clean;
        }
        return $clean . '?v=' . $mtime;
    }

    /**
     * Ordered list of packaged (git-tracked) fallback web paths for a built-in
     * branding key. Respects ACTIVE_THEME / THEME_BASE_PATH so both FKSS and
     * WBSS (and any future school theme) resolve their own theme assets first.
     *
     * @return list<string>
     */
    public static function packagedCandidates(string $key): array
    {
        $themeBase = defined('THEME_BASE_PATH')
            ? rtrim((string)THEME_BASE_PATH, '/')
            : ('/themes/' . (defined('ACTIVE_THEME') ? ACTIVE_THEME : 'fkss'));

        $list = [];
        switch ($key) {
            case 'logo':
                if (defined('SCHOOL_LOGO_PATH') && SCHOOL_LOGO_PATH !== '') {
                    $list[] = (string)SCHOOL_LOGO_PATH;
                }
                $list[] = $themeBase . '/assets/logos/school_logo.png';
                if (defined('SCHOOL_LOGO_PATH_LEGACY') && SCHOOL_LOGO_PATH_LEGACY !== '') {
                    $list[] = (string)SCHOOL_LOGO_PATH_LEGACY;
                }
                $list[] = '/admin/id_cards/assets/logos/school_logo.png';
                break;

            case 'seal':
                if (defined('SCHOOL_SEAL_PATH') && SCHOOL_SEAL_PATH !== '') {
                    $list[] = (string)SCHOOL_SEAL_PATH;
                }
                $list[] = $themeBase . '/assets/seals/school_seal.png';
                if (defined('SCHOOL_SEAL_PATH_LEGACY') && SCHOOL_SEAL_PATH_LEGACY !== '') {
                    $list[] = (string)SCHOOL_SEAL_PATH_LEGACY;
                }
                $list[] = '/admin/id_cards/assets/seals/school_seal.png';
                break;

            case 'sig_head':
                $list[] = $themeBase . '/assets/signatures/head_signature.png';
                $list[] = '/admin/id_cards/assets/signatures/head_signature.png';
                break;

            case 'sig_admin':
                $list[] = $themeBase . '/assets/signatures/director_signature.png';
                $list[] = '/admin/id_cards/assets/signatures/director_signature.png';
                break;

            case 'card_bg':
                if (defined('ID_CARD_BACKGROUND') && ID_CARD_BACKGROUND !== '') {
                    $list[] = (string)ID_CARD_BACKGROUND;
                }
                $list[] = '/admin/id_cards/assets/backgrounds/id_card_bg.jpg';
                break;
        }

        // Deduplicate while preserving priority order.
        $unique = [];
        foreach ($list as $item) {
            $clean = self::stripQuery($item);
            if ($clean !== '' && !in_array($clean, $unique, true)) {
                $unique[] = $clean;
            }
        }
        return $unique;
    }

    /**
     * Return true when $webPath points to a git-tracked packaged default that
     * must NEVER be overwritten or unlinked by runtime branding uploads/deletes.
     */
    public static function isPackagedPath(string $webPath): bool
    {
        $clean = self::stripQuery($webPath);
        if ($clean === '') {
            return false;
        }
        if (strpos($clean, '/themes/') === 0) {
            return true;
        }
        foreach (['logo', 'seal', 'sig_head', 'sig_admin', 'card_bg'] as $k) {
            if (in_array($clean, self::packagedCandidates($k), true)) {
                return true;
            }
        }
        return in_array($clean, [
            '/admin/id_cards/assets/logos/school_logo.png',
            '/admin/id_cards/assets/backgrounds/id_card_bg.jpg',
        ], true);
    }

    /**
     * Load all branding rows once per request (fail-soft).
     *
     * @return array<string,string>
     */
    private static function loadFromDb(?\mysqli $conn): array
    {
        if (self::$dbMap !== null) {
            return self::$dbMap;
        }
        if (self::$dbAttempted) {
            return [];
        }
        if (!($conn instanceof \mysqli) || $conn->connect_error) {
            return [];
        }

        self::$dbAttempted = true;
        $map = [];
        try {
            $res = $conn->query("SELECT asset_key, file_path FROM system_branding WHERE asset_key NOT LIKE '\\_%'");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $k = (string)($row['asset_key'] ?? '');
                    $p = self::stripQuery((string)($row['file_path'] ?? ''));
                    if ($k !== '' && $p !== '') {
                        $map[$k] = $p;
                    }
                }
                $res->free();
            }
            self::$dbMap = $map;
        } catch (\Throwable $e) {
            // Table may not exist yet or DB may be transiently unavailable; fall back silently.
            self::$dbMap = [];
        }
        return self::$dbMap;
    }

    /**
     * Resolve the best available web URL for a branding asset key.
     * Priority:
     *   1. Uploaded custom file in system_branding (verified on disk)
     *   2. Packaged theme / legacy fallback (verified on disk)
     *   3. Caller-supplied $fallback string
     */
    public static function assetUrl(
        ?\mysqli $conn,
        string $key,
        string $fallback = '',
        bool $withVersion = true
    ): string {
        $dbMap = self::loadFromDb($conn);
        if (isset($dbMap[$key])) {
            $disk = self::resolveDiskPath($dbMap[$key]);
            if ($disk !== null) {
                return $withVersion ? self::versionedUrl($dbMap[$key], $disk) : $dbMap[$key];
            }
        }

        foreach (self::packagedCandidates($key) as $candidate) {
            $disk = self::resolveDiskPath($candidate);
            if ($disk !== null) {
                return $withVersion ? self::versionedUrl($candidate, $disk) : $candidate;
            }
        }

        $cleanFallback = self::stripQuery($fallback);
        if ($cleanFallback !== '') {
            $disk = self::resolveDiskPath($cleanFallback);
            if ($disk !== null && $withVersion) {
                return self::versionedUrl($cleanFallback, $disk);
            }
            return $cleanFallback;
        }

        return '';
    }

    /** Resolve the live school logo URL (with cache-busting ?v=mtime). */
    public static function logoUrl(?\mysqli $conn = null): string
    {
        $fallback = defined('SCHOOL_LOGO_PATH')
            ? (string)SCHOOL_LOGO_PATH
            : '/admin/id_cards/assets/logos/school_logo.png';
        return self::assetUrl($conn, 'logo', $fallback, true);
    }

    /** Resolve the live school seal URL (empty string if none exists and $allowEmpty is true). */
    public static function sealUrl(?\mysqli $conn = null, bool $allowEmpty = false): string
    {
        $fallback = $allowEmpty
            ? ''
            : (defined('SCHOOL_SEAL_PATH') ? (string)SCHOOL_SEAL_PATH : '');
        return self::assetUrl($conn, 'seal', $fallback, true);
    }

    /** Resolve the live ID card background URL (with cache-busting ?v=mtime). */
    public static function cardBackgroundUrl(?\mysqli $conn = null): string
    {
        $fallback = defined('ID_CARD_BACKGROUND')
            ? (string)ID_CARD_BACKGROUND
            : '/admin/id_cards/assets/backgrounds/id_card_bg.jpg';
        return self::assetUrl($conn, 'card_bg', $fallback, true);
    }
}
