<?php
require_once __DIR__ . '/../config/i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = app_pdo();
    $lang = get_request_lang();

    $strings = [];
    try {
        $has = (bool)$pdo->query("SHOW TABLES LIKE 'ui_strings'")->fetchColumn();
        if ($has) {
            $strings = get_ui_strings($pdo, $lang);
        }
    } catch (Throwable $e) {
        $strings = [];
    }

    echo json_encode([
        'lang' => $lang,
        'dir' => lang_dir($lang),
        'strings' => $strings,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
