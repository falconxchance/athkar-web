<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/pages.php';
require_superadmin();
verify_csrf_or_fail();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: pages.php'); exit; }

$pdo = app_pdo();
ensure_custom_pages_tables($pdo);
$target = (string)($_POST['toggle_target'] ?? 'status');
if ($target === 'home') {
    $value = isset($_POST['show_on_home']) ? (int)$_POST['show_on_home'] : 0;
    $stmt = $pdo->prepare('UPDATE athkar_pages SET show_on_home = :value WHERE id = :id');
    $stmt->execute(['value' => $value === 1 ? 1 : 0, 'id' => $id]);
} else {
    $value = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
    $stmt = $pdo->prepare('UPDATE athkar_pages SET is_active = :value WHERE id = :id');
    $stmt->execute(['value' => $value === 1 ? 1 : 0, 'id' => $id]);
}
header('Location: pages.php');
exit;
