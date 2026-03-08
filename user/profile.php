<?php
require_once __DIR__ . '/../config/auth.php';
require_admin();

$user = admin_current_user();
$pdo = null;
try { $pdo = app_pdo(); } catch (Throwable $e) { $pdo = null; }

$isBootstrap = !empty($user['bootstrap']);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();

    if ($isBootstrap) {
        $error = 'Password change is not available for this account.';
    } elseif (!($pdo instanceof PDO) || !db_has_admin_users($pdo)) {
        $error = 'Account system is not ready yet.';
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if (strlen($new) < 10) {
            $error = 'New password must be at least 10 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } else {
            $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int)$user['id']]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($current, (string)$row['password_hash'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id');
                $upd->execute(['h' => $hash, 'id' => (int)$user['id']]);
                $success = 'Password updated successfully.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Account - Athkar</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'profile'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content admin-content--narrow">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="eyebrow">Athkar</p>
          <h1>My Account</h1>
          <p class="admin-subtitle">Manage your account details and password from one place.</p>
        </div>
      </header>

    <section class="admin-panel">
      <?php if ($success): ?>
        <p class="admin-alert admin-alert-success"><?= esc($success) ?></p>
      <?php endif; ?>
      <?php if ($error): ?>
        <p class="admin-alert admin-alert-error"><?= esc($error) ?></p>
      <?php endif; ?>

      <div class="admin-card">
        <h2>Account</h2>
        <p class="admin-help"><strong>Username:</strong> <?= esc((string)($user['username'] ?? '')) ?></p>
        <p class="admin-help"><strong>Role:</strong> <?= esc(role_label(admin_current_role())) ?></p>
      </div>

      <div class="admin-card">
        <h2>Change password</h2>
        <?php if ($isBootstrap): ?>
          <p class="admin-help">Password changes are disabled for this account.</p>
        <?php else: ?>
          <form method="post" class="admin-form-stack">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
            <label class="admin-label">
              <span>Current password</span>
              <input class="admin-input" type="password" name="current_password" required />
            </label>
            <label class="admin-label">
              <span>New password</span>
              <input class="admin-input" type="password" name="new_password" required />
            </label>
            <label class="admin-label">
              <span>Confirm new password</span>
              <input class="admin-input" type="password" name="confirm_password" required />
            </label>
            <button class="primary-button" type="submit">Update Password</button>
          </form>
        <?php endif; ?>
      </div>
    </section>
    </section>
  </main>
</body>
</html>
