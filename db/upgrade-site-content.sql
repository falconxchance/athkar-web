-- Adds editable home header/footer content stored in the database.
-- Run this once on your existing DB.
CREATE TABLE IF NOT EXISTS athkar_site_content (
  id INT AUTO_INCREMENT PRIMARY KEY,
  content_key VARCHAR(64) NOT NULL UNIQUE,
  content_html MEDIUMTEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO athkar_site_content (content_key, content_html)
VALUES
('home_header', '<p class="eyebrow">Database-driven home</p><h1>Athkar</h1><p class="hero-text">Your home screen and reader sections now load directly from <code>athkar_sections</code> and <code>athkar_items</code>.</p><p class="hero-note">Progress is still saved on this device only. Deactivating a section in admin hides it from the home screen instantly.</p>'),
('home_footer', '<h2>Project notes</h2><ul><li>Frontend: HTML, CSS, vanilla JavaScript</li><li>Content: MySQL through a small PHP API</li><li>Sections: loaded from <code>athkar_sections</code></li><li>Progress: localStorage on each device</li><li>Admin: simple PHP admin panel for sections and athkar</li></ul><p><strong>Admin:</strong> <code>/user/login.php</code></p>')
ON DUPLICATE KEY UPDATE content_html = VALUES(content_html);
