<?php
require_once __DIR__ . '/../config/auth.php';

start_secure_session();
if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$lockedStatus = admin_login_lock_status();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $lockedStatus = admin_login_lock_status($username);
    if (!empty($lockedStatus['locked'])) {
        $error = admin_login_lock_message($lockedStatus);
    } elseif (attempt_login($username, $password)) {
        $u = admin_current_user();
        if (!empty($u['bootstrap'])) {
            header('Location: setup.php');
        } else {
            // Users land on profile; editors/superadmins land on content dashboard.
            if (admin_is_editor()) {
                header('Location: index.php');
            } else {
                header('Location: profile.php');
            }
        }
        exit;
    } else {
        $lockedStatus = admin_login_lock_status($username);
        $error = admin_login_lock_message($lockedStatus);
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Athkar Portal Login</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <main class="admin-login-shell">
    <section class="admin-login-card">
      <p class="eyebrow">Athkar Portal</p>
      <h1>Sign in</h1>
      <p class="admin-help">Sign in with your username and password.</p>

      <?php if ($error): ?>
        <p class="admin-alert admin-alert-error"><?= esc($error) ?></p>
      <?php endif; ?>

      <form method="post" class="admin-form-stack">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
        <label class="admin-label">
          <span>Username</span>
          <input class="admin-input" type="text" name="username" autocomplete="username" required />
        </label>
        <label class="admin-label">
          <span>Password</span>
          <input class="admin-input" type="password" name="password" autocomplete="current-password" required />
        </label>
        <button class="primary-button admin-submit" type="submit"<?= !empty($lockedStatus['locked']) ? ' disabled aria-disabled="true"' : '' ?>>Sign in</button>
      </form>
    </section>
  </main>
</body>
</html>
