<?php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/pages.php';

if (!function_exists('esc')) {
    function esc(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function build_app_url(string $lang, ?string $sectionSlug = null, ?string $itemKey = null): string
{
    $lang = sanitize_lang($lang);
    $base = base_url();
    if ($sectionSlug !== null && $sectionSlug !== '') {
        $url = $base . '/app/' . rawurlencode($lang) . '/section/' . rawurlencode($sectionSlug) . '/';
        if ($itemKey !== null && $itemKey !== '') {
            $url .= '?item=' . rawurlencode($itemKey);
        }
        return $url;
    }
    return $base . '/app/' . rawurlencode($lang) . '/';
}

function build_public_url(string $lang, string $type = 'home', ?string $slug = null): string
{
    $lang = sanitize_lang($lang);
    $base = base_url();
    if ($type === 'home') return build_app_url($lang);
    if ($type === 'section' && $slug !== null) return $base . '/' . rawurlencode($lang) . '/section/' . rawurlencode($slug) . '/';
    if ($type === 'item' && $slug !== null) return $base . '/' . rawurlencode($lang) . '/item/' . rawurlencode($slug) . '/';
    if ($type === 'page' && $slug !== null) return $base . '/' . rawurlencode($lang) . '/page/' . rawurlencode($slug) . '/';
    if ($type === 'sitemap') return $base . '/' . rawurlencode($lang) . '/sitemap/';
    return build_app_url($lang);
}

function current_lang_meta(PDO $pdo, string $requested): array
{
    $requested = sanitize_lang($requested);
    $active = get_languages_map($pdo, true);
    if (isset($active[$requested])) return $active[$requested];
    return get_default_language($pdo);
}

function current_lang_code(PDO $pdo, string $requested): string
{
    $meta = current_lang_meta($pdo, $requested);
    return (string)($meta['code'] ?? 'en');
}

function site_defaults_for_lang(PDO $pdo, string $lang): array
{
    return get_site_content($pdo, $lang, [
        'site_title' => 'Athkar',
        'site_short_name' => 'Athkar',
        'site_description' => 'Athkar app with database-driven sections and content.',
        'theme_color' => '#0b3b2e',
        'favicon_url' => '',
        'app_icon_url' => '',
        'logo_url' => '',
        'report_email' => '',
        'home_header' => '',
        'home_footer' => '',
        'footer_note' => 'goAthkar | 2026 | v0.1',
        'theme_light_bg' => '#f6f3ec',
        'theme_light_surface' => '#ffffff',
        'theme_dark_bg' => '#0c1210',
        'theme_dark_surface' => '#111a16',
    ]);
}

function seo_ui_strings(PDO $pdo, string $lang): array
{
    ensure_default_ui_strings($pdo);
    return get_ui_strings($pdo, $lang);
}

function seo_t(array $ui, string $key, string $fallback, array $vars = []): string
{
    $value = isset($ui[$key]) && trim((string)$ui[$key]) !== '' ? (string)$ui[$key] : $fallback;
    foreach ($vars as $k => $v) {
        $value = str_replace('{' . $k . '}', (string)$v, $value);
    }
    return $value;
}


function site_manifest_url(string $lang): string
{
    return base_url() . '/manifest.php?lang=' . rawurlencode(sanitize_lang($lang));
}

function seo_hex_color(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) return strtoupper($value);
    return strtoupper($fallback);
}

function render_site_theme_vars(array $site): string
{
    $accent = seo_hex_color($site['theme_color'] ?? null, '#0B3B2E');
    $lightBg = seo_hex_color($site['theme_light_bg'] ?? null, '#F6F3EC');
    $lightSurface = seo_hex_color($site['theme_light_surface'] ?? null, '#FFFFFF');
    $darkBg = seo_hex_color($site['theme_dark_bg'] ?? null, '#0C1210');
    $darkSurface = seo_hex_color($site['theme_dark_surface'] ?? null, '#111A16');
    return '<style>:root{--theme-accent:' . esc($accent) . ' !important;--theme-light-bg:' . esc($lightBg) . ' !important;--theme-light-surface:' . esc($lightSurface) . ' !important;--theme-dark-bg:' . esc($darkBg) . ' !important;--theme-dark-surface:' . esc($darkSurface) . ' !important;}</style>' ;
}

function render_site_head_assets(array $site, string $lang): string
{
    $theme = trim((string)($site['theme_color'] ?? '#0b3b2e'));
    if ($theme === '') $theme = '#0b3b2e';
    $favicon = trim((string)($site['favicon_url'] ?? ''));
    $appIcon = trim((string)($site['app_icon_url'] ?? ''));
    if ($favicon === '' && $appIcon !== '') $favicon = $appIcon;

    ob_start();
    ?>
  <meta name="theme-color" content="<?= esc($theme) ?>" />
  <?php if ($favicon !== ''): ?><link rel="icon" href="<?= esc($favicon) ?>" /><?php endif; ?>
  <?php if ($appIcon !== ''): ?><link rel="apple-touch-icon" href="<?= esc($appIcon) ?>" /><?php endif; ?>
  <link rel="manifest" href="<?= esc(site_manifest_url($lang)) ?>" />
  <?= render_site_theme_vars($site) ?>
    <?php
    return trim((string)ob_get_clean());
}


function render_public_theme_boot_script(): string
{
    return '<script>(function(){try{var t=localStorage.getItem("athkar_theme");if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>';
}

function render_public_sidebar(PDO $pdo, array $site, string $currentLang, callable $urlFactory, string $currentType = 'home', ?string $currentSlug = null, string $selectId = 'public-lang-select', bool $jsManagedSelect = false): string
{
    $languages = get_languages($pdo, true);
    $pages = get_pages($pdo, $currentLang, true, true);
    $brandName = trim((string)($site['site_short_name'] ?: $site['site_title'] ?: 'Athkar'));
    $ui = seo_ui_strings($pdo, $currentLang);
    ob_start();
    ?>
    <button class="ghost-button public-menu-button tap-safe" type="button" data-public-menu-open aria-label="<?= esc(seo_t($ui, 'public_sidebar_open_menu', 'Open menu')) ?>" title="<?= esc(seo_t($ui, 'public_sidebar_open_menu', 'Open menu')) ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /></svg>
    </button>
    <div class="public-sidebar-backdrop" data-public-sidebar-backdrop hidden></div>
    <aside class="public-sidebar" data-public-sidebar aria-label="Site navigation">
      <div class="public-sidebar-card public-sidebar-brand">
        <div class="public-sidebar-brand__row">
          <div>
            <p class="eyebrow"><?= esc($brandName) ?></p>
            <h2><?= esc($site['site_title'] ?: $brandName) ?></h2>
            <p class="public-sidebar-help"><?= esc(seo_t($ui, 'public_sidebar_help', 'Browse the app, switch language, and change the theme from one menu.')) ?></p>
          </div>
          <button class="ghost-button public-sidebar-close tap-safe" type="button" data-public-menu-close aria-label="<?= esc(seo_t($ui, 'public_sidebar_close_menu', 'Close menu')) ?>" title="<?= esc(seo_t($ui, 'public_sidebar_close_menu', 'Close menu')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12" /><path d="M18 6L6 18" /></svg>
          </button>
        </div>
      </div>

      <nav class="public-sidebar-card public-sidebar-nav" aria-label="Site links">
        <a class="public-sidebar-link<?= $currentType === 'home' ? ' is-active' : '' ?>" href="<?= esc($urlFactory($currentLang, 'home', null)) ?>">🏠 <span><?= esc(seo_t($ui, 'public_sidebar_home', 'Home')) ?></span></a>
        <a class="public-sidebar-link<?= $currentType === 'sitemap' ? ' is-active' : '' ?>" href="<?= esc($urlFactory($currentLang, 'sitemap', null)) ?>">🗺️ <span><?= esc(seo_t($ui, 'public_sidebar_sitemap', 'Sitemap')) ?></span></a>
        <?php foreach ($pages as $page): ?>
          <a class="public-sidebar-link<?= $currentType === 'page' && $currentSlug === $page['slug'] ? ' is-active' : '' ?>" href="<?= esc($urlFactory($currentLang, 'page', $page['slug'])) ?>">📄 <span><?= esc($page['title']) ?></span></a>
        <?php endforeach; ?>
      </nav>

      <div class="public-sidebar-card public-sidebar-tools">
        <div class="public-sidebar-tool">
          <span class="public-sidebar-tool__label"><?= esc(seo_t($ui, 'public_sidebar_language', 'Language')) ?></span>
          <label class="public-sidebar-select-wrap" aria-label="<?= esc(seo_t($ui, 'public_sidebar_language', 'Language')) ?>">
            <select id="<?= esc($selectId) ?>" class="public-sidebar-select<?= $jsManagedSelect ? ' lang-select' : '' ?>"<?= $jsManagedSelect ? '' : ' onchange="if(this.value){window.location.href=this.value;}"' ?>>
              <?php foreach ($languages as $row): ?>
                <?php $optionValue = $jsManagedSelect ? $row['code'] : $urlFactory($row['code'], $currentType, $currentSlug); ?>
                <option value="<?= esc($optionValue) ?>" <?= $row['code'] === $currentLang ? 'selected' : '' ?>><?= esc($row['native_label'] ?: $row['label'] ?: strtoupper($row['code'])) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="public-sidebar-tool">
          <span class="public-sidebar-tool__label"><?= esc(seo_t($ui, 'public_sidebar_theme', 'Theme')) ?></span>
          <button class="ghost-button icon-button public-sidebar-theme-button tap-safe" type="button" data-theme-toggle aria-label="<?= esc(seo_t($ui, 'public_sidebar_theme', 'Theme')) ?>"></button>
        </div>
      </div>
    </aside>
    <?php
    return trim((string)ob_get_clean());
}

function render_public_footer_note(array $site): string
{
    $note = trim((string)($site['footer_note'] ?? ''));
    if ($note === '') return '';
    return '<p class="public-footer-note">' . esc($note) . '</p>';
}

function render_public_brand_card(array $site, array $ui, string $lang): string
{
    $brandName = trim((string)($site['site_short_name'] ?: $site['site_title'] ?: 'Athkar'));
    $brandTitle = seo_t($ui, 'home_welcome_title', 'Welcome to {app}', ['app' => $brandName]);
    $brandIntro = trim((string)($site['site_description'] ?: seo_t($ui, 'home_welcome_intro', 'Read your daily athkar in a simple, polished, multilingual experience.')));
    ob_start();
    ?>
    <section class="hero-card home-hero-card home-hero-card-welcome public-brand-card">
      <?php if (!empty($site['logo_url'])): ?>
        <a class="brand-logo-link" href="<?= esc(build_app_url($lang)) ?>" aria-label="<?= esc($brandName) ?> home">
          <div class="home-hero-brand"><img class="home-logo" src="<?= esc($site['logo_url']) ?>" alt="<?= esc($site['site_title'] ?: $brandName) ?> logo" /></div>
        </a>
      <?php endif; ?>
      <div class="home-hero-copy">
        <p class="eyebrow"><?= esc(seo_t($ui, 'home_welcome_eyebrow', 'Welcome')) ?></p>
        <p class="public-brand-title"><?= esc($brandTitle) ?></p>
        <?php if ($brandIntro !== ''): ?><p class="hero-text"><?= esc($brandIntro) ?></p><?php endif; ?>
      </div>
    </section>
    <?php
    return trim((string)ob_get_clean());
}

function detect_public_lang_from_request(PDO $pdo): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string)parse_url($uri, PHP_URL_PATH);
    if (preg_match('#^/(?:app/)?([a-zA-Z]{2,8})(?:/|$)#', $path, $m)) {
        return current_lang_code($pdo, $m[1]);
    }
    return get_default_language($pdo)['code'] ?? 'en';
}

