-- Adds role-based access control (RBAC) to admin_users.
-- Run once. Safe-ish: it checks if the role column exists before adding.
-- Roles: user | editor | super_admin

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'admin_users'
    AND COLUMN_NAME = 'role'
);

SET @sql := IF(@col_exists = 0,
  "ALTER TABLE admin_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'editor' AFTER password_hash",
  "SELECT 1"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill roles for existing rows (super_admin for legacy is_admin=1, otherwise editor).
UPDATE admin_users
SET role = CASE
  WHEN role IS NULL OR role = '' THEN (CASE WHEN is_admin = 1 THEN 'super_admin' ELSE 'editor' END)
  WHEN role NOT IN ('user','editor','super_admin') THEN (CASE WHEN is_admin = 1 THEN 'super_admin' ELSE 'editor' END)
  ELSE role
END;

-- Keep legacy is_admin in sync for existing installs (optional but helpful for old code/queries).
UPDATE admin_users
SET is_admin = CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END;
