-- ============================================================
-- 097_members_status_before_archive.sql   (IDEMPOTENT / RE-RUNNABLE)
-- (Renumbered from FKSS 095 for WBSS to avoid colliding with 095_public_announcements.sql)
-- M7: archiving overwrote members.status with 'archived', so restore always
-- reset to 'active' and a warning/inactive member's prior status was lost.
-- This column preserves the pre-archive status; archive writes it, restore
-- reads it (NULL, e.g. rows archived before 097, still restores 'active').
-- Touches no data: pure nullable-column append. Apply after 094.
-- Shared-hosting safe: single guarded ALTER, no FKs, no JSON.
-- Preflight (read-only, run before/after on each server):
--   sql/preflight/status_before_archive_preflight.sql
-- ============================================================

SET @ssms_095_sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE table_schema = DATABASE()
              AND table_name = 'members'
              AND column_name = 'status_before_archive'
        ),
        'SELECT 1',
        'ALTER TABLE `members` ADD COLUMN `status_before_archive`'
        ' VARCHAR(20) NULL DEFAULT NULL AFTER `status`'
    )
);

PREPARE ssms_095_stmt FROM @ssms_095_sql;
EXECUTE ssms_095_stmt;
DEALLOCATE PREPARE ssms_095_stmt;
