<?php
require_once __DIR__ . '/../config/auth.php';
require_superadmin();

$user = admin_current_user();
if (empty($user['bootstrap'])) {
    header('Location: users.php');
    exit;
}

$pdo = app_pdo();

// Ensure table exists (safe in case someone is doing first-time setup)
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS admin_users (\n"
    . "  id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
    . "  username VARCHAR(80) NOT NULL,\n"
    . "  password_hash VARCHAR(255) NOT NULL,\n"
    . "  role VARCHAR(20) NOT NULL DEFAULT 'super_admin',\n"
    . "  is_admin TINYINT(1) NOT NULL DEFAULT 1,\n"
    . "  is_active TINYINT(1) NOT NULL DEFAULT 1,\n"
    . "  last_login_at DATETIME NULL,\n"
    . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
    . "  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
    . "  PRIMARY KEY (id),\n"
    . "  UNIQUE KEY uniq_admin_username (username)\n"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

if (db_has_admin_users($pdo)) {
    header('Location: users.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();

    $username = trim((string)($_POST['username'] ?? 'admin'));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');

    if ($username === '' || strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 10) {
        $error = 'Password must be at least 10 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Password confirmation does not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, role, is_admin, is_active) VALUES (:u, :h, :r, 1, 1)');
            $stmt->execute(['u' => $username, 'h' => $hash, 'r' => ATHKAR_ROLE_SUPER]);

            $newId = (int)$pdo->lastInsertId();

            // Log in as the newly created DB super admin user
            start_secure_session();
            $_SESSION['athkar_admin'] = [
                'id' => $newId,
                'username' => $username,
                'role' => ATHKAR_ROLE_SUPER,
                'is_admin' => true,
                'bootstrap' => false,
                'logged_in_at' => time(),
            ];
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

            header('Location: users.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Failed to create user. If the username already exists, choose another.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Initial Setup - Athkar</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = ''; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content admin-content--narrow">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="eyebrow">Athkar</p>
          <h1>Initial Setup</h1>
          <p class="admin-subtitle">Create the first Super Admin user for this site.</p>
        </div>
      </header>

    <section class="admin-panel">
      <p class="admin-help">Create the first Super Admin user for this site.</p>

      <?php if ($error): ?>
        <p class="admin-alert admin-alert-error"><?= esc($error) ?></p>
      <?php endif; ?>

      <div class="admin-card">
        <form method="post" class="admin-form-stack">
          <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
          <label class="admin-label">
            <span>Username</span>
            <input class="admin-input" type="text" name="username" value="admin" required />
          </label>
          <label class="admin-label">
            <span>Password</span>
            <input class="admin-input" type="password" name="password" required />
          </label>
          <label class="admin-label">
            <span>Confirm password</span>
            <input class="admin-input" type="password" name="confirm" required />
          </label>
          <button class="primary-button" type="submit">Create Super Admin</button>
        </form>
      </div>
    </section>
    </section>
  </main>
</body>
</html>