function render_public_404(PDO $pdo, ?string $requestedLang = null): void
{
    http_response_code(404);
    $lang = current_lang_code($pdo, $requestedLang ?: detect_public_lang_from_request($pdo));
    $langMeta = current_lang_meta($pdo, $lang);
    $site = site_defaults_for_lang($pdo, $lang);
    $ui = seo_ui_strings($pdo, $lang);
    $requestedPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    ?><!doctype html>
<html lang="<?= esc($lang) ?>" dir="<?= esc($langMeta['dir']) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?= render_public_theme_boot_script() ?>
  <title><?= esc(seo_t($ui, 'seo_404_title', 'Page not found') . ' • ' . ($site['site_title'] ?: 'Athkar')) ?></title>
  <meta name="description" content="<?= esc(seo_t($ui, 'seo_404_description', 'The page you requested could not be found.')) ?>" />
  <meta name="robots" content="noindex,follow" />
  <?= render_site_head_assets($site, $lang) ?>
  <link rel="stylesheet" href="/css/style.css" />
</head>
<body class="home-body">
  <?= render_public_sidebar($pdo, $site, $lang, fn($code, $type = 'home', $slug = null) => build_public_url($code, $type, $slug), 'home') ?>
  <main class="home-shell public-page-shell">
    <section class="hero-card">
      <p class="eyebrow">404</p>
      <h1><?= esc(seo_t($ui, 'seo_404_heading', 'Page not found')) ?></h1>
      <p class="hero-text"><?= esc(seo_t($ui, 'seo_404_body', 'The page you were looking for does not exist or may have moved.')) ?></p>
      <?php if ($requestedPath !== ''): ?><p class="hero-note"><code><?= esc($requestedPath) ?></code></p><?php endif; ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
        <a class="primary-button" href="<?= esc(build_public_url($lang, 'home')) ?>"><?= esc(seo_t($ui, 'seo_nav_home', 'Home')) ?></a>
        <a class="ghost-button" href="<?= esc(build_public_url($lang, 'sitemap')) ?>"><?= esc(seo_t($ui, 'seo_sitemap_title', 'Sitemap')) ?></a>
      </div>
    </section>
    <?= render_public_footer_note($site) ?>
  </main>
  <script src="/js/theme.js"></script>
  <script src="/js/public-menu.js"></script>
</body>
</html><?php
    exit;
}

