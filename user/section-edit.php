<?php
require_once __DIR__ . '/../config/auth.php';
require_editor();
require_once __DIR__ . '/../config/lang_admin.php';

$pdo = app_pdo();
$languages = admin_edit_language_rows($pdo);
$langCodes = array_map(fn($row) => $row['code'], $languages);
$slug = trim((string)($_GET['slug'] ?? ''));
$isEdit = $slug !== '';
$error = '';
$success = '';

$section = [
    'slug' => '', 'icon' => '', 'display_order' => 1, 'is_active' => 1,
    'label' => '', 'description' => ''
];
$i18n = [];
foreach ($langCodes as $code) $i18n[$code] = ['label' => '', 'description' => ''];
$hasI18n = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_sections_i18n'")->fetchColumn();

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM athkar_sections WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $found = $stmt->fetch();
    if (!$found) { http_response_code(404); exit('Section not found.'); }
    $section = array_merge($section, $found);
    if ($hasI18n) {
        $tr = $pdo->prepare('SELECT lang, label, description FROM athkar_sections_i18n WHERE section_slug = :slug');
        $tr->execute(['slug' => $slug]);
        foreach ($tr->fetchAll() as $row) {
            $lang = (string)$row['lang'];
            if (!isset($i18n[$lang])) $i18n[$lang] = ['label' => '', 'description' => ''];
            $i18n[$lang] = ['label' => (string)($row['label'] ?? ''), 'description' => (string)($row['description'] ?? '')];
        }
    }
    if (isset($i18n['en'])) {
        if (trim($i18n['en']['label']) === '') $i18n['en']['label'] = (string)($section['label'] ?? '');
        if (trim($i18n['en']['description']) === '') $i18n['en']['description'] = (string)($section['description'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $originalSlug = trim((string)($_POST['original_slug'] ?? ''));
    $section['slug'] = strtolower(trim((string)($_POST['slug'] ?? '')));
    $section['icon'] = trim((string)($_POST['icon'] ?? ''));
    $section['display_order'] = max(1, (int)($_POST['display_order'] ?? 1));
    $section['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    foreach ($langCodes as $code) {
        $i18n[$code] = [
            'label' => trim((string)($_POST['label_' . $code] ?? '')),
            'description' => trim((string)($_POST['description_' . $code] ?? '')),
        ];
    }
    $fallbackLang = in_array('en', $langCodes, true) ? 'en' : ($langCodes[0] ?? '');
    $fallbackLabel = $fallbackLang !== '' ? trim((string)($i18n[$fallbackLang]['label'] ?? '')) : '';
    $fallbackDesc = $fallbackLang !== '' ? trim((string)($i18n[$fallbackLang]['description'] ?? '')) : '';
    if ($section['slug'] === '' || $fallbackLabel === '') {
        $error = 'Please fill in section slug and at least one label.';
    } elseif (!preg_match('/^[a-z0-9-]+$/', $section['slug'])) {
        $error = 'Slug may only contain lowercase letters, numbers, and hyphens.';
    } else {
        if ($isEdit) {
            $check = $pdo->prepare('SELECT slug FROM athkar_sections WHERE slug = :slug AND slug <> :original_slug LIMIT 1');
            $check->execute(['slug' => $section['slug'], 'original_slug' => $originalSlug]);
        } else {
            $check = $pdo->prepare('SELECT slug FROM athkar_sections WHERE slug = :slug LIMIT 1');
            $check->execute(['slug' => $section['slug']]);
        }
        if ($check->fetch()) {
            $error = 'Slug must be unique.';
        } else {
            try {
                $pdo->beginTransaction();
                if ($isEdit) {
                    $stmt = $pdo->prepare('UPDATE athkar_sections SET slug = :slug, label = :label, description = :description, icon = :icon, display_order = :display_order, is_active = :is_active WHERE slug = :original_slug');
                    $stmt->execute([
                        'slug' => $section['slug'], 'label' => $fallbackLabel, 'description' => $fallbackDesc !== '' ? $fallbackDesc : null,
                        'icon' => $section['icon'] !== '' ? $section['icon'] : null, 'display_order' => $section['display_order'], 'is_active' => $section['is_active'], 'original_slug' => $originalSlug,
                    ]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO athkar_sections (slug, label, description, icon, display_order, is_active) VALUES (:slug, :label, :description, :icon, :display_order, :is_active)');
                    $stmt->execute([
                        'slug' => $section['slug'], 'label' => $fallbackLabel, 'description' => $fallbackDesc !== '' ? $fallbackDesc : null,
                        'icon' => $section['icon'] !== '' ? $section['icon'] : null, 'display_order' => $section['display_order'], 'is_active' => $section['is_active'],
                    ]);
                    $isEdit = true;
                    $originalSlug = $section['slug'];
                }
                if ($hasI18n) {
                    $up = $pdo->prepare('INSERT INTO athkar_sections_i18n (section_slug, lang, label, description) VALUES (:slug,:lang,:label,:description) ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description)');
                    foreach ($langCodes as $code) {
                        $v = $i18n[$code];
                        $up->execute([
                            'slug' => $section['slug'], 'lang' => $code,
                            'label' => $v['label'] !== '' ? $v['label'] : $fallbackLabel,
                            'description' => $v['description'] !== '' ? $v['description'] : ($fallbackDesc !== '' ? $fallbackDesc : null),
                        ]);
                    }
                }
                $pdo->commit();
                header('Location: section-edit.php?slug=' . urlencode($section['slug']) . '&saved=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Unable to save section.';
            }
        }
    }
}
if (isset($_GET['saved'])) $success = 'Section saved.';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $isEdit ? 'Edit Section' : 'Add Section' ?></title>
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
    <?php $activePage = 'sections'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content admin-content--narrow">
      <header class="admin-page-header">
        <div class="admin-page-title"><p class="eyebrow">Athkar Portal</p><h1><?= $isEdit ? 'Edit Section' : 'Add Section' ?></h1><p class="admin-subtitle">Control how the section appears in the app and SEO pages.</p></div>
      </header>

    <section class="admin-panel">
      <?php if ($error): ?><p class="admin-alert admin-alert-error"><?= esc($error) ?></p><?php endif; ?>
      <?php if ($success): ?><p class="admin-alert admin-alert-success"><?= esc($success) ?></p><?php endif; ?>
      <form method="post" class="admin-form-stack">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
        <input type="hidden" name="original_slug" value="<?= esc($slug) ?>" />
        <div class="admin-grid-two">
          <label class="admin-label"><span>Section slug</span><input class="admin-input" type="text" name="slug" value="<?= esc($section['slug']) ?>" placeholder="morning" required /></label>
          <label class="admin-label"><span>Icon</span><input class="admin-input" type="text" name="icon" value="<?= esc($section['icon'] ?? '') ?>" placeholder="☀️" /></label>
        </div>
        <div class="lang-tabs" role="tablist" aria-label="Languages"><?php foreach ($languages as $idx => $lang): ?><button class="lang-tab<?= $idx === 0 ? ' is-active' : '' ?>" type="button" data-langtab="<?= esc($lang['code']) ?>"><?= esc($lang['native_label']) ?> (<?= esc($lang['code']) ?>)</button><?php endforeach; ?></div>
        <?php foreach ($languages as $idx => $lang): $code = $lang['code']; ?>
          <div class="lang-pane" data-langpane="<?= esc($code) ?>" <?= $idx === 0 ? '' : 'hidden' ?>>
            <label class="admin-label"><span>Label (<?= esc(strtoupper($code)) ?>)</span><input class="admin-input" type="text" name="label_<?= esc($code) ?>" value="<?= esc($i18n[$code]['label'] ?? '') ?>" placeholder="Morning Athkar" /></label>
            <label class="admin-label"><span>Description (<?= esc(strtoupper($code)) ?>)</span><input class="admin-input" type="text" name="description_<?= esc($code) ?>" value="<?= esc($i18n[$code]['description'] ?? '') ?>" placeholder="Subtitle shown on the home tile" /></label>
          </div>
        <?php endforeach; ?>
        <div class="admin-grid-two">
          <label class="admin-label"><span>Display order</span><input class="admin-input" type="number" min="1" name="display_order" value="<?= esc((string)$section['display_order']) ?>" required /></label>
          <div></div>
        </div>
        <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" <?= (int)$section['is_active'] === 1 ? 'checked' : '' ?> /><span>Section is active</span></label>
        <div class="admin-form-actions"><button class="primary-button" type="submit"><?= $isEdit ? 'Save changes' : 'Create section' ?></button><a class="ghost-button" href="sections.php">Cancel</a></div>
      </form>
    </section>
    </section>
  </main>
  <script>
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-langtab]'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('[data-langpane]'));
    function activate(lang) { tabs.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-langtab') === lang); }); panes.forEach(function (p) { p.hidden = p.getAttribute('data-langpane') !== lang; }); }
    tabs.forEach(function (b) { b.addEventListener('click', function () { activate(b.getAttribute('data-langtab')); }); });
  </script>
</body>
</html>
