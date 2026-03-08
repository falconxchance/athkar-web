# Changelog

All notable updates to the Athkar Web App are documented here, most recent first.

## v25 — admin login hardening

- Added admin login rate limiting and temporary lockout protection.
- Login throttling now checks both:
  - repeated failures for the same username
  - repeated failures from the same IP address
- Added a session-based fallback throttle if the SQL upgrade has not been run yet.
- Successful admin login now regenerates the PHP session ID.
- Refreshed the CSRF token after successful login.
- Strengthened session startup with strict cookie/session settings.

**DB / SQL changes:**
- `db/schema.sql` **changed** — added `admin_login_attempts` table for fresh installs.
- `db/upgrade-login-security.sql` **new** — run this on existing installations to enable DB-backed login throttling / lockout.

**Notes:**
- Until `db/upgrade-login-security.sql` is applied, login throttling still works in a lighter session-based fallback mode for the current browser.
- Once the SQL upgrade is applied, the lockout becomes server-side and stronger against repeated brute-force attempts across new sessions/incognito windows.

## v23 — report hardening + sidebar i18n

- Strengthened the athkar report flow with an added lightweight arithmetic challenge:
  - works in the app item view
  - works on the public SEO athkar item page
  - keeps the existing honeypot, minimum-delay, and IP rate-limit checks
- Reduced report rate limits further:
  - max 2 reports per IP in 15 minutes
  - max 6 reports per IP per day
- Homepage/public sidebar labels now use translatable UI string keys instead of hardcoded English.
- Added new translatable UI string keys for the sidebar: `public_sidebar_home`, `public_sidebar_sitemap`, `public_sidebar_help`, `public_sidebar_language`, `public_sidebar_theme`, `public_sidebar_open_menu`, `public_sidebar_close_menu`.
- Added new translatable UI string keys for the report quick-check: `report_captcha_prompt`, `report_captcha_label`, `report_captcha_placeholder`, `report_captcha_error`.

**DB / SQL changes:** none in this revision.

**Notes:**
- Because the quick-check token is single-use, refreshing the page gives a fresh challenge immediately.
- The report data still saves in admin exactly as before.

## v21 — reporting polish

- Improved report email delivery path for athkar issue reports:
  - stronger email headers
  - better sender identity based on host domain
  - sendmail fallback when available if native PHP mail does not send
- Visually distinguished the report button with a soft warning/accent style.
- Added lightweight anti-spam protection to report submissions:
  - hidden honeypot field for bots
  - minimum form-open delay before submit
  - per-IP rate limiting on recent report submissions
- Kept the report UI quick and compact on both app item view and SEO item view.

**DB / SQL changes:** none — existing `athkar_reports` table and `db/upgrade-reports.sql` remain the same.

**Notes:**
- Report email sending still depends on the hosting server allowing PHP mail and/or sendmail.
- If the report email field is empty in Site Settings, reports are still saved in admin but no email copy can be sent.

## v20 — athkar item reporting feature

- Added a **Report** action for a specific athkar item inside the app reader.
- Added the same **Report** action on the public SEO page for a specific athkar item.
- The report action opens a quick popup form designed to stay compact on mobile/touch screens.
- The popup supports these issue types through translatable UI keys: Incorrect Source, Incorrect Translation, Incorrect Athkar Item, Incorrect Transliteration, Other.
- Users can optionally add their name/email and submit a short explanation.
- Reports are saved into the database and can be reviewed inside the admin panel.
- Added a new **Item Reports** page in the admin panel (Editor and Super Admin roles).
- Reports list shows reporter/contact, issue type, item + section, message, timing, and quick links back to the item.
- Added a new **Report email** field in **Site Settings** — when set, every saved report is also sent to that email using PHP mail on a best-effort basis.

**Files added / updated:**
- Added `api/report.php`, `js/report-modal.js`, `user/reports.php`, `db/upgrade-reports.sql`
- Updated `section.php`, `seo-item.php`, `js/app.js`, `css/style.css`, `config/i18n.php`, `config/seo.php`, `user/site-content.php`, `user/_nav.php`, `db/schema.sql`

**DB / SQL changes:**
- `db/schema.sql` updated to include the `athkar_reports` table for fresh installs.
- Added `db/upgrade-reports.sql` for existing installations.

## v22 — report placement follow-up

- Moved the Report action out of the top control area and placed it after the athkar detail text blocks for a cleaner touch/mobile layout.
- Applied the same report placement to the public SEO athkar item page.
- Updated the report modal script to support multiple report triggers on the same page.
- DB/SQL changes: none.

## v24 — report challenge refresh

- Report popup now fetches a fresh quick-check challenge every time it opens, so users do not need to reload the page manually after a successful submission.
- Report popup now refreshes the challenge token immediately after a successful submission, preventing stale-token failures on a second report attempt.
- SEO athkar item pages now show a single Report button at the end of the item instead of repeating it after transliteration, translation, and source.
- App report modal sync now stores the correct `item_key` for the selected athkar item.
- DB/SQL changes: none.

## Public page polish follow-up

- App home now shows the footer note text from `site-content.php`.
- Public SEO/custom/sitemap top controls now sit on opposite sides so the brand block stays visually centered like the app home.
- Added a bit more spacing above the Sitemap Pages section.
- Brand-block logo now links directly to the app home.
- Removed the Back to home button from custom pages.
- DB/SQL changes: none.

## Top spacing alignment cleanup

