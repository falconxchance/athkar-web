ALTER TABLE athkar_sections
  ADD COLUMN icon VARCHAR(16) DEFAULT NULL AFTER description;

UPDATE athkar_sections SET icon = '☀️' WHERE slug = 'morning' AND (icon IS NULL OR icon = '');
UPDATE athkar_sections SET icon = '🌙' WHERE slug = 'evening' AND (icon IS NULL OR icon = '');
UPDATE athkar_sections SET icon = '🕌' WHERE slug = 'prayer' AND (icon IS NULL OR icon = '');
UPDATE athkar_sections SET icon = '🤲' WHERE slug = 'after-prayer' AND (icon IS NULL OR icon = '');
