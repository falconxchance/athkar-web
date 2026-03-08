<?php
require_once __DIR__ . '/../config/auth.php';
require_superadmin();

$pdo = app_pdo();
$me = admin_current_user();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

$userRow = [
    'username' => '',
    'role' => ATHKAR_ROLE_EDITOR,
    'is_active' => 1,
];
$error = '';
$success = '';

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT id, username, role, is_active FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('User not found');
    }
    $userRow = $row;
}

function validate_role(string $role): string {
    $role = trim($role);
    if ($role === ATHKAR_ROLE_USER || $role === ATHKAR_ROLE_EDITOR || $role === ATHKAR_ROLE_SUPER) {
        return $role;
    }
    return ATHKAR_ROLE_EDITOR;
}

function active_superadmin_count(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND is_active = 1")->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();

    $username = trim((string)($_POST['username'] ?? ''));
    $role = validate_role((string)($_POST['role'] ?? ATHKAR_ROLE_EDITOR));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($username === '' || strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 10) {
        $error = 'New password must be at least 10 characters.';
    } elseif ($newPassword !== '' && $newPassword !== $confirm) {
        $error = 'Password confirmation does not match.';
    } else {
        try {
            if ($isEdit) {
                // Prevent locking yourself out
                if ((int)$id === (int)($me['id'] ?? 0)) {
                    $role = ATHKAR_ROLE_SUPER;
                    $isActive = 1;
                }

                // If user is a super admin and this change would remove the last active super admin, block it.
                $currentRole = (string)($userRow['role'] ?? ATHKAR_ROLE_EDITOR);
                if ($currentRole === ATHKAR_ROLE_SUPER) {
                    $superCount = active_superadmin_count($pdo);
                    if ($superCount <= 1 && $role !== ATHKAR_ROLE_SUPER) {
                        $error = 'You cannot demote the last active Super Admin user.';
                    }
                    if ($superCount <= 1 && $isActive !== 1) {
                        $error = 'You cannot deactivate the last active Super Admin user.';
                    }
                }

                if (!$error) {
                    $pdo->beginTransaction();

                    $isAdminLegacy = ($role === ATHKAR_ROLE_SUPER) ? 1 : 0;

                    if ($newPassword !== '') {
                        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare('UPDATE admin_users SET username = :u, role = :r, is_admin = :a, is_active = :s, password_hash = :h WHERE id = :id');
                        $stmt->execute(['u' => $username, 'r' => $role, 'a' => $isAdminLegacy, 's' => $isActive, 'h' => $hash, 'id' => $id]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE admin_users SET username = :u, role = :r, is_admin = :a, is_active = :s WHERE id = :id');
                        $stmt->execute(['u' => $username, 'r' => $role, 'a' => $isAdminLegacy, 's' => $isActive, 'id' => $id]);
                    }

                    $pdo->commit();
                    header('Location: users.php?success=' . urlencode('User saved.'));
                    exit;
                }
            } else {
                if ($newPassword === '') {
                    $error = 'Password is required for new users.';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $isAdminLegacy = ($role === ATHKAR_ROLE_SUPER) ? 1 : 0;
                    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, role, is_admin, is_active) VALUES (:u, :h, :r, :a, :s)');
                    $stmt->execute(['u' => $username, 'h' => $hash, 'r' => $role, 'a' => $isAdminLegacy, 's' => $isActive]);
                    header('Location: users.php?success=' . urlencode('User created.'));
                    exit;
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = 'Save failed. Username may already exist, or your DB schema needs upgrade.';
        }
    }

    $userRow['username'] = $username;
    $userRow['role'] = $role;
    $userRow['is_active'] = $isActive;
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $isEdit ? 'Edit User' : 'Add User' ?> - Athkar Portal</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'users'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content admin-content--narrow">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="eyebrow">Athkar Portal</p>
          <h1><?= $isEdit ? 'Edit User' : 'Add User' ?></h1>
          <p class="admin-subtitle">Manage login access and role permissions for the portal.</p>
        </div>
      </header>

    <section class="admin-panel">
      <?php if ($error): ?>
        <p class="admin-alert admin-alert-error"><?= esc($error) ?></p>
      <?php endif; ?>

      <div class="admin-card">
        <p class="admin-help">Roles: <strong>User</strong> (profile only), <strong>Editor</strong> (can manage athkar sections & items), <strong>Super Admin</strong> (full access).</p>

        <form method="post" class="admin-form-stack">
          <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />

          <label class="admin-label">
            <span>Username</span>
            <input class="admin-input" type="text" name="username" value="<?= esc($userRow['username'] ?? '') ?>" required />
          </label>

          <label class="admin-label">
            <span>Role</span>
            <select class="admin-input" name="role">
              <option value="user" <?= (($userRow['role'] ?? '') === 'user') ? 'selected' : '' ?>>User</option>
              <option value="editor" <?= (($userRow['role'] ?? '') === 'editor' || empty($userRow['role'])) ? 'selected' : '' ?>>Editor</option>
              <option value="super_admin" <?= (($userRow['role'] ?? '') === 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
            </select>
          </label>

          <label class="admin-checkbox">
            <input type="checkbox" name="is_active" <?= ((int)($userRow['is_active'] ?? 1) === 1) ? 'checked' : '' ?> />
            <span>Active (can log in)</span>
          </label>

          <hr class="admin-divider" />

          <p class="admin-help"><?= $isEdit ? 'Leave password blank to keep unchanged.' : 'Set an initial password for this user.' ?></p>

          <label class="admin-label">
            <span><?= $isEdit ? 'New password (optional)' : 'Password' ?></span>
            <input class="admin-input" type="password" name="new_password" <?= $isEdit ? '' : 'required' ?> />
          </label>

          <label class="admin-label">
            <span>Confirm password</span>
            <input class="admin-input" type="password" name="confirm_password" <?= $isEdit ? '' : 'required' ?> />
          </label>

          <div class="admin-form-actions">
            <a class="ghost-button" href="users.php">Cancel</a>
            <button class="primary-button" type="submit">Save</button>
          </div>
        </form>
      </div>
    </section>
    </section>
  </main>
</body>
</html>
