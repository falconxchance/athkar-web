<?php
require_once __DIR__ . '/../config/i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = app_pdo();
    $lang = get_request_lang();

    // Prefer translations table if present
    $hasI18n = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_sections_i18n'")->fetchColumn();
    if ($hasI18n) {
        $stmt = $pdo->prepare(
            'SELECT s.slug,
                    COALESCE(t_req.label, t_en.label, s.label) AS label,
                    COALESCE(t_req.description, t_en.description, s.description) AS description,
                    s.icon,
                    s.display_order
             FROM athkar_sections s
             LEFT JOIN athkar_sections_i18n t_req
               ON t_req.section_slug = s.slug AND t_req.lang = :lang
             LEFT JOIN athkar_sections_i18n t_en
               ON t_en.section_slug = s.slug AND t_en.lang = :fallback_lang
             WHERE s.is_active = 1
             ORDER BY s.display_order ASC, s.slug ASC'
        );
        $stmt->execute(['lang' => $lang, 'fallback_lang' => 'en']);
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query(
            'SELECT slug, label, description, icon, display_order
             FROM athkar_sections
             WHERE is_active = 1
             ORDER BY display_order ASC, slug ASC'
        );
        $rows = $stmt->fetchAll();
    }

    echo json_encode([
        'lang' => $lang,
        'dir' => lang_dir($lang),
        'sections' => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load sections.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
