<?php
require_once __DIR__ . '/../config/auth.php';
require_editor();
verify_csrf_or_fail();

$slug = trim((string)($_POST['slug'] ?? ''));
if ($slug === '') {
    http_response_code(400);
    exit('Missing section slug.');
}

$pdo = app_pdo();
$pdo->beginTransaction();
try {
    $deleteItems = $pdo->prepare('DELETE FROM athkar_items WHERE section_slug = :slug');
    $deleteItems->execute(['slug' => $slug]);

    $deleteSection = $pdo->prepare('DELETE FROM athkar_sections WHERE slug = :slug');
    $deleteSection->execute(['slug' => $slug]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to delete section.');
}

header('Location: sections.php');
exit;
