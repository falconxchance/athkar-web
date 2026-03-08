<?php
require_once __DIR__ . '/config/seo.php';

$pdo = app_pdo();
$lang = current_lang_code($pdo, $_GET['lang'] ?? 'en');
$langMeta = current_lang_meta($pdo, $lang);
$site = site_defaults_for_lang($pdo, $lang);
$sections = seo_sections($pdo, $lang);
$pages = get_pages($pdo, $lang, true, true);
$activeLanguages = get_languages($pdo, true);
$ui = seo_ui_strings($pdo, $lang);
$canonical = build_public_url($lang, 'home');
$webJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => (string)($site['site_title'] ?? 'Athkar'),
    'url' => $canonical,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?><!doctype html>
<html lang="<?= esc($lang) ?>" dir="<?= esc($langMeta['dir']) ?>">
<head>
  <meta charset="utf-8" />
  <?= render_public_theme_boot_script() ?>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= esc($site['site_title']) ?></title>
  <meta name="description" content="<?= esc(excerpt_text($site['site_description'], 160)) ?>" />
  <meta name="robots" content="index,follow,max-image-preview:large" />
  <link rel="canonical" href="<?= esc($canonical) ?>" />
  <?php foreach ($activeLanguages as $alt): ?><link rel="alternate" hreflang="<?= esc($alt['code']) ?>" href="<?= esc(build_public_url($alt['code'], 'home')) ?>" /><?php endforeach; ?>
  <link rel="alternate" hreflang="x-default" href="<?= esc(build_public_url(get_default_language($pdo)['code'], 'home')) ?>" />
  <?= render_site_head_assets($site, $lang) ?>
  <link rel="stylesheet" href="/css/style.css" />
  <script type="application/ld+json"><?= $webJsonLd ?></script>
</head>
<body class="home-body">
  <?= render_public_sidebar($pdo, $site, $lang, fn($code, $type = 'home', $slug = null) => build_public_url($code, $type, $slug), 'home') ?>
  <main class="home-shell home-shell-app home-shell-simple public-page-shell">
    <section class="hero-card home-hero-card home-hero-card-welcome">
      <?php if (!empty($site['logo_url'])): ?>
        <a class="brand-logo-link" href="<?= esc(build_app_url($lang)) ?>" aria-label="<?= esc(($site['site_short_name'] ?: $site['site_title'] ?: 'Athkar')) ?> home"><div class="home-hero-brand"><img class="home-logo" src="<?= esc($site['logo_url']) ?>" alt="<?= esc($site['site_title']) ?> logo" /></div></a>
      <?php endif; ?>
      <div class="home-hero-copy">
        <p class="eyebrow"><?= esc(seo_t($ui, 'home_welcome_eyebrow', 'Welcome')) ?></p>
        <h1><?= esc(seo_t($ui, 'home_welcome_title', 'Welcome to {app}', ['app' => ($site['site_short_name'] ?: $site['site_title'])])) ?></h1>
        <p class="hero-text"><?= esc($site['site_description'] ?: seo_t($ui, 'home_welcome_intro', 'Read your daily athkar in a simple, polished, multilingual experience.')) ?></p>
        <p class="hero-note"><a class="primary-button" href="<?= esc(build_app_url($lang)) ?>"><?= esc(seo_t($ui, 'seo_nav_open_app', 'Open App')) ?></a></p>
      </div>
    </section>

    <?php if (trim(strip_tags((string)($site['home_header'] ?? ''))) !== ''): ?>
      <section class="info-card home-content-card"><?= $site['home_header'] ?></section>
    <?php endif; ?>

    <section class="home-sections-section">
      <div class="home-section-heading home-section-heading-simple"><p class="eyebrow">Athkar</p><h2><?= esc(seo_t($ui, 'home_sections_title', 'Choose a section')) ?></h2><p class="hero-text"><?= esc(seo_t($ui, 'home_sections_intro', 'Start with one of the athkar sections below.')) ?></p></div>
      <section class="section-grid" aria-label="Athkar sections">
        <?php foreach ($sections as $s): ?>
          <a class="section-tile" href="<?= esc(build_public_url($lang, 'section', $s['slug'])) ?>">
            <span class="tile-icon"><?= esc($s['icon'] ?: '✨') ?></span>
            <span class="tile-title"><?= esc($s['label']) ?></span>
            <span class="tile-subtitle"><?= esc($s['description'] ?: seo_t($ui, 'lbl_open_section', 'Open section')) ?></span>
          </a>
        <?php endforeach; ?>
      </section>
    </section>

    <?php if (trim(strip_tags((string)($site['home_footer'] ?? ''))) !== ''): ?>
      <section class="info-card home-content-card"><?= $site['home_footer'] ?></section>
    <?php endif; ?>

    <footer class="home-footer-links" aria-label="More pages">
      <div class="home-footer-links-head"><p class="eyebrow"><?= esc(seo_t($ui, 'home_footer_links_title', 'More')) ?></p></div>
      <nav class="home-link-chips">
        <a class="home-link-chip" href="<?= esc(build_public_url($lang, 'sitemap')) ?>"><span class="home-link-chip-icon">🗺️</span><span class="home-link-chip-text"><?= esc(seo_t($ui, 'home_link_sitemap', 'Sitemap')) ?></span></a>
        <?php foreach ($pages as $page): ?>
          <a class="home-link-chip" href="<?= esc(build_public_url($lang, 'page', $page['slug'])) ?>"><span class="home-link-chip-icon">📄</span><span class="home-link-chip-text"><?= esc($page['title']) ?></span></a>
        <?php endforeach; ?>
      </nav>
    </footer>
    <?= render_public_footer_note($site) ?>
  </main>
  <script src="/js/theme.js"></script>
  <script src="/js/public-menu.js"></script>
</body>
</html>
