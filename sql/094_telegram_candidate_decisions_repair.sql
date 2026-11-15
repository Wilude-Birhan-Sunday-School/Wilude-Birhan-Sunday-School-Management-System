-- 094_telegram_candidate_decisions_repair.sql (IDEMPOTENT)
-- Ensures the telegram_candidate_decisions audit table exists. It was defined
-- by 091 §3, but at least one server applied 091 only partially (it has 091's
-- session columns yet lacks this table), and the bot's submit path reads it
-- for medium-duplicate verdicts — the missing table turns every such submit
-- into a retryable error. Definition is byte-identical to 091 §3; 091 itself
-- is left untouched per the never-edit-applied-migrations rule. Safe no-op
-- where 091 was fully applied. Apply after 093. Re-runnable; touches no data.
-- Preflight (read-only, run before/after on each server):
--   sql/preflight/telegram_candidate_decisions_preflight.sql

CREATE TABLE IF NOT EXISTS `telegram_candidate_decisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `candidate_member_id` INT UNSIGNED NOT NULL,
  `decision` ENUM('same','different','uncertain','viewed') NOT NULL,
  `decided_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tg_cand_session` (`session_id`,`candidate_member_id`),
  KEY `idx_tg_cand_member` (`candidate_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