- Removed the extra `Athkar` eyebrow label above the sections block on the app home page.
- Public custom pages, SEO pages, and sitemap now use the same top spacing feel as the app home so the brand block sits higher without the extra top gap.
- DB/SQL changes: none.

## v19 — public theme + navigation fixes

- Fixed public theme colors on sitemap, SEO pages, custom pages, and the loading overlay so they now respect the current **Site Settings** palette.
- Removed the old **More** links section from the main public app homepage because navigation now lives in the sidebar.
- Sidebar page links are now limited to custom pages that are both **active** and **shown on home page**.
- Files updated: `config/seo.php`, `index.php`, `js/home.js`, `css/style.css`.
- DB/SQL changes: none.

## v18 — sidebar link fix

- Fixed the public sidebar links on the main app home (`/app/{lang}/`).
- Custom page links and the sitemap link in that sidebar now open their correct public URLs instead of always returning to the app home.
- DB/SQL changes: none.

## v17 — shared sidebar menu + theme controls

- Replaced the floating language selector and dark-mode button on the public home, custom pages, sitemap, and SEO pages with one shared sidebar menu.
- The sidebar now keeps navigation, language switching, and dark-mode toggle in the same place across those public-facing pages, while the in-app athkar reader stays unchanged.
- Added basic theme controls to **Site Settings** for accent color, light background, light card color, dark background, and dark card color.
- The app and public pages now read those theme values so both light and dark mode can be adjusted from the admin panel.
- Renamed **Site Content** to **Site Settings** in the admin panel.
- Updated the reader dock undo icon to a proper return / u-turn style icon.
- Added a little more spacing below the brand block on the public home, sitemap, custom pages, and SEO pages.
- DB/SQL changes: none.

## v16 — deep links + matching sidebar markup

- Public custom pages, sitemap, and SEO pages now use the exact same floating language selector and dark-mode toggle markup/classes as the app home, so their corner positions match precisely.
- The public SEO page for a single athkar item now opens the matching item inside the app, not just the section root.
- The app reader now supports deep links like `/app/{lang}/section/{slug}/?item={item_key}` and keeps the active item reflected in the app URL.
- DB/SQL changes: none.

## v15 — floating control alignment

- Fixed public page floating controls so the language selector and dark mode toggle now use the exact same positioning as the app home.
- Updated the public SEO/custom/sitemap pages to use the same top-corner control placement as `/app/{lang}/`.
- DB/SQL changes: none.

---

## Earlier consolidated build — Athkar Web App

This entry documents the initial consolidated build of the Athkar Web App based on the multilingual / SEO-enabled version, with the admin panel, app home, custom pages, routing, theming, sharing, and public-page polish applied.

**Included in this build:**

1. **Multilingual app + SEO routing** — multilingual public/app experience, pretty SEO/public routes, pretty app routes, public language switching on home/sitemap/custom/section/item pages, `/{lang}/` redirects to `/app/{lang}/`.
2. **User panel / admin improvements** — working sidebar, mobile off-canvas sidebar, `/user/` login page, language-aware admin editing, only active languages shown in translation areas.
3. **Translation / UI string improvements** — working export/import, updated JSON support for latest keys, public labels moved into translatable UI strings, polished UI strings page icons.
4. **Athkar item editor improvements** — large textarea for the source field, auto-generated item key from title/slug logic kept in sync, SEO slug used for routing, shareable SEO/public links per item.
5. **Custom pages system** — database-managed custom pages with admin CRUD, activate/deactivate, show/hide from home, editable real slug used for SEO/public routing.
6. **App home redesign** — simpler and more polished home, logo support managed from `site-content.php`, consistent intro/brand styling, hidden empty header/footer blocks, footer quick links, editable footer note text.
7. **Public pages / sitemap / consistency** — public sitemap page, XML sitemap support, consistent branded header/intro styling across home/custom/sitemap/section/item pages.
8. **Dark mode support** — works on app screens and was extended to public custom pages, sitemap, and SEO pages, with polished link colors.
9. **Assets / uploads** — `site-content.php` supports uploaded assets (favicon, app icon, logo) instead of pasted URLs.
10. **404 handling** — proper custom 404 page handling for missing routes/pages.

**Important behavior notes:**
- Visiting `/en/` or `/ar/` redirects to `/app/en/` or `/app/ar/` to avoid confusion between "website home" and "open app".
- Athkar items and custom pages use their real slugs for public SEO URLs; if a title/slug changes, the public URL can change too. No ID suffix is used for custom page slugs.
- The footer note is plain text (not a card/block), controlled from `site-content.php`, and is dark-mode compatible.

**DB / SQL changes included:**
- `db/schema.sql` — updated project schema reflecting the multilingual / public page / site content structure.
- `db/upgrade-i18n.sql` — multilingual / UI string / i18n updates.
- `db/upgrade-pages.sql` — adds support for database-managed custom pages.
- `db/upgrade-site-content.sql` — site content storage used by public/app content and footer note style settings.
- `db/upgrade-reports.sql` — adds the athkar item reports table used by the reporting feature.
- `db/upgrade-login-security.sql` — adds the admin login attempt tracking table used for login rate limiting and lockout.
- Also included: `db/upgrade-language-manager.sql`, `db/upgrade-sections.sql`, `db/upgrade-admin-roles.sql`, `db/upgrade-admin-users.sql`.

**DB note:** if your live database was already upgraded step-by-step from earlier packages, do not blindly rerun every SQL file without reviewing what was already applied. If deploying as a fresh install, review `db/schema.sql` first.
