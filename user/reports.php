<?php
require_once __DIR__ . '/../config/auth.php';
require_editor();

$pdo = app_pdo();
$activePage = 'reports';
$issueLabels = [
    'incorrect_source' => 'Incorrect Source',
    'incorrect_translation' => 'Incorrect Translation',
    'incorrect_athkar_item' => 'Incorrect Athkar Item',
    'incorrect_transliteration' => 'Incorrect Transliteration',
    'other' => 'Other',
];
$contextLabels = ['app' => 'In app', 'seo_item' => 'SEO item page'];
$tableExists = (bool)$pdo->query("SHOW TABLES LIKE 'athkar_reports'")->fetchColumn();
$reports = [];
$summary = [];
$typeFilter = trim((string)($_GET['type'] ?? ''));
$sectionFilter = trim((string)($_GET['section'] ?? ''));
$sections = $pdo->query('SELECT slug, label FROM athkar_sections ORDER BY display_order ASC, slug ASC')->fetchAll() ?: [];
if ($tableExists) {
    $where = [];
    $params = [];
    if ($typeFilter !== '' && isset($issueLabels[$typeFilter])) { $where[] = 'r.issue_type = :issue_type'; $params['issue_type'] = $typeFilter; }
    if ($sectionFilter !== '') { $where[] = 'r.section_slug = :section_slug'; $params['section_slug'] = $sectionFilter; }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $stmt = $pdo->prepare('SELECT r.*, i.title AS item_title, s.label AS section_label FROM athkar_reports r LEFT JOIN athkar_items i ON i.id = r.item_id LEFT JOIN athkar_sections s ON s.slug = r.section_slug ' . $whereSql . ' ORDER BY r.created_at DESC, r.id DESC LIMIT 300');
    $stmt->execute($params);
    $reports = $stmt->fetchAll() ?: [];
    foreach (($pdo->query('SELECT issue_type, COUNT(*) AS total FROM athkar_reports GROUP BY issue_type ORDER BY total DESC')->fetchAll() ?: []) as $row) $summary[(string)$row['issue_type']] = (int)$row['total'];
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reports • Athkar Portal</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
  <style>
    .report-admin-card{display:grid;gap:8px}
    .report-admin-meta{display:flex;gap:8px;flex-wrap:wrap;color:var(--muted);font-size:.92rem}
    .report-admin-message{white-space:pre-wrap;line-height:1.55}
    .report-admin-grid{display:grid;gap:14px}
    .report-admin-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
    .report-admin-summary .admin-panel{margin-bottom:0;padding:16px}
    .report-admin-empty{color:var(--muted)}
  </style>
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="eyebrow">Athkar Portal</p>
          <h1>Item Reports</h1>
          <p class="admin-subtitle">Review issue reports submitted from the app reader and public SEO athkar pages.</p>
        </div>
      </header>
      <?php if (!$tableExists): ?>
        <section class="admin-panel"><div class="admin-alert admin-alert-error">Reports table is missing. Please import <code>db/upgrade-reports.sql</code> first.</div></section>
      <?php else: ?>
        <section class="report-admin-summary">
          <article class="admin-panel"><strong>Total reports</strong><p><?= esc((string)array_sum($summary)) ?></p></article>
          <?php foreach ($issueLabels as $key => $label): ?>
            <article class="admin-panel"><strong><?= esc($label) ?></strong><p><?= esc((string)($summary[$key] ?? 0)) ?></p></article>
          <?php endforeach; ?>
        </section>
        <section class="admin-panel">
          <form method="get" class="admin-filter-row">
            <label class="admin-inline-field"><span>Issue type</span><select class="admin-select" name="type"><option value="">All reports</option><?php foreach ($issueLabels as $key => $label): ?><option value="<?= esc($key) ?>" <?= $typeFilter === $key ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select></label>
            <label class="admin-inline-field"><span>Section</span><select class="admin-select" name="section"><option value="">All sections</option><?php foreach ($sections as $section): ?><option value="<?= esc($section['slug']) ?>" <?= $sectionFilter === $section['slug'] ? 'selected' : '' ?>><?= esc($section['label']) ?></option><?php endforeach; ?></select></label>
            <button class="ghost-button" type="submit">Apply</button>
          </form>
        </section>
        <section class="report-admin-grid">
          <?php if (!$reports): ?><section class="admin-panel report-admin-empty">No reports found for the current filters.</section><?php endif; ?>
          <?php foreach ($reports as $report): ?>
            <article class="admin-panel report-admin-card">
              <div class="report-admin-meta">
                <span class="admin-status is-inactive"><?= esc($issueLabels[$report['issue_type']] ?? $report['issue_type']) ?></span>
                <span><?= esc($contextLabels[$report['page_context']] ?? $report['page_context']) ?></span>
                <span><?= esc($report['created_at']) ?></span>
                <span><?= esc(strtoupper((string)$report['lang'])) ?></span>
              </div>
              <div class="admin-title-cell"><strong><?= esc($report['item_title'] ?: $report['item_key']) ?></strong><span>Section: <?= esc($report['section_label'] ?: $report['section_slug']) ?> • Key: <?= esc($report['item_key']) ?></span></div>
              <div class="report-admin-meta">
                <span>Reporter: <?= esc(trim((string)$report['reporter_name']) !== '' ? $report['reporter_name'] : 'Anonymous') ?></span>
                <span>Email: <?php if (!empty($report['reporter_email'])): ?><a href="mailto:<?= esc($report['reporter_email']) ?>"><?= esc($report['reporter_email']) ?></a><?php else: ?>Not provided<?php endif; ?></span>
              </div>
              <div class="report-admin-message"><?= nl2br(esc($report['message'])) ?></div>
              <div class="report-admin-meta">
                <?php if (!empty($report['source_url'])): ?><a href="<?= esc($report['source_url']) ?>" target="_blank" rel="noreferrer">Open source page</a><?php endif; ?>
                <a href="edit.php?id=<?= esc((string)$report['item_id']) ?>">Edit athkar item</a>
                <a href="../<?= esc(rawurlencode((string)$report['lang'])) ?>/item/<?= esc(rawurlencode((string)$report['item_key'])) ?>/" target="_blank" rel="noreferrer">Public item page</a>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
