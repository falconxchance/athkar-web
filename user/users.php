<?php
require_once __DIR__ . '/../config/auth.php';
require_superadmin();

$pdo = app_pdo();
$me = admin_current_user();
$success = trim((string)($_GET['success'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));

$users = [];
try {
    $hasRole = admin_users_has_role_column($pdo);

    if ($hasRole) {
        $sql = "SELECT id, username, role, is_active, last_login_at, created_at
                FROM admin_users
                ORDER BY CASE role
                    WHEN 'super_admin' THEN 0
                    WHEN 'editor' THEN 1
                    ELSE 2
                END, username ASC";
        $users = $pdo->query($sql)->fetchAll();
    } else {
        // Legacy schema (no role column yet)
        $sql = "SELECT id, username,
                       CASE WHEN is_admin = 1 THEN 'super_admin' ELSE 'editor' END AS role,
                       is_active, last_login_at, created_at
                FROM admin_users
                ORDER BY is_admin DESC, username ASC";
        $users = $pdo->query($sql)->fetchAll();
    }
} catch (Throwable $e) {
    $error = 'Users table not ready. Please import db/upgrade-admin-users.sql and db/upgrade-admin-roles.sql.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users - Athkar Portal</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'users'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="eyebrow">Athkar Portal</p>
          <h1>Users</h1>
          <p class="admin-subtitle">Control user access, roles, and account status for the portal.</p>
        </div>
        <div class="admin-page-actions">
          <a class="primary-button" href="user-edit.php">Add User</a>
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
        <p class="admin-help">Only Super Admin users can manage users. Deactivating a user blocks their login.</p>

        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last login</th>
                <th>Created</th>
                <th style="width: 260px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$users): ?>
                <tr><td colspan="6">No users found.</td></tr>
              <?php endif; ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td>
                    <strong><?= esc($u['username']) ?></strong>
                    <?php if ((int)$u['id'] === (int)$me['id']): ?>
                      <span class="admin-badge">you</span>
                    <?php endif; ?>
                  </td>
                  <td><?= role_label($u['role'] ?? null) ?></td>
                  <td><?= (int)$u['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                  <td><?= esc($u['last_login_at'] ?? '') ?></td>
                  <td><?= esc($u['created_at'] ?? '') ?></td>
                  <td>
                    <div class="admin-row-actions">
                      <a class="ghost-button admin-icon-button" href="user-edit.php?id=<?= (int)$u['id'] ?>" title="Edit user" aria-label="Edit user"><?= admin_icon('edit') ?></a>

                      <form method="post" action="user-toggle.php">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
                        <input type="hidden" name="is_active" value="<?= (int)$u['is_active'] === 1 ? 0 : 1 ?>" />
                        <button class="ghost-button admin-icon-button <?= (int)$u['is_active'] === 1 ? 'is-toggle-active' : 'is-toggle-inactive' ?>" type="submit" title="<?= (int)$u['is_active'] === 1 ? 'Deactivate user' : 'Activate user' ?>" aria-label="<?= (int)$u['is_active'] === 1 ? 'Deactivate user' : 'Activate user' ?>"><?= (int)$u['is_active'] === 1 ? admin_icon('toggle-on') : admin_icon('toggle-off') ?></button>
                      </form>

                      <form method="post" action="user-delete.php" onsubmit="return confirm('Delete this user?');">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
                        <button class="ghost-button admin-icon-button is-danger" type="submit" title="Delete user" aria-label="Delete user"><?= admin_icon('delete') ?></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
    </section>
  </main>
</body>
</html>
