<?php
require_once __DIR__ . '/config/i18n.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$lang = get_request_lang();
try {
    $pdo = app_pdo();
    $site = get_site_content($pdo, $lang, [
        'site_title' => 'Athkar',
        'site_short_name' => 'Athkar',
        'site_description' => 'Athkar app with database-driven sections and content.',
        'theme_color' => '#0b3b2e',
        'app_icon_url' => '',
        'favicon_url' => '',
    ]);
} catch (Throwable $e) {
    $site = [
        'site_title' => 'Athkar',
        'site_short_name' => 'Athkar',
        'site_description' => 'Athkar app with database-driven sections and content.',
        'theme_color' => '#0b3b2e',
        'app_icon_url' => '',
        'favicon_url' => '',
    ];
}

$icon = trim((string)($site['app_icon_url'] ?? ''));
if ($icon === '') $icon = trim((string)($site['favicon_url'] ?? ''));

$manifest = [
    'name' => (string)($site['site_title'] ?? 'Athkar'),
    'short_name' => (string)($site['site_short_name'] ?? 'Athkar'),
    'start_url' => '/app/' . rawurlencode($lang) . '/',
    'display' => 'standalone',
    'background_color' => '#f6f3ec',
    'theme_color' => (string)($site['theme_color'] ?? '#0b3b2e'),
    'description' => (string)($site['site_description'] ?? 'Athkar app with database-driven sections and content.'),
    'icons' => [],
];
if ($icon !== '') {
    $ext = strtolower((string)pathinfo((string)(parse_url($icon, PHP_URL_PATH) ?: ''), PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'svg' => 'image/svg+xml',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        default => 'image/png',
    };
    $manifest['icons'][] = [
        'src' => $icon,
        'sizes' => 'any',
        'type' => $mime,
        'purpose' => 'any maskable',
    ];
}

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
