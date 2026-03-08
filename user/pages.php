<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/pages.php';
require_superadmin();

$pdo = app_pdo();
$pages = get_pages($pdo, 'en', false, false);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Custom Pages • Athkar Portal</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <main class="admin-app-shell">
    <?php $activePage = 'pages'; include __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
      <header class="admin-page-header">
        <div class="admin-page-title">
          <p class="admin-eyebrow">Athkar Portal</p>
          <h1>Custom Pages</h1>
          <p class="admin-subtitle">Create SEO-friendly public pages like About Us and Disclaimer, and control whether they appear on the home page.</p>
        </div>
        <div class="admin-page-actions">
          <a class="primary-button" href="page-edit.php">Add Page</a>
        </div>
      </header>
      <section class="admin-panel">
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Title</th>
                <th>Slug</th>
                <th>Home</th>
                <th>Status</th>
                <th>Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$pages): ?>
                <tr><td colspan="7" class="admin-empty-cell">No custom pages found yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($pages as $page): ?>
                <tr>
                  <td><?= esc((string)$page['display_order']) ?></td>
                  <td>
                    <div class="admin-title-cell">
                      <strong><?= esc($page['title']) ?></strong>
                      <span><?= esc($page['excerpt']) ?></span>
                    </div>
                  </td>
                  <td><code><?= esc($page['slug']) ?></code></td>
                  <td><span class="admin-status <?= (int)$page['show_on_home'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int)$page['show_on_home'] === 1 ? 'Shown' : 'Hidden' ?></span></td>
                  <td><span class="admin-status <?= (int)$page['is_active'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int)$page['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                  <td><?= esc($page['updated_at']) ?></td>
                  <td>
                    <div class="admin-action-row">
                      <a class="ghost-button admin-icon-button" href="page-edit.php?id=<?= esc((string)$page['id']) ?>" title="Edit page" aria-label="Edit page"><?= admin_icon('edit') ?></a>
                      <form method="post" action="page-toggle.php">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                        <input type="hidden" name="id" value="<?= esc((string)$page['id']) ?>" />
                        <input type="hidden" name="toggle_target" value="status" />
                        <input type="hidden" name="is_active" value="<?= (int)$page['is_active'] === 1 ? '0' : '1' ?>" />
                        <button class="ghost-button admin-icon-button <?= (int)$page['is_active'] === 1 ? 'is-toggle-active' : 'is-toggle-inactive' ?>" type="submit" title="<?= (int)$page['is_active'] === 1 ? 'Deactivate page' : 'Activate page' ?>" aria-label="<?= (int)$page['is_active'] === 1 ? 'Deactivate page' : 'Activate page' ?>"><?= (int)$page['is_active'] === 1 ? admin_icon('toggle-on') : admin_icon('toggle-off') ?></button>
                      </form>
                      <form method="post" action="page-toggle.php">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                        <input type="hidden" name="id" value="<?= esc((string)$page['id']) ?>" />
                        <input type="hidden" name="toggle_target" value="home" />
                        <input type="hidden" name="show_on_home" value="<?= (int)$page['show_on_home'] === 1 ? '0' : '1' ?>" />
                        <button class="ghost-button admin-icon-button" type="submit" title="<?= (int)$page['show_on_home'] === 1 ? 'Hide from home' : 'Show on home' ?>" aria-label="<?= (int)$page['show_on_home'] === 1 ? 'Hide from home' : 'Show on home' ?>">🏠</button>
                      </form>
                      <form method="post" action="page-delete.php" onsubmit="return confirm('Delete this page permanently?');">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>" />
                        <input type="hidden" name="id" value="<?= esc((string)$page['id']) ?>" />
                        <button class="ghost-button admin-icon-button is-danger" type="submit" title="Delete page" aria-label="Delete page"><?= admin_icon('delete') ?></button>
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
