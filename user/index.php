<?php
require_once __DIR__ . '/../config/auth.php';
require_editor();

$pdo = app_pdo();
$sectionFilter = trim((string)($_GET['section'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'active'));

$sections = $pdo->query('SELECT slug, label FROM athkar_sections ORDER BY display_order ASC, slug ASC')->fetchAll();
$sectionStats = $pdo->query(
    'SELECT s.slug, s.label, s.is_active, COUNT(i.id) AS item_count
     FROM athkar_sections s
     LEFT JOIN athkar_items i ON i.section_slug = s.slug
     GROUP BY s.slug, s.label, s.is_active, s.display_order
     ORDER BY s.display_order ASC, s.slug ASC'
)->fetchAll();

$sql = 'SELECT i.id, i.item_key, i.title, i.section_slug, i.repetition_count, i.display_order, i.is_active, i.updated_at, s.label AS section_label
        FROM athkar_items i
        INNER JOIN athkar_sections s ON s.slug = i.section_slug
        WHERE 1=1';
$params = [];

if ($sectionFilter !== '') {
    $sql .= ' AND i.section_slug = :section';
    $params['section'] = $sectionFilter;
}

if ($statusFilter === 'active') {
    $sql .= ' AND i.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $sql .= ' AND i.is_active = 0';
}

$sql .= ' ORDER BY s.display_order ASC, i.display_order ASC, i.id ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Athkar Portal</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'items'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="eyebrow">Athkar Portal</p>
          <h1>Manage Athkar</h1>
          <p class="admin-subtitle">Review athkar items, filter by section, and manage their publishing status from one place.</p>
        </div>
        <div class="admin-page-actions">
          <a class="primary-button" href="edit.php">Add Athkar</a>
        </div>
      </header>

    <section class="admin-panel">
      <div class="admin-section-summary-grid">
        <?php foreach ($sectionStats as $section): ?>
          <article class="admin-section-summary-card">
            <div>
              <strong><?= esc($section['label']) ?></strong>
              <p><?= esc((string)$section['item_count']) ?> item(s)</p>
            </div>
            <span class="admin-status <?= (int)$section['is_active'] === 1 ? 'is-active' : 'is-inactive' ?>">
              <?= (int)$section['is_active'] === 1 ? 'Active' : 'Inactive' ?>
            </span>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="admin-panel">
      <form method="get" class="admin-filter-row">
        <label class="admin-inline-field">
          <span>Section</span>
          <select class="admin-select" name="section">
            <option value="">All sections</option>
            <?php foreach ($sections as $section): ?>
              <option value="<?= esc($section['slug']) ?>" <?= $sectionFilter === $section['slug'] ? 'selected' : '' ?>>
                <?= esc($section['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="admin-inline-field">
          <span>Status</span>
          <select class="admin-select" name="status">
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
          </select>
        </label>

        <button class="ghost-button" type="submit">Apply</button>
      </form>
    </section>

    <section class="admin-panel">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Section</th>
              <th>Title</th>
              <th>Count</th>
              <th>Status</th>
              <th>Updated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$items): ?>
              <tr>
                <td colspan="7" class="admin-empty-cell">No athkar found for this filter.</td>
              </tr>
            <?php endif; ?>

            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= esc((string)$item['display_order']) ?></td>
                <td><?= esc($item['section_label']) ?></td>
                <td>
                  <div class="admin-title-cell">
                    <strong><?= esc($item['title']) ?></strong>
                    <span><?= esc($item['item_key']) ?></span>
                  </div>
                </td>
                <td><?= esc((string)$item['repetition_count']) ?></td>
                <td>
                  <span class="admin-status <?= (int)$item['is_active'] === 1 ? 'is-active' : 'is-inactive' ?>">
                    <?= (int)$item['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td><?= esc($item['updated_at']) ?></td>
                <td>
                  <div class="admin-action-row">
                    <a class="ghost-button admin-icon-button" href="edit.php?id=<?= esc((string)$item['id']) ?>" title="Edit athkar" aria-label="Edit athkar"><?= admin_icon('edit') ?></a>
                    <form method="post" action="toggle.php">
                      <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                      <input type="hidden" name="id" value="<?= esc((string)$item['id']) ?>" />
                      <input type="hidden" name="is_active" value="<?= (int)$item['is_active'] === 1 ? '0' : '1' ?>" />
                      <button class="ghost-button admin-icon-button <?= (int)$item['is_active'] === 1 ? 'is-toggle-active' : 'is-toggle-inactive' ?>" type="submit" title="<?= (int)$item['is_active'] === 1 ? 'Deactivate athkar' : 'Activate athkar' ?>" aria-label="<?= (int)$item['is_active'] === 1 ? 'Deactivate athkar' : 'Activate athkar' ?>">
                        <?= (int)$item['is_active'] === 1 ? admin_icon('toggle-on') : admin_icon('toggle-off') ?>
                      </button>
                    </form>
                    <form method="post" action="delete.php" onsubmit="return confirm('Delete this athkar permanently?');">
                      <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                      <input type="hidden" name="id" value="<?= esc((string)$item['id']) ?>" />
                      <button class="ghost-button admin-icon-button is-danger" type="submit" title="Delete athkar" aria-label="Delete athkar"><?= admin_icon('delete') ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    </section>
  </main>
</body>
</html>
