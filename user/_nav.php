<?php
require_once __DIR__ . '/../config/auth.php';

$user = admin_current_user();
$role = admin_current_role();
$activePage = $activePage ?? '';
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$username = (string)($user['username'] ?? '');
$avatar = strtoupper(substr($username !== '' ? $username : 'U', 0, 1));

function sidebar_link(string $href, string $label, string $pageKey, string $activePage, string $icon = ''): string {
    $cls = 'admin-sidebar-link' . ($pageKey === $activePage ? ' is-active' : '');
    $aria = $pageKey === $activePage ? ' aria-current="page"' : '';
    $iconHtml = $icon !== '' ? '<span class="admin-sidebar-link__icon">' . $icon . '</span>' : '';
    return '<a class="' . $cls . '" href="' . esc($href) . '"' . $aria . ' title="' . esc($label) . '">' . $iconHtml . '<span class="admin-sidebar-link__text">' . esc($label) . '</span></a>';
}

function sidebar_sublink(string $href, string $label, bool $isActive = false): string {
    $cls = 'admin-sidebar-sublink' . ($isActive ? ' is-active' : '');
    $aria = $isActive ? ' aria-current="page"' : '';
    return '<a class="' . $cls . '" href="' . esc($href) . '"' . $aria . '>' . esc($label) . '</a>';
}

$canEdit = admin_is_editor();
$isSuper = admin_is_superadmin();
$roleLabel = role_label($role);
$currentLang = $_GET['lang'] ?? 'en';
?><button class="ghost-button admin-mobile-sidebar-open" type="button" data-admin-mobile-open aria-label="Open navigation" aria-expanded="false" title="Open navigation">
  <span class="admin-sidebar-footer__icon"><?= admin_icon('menu') ?></span>
  <span class="admin-mobile-sidebar-open__text">Menu</span>
</button>
<div class="admin-sidebar-backdrop" data-admin-sidebar-backdrop hidden></div>
<aside class="admin-sidebar" data-admin-sidebar>
  <div class="admin-sidebar-card admin-sidebar-brand">
    <div class="admin-sidebar-brand__row">
      <div>
        <p class="admin-eyebrow">Athkar Portal</p>
        <h2>User Panel</h2>
        <p class="admin-help-inline">Role-based workspace for managing athkar content, languages, and account access.</p>
      </div>
      <div class="admin-sidebar-controls">
        <button class="ghost-button admin-sidebar-toggle" type="button" data-admin-sidebar-toggle aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
          <span class="admin-sidebar-toggle__icon admin-sidebar-toggle__icon--collapse"><?= admin_icon('collapse') ?></span>
          <span class="admin-sidebar-toggle__icon admin-sidebar-toggle__icon--expand"><?= admin_icon('expand') ?></span>
        </button>
        <button class="ghost-button admin-sidebar-close" type="button" data-admin-mobile-close aria-expanded="false" aria-label="Close navigation" title="Close navigation">
          <span class="admin-sidebar-footer__icon"><?= admin_icon('close') ?></span>
        </button>
      </div>
    </div>
  </div>

  <div class="admin-sidebar-card admin-sidebar-account">
    <span class="admin-sidebar-account__avatar"><?= esc($avatar) ?></span>
    <div class="admin-sidebar-account__meta">
      <span class="admin-sidebar-account__name"><?= esc($username) ?></span>
      <span class="admin-sidebar-account__role"><?= esc($roleLabel) ?></span>
    </div>
  </div>

  <nav class="admin-sidebar-card admin-sidebar-nav" aria-label="Portal navigation">
    <?php if ($canEdit): ?>
      <div class="admin-sidebar-group">
        <p class="admin-sidebar-group__label">Content</p>
        <?= sidebar_link('index.php', 'Athkar Items', 'items', $activePage, '📝') ?>
        <div class="admin-sidebar-subnav">
          <?= sidebar_sublink('edit.php', 'Add Athkar', $currentScript === 'edit.php') ?>
        </div>
        <?= sidebar_link('sections.php', 'Manage Sections', 'sections', $activePage, '📚') ?>
        <div class="admin-sidebar-subnav">
          <?= sidebar_sublink('section-edit.php', 'Add Section', $currentScript === 'section-edit.php') ?>
        </div>
        <?= sidebar_link('reports.php', 'Item Reports', 'reports', $activePage, '🚩') ?>
      </div>
    <?php endif; ?>

    <?php if ($isSuper): ?>
      <div class="admin-sidebar-group">
        <p class="admin-sidebar-group__label">Settings</p>
        <?= sidebar_link('site-content.php', 'Site Settings', 'site', $activePage, '⚙️') ?>
        <?= sidebar_link('pages.php', 'Custom Pages', 'pages', $activePage, '📄') ?>
        <div class="admin-sidebar-subnav">
          <?= sidebar_sublink('page-edit.php', 'Add Page', $currentScript === 'page-edit.php') ?>
        </div>
      </div>

      <div class="admin-sidebar-group">
        <p class="admin-sidebar-group__label">Localization</p>
        <?= sidebar_link('ui-strings.php', 'Translations', 'ui', $activePage, '🌐') ?>
        <?= sidebar_link('languages.php', 'Languages', 'langs', $activePage, '🈯') ?>
      </div>

      <div class="admin-sidebar-group">
        <p class="admin-sidebar-group__label">Administration</p>
        <?= sidebar_link('users.php', 'Users', 'users', $activePage, '👥') ?>
        <div class="admin-sidebar-subnav">
          <?= sidebar_sublink('user-edit.php', 'Add User', $currentScript === 'user-edit.php') ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="admin-sidebar-group">
      <p class="admin-sidebar-group__label">Account</p>
      <?= sidebar_link('profile.php', 'My Account', 'profile', $activePage, '🙍') ?>
    </div>
  </nav>

  <div class="admin-sidebar-card admin-sidebar-footer">
    <a class="ghost-button admin-sidebar-footer__button" href="../app/<?= esc(rawurlencode($currentLang !== '' ? $currentLang : 'en')) ?>/" target="_blank" rel="noreferrer" title="Open app">
      <span class="admin-sidebar-footer__icon"><?= admin_icon('open') ?></span>
      <span class="admin-sidebar-footer__button-text">Open App</span>
    </a>
    <a class="ghost-button admin-sidebar-footer__button" href="logout.php" title="Logout">
      <span class="admin-sidebar-footer__icon"><?= admin_icon('logout') ?></span>
      <span class="admin-sidebar-footer__button-text">Logout</span>
    </a>
  </div>
</aside>
<script defer src="../js/admin-panel.js"></script>
