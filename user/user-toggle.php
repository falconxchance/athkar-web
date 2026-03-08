<?php
require_once __DIR__ . '/../config/auth.php';
require_superadmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}
verify_csrf_or_fail();

$pdo = app_pdo();
$hasRole = admin_users_has_role_column($pdo);
$me = admin_current_user();

$id = (int)($_POST['id'] ?? 0);
$isActive = (int)($_POST['is_active'] ?? 0);

if ($id <= 0) {
    header('Location: users.php?error=' . urlencode('Invalid user.'));
    exit;
}

if ($id === (int)($me['id'] ?? 0)) {
    header('Location: users.php?error=' . urlencode('You cannot change your own active status.'));
    exit;
}

// Fetch user row (role-aware)
if ($hasRole) {
    $stmt = $pdo->prepare('SELECT id, role, is_active FROM admin_users WHERE id = :id LIMIT 1');
} else {
    $stmt = $pdo->prepare('SELECT id, is_admin, is_active FROM admin_users WHERE id = :id LIMIT 1');
}
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    header('Location: users.php?error=' . urlencode('User not found.'));
    exit;
}

$currentRole = $hasRole
    ? (string)($row['role'] ?? '')
    : (((int)($row['is_admin'] ?? 0) === 1) ? 'super_admin' : 'editor');

// Prevent deactivating the last active Super Admin
if ($currentRole === 'super_admin' && $isActive === 0) {
    $admins = (int)$pdo->query($hasRole
        ? "SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND is_active = 1"
        : "SELECT COUNT(*) FROM admin_users WHERE is_admin = 1 AND is_active = 1"
    )->fetchColumn();

    if ($admins <= 1) {
        header('Location: users.php?error=' . urlencode('You cannot deactivate the last active Super Admin user.'));
        exit;
    }
}

$upd = $pdo->prepare('UPDATE admin_users SET is_active = :a WHERE id = :id');
$upd->execute(['a' => $isActive === 1 ? 1 : 0, 'id' => $id]);

header('Location: users.php?success=' . urlencode('User updated.'));
exit;
