-- Run this on existing multilingual installs to support dynamic languages + direction.
-- If your MySQL/MariaDB supports ADD COLUMN IF NOT EXISTS, use the first line; otherwise add the dir column manually once.
ALTER TABLE app_languages ADD COLUMN IF NOT EXISTS dir ENUM('ltr','rtl') NOT NULL DEFAULT 'ltr' AFTER native_label;
UPDATE app_languages SET dir = CASE WHEN code IN ('ar','fa','ur','he') THEN 'rtl' ELSE 'ltr' END WHERE dir IS NULL OR dir = '';
