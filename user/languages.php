<?php
require_once __DIR__ . '/../config/auth.php';
require_superadmin();
require_once __DIR__ . '/../config/lang_admin.php';

$pdo = app_pdo();
$has = (bool)$pdo->query("SHOW TABLES LIKE 'app_languages'")->fetchColumn();
if (!$has) {
    http_response_code(500);
    exit('Missing app_languages table. Please import db/upgrade-i18n.sql first.');
}

$flash = '';
$error = '';
$editCode = sanitize_lang_admin_code($_GET['edit'] ?? '');
$current = [
    'code' => '',
    'label' => '',
    'native_label' => '',
    'dir' => 'ltr',
    'is_active' => 1,
    'display_order' => 1,
];

if ($editCode !== '') {
    $stmt = $pdo->prepare('SELECT code, label, native_label, COALESCE(dir, CASE WHEN code = "ar" THEN "rtl" ELSE "ltr" END) AS dir, is_active, display_order FROM app_languages WHERE code = :c LIMIT 1');
    $stmt->execute(['c' => $editCode]);
    $row = $stmt->fetch();
    if ($row) $current = $row;
}

function active_language_count(PDO $pdo): int {
    return (int)$pdo->query('SELECT COUNT(*) FROM app_languages WHERE is_active = 1')->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $action = (string)($_POST['action'] ?? '');
    $code = sanitize_lang_admin_code($_POST['code'] ?? '');

    try {
        if ($action === 'save') {
            $code = sanitize_lang_admin_code($_POST['code'] ?? '');
            $label = trim((string)($_POST['label'] ?? ''));
            $native = trim((string)($_POST['native_label'] ?? ''));
            $dir = ($_POST['dir'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $displayOrder = max(1, (int)($_POST['display_order'] ?? 1));
            $original = sanitize_lang_admin_code($_POST['original_code'] ?? '');

            if ($code === '' || $label === '' || $native === '') {
                throw new RuntimeException('Please fill in language code, label, and native label. Use a simple code like en, ar, ur, or fr.');
            }

            $pdo->beginTransaction();
            if ($original !== '' && $original !== $code) {
                $check = $pdo->prepare('SELECT code FROM app_languages WHERE code = :c LIMIT 1');
                $check->execute(['c' => $code]);
                if ($check->fetch()) throw new RuntimeException('Language code already exists.');

                $up = $pdo->prepare('UPDATE app_languages SET code = :newc, label = :label, native_label = :native, dir = :dir, is_active = :active, display_order = :ord WHERE code = :oldc');
                $up->execute(['newc' => $code, 'label' => $label, 'native' => $native, 'dir' => $dir, 'active' => $isActive, 'ord' => $displayOrder, 'oldc' => $original]);

                foreach (['ui_strings','site_content_i18n'] as $table) {
                    if ((bool)$pdo->query("SHOW TABLES LIKE '$table'")->fetchColumn()) {
                        $pdo->prepare("UPDATE $table SET lang = :newc WHERE lang = :oldc")->execute(['newc' => $code, 'oldc' => $original]);
                    }
                }
                foreach (['athkar_sections_i18n','athkar_items_i18n'] as $table) {
                    if ((bool)$pdo->query("SHOW TABLES LIKE '$table'")->fetchColumn()) {
                        $pdo->prepare("UPDATE $table SET lang = :newc WHERE lang = :oldc")->execute(['newc' => $code, 'oldc' => $original]);
                    }
                }
            } else {
                $up = $pdo->prepare('INSERT INTO app_languages (code, label, native_label, dir, is_active, display_order) VALUES (:code,:label,:native,:dir,:active,:ord) ON DUPLICATE KEY UPDATE label = VALUES(label), native_label = VALUES(native_label), dir = VALUES(dir), is_active = VALUES(is_active), display_order = VALUES(display_order)');
                $up->execute(['code' => $code, 'label' => $label, 'native' => $native, 'dir' => $dir, 'active' => $isActive, 'ord' => $displayOrder]);
            }
            seed_language_content($pdo, $code);
            if ($isActive !== 1 && active_language_count($pdo) < 1) {
                throw new RuntimeException('At least one language must stay active.');
            }
            $pdo->commit();
            header('Location: languages.php?saved=1');
            exit;
        }

        if ($action === 'toggle' && $code !== '') {
            $row = $pdo->prepare('SELECT is_active FROM app_languages WHERE code = :c LIMIT 1');
            $row->execute(['c' => $code]);
            $item = $row->fetch();
            if ($item) {
                if ((int)$item['is_active'] === 1 && active_language_count($pdo) <= 1) {
                    throw new RuntimeException('At least one language must stay active.');
                }
                $stmt = $pdo->prepare('UPDATE app_languages SET is_active = 1 - is_active WHERE code = :c');
                $stmt->execute(['c' => $code]);
                header('Location: languages.php?updated=1');
                exit;
            }
        }

        if ($action === 'delete' && $code !== '') {
            $row = $pdo->prepare('SELECT is_active FROM app_languages WHERE code = :c LIMIT 1');
            $row->execute(['c' => $code]);
            $item = $row->fetch();
            if ($item) {
                if ((int)$item['is_active'] === 1 && active_language_count($pdo) <= 1) {
                    throw new RuntimeException('At least one language must stay active.');
                }
                $pdo->beginTransaction();
                foreach (['ui_strings','site_content_i18n','athkar_sections_i18n','athkar_items_i18n'] as $table) {
                    if ((bool)$pdo->query("SHOW TABLES LIKE '$table'")->fetchColumn()) {
                        $pdo->prepare("DELETE FROM $table WHERE lang = :lang")->execute(['lang' => $code]);
                    }
                }
                $pdo->prepare('DELETE FROM app_languages WHERE code = :c')->execute(['c' => $code]);
                $pdo->commit();
                header('Location: languages.php?deleted=1');
                exit;
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to update languages.';
    }
}

if (isset($_GET['saved'])) $flash = 'Language saved. New languages start by copying English content until you translate their UI, sections, items, and site content.';
if (isset($_GET['updated'])) $flash = 'Language updated.';
if (isset($_GET['deleted'])) $flash = 'Language deleted.';
$rows = admin_language_rows($pdo, false);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Languages • Athkar Portal</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'langs'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="admin-eyebrow">Athkar Portal</p>
          <h1>Languages</h1>
          <p class="admin-subtitle">Create, edit, activate, deactivate, or remove public app languages. Direction (LTR / RTL) is controlled here too.</p>
        </div>
      </header>

    <section class="admin-panel">
      <?php if ($flash): ?><div class="admin-alert success"><?= esc($flash) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="admin-alert error"><?= esc($error) ?></div><?php endif; ?>

      <form method="post" class="admin-form-stack">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
        <input type="hidden" name="action" value="save" />
        <input type="hidden" name="original_code" value="<?= esc($editCode) ?>" />
        <div class="admin-grid-two">
          <label class="admin-label"><span>Code</span><input class="admin-input" type="text" name="code" value="<?= esc($current['code']) ?>" placeholder="en" maxlength="8" required /></label>
          <label class="admin-label"><span>Display order</span><input class="admin-input" type="number" name="display_order" min="1" value="<?= esc((string)$current['display_order']) ?>" required /></label>
          <label class="admin-label"><span>Label</span><input class="admin-input" type="text" name="label" value="<?= esc($current['label']) ?>" placeholder="English" required /></label>
          <label class="admin-label"><span>Native label</span><input class="admin-input" type="text" name="native_label" value="<?= esc($current['native_label']) ?>" placeholder="English" required /></label>
        </div>
        <div class="admin-grid-two">
          <label class="admin-label"><span>Direction</span><select class="admin-select" name="dir"><option value="ltr" <?= $current['dir'] === 'ltr' ? 'selected' : '' ?>>LTR</option><option value="rtl" <?= $current['dir'] === 'rtl' ? 'selected' : '' ?>>RTL</option></select></label>
          <label class="admin-checkbox" style="align-self:end"><input type="checkbox" name="is_active" value="1" <?= (int)$current['is_active'] === 1 ? 'checked' : '' ?> /><span>Language is active</span></label>
        </div>
        <div class="admin-form-actions">
          <button class="primary-button" type="submit"><?= $editCode ? 'Save language' : 'Add language' ?></button>
          <?php if ($editCode): ?><a class="ghost-button" href="languages.php">Cancel</a><?php endif; ?>
        </div>
      </form>
    </section>

    <section class="admin-panel">
      <table class="admin-table">
        <thead><tr><th>Code</th><th>Label</th><th>Native</th><th>Dir</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><code><?= esc($r['code']) ?></code></td>
              <td><?= esc($r['label']) ?></td>
              <td><?= esc($r['native_label']) ?></td>
              <td><span class="badge"><?= esc(strtoupper($r['dir'])) ?></span></td>
              <td><?php if ((int)$r['is_active'] === 1): ?><span class="badge badge-green">Active</span><?php else: ?><span class="badge">Inactive</span><?php endif; ?></td>
              <td>
                <div class="admin-action-row">
                  <a class="ghost-button admin-icon-button" href="languages.php?edit=<?= urlencode($r['code']) ?>" title="Edit language" aria-label="Edit language"><?= admin_icon('edit') ?></a>
                  <a class="ghost-button admin-icon-button" href="translation-export.php?lang=<?= urlencode($r['code']) ?>" title="Download translation file" aria-label="Download translation file"><?= admin_icon('download') ?></a>
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                    <input type="hidden" name="code" value="<?= esc($r['code']) ?>" />
                    <input type="hidden" name="action" value="toggle" />
                    <button class="ghost-button admin-icon-button <?= ((int)$r['is_active'] === 1) ? 'is-toggle-active' : 'is-toggle-inactive' ?>" type="submit" title="<?= ((int)$r['is_active'] === 1) ? 'Deactivate language' : 'Activate language' ?>" aria-label="<?= ((int)$r['is_active'] === 1) ? 'Deactivate language' : 'Activate language' ?>">
                      <?= ((int)$r['is_active'] === 1) ? admin_icon('toggle-on') : admin_icon('toggle-off') ?>
                    </button>
                  </form>
                  <form method="post" onsubmit="return confirm('Delete this language and its translations?');">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                    <input type="hidden" name="code" value="<?= esc($r['code']) ?>" />
                    <input type="hidden" name="action" value="delete" />
                    <button class="ghost-button admin-icon-button is-danger" type="submit" title="Delete language" aria-label="Delete language"><?= admin_icon('delete') ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="admin-help" style="margin-top:14px">When you add a language here, the app switcher, site content, UI strings, sections, and athkar item editors will pick it up automatically. New languages are seeded from English first so the site stays usable immediately; then you can translate that language from the admin pages. Use the download icon to export a full translation JSON for any language. Then go to Translations to import a translated JSON file back into the system in one go.</p>
    </section>
    </section>
  </main>
</body>
</html>
