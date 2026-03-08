CREATE TABLE IF NOT EXISTS athkar_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  item_key VARCHAR(120) NOT NULL,
  section_slug VARCHAR(50) NOT NULL,
  lang VARCHAR(8) NOT NULL DEFAULT 'en',
  page_context ENUM('app','seo_item') NOT NULL DEFAULT 'app',
  issue_type VARCHAR(40) NOT NULL,
  reporter_name VARCHAR(120) DEFAULT NULL,
  reporter_email VARCHAR(190) DEFAULT NULL,
  message TEXT NOT NULL,
  source_url VARCHAR(500) DEFAULT NULL,
  ip_address VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reports_created (created_at),
  KEY idx_reports_item (item_id, created_at),
  KEY idx_reports_issue (issue_type, created_at),
  CONSTRAINT fk_athkar_reports_item FOREIGN KEY (item_id)
    REFERENCES athkar_items (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
