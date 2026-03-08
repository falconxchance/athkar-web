<?php
require_once __DIR__ . '/config/seo.php';

$pdo = app_pdo();
$lang = current_lang_code($pdo, $_GET['lang'] ?? 'en');
$langMeta = current_lang_meta($pdo, $lang);
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') { render_public_404($pdo, $lang); }
$site = site_defaults_for_lang($pdo, $lang);
$section = seo_section_row($pdo, $slug, $lang);
if (!$section) { render_public_404($pdo, $lang); }
$items = seo_section_items($pdo, $slug, $lang);
$activeLanguages = get_languages($pdo, true);
$ui = seo_ui_strings($pdo, $lang);
$canonical = build_public_url($lang, 'section', $slug);
$breadcrumbJson = json_ld_breadcrumbs([
    ['name' => $site['site_title'], 'item' => build_public_url($lang, 'home')],
    ['name' => $section['label'], 'item' => $canonical],
]);
?><!doctype html>
<html lang="<?= esc($lang) ?>" dir="<?= esc($langMeta['dir']) ?>">
<head>
  <meta charset="utf-8" />
  <?= render_public_theme_boot_script() ?>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= esc(seo_section_title($section, $site)) ?></title>
  <meta name="description" content="<?= esc(seo_section_description($section, $site, $lang)) ?>" />
  <meta name="robots" content="index,follow,max-image-preview:large" />
  <link rel="canonical" href="<?= esc($canonical) ?>" />
  <?php foreach ($activeLanguages as $alt): ?><link rel="alternate" hreflang="<?= esc($alt['code']) ?>" href="<?= esc(build_public_url($alt['code'], 'section', $slug)) ?>" /><?php endforeach; ?>
  <link rel="alternate" hreflang="x-default" href="<?= esc(build_public_url(get_default_language($pdo)['code'], 'section', $slug)) ?>" />
  <?= render_site_head_assets($site, $lang) ?>
  <link rel="stylesheet" href="/css/style.css" />
  <script type="application/ld+json"><?= $breadcrumbJson ?></script>
</head>
<body class="reader-body">
  <?= render_public_sidebar($pdo, $site, $lang, fn($code, $type = 'home', $slug = null) => build_public_url($code, $type, $slug), 'section', $slug) ?>
  <main class="reader-shell public-page-shell">
    <?= render_public_brand_card($site, $ui, $lang) ?>
    <header class="reader-header">
      <a class="ghost-button" href="<?= esc(build_public_url($lang, 'home')) ?>"><?= esc(seo_t($ui, 'seo_nav_home', 'Home')) ?></a>
      <div class="reader-title-wrap">
        <p class="eyebrow"><?= esc($section['description'] ?: 'Athkar') ?></p>
        <h1><?= esc($section['label']) ?></h1>
      </div>
      <a class="ghost-button" href="<?= esc(build_app_url($lang, $slug)) ?>"><?= esc(seo_t($ui, 'seo_nav_open_app', 'Open App')) ?></a>
    </header>

    <nav class="info-card breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom:14px">
      <a href="<?= esc(build_public_url($lang, 'home')) ?>"><?= esc($site['site_title']) ?></a> / <strong class="breadcrumb-current"><?= esc($section['label']) ?></strong>
    </nav>

    <?php foreach ($items as $it): ?>
      <article class="athkar-card" style="margin-bottom:14px">
        <h2><a href="<?= esc(build_public_url($lang, 'item', $it['item_key'])) ?>" style="color:inherit;text-decoration:none"><?= esc($it['title'] ?: excerpt_text($it['arabic'], 60)) ?></a></h2>
        <div class="arabic-box"><p class="arabic-text"><?= esc($it['arabic']) ?></p></div>
        <?php if (!empty($it['transliteration'])): ?><div class="text-block"><p><strong><?= esc(seo_t($ui, 'seo_label_transliteration', 'Transliteration')) ?>:</strong> <?= esc($it['transliteration']) ?></p></div><?php endif; ?>
        <?php if (!empty($it['translation'])): ?><div class="text-block"><p><strong><?= esc(seo_t($ui, 'seo_label_translation', 'Translation')) ?>:</strong> <?= esc($it['translation']) ?></p></div><?php endif; ?>
        <?php if (!empty($it['source'])): ?><div class="source-box"><p><strong><?= esc(seo_t($ui, 'seo_label_source', 'Source')) ?>:</strong> <?= esc($it['source']) ?></p></div><?php endif; ?>
        <p class="hero-note" style="margin-top:10px"><strong><?= esc(seo_t($ui, 'seo_count_label', 'Count')) ?>:</strong> <?= (int)$it['repetition_count'] ?></p>
        <p class="hero-note"><a href="<?= esc(build_public_url($lang, 'item', $it['item_key'])) ?>"><?= esc(seo_t($ui, 'seo_item_open', 'Open item')) ?></a></p>
      </article>
    <?php endforeach; ?>
    <?= render_public_footer_note($site) ?>
  </main>
  <script src="/js/theme.js"></script>
  <script src="/js/public-menu.js"></script>
</body>
</html>
