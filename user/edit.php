<?php
require_once __DIR__ . '/../config/auth.php';
require_editor();
require_once __DIR__ . '/../config/lang_admin.php';

$pdo = app_pdo();
$languages = admin_edit_language_rows($pdo);
$langCodes = array_map(fn($row) => $row['code'], $languages);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';
$success = '';

$sections = $pdo->query('SELECT slug, label FROM athkar_sections ORDER BY display_order ASC, slug ASC')->fetchAll();
$hasI18n = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_items_i18n'")->fetchColumn();
$item = [
    'id' => 0,
    'item_key' => '',
    'section_slug' => $sections ? $sections[0]['slug'] : '',
    'arabic' => '',
    'repetition_count' => 1,
    'display_order' => 1,
    'is_active' => 1,
];
$i18n = [];
foreach ($langCodes as $code) $i18n[$code] = ['title' => '', 'transliteration' => '', 'translation' => '', 'source' => ''];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM athkar_items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if (!$found) { http_response_code(404); exit('Athkar item not found.'); }
    $item = array_merge($item, $found);
    if ($hasI18n) {
        $tr = $pdo->prepare('SELECT lang, title, transliteration, translation, source FROM athkar_items_i18n WHERE item_id = :id');
        $tr->execute(['id' => $id]);
        foreach ($tr->fetchAll() as $row) {
            $lang = (string)$row['lang'];
            if (!isset($i18n[$lang])) $i18n[$lang] = ['title' => '', 'transliteration' => '', 'translation' => '', 'source' => ''];
            $i18n[$lang] = [
                'title' => (string)($row['title'] ?? ''),
                'transliteration' => (string)($row['transliteration'] ?? ''),
                'translation' => (string)($row['translation'] ?? ''),
                'source' => (string)($row['source'] ?? ''),
            ];
        }
    }
    if (isset($i18n['en'])) {
        if (trim($i18n['en']['title']) === '') $i18n['en']['title'] = (string)($item['title'] ?? '');
        if (trim($i18n['en']['transliteration']) === '') $i18n['en']['transliteration'] = (string)($item['transliteration'] ?? '');
        if (trim($i18n['en']['translation']) === '') $i18n['en']['translation'] = (string)($item['translation'] ?? '');
        if (trim($i18n['en']['source']) === '') $i18n['en']['source'] = (string)($item['source'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
        $item['section_slug'] = trim((string)($_POST['section_slug'] ?? ''));
    $item['arabic'] = trim((string)($_POST['arabic'] ?? ''));
    $item['repetition_count'] = max(1, (int)($_POST['repetition_count'] ?? 1));
    $item['display_order'] = max(1, (int)($_POST['display_order'] ?? 1));
    $item['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    foreach ($langCodes as $code) {
        $i18n[$code] = [
            'title' => trim((string)($_POST['title_' . $code] ?? '')),
            'transliteration' => trim((string)($_POST['transliteration_' . $code] ?? '')),
            'translation' => trim((string)($_POST['translation_' . $code] ?? '')),
            'source' => trim((string)($_POST['source_' . $code] ?? '')),
        ];
    }

    $fallbackLang = in_array('en', $langCodes, true) ? 'en' : ($langCodes[0] ?? '');
    $fallbackTitle = $fallbackLang !== '' ? trim((string)($i18n[$fallbackLang]['title'] ?? '')) : '';
    if ($item['arabic'] === '' || $item['section_slug'] === '') {
        $error = 'Please fill in section and Arabic text.';
    } elseif ($fallbackTitle === '') {
        $error = 'Please provide at least one translated title.';
    } else {
        try {
            $pdo->beginTransaction();
            $base = $i18n[$fallbackLang] ?? ['title' => '', 'transliteration' => '', 'translation' => '', 'source' => ''];
            if ($isEdit) {
                $item['item_key'] = admin_generate_item_key($fallbackTitle, $id);
                $stmt = $pdo->prepare('UPDATE athkar_items SET item_key = :item_key, section_slug = :section_slug, title = :title, arabic = :arabic, transliteration = :transliteration, translation = :translation, source = :source, repetition_count = :repetition_count, display_order = :display_order, is_active = :is_active WHERE id = :id');
                $stmt->execute([
                    'item_key' => $item['item_key'], 'section_slug' => $item['section_slug'], 'title' => $base['title'] ?: $fallbackTitle, 'arabic' => $item['arabic'],
                    'transliteration' => $base['transliteration'], 'translation' => $base['translation'], 'source' => $base['source'], 'repetition_count' => $item['repetition_count'], 'display_order' => $item['display_order'], 'is_active' => $item['is_active'], 'id' => $id,
                ]);
            } else {
                $temporaryKey = '__tmp__' . bin2hex(random_bytes(8));
                $stmt = $pdo->prepare('INSERT INTO athkar_items (item_key, section_slug, title, arabic, transliteration, translation, source, repetition_count, display_order, is_active) VALUES (:item_key, :section_slug, :title, :arabic, :transliteration, :translation, :source, :repetition_count, :display_order, :is_active)');
                $stmt->execute([
                    'item_key' => $temporaryKey, 'section_slug' => $item['section_slug'], 'title' => $base['title'] ?: $fallbackTitle, 'arabic' => $item['arabic'],
                    'transliteration' => $base['transliteration'], 'translation' => $base['translation'], 'source' => $base['source'], 'repetition_count' => $item['repetition_count'], 'display_order' => $item['display_order'], 'is_active' => $item['is_active'],
                ]);
                $id = (int)$pdo->lastInsertId();
                $item['item_key'] = admin_generate_item_key($fallbackTitle, $id);
                $pdo->prepare('UPDATE athkar_items SET item_key = :item_key WHERE id = :id')->execute([
                    'item_key' => $item['item_key'],
                    'id' => $id,
                ]);
                $isEdit = true;
            }

            if ($hasI18n) {
                    $up = $pdo->prepare('INSERT INTO athkar_items_i18n (item_id, lang, title, transliteration, translation, source) VALUES (:id,:lang,:title,:transliteration,:translation,:source) ON DUPLICATE KEY UPDATE title = VALUES(title), transliteration = VALUES(transliteration), translation = VALUES(translation), source = VALUES(source)');
                    foreach ($langCodes as $code) {
                        $v = $i18n[$code] ?? $base;
                        $up->execute([
                            'id' => $id,
                            'lang' => $code,
                            'title' => $v['title'] !== '' ? $v['title'] : $fallbackTitle,
                            'transliteration' => $v['transliteration'] !== '' ? $v['transliteration'] : null,
                            'translation' => $v['translation'] !== '' ? $v['translation'] : null,
                            'source' => $v['source'] !== '' ? $v['source'] : ($base['source'] ?: null),
                        ]);
                    }
                }
                $pdo->commit();
                header('Location: edit.php?id=' . $id . '&saved=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Unable to save athkar.';
            }
        }
    }
if (isset($_GET['saved'])) $success = $isEdit ? 'Athkar saved.' : 'Athkar created.';
$previewLang = in_array('en', $langCodes, true) ? 'en' : ($langCodes[0] ?? '');
$previewTitle = $previewLang !== '' ? trim((string)($i18n[$previewLang]['title'] ?? '')) : '';
$previewItemKey = ($isEdit && $previewTitle !== '') ? admin_generate_item_key($previewTitle, (int)$id) : '';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $isEdit ? 'Edit Athkar' : 'Add Athkar' ?></title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
  <style>
    .lang-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 2px}
    .lang-tab{border:1px solid rgba(0,0,0,0.1);background:#fff;padding:8px 12px;border-radius:999px;font-weight:800;cursor:pointer}
    .lang-tab.is-active{background:rgba(0,0,0,0.06)}
  </style>
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'items'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content admin-content--narrow">
      <header class="admin-page-header">
        <div class="admin-page-title"><p class="eyebrow">Athkar Portal</p><h1><?= $isEdit ? 'Edit Athkar' : 'Add Athkar' ?></h1><p class="admin-subtitle">Create or update the exact athkar text, translations, counts, and visibility.</p></div>
      </header>

    <section class="admin-panel">
      <?php if ($error): ?><p class="admin-alert admin-alert-error"><?= esc($error) ?></p><?php endif; ?>
      <?php if ($success): ?><p class="admin-alert admin-alert-success"><?= esc($success) ?></p><?php endif; ?>
      <form method="post" class="admin-form-stack">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
        <div class="admin-grid-two">
          <label class="admin-label"><span>SEO item key</span><input class="admin-input" id="seo-item-key-preview" type="text" value="<?= esc($previewItemKey !== '' ? $previewItemKey : 'Will be generated automatically from the title after saving') ?>" readonly /><small class="admin-help">Updates automatically from the <?= esc(strtoupper($previewLang ?: 'title')) ?> title. SEO page title and meta description are mapped from the saved item title, translation, transliteration, and source.</small></label>
          <label class="admin-label"><span>Section</span><select class="admin-select" name="section_slug" required><?php foreach ($sections as $section): ?><option value="<?= esc($section['slug']) ?>" <?= $item['section_slug'] === $section['slug'] ? 'selected' : '' ?>><?= esc($section['label']) ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="admin-label"><span>Arabic (exact text)</span><textarea class="admin-textarea admin-arabic" name="arabic" rows="5" required><?= esc($item['arabic']) ?></textarea></label>
        <div class="lang-tabs" role="tablist" aria-label="Languages">
          <?php foreach ($languages as $idx => $lang): ?><button class="lang-tab<?= $idx === 0 ? ' is-active' : '' ?>" type="button" data-langtab="<?= esc($lang['code']) ?>"><?= esc($lang['native_label']) ?> (<?= esc($lang['code']) ?>)</button><?php endforeach; ?>
        </div>
        <?php foreach ($languages as $idx => $lang): $code = $lang['code']; ?>
          <div class="lang-pane" data-langpane="<?= esc($code) ?>" <?= $idx === 0 ? '' : 'hidden' ?>>
            <label class="admin-label"><span>Title (<?= esc(strtoupper($code)) ?>)</span><input class="admin-input" type="text" name="title_<?= esc($code) ?>" value="<?= esc($i18n[$code]['title'] ?? '') ?>" <?= $code === $previewLang ? 'data-item-key-source="1"' : '' ?> /></label>
            <label class="admin-label"><span>Source (<?= esc(strtoupper($code)) ?>)</span><textarea class="admin-textarea" name="source_<?= esc($code) ?>" rows="3"><?= esc($i18n[$code]['source'] ?? '') ?></textarea></label>
            <label class="admin-label"><span>Transliteration (<?= esc(strtoupper($code)) ?>)</span><textarea class="admin-textarea" name="transliteration_<?= esc($code) ?>" rows="3"><?= esc($i18n[$code]['transliteration'] ?? '') ?></textarea></label>
            <label class="admin-label"><span>Translation (<?= esc(strtoupper($code)) ?>)</span><textarea class="admin-textarea" name="translation_<?= esc($code) ?>" rows="4"><?= esc($i18n[$code]['translation'] ?? '') ?></textarea></label>
          </div>
        <?php endforeach; ?>
        <div class="admin-grid-two">
          <label class="admin-label"><span>Repetition count</span><input class="admin-input" type="number" min="1" name="repetition_count" value="<?= esc((string)$item['repetition_count']) ?>" required /></label>
          <label class="admin-label"><span>Display order</span><input class="admin-input" type="number" min="1" name="display_order" value="<?= esc((string)$item['display_order']) ?>" required /></label>
        </div>
        <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" <?= (int)$item['is_active'] === 1 ? 'checked' : '' ?> /><span>Athkar item is active</span></label>
        <div class="admin-form-actions"><button class="primary-button" type="submit"><?= $isEdit ? 'Save changes' : 'Create athkar' ?></button><a class="ghost-button" href="index.php">Cancel</a></div>
      </form>
    </section>
    </section>
  </main>
  <script>
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-langtab]'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('[data-langpane]'));
    function activate(lang) { tabs.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-langtab') === lang); }); panes.forEach(function (p) { p.hidden = p.getAttribute('data-langpane') !== lang; }); }
    tabs.forEach(function (b) { b.addEventListener('click', function () { activate(b.getAttribute('data-langtab')); }); });

    var keyPreview = document.getElementById('seo-item-key-preview');
    var keySource = document.querySelector('[data-item-key-source="1"]');
    var currentId = <?= (int)$id ?>;
    function slugifyForPreview(value) {
      var text = (value || '').toLowerCase().trim();
      text = text.normalize ? text.normalize('NFKD').replace(/[̀-ͯ]/g, '') : text;
      text = text.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      if (!text) text = 'athkar-item';
      var suffix = currentId > 0 ? String(currentId) : '';
      var maxBaseLength = suffix ? Math.max(1, 120 - suffix.length - 1) : 120;
      if (text.length > maxBaseLength) text = text.slice(0, maxBaseLength).replace(/-+$/g, '');
      return suffix ? (text + '-' + suffix) : text;
    }
    function updateKeyPreview() {
      if (!keyPreview) return;
      var value = keySource ? keySource.value : '';
      keyPreview.value = value.trim() ? slugifyForPreview(value) : 'Will be generated automatically from the title after saving';
    }
    if (keySource) {
      keySource.addEventListener('input', updateKeyPreview);
      keySource.addEventListener('change', updateKeyPreview);
      updateKeyPreview();
    }
  </script>
</body>
</html>
