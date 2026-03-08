<?php
require_once __DIR__ . '/i18n.php';

function pages_slugify(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    if ($value === '') return 'page';
    if (class_exists('Transliterator')) {
        $tr = \Transliterator::create('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; Lower()');
        if ($tr) {
            $value = $tr->transliterate($value);
        }
    } else {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($tmp) && $tmp !== '') $value = strtolower($tmp);
    }
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'page';
}

function pages_unique_slug(PDO $pdo, string $value, int $excludeId = 0): string
{
    ensure_custom_pages_tables($pdo);
    $base = pages_slugify($value);
    $base = substr($base, 0, 120);
    $base = trim($base, '-');
    if ($base === '') $base = 'page';

    $check = $pdo->prepare('SELECT id FROM athkar_pages WHERE slug = :slug' . ($excludeId > 0 ? ' AND id <> :id' : '') . ' LIMIT 1');
    $slug = $base;
    $counter = 2;
    while (true) {
        $params = ['slug' => $slug];
        if ($excludeId > 0) $params['id'] = $excludeId;
        $check->execute($params);
        if (!$check->fetchColumn()) {
            return $slug;
        }
        $suffix = '-' . $counter;
        $maxBase = max(1, 120 - strlen($suffix));
        $trimmed = rtrim(substr($base, 0, $maxBase), '-');
        if ($trimmed === '') $trimmed = 'page';
        $slug = $trimmed . $suffix;
        $counter++;
    }
}

function ensure_custom_pages_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS athkar_pages (\n"
        . "  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n"
        . "  slug VARCHAR(120) NOT NULL,\n"
        . "  display_order INT UNSIGNED NOT NULL DEFAULT 1,\n"
        . "  is_active TINYINT(1) NOT NULL DEFAULT 1,\n"
        . "  show_on_home TINYINT(1) NOT NULL DEFAULT 1,\n"
        . "  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uniq_slug (slug),\n"
        . "  KEY idx_pages_home (show_on_home, is_active, display_order)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS athkar_pages_i18n (\n"
        . "  page_id INT UNSIGNED NOT NULL,\n"
        . "  lang VARCHAR(8) NOT NULL,\n"
        . "  title VARCHAR(255) DEFAULT NULL,\n"
        . "  content MEDIUMTEXT DEFAULT NULL,\n"
        . "  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  PRIMARY KEY (page_id, lang),\n"
        . "  CONSTRAINT fk_pages_i18n_page FOREIGN KEY (page_id) REFERENCES athkar_pages(id) ON UPDATE CASCADE ON DELETE CASCADE\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $count = (int)$pdo->query('SELECT COUNT(*) FROM athkar_pages')->fetchColumn();
    if ($count === 0) {
        $pdo->beginTransaction();
        try {
            $pages = [
                ['slug' => 'about-us', 'order' => 1, 'title' => 'About Us', 'content' => '<h2>About Us</h2><p>Welcome to our Athkar platform. Use this page to describe your mission, your team, or why this app exists.</p>'],
                ['slug' => 'disclaimer', 'order' => 2, 'title' => 'Disclaimer', 'content' => '<h2>Disclaimer</h2><p>Use this page to add any legal notice, source note, or content disclaimer for your Athkar website and app.</p>'],
            ];
            $ins = $pdo->prepare('INSERT INTO athkar_pages (slug, display_order, is_active, show_on_home) VALUES (:slug, :display_order, 1, 1)');
            $tr = $pdo->prepare('INSERT INTO athkar_pages_i18n (page_id, lang, title, content) VALUES (:page_id, :lang, :title, :content)');
            foreach ($pages as $page) {
                $ins->execute(['slug' => $page['slug'], 'display_order' => $page['order']]);
                $pageId = (int)$pdo->lastInsertId();
                $tr->execute(['page_id' => $pageId, 'lang' => 'en', 'title' => $page['title'], 'content' => $page['content']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    $done = true;
}

function page_plain_excerpt(?string $html, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$html)) ?? '');
    if ($text === '') return '';
    if (mb_strlen($text, 'UTF-8') <= $length) return $text;
    return rtrim(mb_substr($text, 0, max(1, $length - 1), 'UTF-8')) . '…';
}

function get_pages(PDO $pdo, string $lang, bool $onlyActive = false, bool $onlyHome = false): array
{
    ensure_custom_pages_tables($pdo);
    $sql = 'SELECT p.id, p.slug, p.display_order, p.is_active, p.show_on_home, p.updated_at,
                   COALESCE(t_req.title, t_en.title, p.slug) AS title,
                   COALESCE(t_req.content, t_en.content, "") AS content
            FROM athkar_pages p
            LEFT JOIN athkar_pages_i18n t_req ON t_req.page_id = p.id AND t_req.lang = :lang
            LEFT JOIN athkar_pages_i18n t_en ON t_en.page_id = p.id AND t_en.lang = :fallback_lang
            WHERE 1=1';
    if ($onlyActive) $sql .= ' AND p.is_active = 1';
    if ($onlyHome) $sql .= ' AND p.show_on_home = 1';
    $sql .= ' ORDER BY p.display_order ASC, p.id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['lang' => $lang, 'fallback_lang' => 'en']);
    $rows = $stmt->fetchAll() ?: [];
    foreach ($rows as &$row) {
        $row['excerpt'] = page_plain_excerpt($row['content'], 155);
    }
    return $rows;
}

function get_page_by_slug(PDO $pdo, string $slug, string $lang, bool $onlyActive = true): ?array
{
    ensure_custom_pages_tables($pdo);
    $sql = 'SELECT p.id, p.slug, p.display_order, p.is_active, p.show_on_home, p.updated_at,
                   COALESCE(t_req.title, t_en.title, p.slug) AS title,
                   COALESCE(t_req.content, t_en.content, "") AS content
            FROM athkar_pages p
            LEFT JOIN athkar_pages_i18n t_req ON t_req.page_id = p.id AND t_req.lang = :lang
            LEFT JOIN athkar_pages_i18n t_en ON t_en.page_id = p.id AND t_en.lang = :fallback_lang
            WHERE p.slug = :slug';
    if ($onlyActive) $sql .= ' AND p.is_active = 1';
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['slug' => $slug, 'lang' => $lang, 'fallback_lang' => 'en']);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['excerpt'] = page_plain_excerpt($row['content'], 155);
    return $row;
}
