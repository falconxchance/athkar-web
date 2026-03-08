<?php
require_once __DIR__ . '/config/seo.php';

$pdo = app_pdo();
$lang = current_lang_code($pdo, $_GET['lang'] ?? 'en');
$langMeta = current_lang_meta($pdo, $lang);
$itemKey = trim((string)($_GET['item'] ?? ''));
if ($itemKey === '') { render_public_404($pdo, $lang); }
$site = site_defaults_for_lang($pdo, $lang);
$item = seo_item_row($pdo, $itemKey, $lang);
if (!$item) { render_public_404($pdo, $lang); }
$section = seo_section_row($pdo, $item['section_slug'], $lang) ?: ['slug' => $item['section_slug'], 'label' => 'Athkar', 'description' => ''];
$activeLanguages = get_languages($pdo, true);
$ui = seo_ui_strings($pdo, $lang);
$reportChallenge = report_challenge_generate();
$canonical = build_public_url($lang, 'item', $itemKey);
$breadcrumbJson = json_ld_breadcrumbs([
    ['name' => $site['site_title'], 'item' => build_public_url($lang, 'home')],
    ['name' => $section['label'], 'item' => build_public_url($lang, 'section', $section['slug'])],
    ['name' => ($item['title'] ?: excerpt_text($item['arabic'], 50)), 'item' => $canonical],
]);
?><!doctype html>
<html lang="<?= esc($lang) ?>" dir="<?= esc($langMeta['dir']) ?>">
<head>
  <meta charset="utf-8" />
  <?= render_public_theme_boot_script() ?>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= esc(seo_item_title($item, $section, $site)) ?></title>
  <meta name="description" content="<?= esc(seo_item_description($item, $section, $site, $lang)) ?>" />
  <meta name="robots" content="index,follow,max-image-preview:large" />
  <link rel="canonical" href="<?= esc($canonical) ?>" />
  <?php foreach ($activeLanguages as $alt): ?><link rel="alternate" hreflang="<?= esc($alt['code']) ?>" href="<?= esc(build_public_url($alt['code'], 'item', $itemKey)) ?>" /><?php endforeach; ?>
  <link rel="alternate" hreflang="x-default" href="<?= esc(build_public_url(get_default_language($pdo)['code'], 'item', $itemKey)) ?>" />
  <?= render_site_head_assets($site, $lang) ?>
  <link rel="stylesheet" href="/css/style.css" />
  <script type="application/ld+json"><?= $breadcrumbJson ?></script>
