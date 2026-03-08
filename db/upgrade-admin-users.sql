-- Creates DB-driven admin users for Athkar admin panel
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'editor',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed a default admin only if there are no users yet.
-- Replace the hash below with your own: php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
INSERT INTO admin_users (username, password_hash, role, is_admin, is_active)
SELECT 'admin', 'CHANGE_ME_BCRYPT_HASH', 'super_admin', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM admin_users LIMIT 1);
