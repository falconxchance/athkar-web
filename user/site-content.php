<?php
require_once __DIR__ . '/../config/auth.php';
require_superadmin();
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/lang_admin.php';

$pdo = app_pdo();
$languages = admin_edit_language_rows($pdo);
$langCodes = array_map(fn($row) => $row['code'], $languages);

$defaultsGlobal = [
    'theme_color' => '#0b3b2e',
    'favicon_url' => '',
    'app_icon_url' => '',
    'logo_url' => '',
    'report_email' => '',
    'theme_light_bg' => '#f6f3ec',
    'theme_light_surface' => '#ffffff',
    'theme_dark_bg' => '#0c1210',
    'theme_dark_surface' => '#111a16',
];
$defaultLangContent = [
    'site_title' => 'Athkar',
    'site_short_name' => 'Athkar',
    'site_description' => 'Athkar app with database-driven sections and content.',
    'home_header' => '',
    'home_footer' => '',
    'footer_note' => 'goAthkar | 2026 | v0.1',
];

function table_exists2(PDO $pdo, string $name): bool {
    return (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote($name))->fetchColumn();
}
function load_site_lang(PDO $pdo, string $lang, array $defaults): array {
    return get_site_content($pdo, $lang, array_merge([
        'theme_color' => '#0b3b2e',
        'favicon_url' => '',
        'app_icon_url' => '',
        'logo_url' => '',
        'report_email' => '',
        'theme_light_bg' => '#f6f3ec',
        'theme_light_surface' => '#ffffff',
        'theme_dark_bg' => '#0c1210',
        'theme_dark_surface' => '#111a16',
    ], $defaults));
}
function site_asset_upload_root(): string {
    return dirname(__DIR__) . '/uploads/site';
}
function site_asset_public_path(string $filename): string {
    return '/uploads/site/' . rawurlencode($filename);
}
function site_asset_is_local(?string $value): bool {
    return is_string($value) && str_starts_with($value, '/uploads/site/');
}
function site_asset_delete_local(?string $value): void {
    if (!site_asset_is_local($value)) return;
    $path = dirname(__DIR__) . rawurldecode((string)$value);
    if (is_file($path)) @unlink($path);
}
function sanitize_hex_value(?string $value, string $fallback): string {
    $value = trim((string)$value);
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : strtoupper($fallback);
}

function save_site_asset(string $fieldName, string $prefix, array $allowedExtensions, ?string $currentValue = null): string {
    $file = $_FILES[$fieldName] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return (string)$currentValue;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed to upload ' . str_replace('_', ' ', $prefix) . '.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload for ' . str_replace('_', ' ', $prefix) . '.');
    }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $allowedExtensions, true)) {
        throw new RuntimeException('Unsupported file type for ' . str_replace('_', ' ', $prefix) . '.');
    }

    $dir = site_asset_upload_root();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }
    $filename = $prefix . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('Unable to save uploaded file.');
    }
    if (site_asset_is_local($currentValue)) {
        site_asset_delete_local($currentValue);
    }
    return site_asset_public_path($filename);
}

