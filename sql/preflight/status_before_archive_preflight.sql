-- Preflight for 095 (read-only): the pre-archive status column exists.
-- Run before/after applying 095 on each server. Expect exactly one row.
SELECT COLUMN_NAME AS `column`, COLUMN_TYPE AS `type`, IS_NULLABLE AS `nullable`
FROM information_schema.COLUMNS
WHERE table_schema = DATABASE()
  AND table_name = 'members'
  AND column_name = 'status_before_archive';
