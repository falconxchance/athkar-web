(function () {
  if (window.AthkarI18n && window.AthkarI18n.init) {
    window.AthkarI18n.init();
  }
  var i18n = window.AthkarI18n;
  var lang = i18n ? i18n.getLang() : 'en';

  var urlParams = new URLSearchParams(window.location.search);
  var sectionType = urlParams.get('type');
  var initialItemId = urlParams.get('item');
  if (!sectionType) {
    var pathMatch = (window.location.pathname || '').match(/^\/app\/[a-zA-Z]{2,8}\/section\/([^/]+)\/?$/);
    sectionType = pathMatch ? decodeURIComponent(pathMatch[1]) : null;
  }
  var sectionMeta = null;
  var siteSettings = {
    site_title: 'Athkar',
    site_short_name: 'Athkar',
    site_description: 'Athkar section reader.',
    theme_color: '#0b3b2e',
    favicon_url: '',
    app_icon_url: '',
    theme_light_bg: '#f6f3ec',
    theme_light_surface: '#ffffff',
    theme_dark_bg: '#0c1210',
    theme_dark_surface: '#111a16'
  };

  var titleEl = document.getElementById('section-title');
  var eyebrowEl = document.getElementById('section-eyebrow');
  var cardEl = document.getElementById('athkar-card');
  var emptyStateEl = document.getElementById('empty-state');
  var positionEl = document.getElementById('item-position');
  var countBadgeEl = document.getElementById('item-count-badge');
  var itemTitleEl = document.getElementById('item-title');
  var arabicEl = document.getElementById('item-arabic');
  var transliterationEl = document.getElementById('item-transliteration');
  var translationEl = document.getElementById('item-translation');
  var sourceEl = document.getElementById('item-source');
  var tabButtons = Array.prototype.slice.call(document.querySelectorAll('.tab-button'));
  var tabPanels = {
    transliteration: document.getElementById('panel-transliteration'),
    translation: document.getElementById('panel-translation'),
    source: document.getElementById('panel-source')
  };
  var activeTab = 'transliteration';
  var counterNumberEl = document.getElementById('counter-number');
  var counterSubtextEl = document.getElementById('counter-subtext');
  var counterMiniEl = document.querySelector('.dock-counter-mini');
  var counterMiniLabelEl = document.getElementById('counter-mini-label');
  var progressTextEl = document.getElementById('progress-text');
  var progressBarEl = document.getElementById('progress-bar');
  var actionDockEl = document.querySelector('.action-dock');
  var overlayEl = document.getElementById('loading-overlay');
  var prevBtn = document.getElementById('prev-btn');
  var nextBtn = document.getElementById('next-btn');
  var counterBtn = document.getElementById('counter-btn');
  var undoBtn = document.getElementById('undo-btn');
  var resetBtn = document.getElementById('reset-section-btn');
  var shareBtn = document.getElementById('share-btn');
  var reportOpenButtons = Array.prototype.slice.call(document.querySelectorAll('.report-trigger-button'));
  var reportItemLabelEl = document.getElementById('report-item-label');
  var reportItemKeyInput = document.getElementById('report-item-key');
  var reportSectionSlugInput = document.getElementById('report-section-slug');
  var reportLangInput = document.getElementById('report-lang');
  var reportIssueSelect = document.getElementById('report-issue-select');
  var reportNameLabelEl = document.getElementById('report-name-label');
  var reportEmailLabelEl = document.getElementById('report-email-label');
  var reportIssueLabelEl = document.getElementById('report-issue-label');
  var reportMessageLabelEl = document.getElementById('report-message-label');
  var reportCaptchaLabelEl = document.getElementById('report-captcha-label');
  var reportCaptchaAnswerEl = document.getElementById('report-captcha-answer');
  var reportModalTitleEl = document.getElementById('report-modal-title');
  var reportModalIntroEl = document.getElementById('report-modal-intro');
  var reportCancelBtn = document.getElementById('report-cancel-btn');
  var reportSubmitBtn = document.getElementById('report-submit-btn');
  var langSelect = document.getElementById('lang-select');
  var backHomeBtn = document.getElementById('back-home-btn');
  if (backHomeBtn) backHomeBtn.href = '/app/' + encodeURIComponent(lang) + '/';

  function showOverlay() { if (overlayEl) overlayEl.classList.remove('is-hidden'); }
  function hideOverlay() { if (overlayEl) overlayEl.classList.add('is-hidden'); }

  function currentLanguages() {
    return i18n && i18n.getLanguages ? i18n.getLanguages().filter(function (row) { return Number(row.is_active) === 1; }) : [];
  }

  function currentItemId() {
    return items[currentIndex] && items[currentIndex].id ? items[currentIndex].id : '';
  }

  function syncUrlLang(reload) {
    try {
      var target = '/app/' + encodeURIComponent(lang) + '/';
      if (sectionType) {
        target += 'section/' + encodeURIComponent(sectionType) + '/';
        var itemId = currentItemId() || initialItemId || '';
        if (itemId) target += '?item=' + encodeURIComponent(itemId);
      }
      if (reload) {
        window.location.href = target;
      } else {
        window.history.replaceState({}, '', target);
      }
    } catch (e) {}
  }

  function renderLanguageSelect() {
    if (!langSelect || !i18n) return;
    var langs = currentLanguages();
    langSelect.innerHTML = '';
    if (!langs.length) {
      var fallback = document.createElement('option');
      fallback.value = 'en';
      fallback.textContent = 'EN';
      langSelect.appendChild(fallback);
      langSelect.value = 'en';
      return;
    }
    langs.forEach(function (entry) {
      var option = document.createElement('option');
      option.value = entry.code;
      option.textContent = entry.native_label || entry.label || entry.code.toUpperCase();
      langSelect.appendChild(option);
    });
    langSelect.value = i18n.getLang();
  }

  function chevronPath(direction) {
    return direction === 'right' ? 'M9 6l6 6-6 6' : 'M15 18l-6-6 6-6';
  }

  function setButtonChevron(button, direction) {
    if (!button) return;
    var path = button.querySelector('svg path');
    if (path) path.setAttribute('d', chevronPath(direction));
  }

  function updateDirectionalControls() {
    var dir = i18n ? i18n.getDir() : 'ltr';
    if (dir === 'rtl') {
      setButtonChevron(prevBtn, 'right');
      setButtonChevron(nextBtn, 'left');
    } else {
      setButtonChevron(prevBtn, 'left');
      setButtonChevron(nextBtn, 'right');
    }
  }

  function reportText(key, fallback, vars) {
    if (!i18n) return fallback;
    var value = i18n.t(key, vars);
    return value === key ? fallback : value;
  }

  function syncReportState() {
    var item = items[currentIndex] || null;
    if (reportItemKeyInput) reportItemKeyInput.value = item && item.item_key ? String(item.item_key) : '';
    if (reportSectionSlugInput) reportSectionSlugInput.value = sectionType || '';
    if (reportLangInput) reportLangInput.value = lang || 'en';
    if (reportItemLabelEl) {
      var label = reportText('report_open_item_label', 'Reporting this item');
      if (item && item.title) label += ': ' + item.title;
      reportItemLabelEl.textContent = label;
    }
    window.AthkarReportConfig = Object.assign({}, window.AthkarReportConfig || {}, {
      successText: reportText('report_success', 'Thank you. Your report has been sent.'),
      errorText: reportText('report_error', 'Unable to send your report right now.'),
      validationText: reportText('report_validation_message', 'Please choose an issue type and add a short explanation.'),
      spamText: reportText('report_spam_error', 'Please wait a few seconds and try again.'),
      rateLimitText: reportText('report_rate_limit_error', 'Please wait a little before sending another report.'),
      captchaText: reportText('report_captcha_error', 'Please solve the quick check correctly before sending.'),
      captchaPromptTemplate: reportText('report_captcha_prompt', 'Quick check: solve {question}'),
      sourceUrl: window.location.href
    });
  }

  function stashReaderPosition() {
    try {
      if (!sectionType) return;
      sessionStorage.setItem('athkar_restore_reader', JSON.stringify({
        section: sectionType,
        index: currentIndex,
        tab: activeTab
      }));
    } catch (e) {}
  }

  function consumeReaderPosition() {
    try {
      var raw = sessionStorage.getItem('athkar_restore_reader');
      if (!raw) return null;
      sessionStorage.removeItem('athkar_restore_reader');
      var parsed = JSON.parse(raw);
      if (!parsed || parsed.section !== sectionType) return null;
      return parsed;
    } catch (e) {
      return null;
    }
  }

  if (langSelect) {
    langSelect.addEventListener('change', function () {
      if (!i18n) return;
      lang = i18n.normalizeLang(langSelect.value);
      showOverlay();
      stashReaderPosition();
      try { sessionStorage.setItem('athkar_pending_lang', lang); } catch (e) {}
      syncUrlLang(true);
    });
  }

  var items = [];
  var currentIndex = 0;
  var state = { counts: {} };

  function shareUrlForItem(item) {
    return window.location.origin + '/' + encodeURIComponent(lang) + '/item/' + encodeURIComponent(item.id) + '/';
  }

  function shareLabel() {
    return (i18n && i18n.getDir && i18n.getDir() === 'rtl') ? 'مشاركة' : 'Share';
  }

  function applyThemePalette(data) {
    var root = document.documentElement;
    root.style.setProperty('--theme-accent', data.theme_color || '#0b3b2e');
    root.style.setProperty('--theme-light-bg', data.theme_light_bg || '#f6f3ec');
    root.style.setProperty('--theme-light-surface', data.theme_light_surface || '#ffffff');
    root.style.setProperty('--theme-dark-bg', data.theme_dark_bg || '#0c1210');
    root.style.setProperty('--theme-dark-surface', data.theme_dark_surface || '#111a16');
  }

  function setOrCreateMeta(selector, attrs) {
    var el = document.querySelector(selector);
    if (!el) {
      el = document.createElement(selector.indexOf('link') === 0 ? 'link' : 'meta');
      document.head.appendChild(el);
    }
    Object.keys(attrs).forEach(function (key) { el.setAttribute(key, attrs[key]); });
  }

  function applySiteSettings(data) {
    siteSettings = Object.assign(siteSettings, data || {});
    var baseTitle = siteSettings.site_title || 'Athkar';
    applyThemePalette(siteSettings);
    document.title = sectionMeta && sectionMeta.label ? (sectionMeta.label + ' • ' + baseTitle) : baseTitle;
    setOrCreateMeta('meta[name="description"]', { name: 'description', content: siteSettings.site_description || 'Athkar section reader.' });
    setOrCreateMeta('meta[name="theme-color"]', { name: 'theme-color', content: siteSettings.theme_color || '#0b3b2e' });
    setOrCreateMeta('meta[name="apple-mobile-web-app-title"]', { name: 'apple-mobile-web-app-title', content: siteSettings.site_short_name || baseTitle });
    if (siteSettings.favicon_url || siteSettings.app_icon_url) setOrCreateMeta('link[rel="icon"]', { rel: 'icon', href: siteSettings.favicon_url || siteSettings.app_icon_url });
    if (siteSettings.app_icon_url) setOrCreateMeta('link[rel="apple-touch-icon"]', { rel: 'apple-touch-icon', href: siteSettings.app_icon_url });
    setOrCreateMeta('link[rel="manifest"]', { rel: 'manifest', href: '/manifest.php?lang=' + encodeURIComponent(lang) });
  }

  function loadSiteSettings() {
    applySiteSettings(null);
    return fetch('/api/site.php?lang=' + encodeURIComponent(lang))
      .then(function (response) {
        if (!response.ok) throw new Error('Unable to load site settings.');
        return response.json();
      })
      .then(function (data) {
        if (data && data.lang && i18n) {
          lang = i18n.normalizeLang(data.lang);
          i18n.setLang(lang);
          renderLanguageSelect();
          updateDirectionalControls();
          syncUrlLang(false);
        }
        applySiteSettings(data);
      })
      .catch(function () {});
  }

  function repetitionLabel(count) {
    var c = i18n ? i18n.fmtNumber(count) : String(count);
    return c + '×';
  }

  function getDoneCount(itemId) { return Number(state.counts[itemId] || 0); }
  function setDoneCount(itemId, value) { state.counts[itemId] = value; window.AthkarStorage.save(sectionType, state); }
  function remainingCount(item) { return Math.max(item.count - getDoneCount(item.id), 0); }
  function completedItems() {
    return items.filter(function (item) { return getDoneCount(item.id) >= item.count; }).length;
  }

  function updateProgress() {
    var done = completedItems();
    var total = items.length || 1;
    var percentage = (done / total) * 100;
    if (done === items.length && items.length > 0) {
      progressTextEl.textContent = i18n ? i18n.t('msg_section_completed') : 'Section completed ✓';
      progressBarEl.classList.add('is-complete');
    } else {
      progressTextEl.textContent = i18n ? i18n.t('msg_complete_of_total', {
        done: i18n.fmtNumber(done), total: i18n.fmtNumber(items.length)
      }) : (done + ' / ' + items.length + ' complete');
      progressBarEl.classList.remove('is-complete');
    }
    progressBarEl.style.width = percentage + '%';
  }

  function triggerButtonFeedback(element) {
    if (!element) return;
    element.classList.remove('is-tapped');
    void element.offsetWidth;
    element.classList.add('is-tapped');
    window.setTimeout(function () { element.classList.remove('is-tapped'); }, 260);
  }

  function triggerCounterFeedback() {
    triggerButtonFeedback(counterBtn);
    if (counterNumberEl) {
      counterNumberEl.classList.remove('is-bump');
      void counterNumberEl.offsetWidth;
      counterNumberEl.classList.add('is-bump');
      window.setTimeout(function () { counterNumberEl.classList.remove('is-bump'); }, 220);
    }
    if (counterMiniEl) {
      counterMiniEl.classList.remove('is-bump');
      void counterMiniEl.offsetWidth;
      counterMiniEl.classList.add('is-bump');
      window.setTimeout(function () { counterMiniEl.classList.remove('is-bump'); }, 220);
    }
  }

  function activateTab(tabName, focusButton) {
    activeTab = tabName;
    tabButtons.forEach(function (button) {
      var isActive = button.getAttribute('data-tab') === tabName;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      if (isActive && focusButton) button.focus();
    });
    Object.keys(tabPanels).forEach(function (key) {
      var panel = tabPanels[key];
      var isActive = key === tabName;
      panel.hidden = !isActive;
      panel.classList.toggle('is-active', isActive);
    });
  }

  function scrollCardToTop() {
    if (cardEl) cardEl.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function moveToIndex(nextIndex) {
    if (nextIndex < 0 || nextIndex >= items.length) return;
    currentIndex = nextIndex;
    render();
    scrollCardToTop();
  }

  function render() {
    if (!items.length) return;
    var item = items[currentIndex];
    var done = getDoneCount(item.id);
    var remaining = remainingCount(item);

    titleEl.textContent = sectionMeta && sectionMeta.label ? sectionMeta.label : (i18n ? i18n.t('app_name') : 'Athkar');
    eyebrowEl.textContent = sectionMeta && sectionMeta.description ? sectionMeta.description : (siteSettings.site_short_name || (i18n ? i18n.t('app_name') : 'Athkar'));
    document.title = (sectionMeta && sectionMeta.label ? (sectionMeta.label + ' • ') : '') + (siteSettings.site_title || 'Athkar');
    if (backHomeBtn) backHomeBtn.href = '/app/' + encodeURIComponent(lang) + '/';
    positionEl.textContent = (i18n ? i18n.fmtNumber(currentIndex + 1) : (currentIndex + 1)) + ' / ' + (i18n ? i18n.fmtNumber(items.length) : items.length);
    countBadgeEl.textContent = repetitionLabel(item.count);
    itemTitleEl.textContent = item.title;
    arabicEl.textContent = item.arabic;
    transliterationEl.textContent = item.transliteration || '—';
    translationEl.textContent = item.translation || '—';
    sourceEl.textContent = item.source || '—';
    activateTab(activeTab, false);
    if (remaining === 0) {
      counterNumberEl.textContent = '✓';
      counterNumberEl.classList.add('is-check');
      counterSubtextEl.textContent = i18n ? i18n.t('dock_completed') : 'Completed';
    } else {
      counterNumberEl.textContent = i18n ? i18n.fmtNumber(remaining) : remaining;
      counterNumberEl.classList.remove('is-check');
      counterSubtextEl.textContent = i18n ? i18n.t('dock_remaining') : 'remaining';
    }
    counterBtn.disabled = done >= item.count;
    counterBtn.classList.toggle('is-complete', remaining === 0);
    undoBtn.disabled = done <= 0;
    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex === items.length - 1;
    updateProgress();
    if (shareBtn) shareBtn.setAttribute('data-share-url', shareUrlForItem(item));
    syncReportState();
  }

  function showEmptyState(title, message) {
    emptyStateEl.hidden = false;
    cardEl.hidden = true;
    if (actionDockEl) actionDockEl.hidden = true;
    resetBtn.hidden = true;
    titleEl.textContent = title;
    eyebrowEl.textContent = siteSettings.site_short_name || 'Athkar';
    document.title = title + ' • ' + (siteSettings.site_title || 'Athkar');
    var h2 = emptyStateEl.querySelector('h2');
    if (h2) h2.textContent = title;
    var p = emptyStateEl.querySelector('p');
    if (p) p.textContent = message || '';
    syncReportState();
  }

  function loadSection() {
    if (!sectionType) {
      hideOverlay();
      showEmptyState(i18n ? i18n.t('msg_section_not_found_title') : 'Section not found', i18n ? i18n.t('msg_section_not_found_body') : 'Please go back and choose one of the available athkar sections.');
      return;
    }
    state = window.AthkarStorage.load(sectionType);
    fetch('/api/athkar.php?section=' + encodeURIComponent(sectionType) + '&lang=' + encodeURIComponent(lang))
      .then(function (response) {
        if (!response.ok) throw new Error('Unable to load section data.');
        return response.json();
      })
      .then(function (data) {
        if (data && data.lang && i18n) {
          lang = i18n.normalizeLang(data.lang);
          i18n.setLang(lang);
          renderLanguageSelect();
          updateDirectionalControls();
          syncUrlLang(false);
        }
        sectionMeta = data.section || null;
        items = data.items || [];
        var restoreState = consumeReaderPosition();
        var requestedItemIndex = -1;
        if (initialItemId) {
          requestedItemIndex = items.findIndex(function (entry) { return String(entry.id || '') === String(initialItemId); });
        }
        if (requestedItemIndex >= 0) {
          currentIndex = requestedItemIndex;
        } else if (restoreState && typeof restoreState.index === 'number' && restoreState.index >= 0 && restoreState.index < items.length) {
          currentIndex = restoreState.index;
        } else {
          currentIndex = 0;
        }
        if (restoreState && restoreState.tab && tabPanels[restoreState.tab]) {
          activeTab = restoreState.tab;
        }
        if (!items.length) {
          titleEl.textContent = sectionMeta && sectionMeta.label ? sectionMeta.label : 'No athkar found';
          showEmptyState(i18n ? i18n.t('msg_no_athkar_found_title') : 'No athkar found', i18n ? i18n.t('msg_no_athkar_found_body') : 'This section does not have any items yet.');
          hideOverlay();
          return;
        }
        cardEl.hidden = false;
        if (actionDockEl) actionDockEl.hidden = false;
        render();
        syncUrlLang(false);
        hideOverlay();
      })
      .catch(function () {
        hideOverlay();
        showEmptyState(i18n ? i18n.t('msg_data_load_error_title') : 'Unable to load data', i18n ? i18n.t('msg_data_load_error_body') : 'Please check your database connection, active section status, and uploaded files.');
      });
  }

  counterBtn.addEventListener('click', function () {
    var item = items[currentIndex];
    var done = getDoneCount(item.id);
    if (done < item.count) {
      setDoneCount(item.id, done + 1);
      render();
      triggerCounterFeedback();
      if (done + 1 >= item.count && currentIndex < items.length - 1) {
        setTimeout(function () { moveToIndex(currentIndex + 1); }, 180);
      }
    }
  });

  undoBtn.addEventListener('click', function () {
    var item = items[currentIndex];
    var done = getDoneCount(item.id);
    if (done > 0) {
      setDoneCount(item.id, done - 1);
      render();
      triggerButtonFeedback(undoBtn);
      triggerCounterFeedback();
    }
  });
  prevBtn.addEventListener('click', function () { triggerButtonFeedback(prevBtn); moveToIndex(currentIndex - 1); });
  nextBtn.addEventListener('click', function () { triggerButtonFeedback(nextBtn); moveToIndex(currentIndex + 1); });
  if (shareBtn) {
    shareBtn.addEventListener('click', function () {
      if (!items.length) return;
      var item = items[currentIndex];
      var url = shareUrlForItem(item);
      var title = item.title || (sectionMeta && sectionMeta.label) || (siteSettings.site_title || 'Athkar');
      var textValue = ((sectionMeta && sectionMeta.label) ? (sectionMeta.label + ' — ') : '') + title;
      var fallbackDone = function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(function () { alert(shareLabel() + ': ' + url); }, function () { window.prompt(shareLabel(), url); });
        } else {
          window.prompt(shareLabel(), url);
        }
      };
      if (navigator.share) {
        navigator.share({ title: title, text: textValue, url: url }).catch(function () {});
      } else {
        fallbackDone();
      }
      triggerButtonFeedback(shareBtn);
    });
  }

  tabButtons.forEach(function (button, index) {
    button.addEventListener('click', function () { activateTab(button.getAttribute('data-tab'), false); });
    button.addEventListener('keydown', function (event) {
      var dir = i18n ? i18n.getDir() : 'ltr';
      var nextIndex = index;
      if ((event.key === 'ArrowRight' && dir === 'ltr') || (event.key === 'ArrowLeft' && dir === 'rtl')) nextIndex = Math.min(tabButtons.length - 1, index + 1);
      if ((event.key === 'ArrowLeft' && dir === 'ltr') || (event.key === 'ArrowRight' && dir === 'rtl')) nextIndex = Math.max(0, index - 1);
      if (nextIndex !== index) activateTab(tabButtons[nextIndex].getAttribute('data-tab'), true);
    });
  });

  resetBtn.addEventListener('click', function () {
    var msg = (i18n && i18n.getDir && i18n.getDir() === 'rtl') ? 'هل تريد إعادة ضبط تقدم هذا القسم على هذا الجهاز؟' : 'Reset this section progress on this device?';
    if (!window.confirm(msg)) return;
    window.AthkarStorage.reset(sectionType);
    state = window.AthkarStorage.load(sectionType);
    render();
  });

  [prevBtn, nextBtn, undoBtn, counterBtn, shareBtn].filter(Boolean).forEach(function (button) {
    button.addEventListener('dblclick', function (event) { event.preventDefault(); });
  });

  function applyUiLabels() {
    if (!i18n) return;
    var tabT = document.getElementById('tab-transliteration');
    var tabTr = document.getElementById('tab-translation');
    var tabS = document.getElementById('tab-source');
    if (tabT) tabT.textContent = i18n.t('tab_transliteration');
    if (tabTr) tabTr.textContent = i18n.t('tab_translation');
    if (tabS) tabS.textContent = i18n.t('tab_source');
    reportOpenButtons.forEach(function (button) { button.textContent = reportText('report_button', 'Report'); });
    if (reportModalTitleEl) reportModalTitleEl.textContent = reportText('report_title', 'Report an issue');
    if (reportModalIntroEl) reportModalIntroEl.textContent = reportText('report_intro', 'Help us review this athkar item quickly.');
    if (reportNameLabelEl) reportNameLabelEl.textContent = reportText('report_name_label', 'Your name (optional)');
    if (reportEmailLabelEl) reportEmailLabelEl.textContent = reportText('report_email_label', 'Your email (optional)');
    if (reportIssueLabelEl) reportIssueLabelEl.textContent = reportText('report_issue_label', 'Issue type');
    if (reportMessageLabelEl) reportMessageLabelEl.textContent = reportText('report_message_label', 'Details');
    if (reportCaptchaLabelEl) {
      var question = reportCaptchaLabelEl.getAttribute('data-question') || '';
      reportCaptchaLabelEl.textContent = reportText('report_captcha_prompt', 'Quick check: solve {question}', { question: question });
    }
    if (reportCaptchaAnswerEl) reportCaptchaAnswerEl.placeholder = reportText('report_captcha_placeholder', 'Type the result');
    if (reportCancelBtn) reportCancelBtn.textContent = reportText('report_cancel_button', 'Cancel');
    if (reportSubmitBtn) reportSubmitBtn.textContent = reportText('report_send_button', 'Send report');
    if (reportIssueSelect) {
      var opts = reportIssueSelect.options;
      if (opts[0]) opts[0].textContent = reportText('report_issue_label', 'Issue type');
      if (opts[1]) opts[1].textContent = reportText('report_option_source', 'Incorrect Source');
      if (opts[2]) opts[2].textContent = reportText('report_option_translation', 'Incorrect Translation');
      if (opts[3]) opts[3].textContent = reportText('report_option_item', 'Incorrect Athkar Item');
      if (opts[4]) opts[4].textContent = reportText('report_option_transliteration', 'Incorrect Transliteration');
      if (opts[5]) opts[5].textContent = reportText('report_option_other', 'Other');
    }
    if (prevBtn) prevBtn.setAttribute('aria-label', i18n.t('aria_prev'));
    if (nextBtn) nextBtn.setAttribute('aria-label', i18n.t('aria_next'));
    if (undoBtn) undoBtn.setAttribute('aria-label', i18n.t('aria_undo'));
    if (counterBtn) counterBtn.setAttribute('aria-label', i18n.t('aria_count'));
    if (shareBtn) shareBtn.setAttribute('aria-label', shareLabel() + ' athkar');
    reportOpenButtons.forEach(function (button) { button.setAttribute('aria-label', reportText('report_button', 'Report') + ' athkar'); });
    if (backHomeBtn) backHomeBtn.setAttribute('aria-label', i18n.t('aria_back_home'));
    var darkBtn = document.getElementById('dark-toggle-btn') || document.querySelector('[data-theme-toggle]');
    if (darkBtn) darkBtn.setAttribute('aria-label', i18n.t('aria_toggle_dark'));
    if (counterMiniLabelEl) counterMiniLabelEl.textContent = i18n.t('dock_tap');
    if (resetBtn) resetBtn.textContent = i18n.t('btn_reset');
    syncReportState();
    updateDirectionalControls();
  }

  showOverlay();
  updateDirectionalControls();
  var chain = Promise.resolve();
  if (i18n && i18n.loadLanguages) {
    chain = chain.then(function () { return i18n.loadLanguages(); }).then(function () {
      lang = i18n.getLang();
      renderLanguageSelect();
      updateDirectionalControls();
    });
  }
  chain.then(function () {
    var uiReady = (i18n && i18n.loadStrings) ? i18n.loadStrings() : Promise.resolve();
    uiReady.then(function () {
      lang = i18n ? i18n.getLang() : lang;
      renderLanguageSelect();
      applyUiLabels();
      try { sessionStorage.removeItem('athkar_pending_lang'); } catch (e) {}
      loadSiteSettings();
      loadSection();
    });
  });
})();