$content = [];
foreach ($langCodes as $code) $content[$code] = load_site_lang($pdo, $code, $defaultLangContent);
$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    try {
        $pdo->beginTransaction();
        if (!table_exists2($pdo, 'site_content_i18n')) {
            throw new RuntimeException('Missing site_content_i18n table. Please import db/upgrade-i18n.sql first.');
        }
        $globalCurrent = $content[$langCodes[0] ?? 'en'] ?? array_merge($defaultsGlobal, $defaultLangContent);
        $save = $pdo->prepare('INSERT INTO site_content_i18n (content_key, lang, value) VALUES (:k,:l,:v) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $themeColor = sanitize_hex_value($_POST['theme_color'] ?? null, $defaultsGlobal['theme_color']);
        $themeLightBg = sanitize_hex_value($_POST['theme_light_bg'] ?? null, $defaultsGlobal['theme_light_bg']);
        $themeLightSurface = sanitize_hex_value($_POST['theme_light_surface'] ?? null, $defaultsGlobal['theme_light_surface']);
        $themeDarkBg = sanitize_hex_value($_POST['theme_dark_bg'] ?? null, $defaultsGlobal['theme_dark_bg']);
        $themeDarkSurface = sanitize_hex_value($_POST['theme_dark_surface'] ?? null, $defaultsGlobal['theme_dark_surface']);
        $faviconUrl = isset($_POST['remove_favicon']) ? '' : save_site_asset('favicon_file', 'favicon', ['ico','png','svg','webp'], (string)($globalCurrent['favicon_url'] ?? ''));
        $appIconUrl = isset($_POST['remove_app_icon']) ? '' : save_site_asset('app_icon_file', 'app-icon', ['png','webp','jpg','jpeg','svg'], (string)($globalCurrent['app_icon_url'] ?? ''));
        $logoUrl = isset($_POST['remove_logo']) ? '' : save_site_asset('logo_file', 'logo', ['png','webp','jpg','jpeg','svg'], (string)($globalCurrent['logo_url'] ?? ''));
        $reportEmail = trim((string)($_POST['report_email'] ?? ''));
        if ($reportEmail !== '' && !filter_var($reportEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid report email address.');
        }

        if (isset($_POST['remove_favicon'])) site_asset_delete_local((string)($globalCurrent['favicon_url'] ?? ''));
        if (isset($_POST['remove_app_icon'])) site_asset_delete_local((string)($globalCurrent['app_icon_url'] ?? ''));
        if (isset($_POST['remove_logo'])) site_asset_delete_local((string)($globalCurrent['logo_url'] ?? ''));

        foreach ($langCodes as $code) {
            $fallbackCode = $langCodes[0] ?? 'en';
            $siteTitle = trim((string)($_POST['site_title_' . $code] ?? ''));
            $shortName = trim((string)($_POST['site_short_name_' . $code] ?? ''));
            $siteDescription = trim((string)($_POST['site_description_' . $code] ?? ''));
            $homeHeader = (string)($_POST['home_header_' . $code] ?? '');
            $homeFooter = (string)($_POST['home_footer_' . $code] ?? '');
            $footerNote = trim((string)($_POST['footer_note_' . $code] ?? ''));
            $fallbackTitle = trim((string)($_POST['site_title_' . $fallbackCode] ?? $defaultLangContent['site_title']));
            $fallbackShort = trim((string)($_POST['site_short_name_' . $fallbackCode] ?? $defaultLangContent['site_short_name']));
            $fallbackDesc = trim((string)($_POST['site_description_' . $fallbackCode] ?? $defaultLangContent['site_description']));
            $fallbackHeader = (string)($_POST['home_header_' . $fallbackCode] ?? $defaultLangContent['home_header']);
            $fallbackFooter = (string)($_POST['home_footer_' . $fallbackCode] ?? $defaultLangContent['home_footer']);
            $fallbackFooterNote = trim((string)($_POST['footer_note_' . $fallbackCode] ?? $defaultLangContent['footer_note']));

            $pairs = [
                'site_title' => $siteTitle !== '' ? $siteTitle : $fallbackTitle,
                'site_short_name' => $shortName !== '' ? $shortName : $fallbackShort,
                'site_description' => $siteDescription !== '' ? $siteDescription : $fallbackDesc,
                'home_header' => trim($homeHeader) !== '' ? $homeHeader : $fallbackHeader,
                'home_footer' => trim($homeFooter) !== '' ? $homeFooter : $fallbackFooter,
                'footer_note' => $footerNote !== '' ? $footerNote : $fallbackFooterNote,
                'theme_color' => $themeColor,
                'favicon_url' => $faviconUrl,
                'app_icon_url' => $appIconUrl,
                'logo_url' => $logoUrl,
                'report_email' => $reportEmail,
                'theme_light_bg' => $themeLightBg,
                'theme_light_surface' => $themeLightSurface,
                'theme_dark_bg' => $themeDarkBg,
                'theme_dark_surface' => $themeDarkSurface,
            ];
            foreach ($pairs as $key => $value) {
                $save->execute(['k' => $key, 'l' => $code, 'v' => $value]);
            }
        }
        $pdo->commit();
        header('Location: site-content.php?saved=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save site content.';
    }
}
if (isset($_GET['saved'])) $flash = 'Site settings saved.';
foreach ($langCodes as $code) $content[$code] = load_site_lang($pdo, $code, $defaultLangContent);
$globalSource = $content[$langCodes[0] ?? 'en'] ?? array_merge($defaultsGlobal, $defaultLangContent);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Site Settings • Athkar Portal</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.1.16/dist/trix.css" />
  <script src="https://cdn.jsdelivr.net/npm/trix@2.1.16/dist/trix.umd.min.js"></script>
  <style>
    .lang-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 2px}
    .lang-tab{border:1px solid rgba(0,0,0,0.1);background:#fff;padding:8px 12px;border-radius:999px;font-weight:800;cursor:pointer}
    .lang-tab.is-active{background:rgba(0,0,0,0.06)}
    .site-asset-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
    .site-asset-card{border:1px solid rgba(15,23,42,0.08);border-radius:18px;padding:14px;background:#fff}
    .site-asset-preview{display:flex;align-items:center;justify-content:center;min-height:88px;border:1px dashed rgba(15,23,42,0.14);border-radius:14px;background:#f8fafc;margin:10px 0 12px}
    .site-asset-preview img{max-width:100%;max-height:64px;object-fit:contain}
    .site-asset-meta{font-size:.82rem;color:#5b6472;margin-top:8px;word-break:break-word}
  </style>
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'site'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="admin-eyebrow">Athkar Portal</p>
          <h1>Site Settings</h1>
          <p class="admin-subtitle">Upload the public assets, set the metadata, and manage the app home content for every active language.</p>
        </div>
      </header>

    <section class="admin-panel">
      <?php if ($flash): ?><div class="admin-alert success"><?= esc($flash) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="admin-alert error"><?= esc($error) ?></div><?php endif; ?>
      <form method="post" class="admin-form-stack" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
        <div class="admin-settings-grid">
          <label class="admin-inline-field"><span>Accent color</span><input class="admin-input" type="text" id="theme_color_input" name="theme_color" value="<?= esc($globalSource['theme_color'] ?? '#0b3b2e') ?>" /></label>
          <label class="admin-inline-field"><span>Accent picker</span><input class="admin-color-picker" type="color" value="<?= esc($globalSource['theme_color'] ?? '#0b3b2e') ?>" data-color-sync="theme_color_input" /></label>
          <label class="admin-inline-field"><span>Light background</span><input class="admin-input" type="text" id="theme_light_bg_input" name="theme_light_bg" value="<?= esc($globalSource['theme_light_bg'] ?? '#f6f3ec') ?>" /></label>
          <label class="admin-inline-field"><span>Picker</span><input class="admin-color-picker" type="color" value="<?= esc($globalSource['theme_light_bg'] ?? '#f6f3ec') ?>" data-color-sync="theme_light_bg_input" /></label>
          <label class="admin-inline-field"><span>Light card / surface</span><input class="admin-input" type="text" id="theme_light_surface_input" name="theme_light_surface" value="<?= esc($globalSource['theme_light_surface'] ?? '#ffffff') ?>" /></label>
          <label class="admin-inline-field"><span>Picker</span><input class="admin-color-picker" type="color" value="<?= esc($globalSource['theme_light_surface'] ?? '#ffffff') ?>" data-color-sync="theme_light_surface_input" /></label>
          <label class="admin-inline-field"><span>Dark background</span><input class="admin-input" type="text" id="theme_dark_bg_input" name="theme_dark_bg" value="<?= esc($globalSource['theme_dark_bg'] ?? '#0c1210') ?>" /></label>
          <label class="admin-inline-field"><span>Picker</span><input class="admin-color-picker" type="color" value="<?= esc($globalSource['theme_dark_bg'] ?? '#0c1210') ?>" data-color-sync="theme_dark_bg_input" /></label>
          <label class="admin-inline-field"><span>Dark card / surface</span><input class="admin-input" type="text" id="theme_dark_surface_input" name="theme_dark_surface" value="<?= esc($globalSource['theme_dark_surface'] ?? '#111a16') ?>" /></label>
          <label class="admin-inline-field"><span>Picker</span><input class="admin-color-picker" type="color" value="<?= esc($globalSource['theme_dark_surface'] ?? '#111a16') ?>" data-color-sync="theme_dark_surface_input" /></label>
        </div>
        <div class="admin-settings-grid" style="margin-top:14px">
          <label class="admin-inline-field admin-span-2"><span>Report email</span><input class="admin-input" type="email" name="report_email" value="<?= esc($globalSource['report_email'] ?? '') ?>" placeholder="reports@example.com" /></label>
        </div>
        <div class="site-asset-grid">
          <section class="site-asset-card">
            <p class="admin-note-title">Favicon</p>
            <div class="site-asset-preview">
              <?php if (!empty($globalSource['favicon_url'])): ?><img src="<?= esc($globalSource['favicon_url']) ?>" alt="Current favicon" /><?php else: ?><span class="admin-help-inline">No favicon uploaded</span><?php endif; ?>
            </div>
            <label class="admin-inline-field"><span>Upload favicon</span><input class="admin-file-input" type="file" name="favicon_file" accept=".ico,image/png,image/svg+xml,image/webp" /></label>
            <label class="admin-checkbox"><input type="checkbox" name="remove_favicon" value="1" /><span>Remove current favicon</span></label>
            <?php if (!empty($globalSource['favicon_url'])): ?><p class="site-asset-meta"><?= esc($globalSource['favicon_url']) ?></p><?php endif; ?>
          </section>
          <section class="site-asset-card">
            <p class="admin-note-title">App icon</p>
            <div class="site-asset-preview">
              <?php if (!empty($globalSource['app_icon_url'])): ?><img src="<?= esc($globalSource['app_icon_url']) ?>" alt="Current app icon" /><?php else: ?><span class="admin-help-inline">No app icon uploaded</span><?php endif; ?>
            </div>
            <label class="admin-inline-field"><span>Upload app icon</span><input class="admin-file-input" type="file" name="app_icon_file" accept="image/png,image/jpeg,image/webp,image/svg+xml" /></label>
            <label class="admin-checkbox"><input type="checkbox" name="remove_app_icon" value="1" /><span>Remove current app icon</span></label>
            <?php if (!empty($globalSource['app_icon_url'])): ?><p class="site-asset-meta"><?= esc($globalSource['app_icon_url']) ?></p><?php endif; ?>
          </section>
          <section class="site-asset-card">
            <p class="admin-note-title">Logo</p>
            <div class="site-asset-preview">
              <?php if (!empty($globalSource['logo_url'])): ?><img src="<?= esc($globalSource['logo_url']) ?>" alt="Current logo" /><?php else: ?><span class="admin-help-inline">No logo uploaded</span><?php endif; ?>
            </div>
            <label class="admin-inline-field"><span>Upload logo</span><input class="admin-file-input" type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/svg+xml" /></label>
            <label class="admin-checkbox"><input type="checkbox" name="remove_logo" value="1" /><span>Remove current logo</span></label>
            <?php if (!empty($globalSource['logo_url'])): ?><p class="site-asset-meta"><?= esc($globalSource['logo_url']) ?></p><?php endif; ?>
          </section>
        </div>
        <div class="admin-divider"></div>
        <div class="lang-tabs" role="tablist" aria-label="Languages">
          <?php foreach ($languages as $idx => $lang): ?>
            <button class="lang-tab<?= $idx === 0 ? ' is-active' : '' ?>" type="button" data-langtab="<?= esc($lang['code']) ?>"><?= esc($lang['native_label']) ?> (<?= esc($lang['code']) ?>)</button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($languages as $idx => $lang): $code = $lang['code']; $c = $content[$code]; ?>
          <div class="lang-pane" data-langpane="<?= esc($code) ?>" <?= $idx === 0 ? '' : 'hidden' ?>>
            <div class="admin-settings-grid">
              <label class="admin-inline-field"><span>Website title (<?= esc(strtoupper($code)) ?>)</span><input class="admin-input" type="text" name="site_title_<?= esc($code) ?>" value="<?= esc($c['site_title'] ?? '') ?>" /></label>
              <label class="admin-inline-field"><span>Short app name (<?= esc(strtoupper($code)) ?>)</span><input class="admin-input" type="text" name="site_short_name_<?= esc($code) ?>" value="<?= esc($c['site_short_name'] ?? '') ?>" /></label>
              <label class="admin-inline-field admin-span-2"><span>Meta description (<?= esc(strtoupper($code)) ?>)</span><textarea class="admin-textarea admin-textarea-compact" name="site_description_<?= esc($code) ?>" rows="3"><?= esc($c['site_description'] ?? '') ?></textarea></label>
              <label class="admin-inline-field admin-span-2"><span>Footer note (<?= esc(strtoupper($code)) ?>)</span><input class="admin-input" type="text" name="footer_note_<?= esc($code) ?>" value="<?= esc($c['footer_note'] ?? '') ?>" placeholder="goAthkar | 2026 | v0.1" /></label>
            </div>
            <div class="admin-form-row">
              <label for="home_header_<?= esc($code) ?>"><strong>Home header content (<?= esc(strtoupper($code)) ?>)</strong></label>
              <textarea id="home_header_<?= esc($code) ?>" name="home_header_<?= esc($code) ?>" class="admin-hidden-input" hidden spellcheck="false"><?php echo htmlspecialchars($c['home_header'] ?? ''); ?></textarea>
              <trix-editor input="home_header_<?= esc($code) ?>" class="admin-trix"></trix-editor>
            </div>
            <div class="admin-form-row">
              <label for="home_footer_<?= esc($code) ?>"><strong>Home footer content (<?= esc(strtoupper($code)) ?>)</strong></label>
              <textarea id="home_footer_<?= esc($code) ?>" name="home_footer_<?= esc($code) ?>" class="admin-hidden-input" hidden spellcheck="false"><?php echo htmlspecialchars($c['home_footer'] ?? ''); ?></textarea>
              <trix-editor input="home_footer_<?= esc($code) ?>" class="admin-trix"></trix-editor>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="admin-form-actions"><button type="submit" class="primary-button">Save</button></div>
      </form>
      <div class="admin-divider"></div>
      <p class="admin-help">Header and footer blocks stay hidden on the home page when left empty. Footer note appears as a simple text line at the end of public pages. Uploaded assets are used for favicon, app icon, logo, and manifest output automatically. Theme colors below control the app and public-page palette in light and dark mode. Report email receives a copy of every athkar issue report submitted by users.</p>
    </section>
    </section>
  </main>
  <script>
    document.querySelectorAll('[data-color-sync]').forEach(function (picker) {
      var target = document.getElementById(picker.getAttribute('data-color-sync')); if (!target) return;
      picker.addEventListener('input', function () { target.value = picker.value; });
      target.addEventListener('input', function () { if (/^#[0-9A-Fa-f]{6}$/.test(target.value)) picker.value = target.value; });
    });
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-langtab]'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('[data-langpane]'));
    function activate(lang) {
      tabs.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-langtab') === lang); });
      panes.forEach(function (p) { p.hidden = p.getAttribute('data-langpane') !== lang; });
    }
    tabs.forEach(function (b) { b.addEventListener('click', function () { activate(b.getAttribute('data-langtab')); }); });
    document.addEventListener('trix-file-accept', function (event) { event.preventDefault(); });
  </script>
</body>
</html>
