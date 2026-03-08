# Athkar Web App

A multilingual, SEO-enabled Islamic athkar (remembrance/dua) web application with a public reading experience and a full admin/user panel for managing content, translations, pages, and reports.

For the detailed version-by-version history, see [CHANGELOG.md](CHANGELOG.md).

## Tech stack

- **Backend:** PHP (procedural, no framework) — `.php` files across the app root, `api/`, `config/`, and `user/`
- **Database:** MySQL — schema and versioned upgrade scripts in [`db/`](db)
- **Frontend:** vanilla JavaScript — [`js/`](js)
- **Styling:** CSS — [`css/`](css)
- **Data:** JSON — seed athkar content in [`data/`](data), PWA [`manifest.json`](manifest.json)
- **Web server config:** Apache `.htaccess` (routing/access rules in the root, `config/`, `db/`, `uploads/`, `user/`)
- **PWA:** service worker ([`sw.js`](sw.js)) and manifest for installable/offline app behavior

## Features

- **Multilingual public app** with pretty SEO routes and pretty app routes; language switching available across home, sitemap, custom pages, section pages, and item pages.
- **Admin / user panel** (`user/`) for managing athkar sections and items, custom pages, site settings, translations, UI strings, and users/roles.
- **Role-based access:** `user`, `editor`, and `super_admin` roles.
- **Hardened admin login:** per-username and per-IP rate limiting with temporary lockout, session regeneration on login, CSRF token refresh, strict session cookie settings.
- **Custom pages system:** database-managed pages with activate/deactivate and show/hide-on-home controls.
- **Athkar item reporting:** users can report incorrect source/translation/transliteration/content from both the app reader and public SEO item pages, protected by a honeypot, minimum-delay check, per-IP rate limits, and a lightweight arithmetic challenge. Reports are reviewable in the admin panel and optionally emailed.
- **Dark mode** across app screens and public pages (sitemap, custom pages, SEO pages).
- **Asset uploads** for favicon, app icon, and logo via Site Settings.
- **XML + public HTML sitemap**, custom 404 handling.

## Project structure

```
.
├── api/            API endpoints (athkar, sections, pages, languages, site, ui, reports)
├── config/         App config, auth, i18n, SEO helpers, admin UI config
├── css/            Public + admin stylesheets
├── data/           Seed athkar JSON content (morning, evening, prayer, after-prayer)
├── db/             MySQL schema.sql, seed.sql, and versioned upgrade-*.sql scripts
├── js/             Frontend JS (app, home, i18n, theme, storage, report modal, sidebar)
├── user/           Admin/user panel pages (login, sections, items, pages, reports, users, settings)
├── uploads/        Uploaded site assets (favicon/app icon/logo)
├── index.php       App entry / router
├── section.php     Athkar section reader
├── seo-*.php       Public SEO-facing pages (home, section, item, page, sitemap)
├── sitemap.php / sitemap.xml.php   Public + XML sitemaps
├── manifest.json / manifest.php    PWA manifest
├── sw.js           Service worker
└── 404.php         Custom 404 page
```

## Setup

1. Create a MySQL database and import [`db/schema.sql`](db/schema.sql) for a fresh install (review `db/upgrade-*.sql` scripts individually if upgrading an existing install — don't run them all blindly).
2. Optionally load sample content with [`db/seed.sql`](db/seed.sql).
3. Copy/edit `config/config.php` with your database credentials.
4. Set a bootstrap admin password: generate a bcrypt hash (e.g. `password_hash('yourpassword', PASSWORD_BCRYPT)` in PHP) and put it in `config/config.php` under `admin.password_hash`. This bootstrap login is only used until you create the first real admin user in the database — after that, log in and create a proper DB-backed admin user, then rotate/replace the bootstrap hash.
5. Point your web server document root at the project root; the included `.htaccess` files handle routing and restrict direct access to `config/`, `db/`, and `uploads/`.

## Recommended test checklist after deploying

**Routing:** `/en/`, `/ar/`, `/app/en/`, `/app/ar/`, `/user/`, and a fake route (for 404 testing).

**Public pages:** custom page route, sitemap page, section SEO page, item SEO page, dark mode.

**Admin:** UI Strings page, translation export/import, language activation/deactivation, athkar item create/edit, custom page create/edit, site content asset uploads.

**Assets:** favicon upload, app icon upload, logo upload, footer note rendering.
