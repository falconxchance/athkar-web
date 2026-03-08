<?php
require_once __DIR__ . '/config/seo.php';

$initialLang = get_request_lang();
try {
    $pdo = app_pdo();
    $site = site_defaults_for_lang($pdo, $initialLang);
    $ui = seo_ui_strings($pdo, $initialLang);
    $reportChallenge = report_challenge_generate();
} catch (Throwable $e) {
    $site = [
        'site_title' => 'Athkar',
        'site_short_name' => 'Athkar',
        'site_description' => 'Athkar section reader.',
        'theme_color' => '#0b3b2e',
        'favicon_url' => '',
        'app_icon_url' => '',
        'theme_light_bg' => '#f6f3ec',
        'theme_light_surface' => '#ffffff',
        'theme_dark_bg' => '#0c1210',
        'theme_dark_surface' => '#111a16',
    ];
    $ui = [];
    $reportChallenge = ['token' => '', 'question' => '2 + 3'];
}
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($initialLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="description" content="<?= htmlspecialchars((string)($site['site_description'] ?? 'Athkar section reader.'), ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="robots" content="noindex,follow" />
  <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars((string)($site['site_short_name'] ?? $site['site_title'] ?? 'Athkar'), ENT_QUOTES, 'UTF-8') ?>" />
  <script>
    (function () {
      try {
        var t = localStorage.getItem('athkar_theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      } catch (e) {}
    })();
  </script>
  <script>
    (function () {
      try {
        var qp = new URLSearchParams(window.location.search);
        var ql = qp.get('lang');
        var pathMatch = window.location.pathname.match(/^\/app\/([a-zA-Z]{2,8})(?:\/|$)/);
        var pathLang = pathMatch ? pathMatch[1] : null;
        var l = ql || pathLang || localStorage.getItem('athkar_lang');
        if (!l) {
          var nav = (navigator.language || 'en').toLowerCase();
          l = nav.split('-')[0] || 'en';
        }
        localStorage.setItem('athkar_lang', l);
        var lang = (l || 'en').toLowerCase().split('-')[0];
        document.documentElement.setAttribute('lang', lang);
        document.documentElement.setAttribute('dir', ['ar','fa','ur','he'].indexOf(lang) !== -1 ? 'rtl' : 'ltr');
      } catch (e) {}
    })();
  </script>
  <title><?= htmlspecialchars((string)($site['site_title'] ?? 'Athkar') . ' Section', ENT_QUOTES, 'UTF-8') ?></title>  <?= render_site_head_assets($site, $initialLang) ?>
  <link rel="stylesheet" href="/css/style.css" />
</head>
<body class="reader-body">
  <div id="loading-overlay" class="loading-overlay" role="status" aria-live="polite" aria-label="Loading">
    <div class="loading-bubble" aria-hidden="true"><div class="spinner"></div></div>
  </div>
  <main class="reader-shell reader-shell-app">
    <header class="reader-header compact-header">
      <a class="ghost-button icon-button compact-button tap-safe" id="back-home-btn" href="/app/en/" aria-label="Back to home">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M3 11l9-8 9 8" />
          <path d="M5 10v10h14V10" />
          <path d="M9 20v-6h6v6" />
        </svg>
      </a>
      <div class="reader-title-wrap">
        <p class="eyebrow" id="section-eyebrow">Athkar</p>
        <h1 id="section-title">Loading…</h1>
      </div>
      <div class="header-actions">
        <button class="ghost-button compact-button" id="reset-section-btn" type="button">Reset</button>
        <label class="ghost-button compact-button lang-select-inline" aria-label="Select language"><select class="lang-select lang-select-inline-control" id="lang-select"></select></label>
        <button class="ghost-button icon-button compact-button tap-safe" id="dark-toggle-btn" type="button" data-theme-toggle aria-label="Toggle dark mode"></button>
      </div>
    </header>
    <article class="athkar-card athkar-card-app" id="athkar-card" hidden>
      <div class="athkar-card-head">
        <h2 id="item-title"></h2>
        <div class="athkar-inline-actions">
          <button class="ghost-button icon-button compact-button tap-safe" id="share-btn" type="button" aria-label="Share athkar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12l8-5" /><path d="M8 12l8 5" /><circle cx="6" cy="12" r="2" /><circle cx="18" cy="7" r="2" /><circle cx="18" cy="17" r="2" /></svg></button>
        </div>
      </div>
      <div class="arabic-box"><p class="arabic-text" id="item-arabic"></p></div>
      <section class="detail-tabs" aria-label="Athkar details">
        <div class="detail-tabs-head">
          <div class="tab-row" role="tablist" aria-label="Athkar details tabs">
            <button class="tab-button is-active" id="tab-transliteration" type="button" role="tab" aria-selected="true" aria-controls="panel-transliteration" data-tab="transliteration">Transliteration</button>
            <button class="tab-button" id="tab-translation" type="button" role="tab" aria-selected="false" aria-controls="panel-translation" data-tab="translation">Translation</button>
            <button class="tab-button" id="tab-source" type="button" role="tab" aria-selected="false" aria-controls="panel-source" data-tab="source">Source</button>
          </div>
        </div>
        <div class="tab-panel text-block compact-block is-active" id="panel-transliteration" role="tabpanel" aria-labelledby="tab-transliteration"><p id="item-transliteration"></p><div class="report-inline-row"><button class="ghost-button compact-button report-trigger-button report-accent-button tap-safe" type="button">Report</button></div></div>
        <div class="tab-panel text-block compact-block" id="panel-translation" role="tabpanel" aria-labelledby="tab-translation" hidden><p id="item-translation"></p><div class="report-inline-row"><button class="ghost-button compact-button report-trigger-button report-accent-button tap-safe" type="button">Report</button></div></div>
        <div class="tab-panel source-box compact-block" id="panel-source" role="tabpanel" aria-labelledby="tab-source" hidden><p id="item-source"></p><div class="report-inline-row"><button class="ghost-button compact-button report-trigger-button report-accent-button tap-safe" type="button">Report</button></div></div>
      </section>
    </article>
    <section class="action-dock action-dock-polished" aria-label="Reader controls">
      <div class="dock-topbar">
        <div class="dock-progress-wrap">
          <div class="dock-progress-line">
            <span class="dock-chip dock-chip-soft" id="item-position">1 / 1</span>
            <span class="dock-progress-text" id="progress-text">0 / 0 complete</span>
            <span class="dock-chip dock-chip-accent" id="item-count-badge">1×</span>
          </div>
          <div class="progress-track dock-progress-track" aria-hidden="true"><div class="progress-bar" id="progress-bar"></div></div>
        </div>
      </div>
      <div class="dock-main-controls" aria-label="Item controls">
        <button class="dock-icon-button tap-safe" id="prev-btn" type="button" aria-label="Previous athkar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg></button>
        <button class="dock-counter-button tap-safe" id="counter-btn" type="button" aria-label="Count next repetition">
          <span class="dock-counter-mini"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg><span id="counter-mini-label">Tap</span></span>
          <span class="dock-counter-value" id="counter-number">0</span>
          <span class="dock-counter-note" id="counter-subtext">remaining</span>
        </button>
        <button class="dock-icon-button dock-undo-button tap-safe" id="undo-btn" type="button" aria-label="Undo last count"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 8l-4 4 4 4" /><path d="M5 12h8a6 6 0 1 1 0 12h-1" /></svg></button>
        <button class="dock-icon-button tap-safe" id="next-btn" type="button" aria-label="Next athkar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg></button>
      </div>
    </section>

    <div class="report-modal is-hidden" id="report-modal" hidden>
      <div class="report-modal-backdrop" data-report-close></div>
      <div class="report-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="report-modal-title">
        <button class="ghost-button icon-button compact-button report-modal-close tap-safe" type="button" data-report-close aria-label="Close report form">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12" /><path d="M18 6L6 18" /></svg>
        </button>
        <div class="report-modal-copy">
          <p class="eyebrow" id="report-item-label">Reporting this item</p>
          <h3 id="report-modal-title">Report an issue</h3>
          <p class="hero-text" id="report-modal-intro">Help us review this athkar item quickly.</p>
        </div>
        <form class="report-form" id="report-form" novalidate>
          <input type="hidden" name="item_key" id="report-item-key" value="" />
          <input type="hidden" name="section_slug" id="report-section-slug" value="" />
          <input type="hidden" name="lang" id="report-lang" value="<?= htmlspecialchars($initialLang, ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="page_context" value="app" />
          <input type="hidden" name="form_started_at" id="report-started-at" value="" />
          <input type="hidden" name="captcha_token" value="<?= htmlspecialchars((string)($reportChallenge['token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
          <input class="report-honeypot" type="text" name="company_name" value="" tabindex="-1" autocomplete="off" aria-hidden="true" />
          <label class="report-field"><span id="report-name-label">Your name (optional)</span><input class="report-input" type="text" name="reporter_name" maxlength="120" /></label>
          <label class="report-field"><span id="report-email-label">Your email (optional)</span><input class="report-input" type="email" name="reporter_email" maxlength="190" /></label>
          <label class="report-field"><span id="report-issue-label">Issue type</span>
            <select class="report-input" name="issue_type" id="report-issue-select" required>
              <option value="">Issue type</option>
              <option value="incorrect_source">Incorrect Source</option>
              <option value="incorrect_translation">Incorrect Translation</option>
              <option value="incorrect_athkar_item">Incorrect Athkar Item</option>
              <option value="incorrect_transliteration">Incorrect Transliteration</option>
              <option value="other">Other</option>
            </select>
          </label>
          <label class="report-field"><span id="report-message-label">Details</span><textarea class="report-input report-textarea" name="message" rows="4" maxlength="2000" required></textarea></label>
          <label class="report-field"><span id="report-captcha-label" data-question="<?= htmlspecialchars((string)($reportChallenge['question'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(report_captcha_question_text((string)($reportChallenge['question'] ?? ''), $ui), ENT_QUOTES, 'UTF-8') ?></span><input class="report-input" type="text" name="captcha_answer" id="report-captcha-answer" inputmode="numeric" pattern="[0-9\-]*" maxlength="10" placeholder="<?= htmlspecialchars(seo_t($ui, 'report_captcha_placeholder', 'Type the result'), ENT_QUOTES, 'UTF-8') ?>" required /></label>
          <p class="report-form-status" id="report-form-status" hidden></p>
          <div class="report-form-actions">
            <button class="ghost-button compact-button" type="button" data-report-close id="report-cancel-btn">Cancel</button>
            <button class="primary-button compact-button" type="submit" id="report-submit-btn">Send report</button>
          </div>
        </form>
      </div>
    </div>

    <section class="empty-card" id="empty-state" hidden>
      <h2>Section not found</h2>
      <p>Please go back and choose one of the available athkar sections.</p>
    </section>
  </main>
  <script src="/js/theme.js"></script>
  <script src="/js/i18n.js"></script>
  <script src="/js/storage.js"></script>
  <script src="/js/app.js"></script>
  <script src="/js/report-modal.js"></script>
</body>
</html>
