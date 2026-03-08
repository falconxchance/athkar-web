<?php
require_once __DIR__ . '/config/seo.php';

$pdo = app_pdo();
$lang = current_lang_code($pdo, $_GET['lang'] ?? 'en');
$langMeta = current_lang_meta($pdo, $lang);
$slug = trim((string)($_GET['page'] ?? ''));
if ($slug === '') { render_public_404($pdo, $lang); }
$site = site_defaults_for_lang($pdo, $lang);
$page = get_page_by_slug($pdo, $slug, $lang, true);
if (!$page) { render_public_404($pdo, $lang); }
$activeLanguages = get_languages($pdo, true);
$ui = seo_ui_strings($pdo, $lang);
$canonical = build_public_url($lang, 'page', $slug);
$breadcrumbJson = json_ld_breadcrumbs([
    ['name' => $site['site_title'], 'item' => build_public_url($lang, 'home')],
    ['name' => ($page['title'] ?: 'Page'), 'item' => $canonical],
]);
?><!doctype html>
<html lang="<?= esc($lang) ?>" dir="<?= esc($langMeta['dir']) ?>">
<head>
  <meta charset="utf-8" />
  <?= render_public_theme_boot_script() ?>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= esc(seo_page_title($page, $site)) ?></title>
  <meta name="description" content="<?= esc(seo_page_description($page, $site, $lang)) ?>" />
  <meta name="robots" content="index,follow,max-image-preview:large" />
  <link rel="canonical" href="<?= esc($canonical) ?>" />
  <?php foreach ($activeLanguages as $alt): ?><link rel="alternate" hreflang="<?= esc($alt['code']) ?>" href="<?= esc(build_public_url($alt['code'], 'page', $slug)) ?>" /><?php endforeach; ?>
  <link rel="alternate" hreflang="x-default" href="<?= esc(build_public_url(get_default_language($pdo)['code'], 'page', $slug)) ?>" />
  <?= render_site_head_assets($site, $lang) ?>
  <link rel="stylesheet" href="/css/style.css" />
  <script type="application/ld+json"><?= $breadcrumbJson ?></script>
</head>
<body class="reader-body">
  <?= render_public_sidebar($pdo, $site, $lang, fn($code, $type = 'home', $slug = null) => build_public_url($code, $type, $slug), 'page', $slug) ?>
  <main class="reader-shell public-page-shell">
    <?= render_public_brand_card($site, $ui, $lang) ?>
    <header class="reader-header reader-header-centered">
      <div class="reader-title-wrap">
        <p class="eyebrow"><?= esc($site['site_short_name'] ?? 'Athkar') ?></p>
        <h1><?= esc($page['title']) ?></h1>
      </div>
    </header>
    <nav class="info-card breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom:14px"><a href="<?= esc(build_public_url($lang, 'home')) ?>"><?= esc($site['site_title']) ?></a> / <strong class="breadcrumb-current"><?= esc($page['title']) ?></strong></nav>
    <article class="athkar-card page-content-card"><?= $page['content'] ?></article>
    <?= render_public_footer_note($site) ?>
  </main>
  <script src="/js/theme.js"></script>
  <script src="/js/public-menu.js"></script>
</body>
</html>
