-- ============================================================
-- 079 — Public website announcements
-- ============================================================
-- The public website (news.php) lists dated announcements stored in this
-- table, newest first. The table is OPTIONAL: the website reads it
-- fail-soft, so the site renders its standing notices normally before this
-- migration is applied and if the table is later removed.
--
-- Additive and idempotent: running it twice changes nothing.
-- Nothing here deletes or rewrites existing rows.
--
-- Example insert (edit the text before running):
--   INSERT INTO cms_announcements (title, title_am, body, published_on)
--   VALUES ('Term opening', 'የትምህርት ዘመን መክፈቻ', 'Lessons resume this Sunday at 9:00 AM.', CURDATE());
-- ============================================================

CREATE TABLE IF NOT EXISTS `cms_announcements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `title_am` VARCHAR(250) DEFAULT NULL,
    `body` TEXT DEFAULT NULL,
    `body_am` TEXT DEFAULT NULL,
    `link_url` VARCHAR(255) DEFAULT NULL,
    `link_label` VARCHAR(100) DEFAULT NULL,
    `published_on` DATE DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `is_active_published` (`is_active`, `published_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
