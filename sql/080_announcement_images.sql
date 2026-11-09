-- ============================================================
-- 080 — Photographs for public website announcements
-- ============================================================
-- The public news page (news.php) can show a photograph with each dated
-- announcement. The two columns added here are OPTIONAL: the website reads
-- them fail-soft, so announcements and standing notices render normally
-- before this migration is applied.
--
-- Additive and idempotent: each column is added only when it is missing, so
-- running this file twice changes nothing. No row is deleted or rewritten.
--
-- Allowed values for image_path (enforced by the website, not by SQL):
--   a root-relative path to a local image, e.g.
--     /themes/fkss/assets/wbss-mezmur-practice.jpg
--     /uploads/gallery/2026/feast-day.jpg
--   Never an absolute URL, a protocol-relative URL or a path containing "..".
-- ============================================================

-- The table belongs to 079. When it is absent this migration is a no-op, so
-- the numbered files stay independent and no table is defined twice.
SET @has_table := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cms_announcements'
);

-- Add image_path when it is missing (works on MariaDB and MySQL 5.7/8).
SET @has_image_path := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cms_announcements'
      AND COLUMN_NAME = 'image_path'
);
SET @sql := IF(@has_table > 0 AND @has_image_path = 0,
    'ALTER TABLE `cms_announcements` ADD COLUMN `image_path` VARCHAR(255) DEFAULT NULL AFTER `link_label`',
    'DO 0');
PREPARE wbss_stmt FROM @sql;
EXECUTE wbss_stmt;
DEALLOCATE PREPARE wbss_stmt;

-- Add image_alt when it is missing. Alternative text is required by the
-- website whenever an image is rendered; an empty value renders the image
-- with an empty alt, which is only acceptable for purely decorative photos.
SET @has_image_alt := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cms_announcements'
      AND COLUMN_NAME = 'image_alt'
);
SET @sql := IF(@has_table > 0 AND @has_image_alt = 0,
    'ALTER TABLE `cms_announcements` ADD COLUMN `image_alt` VARCHAR(255) DEFAULT NULL AFTER `image_path`',
    'DO 0');
PREPARE wbss_stmt FROM @sql;
EXECUTE wbss_stmt;
DEALLOCATE PREPARE wbss_stmt;

-- Example insert (edit the text and the path before running):
--   INSERT INTO cms_announcements (title, title_am, body, image_path, image_alt, published_on)
--   VALUES ('Mezmur practice resumes', 'የመዝሙር ልምምድ ተጀመረ', 'Practice is at the close of the Sunday programme.',
--           '/themes/fkss/assets/wbss-mezmur-practice.jpg', 'Children singing from a hymn book beside a kebero drum.', CURDATE());
