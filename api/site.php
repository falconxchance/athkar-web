<?php
require_once __DIR__ . '/../config/i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$defaults = [
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

$lang = get_request_lang();

try {
    $pdo = app_pdo();
    $result = get_site_content($pdo, $lang, $defaults);
    $result['lang'] = $lang;
    $result['dir'] = lang_dir($lang);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $defaults['lang'] = $lang;
    $defaults['dir'] = lang_dir($lang);
    echo json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
