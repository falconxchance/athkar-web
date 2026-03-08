CREATE TABLE IF NOT EXISTS athkar_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  show_on_home TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_slug (slug),
  KEY idx_pages_home (show_on_home, is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS athkar_pages_i18n (
  page_id INT UNSIGNED NOT NULL,
  lang VARCHAR(8) NOT NULL,
  title VARCHAR(255) DEFAULT NULL,
  content MEDIUMTEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (page_id, lang),
  CONSTRAINT fk_pages_i18n_page FOREIGN KEY (page_id)
    REFERENCES athkar_pages (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO athkar_pages (id, slug, display_order, is_active, show_on_home)
VALUES
  (1, 'about-us', 1, 1, 1),
  (2, 'disclaimer', 2, 1, 1);

INSERT IGNORE INTO athkar_pages_i18n (page_id, lang, title, content)
VALUES
  (1, 'en', 'About Us', '<h2>About Us</h2><p>Welcome to our Athkar platform. Use this page to describe your mission, your team, or why this app exists.</p>'),
  (2, 'en', 'Disclaimer', '<h2>Disclaimer</h2><p>Use this page to add any legal notice, source note, or content disclaimer for your Athkar website and app.</p>');
