-- telegram_candidate_decisions_preflight.sql (v2): read-only preflight for 094.
-- MariaDB 10.6+. Run BEFORE and AFTER applying 094 on each server.
-- HOW TO RUN: paste this whole file into the phpMyAdmin SQL tab (or mysql
-- client) with the site database selected, press Go, read the results.
-- This script is READ-ONLY: plain SELECTs only, no temp tables, no variables,
-- no procedures. Every statement is self-contained, so it works no matter how
-- the client executes pasted scripts (v1 used a temp table, which phpMyAdmin
-- drops between statements: rewrite reason).
-- HOW TO READ: result set 1 lists 3 checks (PASS/BLOCK each); result set 2 is
-- the one-line overall verdict; result set 3 is INFO about the rest of the bot
-- submit-path schema (all rows should say "good").

SELECT '094-1 candidate_decisions table exists' AS check_name,
       IF(COUNT(*) > 0, 'present', 'MISSING') AS actual,
       'present' AS expected,
       IF(COUNT(*) > 0, 'PASS', 'BLOCK') AS result
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_candidate_decisions'
UNION ALL
SELECT '094-2 candidate_decisions columns (id, session_id, candidate_member_id, decision, decided_at)',
       CONCAT(COUNT(*), '/5'),
       '5/5',
       IF(COUNT(*) = 5, 'PASS', 'BLOCK')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_candidate_decisions'
   AND COLUMN_NAME IN ('id','session_id','candidate_member_id','decision','decided_at')
UNION ALL
SELECT '094-3 candidate_decisions indexes (PRIMARY, idx_tg_cand_session, idx_tg_cand_member)',
       CONCAT(COUNT(DISTINCT INDEX_NAME), '/3'),
       '3/3',
       IF(COUNT(DISTINCT INDEX_NAME) = 3, 'PASS', 'BLOCK')
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_candidate_decisions'
   AND INDEX_NAME IN ('PRIMARY','idx_tg_cand_session','idx_tg_cand_member');

SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_candidate_decisions') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_candidate_decisions' AND COLUMN_NAME IN ('id','session_id','candidate_member_id','decision','decided_at')) = 5
  AND (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_candidate_decisions' AND INDEX_NAME IN ('PRIMARY','idx_tg_cand_session','idx_tg_cand_member')) = 3,
  'OVERALL: PASS - this database satisfies 094',
  'OVERALL: BLOCK - apply sql/094 on THIS database, then re-run'
) AS overall_094;

SELECT 'INFO reviews table exists' AS check_name,
       IF(COUNT(*) > 0, 'yes (good)', 'NO') AS state
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_registration_reviews'
UNION ALL
SELECT 'INFO reviews.session_revision exists (082)',
       IF(COUNT(*) > 0, 'yes (good)', 'NO')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_registration_reviews' AND COLUMN_NAME = 'session_revision'
UNION ALL
SELECT 'INFO sessions.submitted_at exists (080)',
       IF(COUNT(*) > 0, 'yes (good)', 'NO')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_registration_sessions' AND COLUMN_NAME = 'submitted_at'
UNION ALL
SELECT 'INFO sessions.lock_version exists (080)',
       IF(COUNT(*) > 0, 'yes (good)', 'NO')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_registration_sessions' AND COLUMN_NAME = 'lock_version'
UNION ALL
SELECT 'INFO sessions.viewing_menu exists (091)',
       IF(COUNT(*) > 0, 'yes (good)', 'NO')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_registration_sessions' AND COLUMN_NAME = 'viewing_menu'
UNION ALL
SELECT 'INFO 091-sec4 uniq_tg_session_decision should be gone',
       IF(COUNT(*) = 0, 'gone (good)', 'STILL PRESENT')
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_registration_reviews' AND INDEX_NAME = 'uniq_tg_session_decision'
UNION ALL
SELECT 'INFO 091-sec5 sessions.profile_key accepts transfer',
       IF(MAX(COLUMN_TYPE) LIKE '%transfer%', 'yes (good)', 'NO')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_registration_sessions' AND COLUMN_NAME = 'profile_key';
