<?php
require_once __DIR__ . '/../config/i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = app_pdo();
    $all = isset($_GET['all']) && $_GET['all'] === '1';
    $rows = get_languages($pdo, !$all);

    echo json_encode([
        'languages' => $rows,
        'default' => get_default_language($pdo),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
