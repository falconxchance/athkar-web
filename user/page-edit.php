<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/pages.php';
require_superadmin();

$pdo = app_pdo();
$languages = function_exists('admin_get_edit_languages') ? admin_get_edit_languages($pdo) : get_languages($pdo, true);
$langCodes = array_column($languages, 'code');
if (!$langCodes) { $langCodes = ['en']; $languages = [default_lang_meta('en')]; }
$fallbackLang = in_array('en', $langCodes, true) ? 'en' : ($langCodes[0] ?? 'en');

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$page = ['slug' => '', 'display_order' => 1, 'is_active' => 1, 'show_on_home' => 1];
$i18n = [];
foreach ($langCodes as $code) $i18n[$code] = ['title' => '', 'content' => ''];

if ($isEdit) {
    ensure_custom_pages_tables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM athkar_pages WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); exit('Page not found.'); }
    $page = array_merge($page, $row);
    $tr = $pdo->prepare('SELECT lang, title, content FROM athkar_pages_i18n WHERE page_id = :id');
    $tr->execute(['id' => $id]);
    foreach ($tr->fetchAll() as $t) {
        $i18n[$t['lang']] = ['title' => (string)($t['title'] ?? ''), 'content' => (string)($t['content'] ?? '')];
    }
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $page['display_order'] = max(1, (int)($_POST['display_order'] ?? 1));
    $page['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $page['show_on_home'] = isset($_POST['show_on_home']) ? 1 : 0;
    $postedSlug = trim((string)($_POST['slug'] ?? ''));
    foreach ($langCodes as $code) {
        $i18n[$code] = [
            'title' => trim((string)($_POST['title_' . $code] ?? '')),
            'content' => (string)($_POST['content_' . $code] ?? ''),
        ];
    }
    $fallbackTitle = trim((string)($i18n[$fallbackLang]['title'] ?? ''));
    if ($fallbackTitle === '') {
        $error = 'The fallback page title is required.';
    } else {
        try {
            ensure_custom_pages_tables($pdo);
            $pdo->beginTransaction();
            $desiredSlug = $postedSlug !== '' ? $postedSlug : $fallbackTitle;
            $page['slug'] = pages_unique_slug($pdo, $desiredSlug, $isEdit ? $id : 0);

            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE athkar_pages SET slug = :slug, display_order = :display_order, is_active = :is_active, show_on_home = :show_on_home WHERE id = :id');
                $stmt->execute(['slug' => $page['slug'], 'display_order' => $page['display_order'], 'is_active' => $page['is_active'], 'show_on_home' => $page['show_on_home'], 'id' => $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO athkar_pages (slug, display_order, is_active, show_on_home) VALUES (:slug, :display_order, :is_active, :show_on_home)');
                $stmt->execute(['slug' => $page['slug'], 'display_order' => $page['display_order'], 'is_active' => $page['is_active'], 'show_on_home' => $page['show_on_home']]);
                $id = (int)$pdo->lastInsertId();
                $isEdit = true;
            }

            $up = $pdo->prepare('INSERT INTO athkar_pages_i18n (page_id, lang, title, content) VALUES (:page_id, :lang, :title, :content) ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content)');
            foreach ($langCodes as $code) {
                $title = trim((string)($i18n[$code]['title'] ?? ''));
                $content = (string)($i18n[$code]['content'] ?? '');
                if ($title === '' && trim(strip_tags($content)) === '') continue;
                $up->execute([
                    'page_id' => $id,
                    'lang' => $code,
                    'title' => $title,
                    'content' => $content,
                ]);
            }
            $pdo->commit();
            header('Location: page-edit.php?id=' . $id . '&saved=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Failed to save page.';
        }
    }
}
if (isset($_GET['saved'])) $success = 'Page saved successfully.';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $isEdit ? 'Edit Page' : 'Add Page' ?> • Athkar Portal</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
  <link rel="stylesheet" href="https://unpkg.com/trix@2.1.8/dist/trix.css" />
  <script src="https://unpkg.com/trix@2.1.8/dist/trix.umd.min.js"></script>
  <style>
    .lang-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 2px}
    .lang-tab{border:1px solid rgba(0,0,0,0.1);background:#fff;padding:8px 12px;border-radius:999px;font-weight:800;cursor:pointer}
    .lang-tab.is-active{background:rgba(0,0,0,0.06)}
  </style>
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'pages'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
      <header class="admin-page-header">
        <div class="admin-page-title"><p class="eyebrow">Athkar Portal</p><h1><?= $isEdit ? 'Edit Page' : 'Add Page' ?></h1><p class="admin-subtitle">Create public content pages with a clean editable slug used directly in the SEO URL.</p></div>
      </header>
      <section class="admin-panel">
        <?php if ($success): ?><div class="admin-alert success"><?= esc($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="admin-alert error"><?= esc($error) ?></div><?php endif; ?>
        <form method="post" class="admin-form-stack">
          <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
          <div class="admin-grid admin-grid-2">
            <label class="admin-label"><span>Display order</span><input class="admin-input" type="number" min="1" name="display_order" value="<?= esc((string)$page['display_order']) ?>" /></label>
            <label class="admin-label"><span>Page slug</span><input class="admin-input" id="page-slug" type="text" name="slug" value="<?= esc($page['slug']) ?>" placeholder="about-us" /><small class="admin-help">This exact slug is used for the page SEO URL. Leave it empty to generate it from the <?= esc(strtoupper($fallbackLang)) ?> title.</small></label>
          </div>
          <div class="lang-tabs" role="tablist" aria-label="Languages"><?php foreach ($languages as $idx => $lang): ?><button class="lang-tab<?= $idx === 0 ? ' is-active' : '' ?>" type="button" data-langtab="<?= esc($lang['code']) ?>"><?= esc($lang['native_label']) ?> (<?= esc($lang['code']) ?>)</button><?php endforeach; ?></div>
          <?php foreach ($languages as $idx => $lang): $code = $lang['code']; ?>
            <div class="lang-pane" data-langpane="<?= esc($code) ?>" <?= $idx === 0 ? '' : 'hidden' ?>>
              <label class="admin-label"><span>Page title (<?= esc(strtoupper($code)) ?>)</span><input class="admin-input" type="text" name="title_<?= esc($code) ?>" value="<?= esc($i18n[$code]['title'] ?? '') ?>" <?= $code === $fallbackLang ? 'id="page-slug-source"' : '' ?> /></label>
              <div class="admin-form-row">
                <label for="content_<?= esc($code) ?>"><strong>Page content (<?= esc(strtoupper($code)) ?>)</strong></label>
                <textarea id="content_<?= esc($code) ?>" name="content_<?= esc($code) ?>" class="admin-hidden-input" hidden spellcheck="false"><?php echo htmlspecialchars($i18n[$code]['content'] ?? ''); ?></textarea>
                <trix-editor input="content_<?= esc($code) ?>" class="admin-trix"></trix-editor>
              </div>
            </div>
          <?php endforeach; ?>
          <label class="admin-checkbox"><input type="checkbox" name="show_on_home" value="1" <?= (int)$page['show_on_home'] === 1 ? 'checked' : '' ?> /><span>Show this page on the home page</span></label>
          <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" <?= (int)$page['is_active'] === 1 ? 'checked' : '' ?> /><span>Page is active</span></label>
          <div class="admin-form-actions"><button class="primary-button" type="submit"><?= $isEdit ? 'Save changes' : 'Create page' ?></button><a class="ghost-button" href="pages.php">Cancel</a></div>
        </form>
      </section>
    </section>
  </main>
  <script>
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-langtab]'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('[data-langpane]'));
    function activate(lang) { tabs.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-langtab') === lang); }); panes.forEach(function (p) { p.hidden = p.getAttribute('data-langpane') !== lang; }); }
    tabs.forEach(function (b) { b.addEventListener('click', function () { activate(b.getAttribute('data-langtab')); }); });

    var slugInput = document.getElementById('page-slug');
    var slugSource = document.getElementById('page-slug-source');
    var slugTouched = !!(slugInput && slugInput.value.trim());

    function slugifyForInput(value) {
      var text = (value || '').toLowerCase().trim();
      text = text.normalize ? text.normalize('NFKD').replace(/[\u0300-\u036f]/g, '') : text;
      text = text.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      return text.slice(0, 120).replace(/-+$/g, '');
    }

    if (slugInput) {
      slugInput.addEventListener('input', function () {
        slugTouched = slugInput.value.trim() !== '';
      });
    }
    if (slugSource && slugInput) {
      function syncSlugFromTitle() {
        if (slugTouched) return;
        slugInput.value = slugifyForInput(slugSource.value || '');
      }
      slugSource.addEventListener('input', syncSlugFromTitle);
      slugSource.addEventListener('change', syncSlugFromTitle);
      syncSlugFromTitle();
    }
  </script>
</body>
</html>
