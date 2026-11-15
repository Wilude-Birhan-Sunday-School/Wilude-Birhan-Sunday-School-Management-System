-- ============================================================
-- 092_acm_guardian_link_verification.sql   (IDEMPOTENT / RE-RUNNABLE)
-- G2: grandfather office-created links as verified. A link stamped
-- by a real console actor (created_by > 0) counts as office-verified
-- per owner decision 2026-11-14; system-created links (backfill CLI,
-- auto-match batches, mobile reverse-sync: created_by NULL/0) stay
-- unverified until an officer stamps them via verify_link.
-- Zero DDL: one guarded UPDATE, safe to re-run.
-- ============================================================

UPDATE member_guardians
SET verified_by = created_by, verified_at = created_at
WHERE verified_by IS NULL AND created_by > 0;
