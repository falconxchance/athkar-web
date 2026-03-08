<?php
require_once __DIR__ . '/../config/auth.php';
require_superadmin();
require_once __DIR__ . '/../config/lang_admin.php';
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/pages.php';

verify_csrf_or_fail();

$pdo = app_pdo();
$langMap = admin_language_map($pdo, true);
ensure_default_ui_strings($pdo);
ensure_custom_pages_tables($pdo);

function redirect_import_error(): void {
    header('Location: ui-strings.php?import_error=1');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect_import_error();
}

if (!isset($_FILES['translation_file']) || !is_array($_FILES['translation_file'])) {
    redirect_import_error();
}

$file = $_FILES['translation_file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    redirect_import_error();
}

$tmp = (string)($file['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    redirect_import_error();
}

$json = file_get_contents($tmp);
if (!is_string($json) || trim($json) === '') {
    redirect_import_error();
}

$payload = json_decode($json, true);
if (!is_array($payload)) {
    redirect_import_error();
}

$lang = sanitize_lang_admin_code($_POST['lang'] ?? '');
if ($lang === '') {
    $lang = sanitize_lang_admin_code($payload['meta']['language']['code'] ?? ($payload['meta']['language'] ?? ''));
}
if ($lang === '' || !isset($langMap[$lang])) {
    redirect_import_error();
}

try {
    $pdo->beginTransaction();

    if (isset($payload['site_content']) && is_array($payload['site_content']) && (bool)$pdo->query("SHOW TABLES LIKE 'site_content_i18n'")->fetchColumn()) {
        $stmt = $pdo->prepare('INSERT INTO site_content_i18n (content_key, lang, value) VALUES (:k, :l, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        foreach ($payload['site_content'] as $key => $value) {
            $key = trim((string)$key);
            if ($key === '') continue;
            $stmt->execute(['k' => $key, 'l' => $lang, 'v' => trim((string)$value)]);
        }
    }

    if (isset($payload['ui_strings']) && is_array($payload['ui_strings']) && (bool)$pdo->query("SHOW TABLES LIKE 'ui_strings'")->fetchColumn()) {
        $stmt = $pdo->prepare('INSERT INTO ui_strings (string_key, lang, value) VALUES (:k, :l, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        foreach ($payload['ui_strings'] as $key => $value) {
            $key = trim((string)$key);
            if ($key === '') continue;
            $stmt->execute(['k' => $key, 'l' => $lang, 'v' => trim((string)$value)]);
        }
    }

    if (isset($payload['sections']) && is_array($payload['sections']) && (bool)$pdo->query("SHOW TABLES LIKE 'athkar_sections_i18n'")->fetchColumn()) {
        $stmt = $pdo->prepare('INSERT INTO athkar_sections_i18n (section_slug, lang, label, description) VALUES (:slug, :lang, :label, :description) ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description)');
        foreach ($payload['sections'] as $row) {
            if (!is_array($row)) continue;
            $slug = trim((string)($row['section_slug'] ?? ''));
            if ($slug === '') continue;
            $stmt->execute([
                'slug' => $slug,
                'lang' => $lang,
                'label' => trim((string)($row['label'] ?? '')),
                'description' => trim((string)($row['description'] ?? '')),
            ]);
        }
    }

    if (isset($payload['items']) && is_array($payload['items']) && (bool)$pdo->query("SHOW TABLES LIKE 'athkar_items_i18n'")->fetchColumn()) {
        $itemIdMap = [];
        foreach ($pdo->query('SELECT id, item_key FROM athkar_items') as $row) {
            $itemIdMap[(string)$row['item_key']] = (int)$row['id'];
        }
        $stmt = $pdo->prepare('INSERT INTO athkar_items_i18n (item_id, lang, title, transliteration, translation, source) VALUES (:id, :lang, :title, :transliteration, :translation, :source) ON DUPLICATE KEY UPDATE title = VALUES(title), transliteration = VALUES(transliteration), translation = VALUES(translation), source = VALUES(source)');
        foreach ($payload['items'] as $row) {
            if (!is_array($row)) continue;
            $key = trim((string)($row['item_key'] ?? ''));
            if ($key === '' || !isset($itemIdMap[$key])) continue;
            $stmt->execute([
                'id' => $itemIdMap[$key],
                'lang' => $lang,
                'title' => trim((string)($row['title'] ?? '')),
                'transliteration' => trim((string)($row['transliteration'] ?? '')),
                'translation' => trim((string)($row['translation'] ?? '')),
                'source' => trim((string)($row['source'] ?? '')),
            ]);
        }
    }

    if (isset($payload['pages']) && is_array($payload['pages']) && (bool)$pdo->query("SHOW TABLES LIKE 'athkar_pages_i18n'")->fetchColumn()) {
        $pageIdMap = [];
        foreach ($pdo->query('SELECT id, slug FROM athkar_pages') as $row) {
            $pageIdMap[(string)$row['slug']] = (int)$row['id'];
        }
        $stmt = $pdo->prepare('INSERT INTO athkar_pages_i18n (page_id, lang, title, content) VALUES (:page_id, :lang, :title, :content) ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content)');
        foreach ($payload['pages'] as $row) {
            if (!is_array($row)) continue;
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '' || !isset($pageIdMap[$slug])) continue;
            $stmt->execute([
                'page_id' => $pageIdMap[$slug],
                'lang' => $lang,
                'title' => trim((string)($row['title'] ?? '')),
                'content' => (string)($row['content'] ?? ''),
            ]);
        }
    }


    $pdo->commit();
    header('Location: ui-strings.php?imported=1&lang=' . urlencode($lang));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect_import_error();
}
