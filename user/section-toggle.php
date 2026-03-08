<?php
require_once __DIR__ . '/../config/auth.php';
require_editor();
verify_csrf_or_fail();

$slug = trim((string)($_POST['slug'] ?? ''));
$isActive = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;

if ($slug === '') {
    http_response_code(400);
    exit('Missing section slug.');
}

$pdo = app_pdo();
$stmt = $pdo->prepare('UPDATE athkar_sections SET is_active = :is_active WHERE slug = :slug');
$stmt->execute([
    'is_active' => $isActive,
    'slug' => $slug,
]);

header('Location: sections.php');
exit;
