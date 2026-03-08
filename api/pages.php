<?php
require_once __DIR__ . '/../config/pages.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = app_pdo();
    $lang = get_request_lang();
    $pages = get_pages($pdo, $lang, true, true);
    echo json_encode([
        'lang' => $lang,
        'dir' => lang_dir($lang),
        'pages' => array_map(static function (array $page): array {
            return [
                'slug' => (string)$page['slug'],
                'title' => (string)$page['title'],
                'excerpt' => (string)($page['excerpt'] ?? ''),
            ];
        }, $pages),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load pages.',
        'pages' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
