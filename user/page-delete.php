<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/pages.php';
require_superadmin();
verify_csrf_or_fail();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $pdo = app_pdo();
    ensure_custom_pages_tables($pdo);
    $stmt = $pdo->prepare('DELETE FROM athkar_pages WHERE id = :id');
    $stmt->execute(['id' => $id]);
}
header('Location: pages.php');
exit;
