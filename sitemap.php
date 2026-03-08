<?php
while (ob_get_level() > 0) { ob_end_clean(); }

require_once __DIR__ . '/config/seo.php';

header('Content-Type: application/xml; charset=utf-8');

function xml_escape($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

try {
    $pdo = app_pdo();
    $langs = get_languages($pdo, true);
    $defaultLang = get_default_language($pdo)['code'];
    $sections = $pdo->query('SELECT slug, updated_at FROM athkar_sections WHERE is_active=1 ORDER BY display_order ASC, slug ASC')->fetchAll() ?: [];
    $items = $pdo->query('SELECT item_key, updated_at FROM athkar_items WHERE is_active=1 ORDER BY display_order ASC, id ASC')->fetchAll() ?: [];
    $pages = get_pages($pdo, 'en', true, false);

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

    $emit = function (string $loc, ?string $lastmod, callable $alternateFactory) use ($langs, $defaultLang) {
        echo "  <url>\n";
        echo '    <loc>' . xml_escape($loc) . "</loc>\n";
        foreach ($langs as $alt) {
            $href = $alternateFactory($alt['code']);
            echo '    <xhtml:link rel="alternate" hreflang="' . xml_escape($alt['code']) . '" href="' . xml_escape($href) . '" />' . "\n";
        }
        echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . xml_escape($alternateFactory($defaultLang)) . '" />' . "\n";
        if ($lastmod) echo '    <lastmod>' . xml_escape($lastmod) . "</lastmod>\n";
        echo "  </url>\n";
    };

    foreach ($langs as $lang) {
        $emit(build_public_url($lang['code'], 'home'), null, function ($code) { return build_public_url($code, 'home'); });
        $emit(build_public_url($lang['code'], 'sitemap'), null, function ($code) { return build_public_url($code, 'sitemap'); });
    }
    foreach ($sections as $s) {
        $last = !empty($s['updated_at']) ? date('c', strtotime($s['updated_at'])) : null;
        foreach ($langs as $lang) {
            $slug = (string)$s['slug'];
            $emit(build_public_url($lang['code'], 'section', $slug), $last, function ($code) use ($slug) { return build_public_url($code, 'section', $slug); });
        }
    }
    foreach ($items as $it) {
        $last = !empty($it['updated_at']) ? date('c', strtotime($it['updated_at'])) : null;
        foreach ($langs as $lang) {
            $key = (string)$it['item_key'];
            $emit(build_public_url($lang['code'], 'item', $key), $last, function ($code) use ($key) { return build_public_url($code, 'item', $key); });
        }
    }
    foreach ($pages as $p) {
        $last = !empty($p['updated_at']) ? date('c', strtotime($p['updated_at'])) : null;
        foreach ($langs as $lang) {
            $slug = (string)$p['slug'];
            $emit(build_public_url($lang['code'], 'page', $slug), $last, function ($code) use ($slug) { return build_public_url($code, 'page', $slug); });
        }
    }
    echo "</urlset>\n";
} catch (Throwable $e) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"></urlset>";
}
