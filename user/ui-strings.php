<?php
require_once __DIR__ . '/../config/auth.php';
require_superadmin();
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/lang_admin.php';

$pdo = app_pdo();
ensure_default_ui_strings($pdo);
$has = (bool)$pdo->query("SHOW TABLES LIKE 'ui_strings'")->fetchColumn();
if (!$has) {
    http_response_code(500);
    exit('Missing ui_strings table. Please import db/upgrade-i18n.sql first.');
}

$languages = admin_edit_language_rows($pdo);
$langCodes = array_map(fn($row) => $row['code'], $languages);
$flash = '';
$error = '';
$keys = $pdo->query('SELECT DISTINCT string_key FROM ui_strings ORDER BY string_key ASC')->fetchAll(PDO::FETCH_COLUMN);
if (!is_array($keys)) $keys = [];

function load_strings_by_lang(PDO $pdo, string $lang): array {
    $stmt = $pdo->prepare('SELECT string_key, value FROM ui_strings WHERE lang = :l');
    $stmt->execute(['l' => $lang]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return is_array($rows) ? $rows : [];
}

$strings = [];
foreach ($langCodes as $code) $strings[$code] = load_strings_by_lang($pdo, $code);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $posted = $_POST['strings'] ?? null;
    if (!is_array($posted)) {
        $error = 'Invalid submission.';
    } else {
        try {
            $pdo->beginTransaction();
            $up = $pdo->prepare('INSERT INTO ui_strings (string_key, lang, value) VALUES (:k,:l,:v) ON DUPLICATE KEY UPDATE value = VALUES(value)');
            foreach ($posted as $key => $langs) {
                $key = trim((string)$key);
                if ($key === '' || !preg_match('/^[a-z0-9_\-.]+$/i', $key) || !is_array($langs)) continue;
                $fallback = trim((string)($langs['en'] ?? ''));
                if ($fallback === '' && $langCodes) $fallback = trim((string)reset($langs));
                if ($fallback === '') continue;
                foreach ($langCodes as $code) {
                    $value = trim((string)($langs[$code] ?? ''));
                    $up->execute(['k' => $key, 'l' => $code, 'v' => $value !== '' ? $value : $fallback]);
                }
            }
            $newKey = trim((string)($_POST['new_key'] ?? ''));
            if ($newKey !== '' && preg_match('/^[a-z0-9_\-.]+$/i', $newKey)) {
                $fallback = '';
                foreach ($langCodes as $code) {
                    $value = trim((string)($_POST['new_' . $code] ?? ''));
                    if ($value !== '' && $fallback === '') $fallback = $value;
                }
                if ($fallback !== '') {
                    foreach ($langCodes as $code) {
                        $value = trim((string)($_POST['new_' . $code] ?? ''));
                        $up->execute(['k' => $newKey, 'l' => $code, 'v' => $value !== '' ? $value : $fallback]);
                    }
                }
            }
            $pdo->commit();
            header('Location: ui-strings.php?saved=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Failed to save translations.';
        }
    }
}
if (isset($_GET['saved'])) $flash = 'Saved translations.';
if (isset($_GET['imported'])) $flash = 'Imported translation bundle for ' . strtoupper((string)($_GET['lang'] ?? '')) . '.';
if (isset($_GET['import_error'])) $error = 'Unable to import the translation file. Please check that it is a valid JSON export and try again.';
foreach ($langCodes as $code) $strings[$code] = load_strings_by_lang($pdo, $code);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Translations • Athkar Portal</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
  <style>
    .i18n-table { width: 100%; border-collapse: collapse; }
    .i18n-table th, .i18n-table td { border-top: 1px solid rgba(0,0,0,0.08); padding: 10px; vertical-align: top; }
    .i18n-table th { text-align: left; font-size: 0.9rem; color: #556; }
    .i18n-key { font-family: ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace; font-size: 0.85rem; color: #334; }
    .i18n-input { width: 100%; }
    .admin-inline-icon-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .admin-inline-icon-button svg { width: 15px; height: 15px; flex: 0 0 15px; opacity: 0.92; }
    .admin-inline-icon-button span { line-height: 1; }
  </style>
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'ui'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="admin-eyebrow">Athkar Portal</p>
          <h1>Translations</h1>
          <p class="admin-subtitle">Edit app UI text for every language defined in Languages.</p>
        </div>
      </header>

    <section class="admin-panel">
      <div class="admin-export-bar">
        <div>
          <p class="admin-note-title">Export a translation file</p>
          <p class="admin-help-inline">Download one JSON bundle for a selected language, attach it here later, and I can help translate it for you.</p>
        </div>
        <form method="get" action="translation-export.php">
          <label class="admin-inline-field">
            <span>Language</span>
            <select class="admin-select" name="lang">
              <?php foreach ($languages as $lang): ?>
                <option value="<?= esc($lang['code']) ?>"><?= esc($lang['native_label']) ?> (<?= esc($lang['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <button class="ghost-button admin-inline-icon-button" type="submit">
            <?= admin_icon('download') ?>
            <span>Download JSON</span>
          </button>
        </form>
      </div>
      <div class="admin-import-bar">
        <div>
          <p class="admin-note-title">Import a translated file</p>
          <p class="admin-help-inline">Upload a JSON bundle that was translated externally and import it back in one go. Existing values for that language will be updated automatically.</p>
        </div>
        <form method="post" action="translation-import.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
          <label class="admin-inline-field">
            <span>Language</span>
            <select class="admin-select" name="lang">
              <option value="">Auto-detect from file</option>
              <?php foreach ($languages as $lang): ?>
                <option value="<?= esc($lang['code']) ?>"><?= esc($lang['native_label']) ?> (<?= esc($lang['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="admin-inline-field admin-span-2">
            <span>Translation JSON file</span>
            <input class="admin-file-input" type="file" name="translation_file" accept="application/json,.json" required />
          </label>
          <button class="ghost-button admin-inline-icon-button" type="submit">
            <?= admin_icon('upload') ?>
            <span>Import JSON</span>
          </button>
        </form>
      </div>
    </section>

    <section class="admin-panel">
      <?php if ($flash): ?><div class="admin-alert success"><?= esc($flash) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="admin-alert error"><?= esc($error) ?></div><?php endif; ?>

      <form method="post" class="admin-form-stack">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
        <table class="i18n-table">
          <thead>
            <tr>
              <th style="width:16%">Key</th>
              <?php foreach ($languages as $lang): ?><th><?= esc($lang['native_label']) ?> <small>(<?= esc($lang['code']) ?>)</small></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($keys as $k): ?>
              <tr>
                <td class="i18n-key"><?= esc($k) ?></td>
                <?php foreach ($langCodes as $code): ?>
                  <td><textarea class="admin-textarea admin-textarea-compact i18n-input" name="strings[<?= esc($k) ?>][<?= esc($code) ?>]" rows="2"><?= esc($strings[$code][$k] ?? '') ?></textarea></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="admin-divider"></div>
        <h2 class="admin-note-title">Add a new key</h2>
        <div class="admin-grid-two"><label class="admin-label"><span>Key (letters/numbers/_ only)</span><input class="admin-input" type="text" name="new_key" placeholder="btn_save" /></label><div></div></div>
        <div class="admin-settings-grid">
          <?php foreach ($languages as $lang): ?>
            <label class="admin-inline-field<?= count($languages) === 1 ? ' admin-span-2' : '' ?>">
              <span><?= esc($lang['native_label']) ?> (<?= esc($lang['code']) ?>)</span>
              <input class="admin-input" type="text" name="new_<?= esc($lang['code']) ?>" placeholder="Text" />
            </label>
          <?php endforeach; ?>
        </div>
        <div class="admin-form-actions"><button type="submit" class="primary-button">Save</button></div>
      </form>
    </section>
    </section>
  </main>
</body>
</html>
