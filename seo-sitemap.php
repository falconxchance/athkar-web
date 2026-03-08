<?php
require_once __DIR__ . '/config/seo.php';

$pdo = app_pdo();
$lang = current_lang_code($pdo, $_GET['lang'] ?? 'en');
$langMeta = current_lang_meta($pdo, $lang);
$site = site_defaults_for_lang($pdo, $lang);
$sections = seo_sections($pdo, $lang);
$pages = get_pages($pdo, $lang, true, false);
$activeLanguages = get_languages($pdo, true);
$ui = seo_ui_strings($pdo, $lang);
$canonical = build_public_url($lang, 'sitemap');
?><!doctype html>
<html lang="<?= esc($lang) ?>" dir="<?= esc($langMeta['dir']) ?>">
<head>
  <meta charset="utf-8" />
  <?= render_public_theme_boot_script() ?>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= esc(seo_t($ui, 'seo_sitemap_title', 'Sitemap') . ' • ' . $site['site_title']) ?></title>
  <meta name="description" content="<?= esc(seo_t($ui, 'seo_sitemap_description', 'Browse the public pages and athkar sections.')) ?>" />
  <meta name="robots" content="index,follow,max-image-preview:large" />
  <link rel="canonical" href="<?= esc($canonical) ?>" />
  <?php foreach ($activeLanguages as $alt): ?><link rel="alternate" hreflang="<?= esc($alt['code']) ?>" href="<?= esc(build_public_url($alt['code'], 'sitemap')) ?>" /><?php endforeach; ?>
  <link rel="alternate" hreflang="x-default" href="<?= esc(build_public_url(get_default_language($pdo)['code'], 'sitemap')) ?>" />
  <?= render_site_head_assets($site, $lang) ?>
  <link rel="stylesheet" href="/css/style.css" />
</head>
<body class="home-body">
  <?= render_public_sidebar($pdo, $site, $lang, fn($code, $type = 'home', $slug = null) => build_public_url($code, $type, $slug), 'sitemap') ?>
  <main class="home-shell public-page-shell">
    <?= render_public_brand_card($site, $ui, $lang) ?>
    <section class="hero-card"><p class="eyebrow"><?= esc($site['site_short_name'] ?? 'Athkar') ?></p><h1><?= esc(seo_t($ui, 'seo_sitemap_title', 'Sitemap')) ?></h1><p class="hero-text"><?= esc(seo_t($ui, 'seo_sitemap_description', 'Browse the public pages and athkar sections.')) ?></p></section>
    <?php if ($pages): ?>
      <section class="info-card sitemap-pages-card"><h2><?= esc(seo_t($ui, 'seo_sitemap_pages', 'Pages')) ?></h2><ul><?php foreach ($pages as $page): ?><li><a href="<?= esc(build_public_url($lang, 'page', $page['slug'])) ?>"><?= esc($page['title']) ?></a></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
    <section class="info-card"><h2><?= esc(seo_t($ui, 'seo_sitemap_sections', 'Athkar sections')) ?></h2><ul><?php foreach ($sections as $section): ?><li><a href="<?= esc(build_public_url($lang, 'section', $section['slug'])) ?>"><?= esc($section['label']) ?></a></li><?php endforeach; ?></ul></section>
    <?= render_public_footer_note($site) ?>
  </main>
  <script src="/js/theme.js"></script>
  <script src="/js/public-menu.js"></script>
</body>
</html>
