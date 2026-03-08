<?php
require_once __DIR__ . '/../config/auth.php';
require_editor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf_or_fail();

$id = (int)($_POST['id'] ?? 0);
$isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

if ($id > 0) {
    $pdo = app_pdo();
    $stmt = $pdo->prepare('UPDATE athkar_items SET is_active = :is_active WHERE id = :id');
    $stmt->execute([
        'is_active' => $isActive === 1 ? 1 : 0,
        'id' => $id,
    ]);
}

header('Location: index.php');
exit;
