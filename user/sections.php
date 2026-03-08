<?php
require_once __DIR__ . '/../config/auth.php';
require_editor();

$pdo = app_pdo();
$statusFilter = trim((string)($_GET['status'] ?? 'all'));

$sql = 'SELECT s.slug, s.label, s.description, s.icon, s.display_order, s.is_active, s.updated_at,
               (SELECT COUNT(*) FROM athkar_items i WHERE i.section_slug = s.slug) AS item_count
        FROM athkar_sections s
        WHERE 1=1';

if ($statusFilter === 'active') {
    $sql .= ' AND s.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $sql .= ' AND s.is_active = 0';
}

$sql .= ' ORDER BY s.display_order ASC, s.slug ASC';
$sections = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Sections</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'sections'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="eyebrow">Athkar Portal</p>
          <h1>Manage Sections</h1>
          <p class="admin-subtitle">Organize the public home screen sections, ordering, labels, icons, and visibility.</p>
        </div>
        <div class="admin-page-actions">
          <a class="primary-button" href="section-edit.php">Add Section</a>
        </div>
      </header>

    <section class="admin-panel">
      <form method="get" class="admin-filter-row">
        <label class="admin-inline-field">
          <span>Status</span>
          <select class="admin-select" name="status">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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
              <th>Icon</th>
              <th>Label</th>
              <th>Slug</th>
              <th>Items</th>
              <th>Status</th>
              <th>Updated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$sections): ?>
              <tr>
                <td colspan="8" class="admin-empty-cell">No sections found.</td>
              </tr>
            <?php endif; ?>

            <?php foreach ($sections as $section): ?>
              <tr>
                <td><?= esc((string)$section['display_order']) ?></td>
                <td><span class="admin-icon-preview"><?= esc($section['icon'] ?: '✨') ?></span></td>
                <td>
                  <div class="admin-title-cell">
                    <strong><?= esc($section['label']) ?></strong>
                    <span><?= esc($section['description']) ?></span>
                  </div>
                </td>
                <td><code><?= esc($section['slug']) ?></code></td>
                <td><?= esc((string)$section['item_count']) ?></td>
                <td>
                  <span class="admin-status <?= (int)$section['is_active'] === 1 ? 'is-active' : 'is-inactive' ?>">
                    <?= (int)$section['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td><?= esc($section['updated_at']) ?></td>
                <td>
                  <div class="admin-action-row">
                    <a class="ghost-button admin-icon-button" href="section-edit.php?slug=<?= urlencode($section['slug']) ?>" title="Edit section" aria-label="Edit section"><?= admin_icon('edit') ?></a>
                    <form method="post" action="section-toggle.php">
                      <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                      <input type="hidden" name="slug" value="<?= esc($section['slug']) ?>" />
                      <input type="hidden" name="is_active" value="<?= (int)$section['is_active'] === 1 ? '0' : '1' ?>" />
                      <button class="ghost-button admin-icon-button <?= (int)$section['is_active'] === 1 ? 'is-toggle-active' : 'is-toggle-inactive' ?>" type="submit" title="<?= (int)$section['is_active'] === 1 ? 'Deactivate section' : 'Activate section' ?>" aria-label="<?= (int)$section['is_active'] === 1 ? 'Deactivate section' : 'Activate section' ?>">
                        <?= (int)$section['is_active'] === 1 ? admin_icon('toggle-on') : admin_icon('toggle-off') ?>
                      </button>
                    </form>
                    <form method="post" action="section-delete.php" onsubmit="return confirm('Delete this section and all athkar inside it? This cannot be undone.');">
                      <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                      <input type="hidden" name="slug" value="<?= esc($section['slug']) ?>" />
                      <button class="ghost-button admin-icon-button is-danger" type="submit" title="Delete section" aria-label="Delete section"><?= admin_icon('delete') ?></button>
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
