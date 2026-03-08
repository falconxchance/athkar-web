<?php
require_once __DIR__ . '/../config/auth.php';
require_superadmin();
require_once __DIR__ . '/../config/lang_admin.php';
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/pages.php';

$pdo = app_pdo();
ensure_default_ui_strings($pdo);
ensure_custom_pages_tables($pdo);
$lang = sanitize_lang_admin_code($_GET['lang'] ?? '');
if ($lang === '') {
    http_response_code(400);
    exit('Missing language code.');
}

$langMap = admin_language_map($pdo, true);
if (!isset($langMap[$lang])) {
    http_response_code(404);
    exit('Language not found.');
}

function fetch_pairs(PDO $pdo, string $table, string $keyColumn, string $lang): array
{
    $has = (bool)$pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn();
    if (!$has) return [];
    $stmt = $pdo->prepare("SELECT {$keyColumn}, value FROM {$table} WHERE lang = :lang ORDER BY {$keyColumn} ASC");
    $stmt->execute(['lang' => $lang]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return is_array($rows) ? $rows : [];
}

$payload = [
    'meta' => [
        'language' => $langMap[$lang],
        'generated_at' => gmdate('c'),
        'export_type' => 'athkar-translation-bundle',
        'fallback_language' => 'en',
    ],
    'site_content' => fetch_pairs($pdo, 'site_content_i18n', 'content_key', $lang),
    'ui_strings' => fetch_pairs($pdo, 'ui_strings', 'string_key', $lang),
    'sections' => [],
    'items' => [],
    'pages' => [],
];

if ((bool)$pdo->query("SHOW TABLES LIKE 'athkar_sections_i18n'")->fetchColumn()) {
    $stmt = $pdo->prepare('SELECT section_slug, label, description FROM athkar_sections_i18n WHERE lang = :lang ORDER BY section_slug ASC');
    $stmt->execute(['lang' => $lang]);
    $payload['sections'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ((bool)$pdo->query("SHOW TABLES LIKE 'athkar_items_i18n'")->fetchColumn()) {
    $stmt = $pdo->prepare('SELECT i.item_key, i.section_slug, t.title, t.transliteration, t.translation, t.source
                           FROM athkar_items_i18n t
                           INNER JOIN athkar_items i ON i.id = t.item_id
                           WHERE t.lang = :lang
                           ORDER BY i.section_slug ASC, i.display_order ASC, i.id ASC');
    $stmt->execute(['lang' => $lang]);
    $payload['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}



if ((bool)$pdo->query("SHOW TABLES LIKE 'athkar_pages_i18n'")->fetchColumn()) {
    $stmt = $pdo->prepare('SELECT p.slug, t.title, t.content
                           FROM athkar_pages_i18n t
                           INNER JOIN athkar_pages p ON p.id = t.page_id
                           WHERE t.lang = :lang
                           ORDER BY p.display_order ASC, p.slug ASC');
    $stmt->execute(['lang' => $lang]);
    $payload['pages'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$filename = 'athkar-translations-' . $lang . '-' . gmdate('Ymd-His') . '.json';
header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;
