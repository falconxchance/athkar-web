<?php
require_once __DIR__ . '/../config/auth.php';
require_editor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf_or_fail();

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $pdo = app_pdo();
    $stmt = $pdo->prepare('DELETE FROM athkar_items WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

header('Location: index.php');
exit;