function seo_section_row(PDO $pdo, string $slug, string $lang): ?array
{
    $hasI18n = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_sections_i18n'")->fetchColumn();
    if ($hasI18n) {
        $stmt = $pdo->prepare(
            'SELECT s.slug, s.icon, s.updated_at, COALESCE(t_req.label, t_en.label, s.label) AS label, COALESCE(t_req.description, t_en.description, s.description) AS description
             FROM athkar_sections s
             LEFT JOIN athkar_sections_i18n t_req ON t_req.section_slug = s.slug AND t_req.lang = :lang
             LEFT JOIN athkar_sections_i18n t_en ON t_en.section_slug = s.slug AND t_en.lang = :fallback_lang
             WHERE s.slug = :slug AND s.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug, 'lang' => $lang, 'fallback_lang' => 'en']);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    $stmt = $pdo->prepare('SELECT slug, icon, updated_at, label, description FROM athkar_sections WHERE slug=:slug AND is_active=1 LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function seo_sections(PDO $pdo, string $lang): array
{
    $hasI18n = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_sections_i18n'")->fetchColumn();
    if ($hasI18n) {
        $stmt = $pdo->prepare(
            'SELECT s.slug, s.icon, s.updated_at, COALESCE(t_req.label, t_en.label, s.label) AS label, COALESCE(t_req.description, t_en.description, s.description) AS description
             FROM athkar_sections s
             LEFT JOIN athkar_sections_i18n t_req ON t_req.section_slug = s.slug AND t_req.lang = :lang
             LEFT JOIN athkar_sections_i18n t_en ON t_en.section_slug = s.slug AND t_en.lang = :fallback_lang
             WHERE s.is_active = 1
             ORDER BY s.display_order ASC, s.slug ASC'
        );
        $stmt->execute(['lang' => $lang, 'fallback_lang' => 'en']);
        return $stmt->fetchAll() ?: [];
    }
    return $pdo->query('SELECT slug, icon, updated_at, label, description FROM athkar_sections WHERE is_active=1 ORDER BY display_order ASC, slug ASC')->fetchAll() ?: [];
}

function seo_section_items(PDO $pdo, string $slug, string $lang): array
{
    $hasItemI18n = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_items_i18n'")->fetchColumn();
    if ($hasItemI18n) {
        $stmt = $pdo->prepare(
            'SELECT i.id, i.item_key, i.arabic, i.repetition_count, i.updated_at,
                    COALESCE(t_req.title, t_en.title, i.title) AS title,
                    COALESCE(t_req.transliteration, t_en.transliteration, i.transliteration) AS transliteration,
                    COALESCE(t_req.translation, t_en.translation, i.translation) AS translation,
                    COALESCE(t_req.source, t_en.source, i.source) AS source
             FROM athkar_items i
             LEFT JOIN athkar_items_i18n t_req ON t_req.item_id=i.id AND t_req.lang=:lang
             LEFT JOIN athkar_items_i18n t_en ON t_en.item_id=i.id AND t_en.lang=:fallback_lang
             WHERE i.section_slug=:slug AND i.is_active=1
             ORDER BY i.display_order ASC, i.id ASC'
        );
        $stmt->execute(['slug' => $slug, 'lang' => $lang, 'fallback_lang' => 'en']);
        return $stmt->fetchAll() ?: [];
    }
    $stmt = $pdo->prepare('SELECT id, item_key, arabic, repetition_count, updated_at, title, transliteration, translation, source FROM athkar_items WHERE section_slug=:slug AND is_active=1 ORDER BY display_order ASC, id ASC');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetchAll() ?: [];
}

function seo_item_row(PDO $pdo, string $itemKey, string $lang): ?array
{
    $hasItemI18n = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_items_i18n'")->fetchColumn();
    if ($hasItemI18n) {
        $stmt = $pdo->prepare(
            'SELECT i.id, i.item_key, i.section_slug, i.arabic, i.repetition_count, i.updated_at,
                    COALESCE(t_req.title, t_en.title, i.title) AS title,
                    COALESCE(t_req.transliteration, t_en.transliteration, i.transliteration) AS transliteration,
                    COALESCE(t_req.translation, t_en.translation, i.translation) AS translation,
                    COALESCE(t_req.source, t_en.source, i.source) AS source
             FROM athkar_items i
             LEFT JOIN athkar_items_i18n t_req ON t_req.item_id=i.id AND t_req.lang=:lang
             LEFT JOIN athkar_items_i18n t_en ON t_en.item_id=i.id AND t_en.lang=:fallback_lang
             WHERE i.item_key=:item_key AND i.is_active=1
             LIMIT 1'
        );
        $stmt->execute(['item_key' => $itemKey, 'lang' => $lang, 'fallback_lang' => 'en']);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    $stmt = $pdo->prepare('SELECT id, item_key, section_slug, arabic, repetition_count, updated_at, title, transliteration, translation, source FROM athkar_items WHERE item_key=:item_key AND is_active=1 LIMIT 1');
    $stmt->execute(['item_key' => $itemKey]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function excerpt_text(?string $text, int $limit = 155): string
{
    $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags((string)$text)));
    if ($text === '') return '';

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $limit) return $text;
        return rtrim(mb_substr($text, 0, $limit - 1, 'UTF-8')) . '…';
    }

    if (strlen($text) <= $limit) return $text;
    return rtrim(substr($text, 0, $limit - 1)) . '…';
}

function seo_section_title(array $section, array $site): string
{
    $name = trim((string)($section['label'] ?? 'Athkar'));
    $siteName = trim((string)($site['site_title'] ?? 'Athkar'));
    return $name === '' ? $siteName : ($name . ' | ' . $siteName);
}

function seo_section_description(array $section, array $site, string $lang): string
{
    $desc = excerpt_text($section['description'] ?? '', 160);
    if ($desc !== '') return $desc;
    $name = trim((string)($section['label'] ?? 'Athkar'));
    if ($lang === 'ar') return 'تصفح قسم ' . ($name ?: 'الأذكار') . ' مع النص العربي والترجمة والمصدر.';
    return 'Read ' . ($name ?: 'athkar') . ' with Arabic text, translation, transliteration, and source.';
}

function seo_item_title(array $item, array $section, array $site): string
{
    $main = trim((string)($item['title'] ?? ''));
    if ($main === '') $main = excerpt_text($item['arabic'] ?? '', 70);
    $sectionName = trim((string)($section['label'] ?? 'Athkar'));
    $siteName = trim((string)($site['site_title'] ?? 'Athkar'));
    if ($main === '') return $sectionName . ' | ' . $siteName;
    return $main . ' | ' . $sectionName . ' | ' . $siteName;
}

function seo_item_description(array $item, array $section, array $site, string $lang): string
{
    foreach (['translation','source','transliteration'] as $field) {
        $desc = excerpt_text($item[$field] ?? '', 160);
        if ($desc !== '') return $desc;
    }
    $sectionName = trim((string)($section['label'] ?? 'Athkar'));
    if ($lang === 'ar') return 'اقرأ هذا الذكر من قسم ' . ($sectionName ?: 'الأذكار') . ' مع النص العربي والمصدر والعدد.';
    return 'Read this athkar from ' . ($sectionName ?: 'Athkar') . ' with Arabic text, source, and repetition count.';
}

function json_ld_breadcrumbs(array $items): string
{
    $list = [];
    $pos = 1;
    foreach ($items as $item) {
        $list[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => (string)$item['name'],
            'item' => (string)$item['item'],
        ];
    }
    return json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function seo_page_title(array $page, array $site): string
{
    $title = trim((string)($page['title'] ?? ''));
    $siteTitle = trim((string)($site['site_title'] ?? 'Athkar'));
    if ($title === '') return $siteTitle;
    return $title . ' • ' . $siteTitle;
}

function seo_page_description(array $page, array $site, string $lang): string
{
    $excerpt = page_plain_excerpt((string)($page['content'] ?? ''), 160);
    if ($excerpt !== '') return $excerpt;
    return excerpt_text((string)($site['site_description'] ?? 'Athkar app with database-driven sections and content.'), 160);
}
