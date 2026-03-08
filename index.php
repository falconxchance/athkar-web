<?php
require_once __DIR__ . '/config/seo.php';

$initialLang = get_request_lang();
try {
    $pdo = app_pdo();
    $site = site_defaults_for_lang($pdo, $initialLang);
    $ui = seo_ui_strings($pdo, $initialLang);
} catch (Throwable $e) {
    $pdo = null;
    $site = [
        'site_title' => 'Athkar',
        'site_short_name' => 'Athkar',
        'site_description' => 'Athkar app with database-driven sections and content.',
        'theme_color' => '#0b3b2e',
        'favicon_url' => '',
        'app_icon_url' => '',
        'logo_url' => '',
        'home_header' => '',
        'home_footer' => '',
        'footer_note' => 'goAthkar | 2026 | v0.1',
        'theme_light_bg' => '#f6f3ec',
        'theme_light_surface' => '#ffffff',
        'theme_dark_bg' => '#0c1210',
        'theme_dark_surface' => '#111a16',
    ];
    $ui = [];
}
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($initialLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= htmlspecialchars((string)($site['site_description'] ?? 'Athkar app with database-driven sections and content.'), ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="robots" content="noindex,follow" />
  <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars((string)($site['site_short_name'] ?? $site['site_title'] ?? 'Athkar'), ENT_QUOTES, 'UTF-8') ?>" />
  <script>
    (function () {
      try {
        var t = localStorage.getItem('athkar_theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      } catch (e) {}
    })();
  </script>
  <script>
    (function () {
      try {
        var qp = new URLSearchParams(window.location.search);
        var ql = qp.get('lang');
        var pathMatch = window.location.pathname.match(/^\/app\/([a-zA-Z]{2,8})(?:\/|$)/);
        var pathLang = pathMatch ? pathMatch[1] : null;
        var l = ql || pathLang || localStorage.getItem('athkar_lang');
        if (!l) {
          var nav = (navigator.language || 'en').toLowerCase();
          l = nav.split('-')[0] || 'en';
        }
        localStorage.setItem('athkar_lang', l);
        document.documentElement.setAttribute('lang', (l || 'en').toLowerCase().split('-')[0]);
        var code = (l || 'en').toLowerCase().split('-')[0];
        document.documentElement.setAttribute('dir', ['ar','fa','ur','he'].indexOf(code) !== -1 ? 'rtl' : 'ltr');
      } catch (e) {}
    })();
  </script>
  <title><?= htmlspecialchars((string)($site['site_title'] ?? 'Athkar'), ENT_QUOTES, 'UTF-8') ?></title>  <?= render_site_head_assets($site, $initialLang) ?>
  <link rel="stylesheet" href="/css/style.css" />
</head>
<body class="home-body">
  <div id="loading-overlay" class="loading-overlay" role="status" aria-live="polite" aria-label="Loading">
    <div class="loading-bubble" aria-hidden="true"><div class="spinner"></div></div>
  </div>
  <?= $pdo ? render_public_sidebar($pdo, $site, $initialLang, fn($code, $type = 'home', $slug = null) => $type === 'home' ? build_app_url($code) : build_public_url($code, $type, $slug), 'home', null, 'lang-select', true) : '' ?>
  <main class="home-shell home-shell-app home-shell-simple">
    <section class="hero-card home-hero-card home-hero-card-welcome" id="home-hero-card">
      <a class="brand-logo-link" id="home-brand-link" href="/app/<?= htmlspecialchars($initialLang, ENT_QUOTES, 'UTF-8') ?>/" aria-label="App home">
        <div class="home-hero-brand" id="home-hero-brand" hidden>
          <img id="home-logo" class="home-logo" src="" alt="Site logo" />
        </div>
      </a>
      <div class="home-hero-copy">
        <p class="eyebrow" id="home-hero-eyebrow">Welcome</p>
        <h1 id="home-hero-title">Welcome to Athkar</h1>
        <p class="hero-text" id="home-hero-description">Read your daily athkar in a simple, polished, multilingual experience.</p>
      </div>
    </section>

    <section class="info-card home-content-card" id="home-header-card" hidden>
      <div id="home-header" aria-live="polite"></div>
    </section>

    <section class="home-sections-section">
      <div class="home-section-heading home-section-heading-simple">
        <h2 id="home-sections-title">Choose a section</h2>
        <p class="hero-text" id="home-sections-description">Start with one of the athkar sections below.</p>
      </div>
      <section class="section-grid" id="section-grid" aria-label="Athkar sections">
        <div class="info-card skeleton-card" id="sections-loading-card" aria-hidden="true">
          <div class="skeleton-line w-55"></div>
          <div class="skeleton-line w-80"></div>
          <div class="skeleton-line w-70"></div>
        </div>
      </section>
    </section>

    <section class="info-card" id="sections-empty-card" hidden>
      <h2>No active sections found</h2>
      <p>Create or activate a section from <code>/user/sections.php</code>, then refresh this page.</p>
    </section>
    <section class="info-card" id="sections-error-card" hidden>
      <h2>Unable to load sections</h2>
      <p>Please check the database connection and the <code>api/sections.php</code> file on your hosting.</p>
    </section>

    <section class="info-card home-content-card" id="home-footer-card" hidden>
      <div id="home-footer" aria-live="polite"></div>
    </section>
    <p class="public-footer-note" id="home-footer-note" hidden></p>
  </main>
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js').catch(function () {});
      });
    }
  </script>
  <script src="/js/theme.js"></script>
  <script src="/js/public-menu.js"></script>
  <script src="/js/i18n.js"></script>
  <script src="/js/home.js"></script>
</body>
</html>