</head>
<body class="reader-body">
  <?= render_public_sidebar($pdo, $site, $lang, fn($code, $type = 'home', $slug = null) => build_public_url($code, $type, $slug), 'item', $itemKey) ?>
  <main class="reader-shell public-page-shell">
    <?= render_public_brand_card($site, $ui, $lang) ?>
    <header class="reader-header">
      <a class="ghost-button" href="<?= esc(build_public_url($lang, 'section', $section['slug'])) ?>"><?= esc(seo_t($ui, 'seo_nav_back', 'Back')) ?></a>
      <div class="reader-title-wrap">
        <p class="eyebrow"><?= esc($section['label']) ?></p>
        <h1><?= esc($item['title'] ?: excerpt_text($item['arabic'], 50)) ?></h1>
      </div>
      <a class="ghost-button" href="<?= esc(build_app_url($lang, $section['slug'], $itemKey)) ?>"><?= esc(seo_t($ui, 'seo_nav_open_app', 'Open App')) ?></a>
    </header>

    <nav class="info-card breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom:14px">
      <a href="<?= esc(build_public_url($lang, 'home')) ?>"><?= esc($site['site_title']) ?></a> /
      <a href="<?= esc(build_public_url($lang, 'section', $section['slug'])) ?>"><?= esc($section['label']) ?></a> /
      <strong class="breadcrumb-current"><?= esc($item['title'] ?: excerpt_text($item['arabic'], 50)) ?></strong>
    </nav>

    <article class="athkar-card">
      <div class="item-meta-row item-meta-row-actions">
        <span class="badge"><?= esc(seo_t($ui, 'seo_count_label', 'Count')) ?>: <?= (int)$item['repetition_count'] ?></span>
      </div>
      <div class="arabic-box"><p class="arabic-text"><?= esc($item['arabic']) ?></p></div>
      <?php if (!empty($item['transliteration'])): ?><div class="text-block"><h3><?= esc(seo_t($ui, 'seo_label_transliteration', 'Transliteration')) ?></h3><p><?= esc($item['transliteration']) ?></p></div><?php endif; ?>
      <?php if (!empty($item['translation'])): ?><div class="text-block"><h3><?= esc(seo_t($ui, 'seo_label_translation', 'Translation')) ?></h3><p><?= esc($item['translation']) ?></p></div><?php endif; ?>
      <?php if (!empty($item['source'])): ?><div class="source-box"><p><strong><?= esc(seo_t($ui, 'seo_label_source', 'Source')) ?>:</strong> <?= esc($item['source']) ?></p></div><?php endif; ?>
      <div class="report-inline-row report-inline-row-end"><button class="ghost-button compact-button report-trigger-button report-accent-button tap-safe" type="button"><?= esc(seo_t($ui, 'seo_nav_report', 'Report')) ?></button></div>
    </article>
    <div class="report-modal is-hidden" id="report-modal" hidden>
      <div class="report-modal-backdrop" data-report-close></div>
      <div class="report-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="report-modal-title">
        <button class="ghost-button icon-button compact-button report-modal-close tap-safe" type="button" data-report-close aria-label="<?= esc(seo_t($ui, 'report_cancel_button', 'Cancel')) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12" /><path d="M18 6L6 18" /></svg>
        </button>
        <div class="report-modal-copy">
          <p class="eyebrow"><?= esc(seo_t($ui, 'report_open_item_label', 'Reporting this item')) ?></p>
          <h3 id="report-modal-title"><?= esc(seo_t($ui, 'report_title', 'Report an issue')) ?></h3>
          <p class="hero-text"><?= esc(seo_t($ui, 'report_intro', 'Help us review this athkar item quickly.')) ?></p>
        </div>
        <form class="report-form" id="report-form" novalidate>
          <input type="hidden" name="item_key" value="<?= esc($item['item_key']) ?>" />
          <input type="hidden" name="section_slug" value="<?= esc($item['section_slug']) ?>" />
          <input type="hidden" name="lang" value="<?= esc($lang) ?>" />
          <input type="hidden" name="page_context" value="seo_item" />
          <input type="hidden" name="form_started_at" id="report-started-at" value="" />
          <input type="hidden" name="captcha_token" value="<?= esc((string)($reportChallenge['token'] ?? '')) ?>" />
          <input class="report-honeypot" type="text" name="company_name" value="" tabindex="-1" autocomplete="off" aria-hidden="true" />
          <label class="report-field"><span><?= esc(seo_t($ui, 'report_name_label', 'Your name (optional)')) ?></span><input class="report-input" type="text" name="reporter_name" maxlength="120" /></label>
          <label class="report-field"><span><?= esc(seo_t($ui, 'report_email_label', 'Your email (optional)')) ?></span><input class="report-input" type="email" name="reporter_email" maxlength="190" /></label>
          <label class="report-field"><span><?= esc(seo_t($ui, 'report_issue_label', 'Issue type')) ?></span>
            <select class="report-input" name="issue_type" required>
              <option value=""><?= esc(seo_t($ui, 'report_issue_label', 'Issue type')) ?></option>
              <option value="incorrect_source"><?= esc(seo_t($ui, 'report_option_source', 'Incorrect Source')) ?></option>
              <option value="incorrect_translation"><?= esc(seo_t($ui, 'report_option_translation', 'Incorrect Translation')) ?></option>
              <option value="incorrect_athkar_item"><?= esc(seo_t($ui, 'report_option_item', 'Incorrect Athkar Item')) ?></option>
              <option value="incorrect_transliteration"><?= esc(seo_t($ui, 'report_option_transliteration', 'Incorrect Transliteration')) ?></option>
              <option value="other"><?= esc(seo_t($ui, 'report_option_other', 'Other')) ?></option>
            </select>
          </label>
          <label class="report-field"><span><?= esc(seo_t($ui, 'report_message_label', 'Details')) ?></span><textarea class="report-input report-textarea" name="message" rows="4" maxlength="2000" required></textarea></label>
          <label class="report-field"><span id="report-captcha-label" data-question="<?= esc((string)($reportChallenge['question'] ?? '')) ?>"><?= esc(report_captcha_question_text((string)($reportChallenge['question'] ?? ''), $ui)) ?></span><input class="report-input" type="text" name="captcha_answer" id="report-captcha-answer" inputmode="numeric" pattern="[0-9\-]*" maxlength="10" placeholder="<?= esc(seo_t($ui, 'report_captcha_placeholder', 'Type the result')) ?>" required /></label>
          <p class="report-form-status" id="report-form-status" hidden></p>
          <div class="report-form-actions">
            <button class="ghost-button compact-button" type="button" data-report-close><?= esc(seo_t($ui, 'report_cancel_button', 'Cancel')) ?></button>
            <button class="primary-button compact-button" type="submit"><?= esc(seo_t($ui, 'report_send_button', 'Send report')) ?></button>
          </div>
        </form>
      </div>
    </div>
    <?= render_public_footer_note($site) ?>
  </main>
  <script>
    window.AthkarReportConfig = {
      successText: <?= json_encode(seo_t($ui, 'report_success', 'Thank you. Your report has been sent.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      errorText: <?= json_encode(seo_t($ui, 'report_error', 'Unable to send your report right now.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      validationText: <?= json_encode(seo_t($ui, 'report_validation_message', 'Please choose an issue type and add a short explanation.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      spamText: <?= json_encode(seo_t($ui, 'report_spam_error', 'Please wait a few seconds and try again.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      rateLimitText: <?= json_encode(seo_t($ui, 'report_rate_limit_error', 'Please wait a little before sending another report.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      captchaText: <?= json_encode(seo_t($ui, 'report_captcha_error', 'Please solve the quick check correctly before sending.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      captchaPromptTemplate: <?= json_encode(seo_t($ui, 'report_captcha_prompt', 'Quick check: solve {question}'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      sourceUrl: window.location.href
    };
  </script>
  <script src="/js/theme.js"></script>
  <script src="/js/public-menu.js"></script>
  <script src="/js/report-modal.js"></script>
</body>
</html>
