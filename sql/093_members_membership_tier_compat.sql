-- 093_members_membership_tier_compat.sql (IDEMPOTENT)
-- Restores the members.membership_tier column on databases built purely from
-- sql/*.sql. The column was historically added by the legacy PHP migration
-- (sql/legacy/php-migrations/admin/006_add_temporary_members_tier.php), so
-- SQL-only rebuilds never had it; migrations 014/068 index it and the
-- Telegram HR approval INSERT writes it. Definition mirrors the legacy one.
-- Apply after 092.

DROP PROCEDURE IF EXISTS `ssms_093_add_column`;
DELIMITER $$
CREATE PROCEDURE `ssms_093_add_column`(IN p_column VARCHAR(64), IN p_definition TEXT)
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables
               WHERE table_schema=DATABASE() AND table_name='members')
     AND NOT EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema=DATABASE() AND table_name='members' AND column_name=p_column) THEN
    SET @s = CONCAT('ALTER TABLE `members` ADD COLUMN `', REPLACE(p_column,'`','``'), '` ', p_definition);
    PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
  END IF;
END$$
DELIMITER ;
CALL ssms_093_add_column('membership_tier',
  "ENUM('temporary','permanent') NULL DEFAULT 'permanent' AFTER `status`");
DROP PROCEDURE IF EXISTS `ssms_093_add_column`;
