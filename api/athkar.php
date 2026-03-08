<?php
require_once __DIR__ . '/../config/i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$section = trim((string)($_GET['section'] ?? ''));
if ($section === '') {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing section.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = app_pdo();
    $lang = get_request_lang();

    $hasSectionI18n = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_sections_i18n'")->fetchColumn();
    $hasItemI18n = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_items_i18n'")->fetchColumn();

    if ($hasSectionI18n) {
        $sectionStmt = $pdo->prepare(
            'SELECT s.slug,
                    COALESCE(t_req.label, t_en.label, s.label) AS label,
                    COALESCE(t_req.description, t_en.description, s.description) AS description,
                    s.icon
             FROM athkar_sections s
             LEFT JOIN athkar_sections_i18n t_req
               ON t_req.section_slug = s.slug AND t_req.lang = :lang
             LEFT JOIN athkar_sections_i18n t_en
               ON t_en.section_slug = s.slug AND t_en.lang = :fallback_lang
             WHERE s.slug = :slug AND s.is_active = 1
             LIMIT 1'
        );
        $sectionStmt->execute(['slug' => $section, 'lang' => $lang, 'fallback_lang' => 'en']);
    } else {
        $sectionStmt = $pdo->prepare(
            'SELECT slug, label, description, icon
             FROM athkar_sections
             WHERE slug = :slug AND is_active = 1
             LIMIT 1'
        );
        $sectionStmt->execute(['slug' => $section]);
    }
    $sectionRow = $sectionStmt->fetch();

    if (!$sectionRow) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Section not found.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($hasItemI18n) {
        $stmt = $pdo->prepare(
            'SELECT i.item_key AS id,
                    COALESCE(it_req.title, it_en.title, i.title) AS title,
                    i.arabic,
                    COALESCE(it_req.transliteration, it_en.transliteration, i.transliteration) AS transliteration,
                    COALESCE(it_req.translation, it_en.translation, i.translation) AS translation,
                    COALESCE(it_req.source, it_en.source, i.source) AS source,
                    i.repetition_count AS count
             FROM athkar_items i
             LEFT JOIN athkar_items_i18n it_req
               ON it_req.item_id = i.id AND it_req.lang = :lang
             LEFT JOIN athkar_items_i18n it_en
               ON it_en.item_id = i.id AND it_en.lang = :fallback_lang
             WHERE i.section_slug = :section_slug AND i.is_active = 1
             ORDER BY i.display_order ASC, i.id ASC'
        );
        $stmt->execute(['section_slug' => $section, 'lang' => $lang, 'fallback_lang' => 'en']);
    } else {
        $stmt = $pdo->prepare(
            'SELECT i.item_key AS id, i.title, i.arabic, i.transliteration, i.translation, i.source, i.repetition_count AS count
             FROM athkar_items i
             WHERE i.section_slug = :section_slug AND i.is_active = 1
             ORDER BY i.display_order ASC, i.id ASC'
        );
        $stmt->execute(['section_slug' => $section]);
    }

    echo json_encode([
        'lang' => $lang,
        'dir' => lang_dir($lang),
        'section' => $sectionRow,
        'items' => $stmt->fetchAll(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load athkar.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
