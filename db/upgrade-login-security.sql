-- Adds login-attempt tracking for admin login throttling / lockout.
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS admin_login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL DEFAULT '',
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(190) DEFAULT NULL,
  was_success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin_login_ip_time (ip_address, attempted_at),
  KEY idx_admin_login_user_time (username, attempted_at),
  KEY idx_admin_login_success_time (was_success, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
