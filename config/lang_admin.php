<?php
require_once __DIR__ . '/i18n.php';


function sanitize_lang_admin_code(?string $lang): string
{
    $lang = strtolower(trim((string)$lang));
    if ($lang === '') return '';
    $lang = explode('-', $lang)[0];
    if (!preg_match('/^[a-z]{2,8}$/', $lang)) return '';
    return $lang;
}

function admin_language_rows(PDO $pdo, bool $activeOnly = false): array
{
    return get_languages($pdo, $activeOnly);
}

function admin_language_codes(PDO $pdo, bool $activeOnly = false): array
{
    return array_map(function ($row) { return $row['code']; }, admin_language_rows($pdo, $activeOnly));
}

function admin_language_map(PDO $pdo, bool $activeOnly = false): array
{
    $map = [];
    foreach (admin_language_rows($pdo, $activeOnly) as $row) $map[$row['code']] = $row;
    return $map;
}


function admin_edit_language_rows(PDO $pdo): array
{
    $rows = admin_language_rows($pdo, true);
    return $rows ?: admin_language_rows($pdo, false);
}

function admin_slugify(string $value, string $fallback = 'athkar-item'): string
{
    $value = trim($value);
    if ($value === '') return $fallback;

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && trim($converted) !== '') {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : $fallback;
}

function admin_generate_item_key(string $title, int $id): string
{
    $suffix = (string)max(1, $id);
    $base = admin_slugify($title, 'athkar-item');
    $maxBaseLength = max(1, 120 - strlen($suffix) - 1);
    if (strlen($base) > $maxBaseLength) {
        $base = rtrim(substr($base, 0, $maxBaseLength), '-');
    }
    if ($base === '') $base = 'athkar-item';
    return $base . '-' . $suffix;
}

function seed_language_content(PDO $pdo, string $code): void
{
    $lang = sanitize_lang($code);
    if ($lang === 'en') return;

    foreach ([['site_content_i18n','content_key'], ['ui_strings','string_key']] as $pair) {
        [$table,$key] = $pair;
        try {
            if (!(bool)$pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn()) continue;
            $rows = $pdo->query("SELECT $key, value FROM $table WHERE lang = 'en'")->fetchAll();
            $ins = $pdo->prepare("INSERT IGNORE INTO $table ($key, lang, value) VALUES (:k, :l, :v)");
            foreach ($rows as $row) {
                $ins->execute(['k' => $row[$key], 'l' => $lang, 'v' => $row['value']]);
            }
        } catch (Throwable $e) {}
    }

    try {
        if ((bool)$pdo->query("SHOW TABLES LIKE 'athkar_sections_i18n'")->fetchColumn()) {
            $rows = $pdo->query("SELECT section_slug, label, description FROM athkar_sections_i18n WHERE lang = 'en'")->fetchAll();
            $ins = $pdo->prepare('INSERT IGNORE INTO athkar_sections_i18n (section_slug, lang, label, description) VALUES (:slug, :lang, :label, :description)');
            foreach ($rows as $row) {
                $ins->execute(['slug' => $row['section_slug'], 'lang' => $lang, 'label' => $row['label'], 'description' => $row['description']]);
            }
        }
    } catch (Throwable $e) {}

    try {
        if ((bool)$pdo->query("SHOW TABLES LIKE 'athkar_items_i18n'")->fetchColumn()) {
            $rows = $pdo->query("SELECT item_id, title, transliteration, translation, source FROM athkar_items_i18n WHERE lang = 'en'")->fetchAll();
            $ins = $pdo->prepare('INSERT IGNORE INTO athkar_items_i18n (item_id, lang, title, transliteration, translation, source) VALUES (:id, :lang, :title, :transliteration, :translation, :source)');
            foreach ($rows as $row) {
                $ins->execute(['id' => $row['item_id'], 'lang' => $lang, 'title' => $row['title'], 'transliteration' => $row['transliteration'], 'translation' => $row['translation'], 'source' => $row['source']]);
            }
        }
    } catch (Throwable $e) {}
}
