(function () {
  var i18n = window.AthkarI18n;
  if (i18n && i18n.init) i18n.init();
  var lang = i18n ? i18n.getLang() : 'en';
  var gridEl = document.getElementById('section-grid');
  var loadingCard = document.getElementById('sections-loading-card');
  var emptyCard = document.getElementById('sections-empty-card');
  var errorCard = document.getElementById('sections-error-card');
  var headerEl = document.getElementById('home-header');
  var footerEl = document.getElementById('home-footer');
  var headerCardEl = document.getElementById('home-header-card');
  var footerCardEl = document.getElementById('home-footer-card');
  var langSelect = document.getElementById('lang-select');
  var overlayEl = document.getElementById('loading-overlay');
  var heroEyebrowEl = document.getElementById('home-hero-eyebrow');
  var heroTitleEl = document.getElementById('home-hero-title');
  var heroDescriptionEl = document.getElementById('home-hero-description');
  var sectionsTitleEl = document.getElementById('home-sections-title');
  var sectionsDescriptionEl = document.getElementById('home-sections-description');
  var heroBrandEl = document.getElementById('home-hero-brand');
  var heroBrandLinkEl = document.getElementById('home-brand-link');
  var logoEl = document.getElementById('home-logo');
  var footerNoteEl = document.getElementById('home-footer-note');

  function showOverlay() { if (overlayEl) overlayEl.classList.remove('is-hidden'); }
  function hideOverlay() { if (overlayEl) overlayEl.classList.add('is-hidden'); }
  function currentLanguages() { return i18n && i18n.getLanguages ? i18n.getLanguages().filter(function (row) { return Number(row.is_active) === 1; }) : []; }
  function syncUrlLang(reload) {
    try {
      if (reload) window.location.href = '/app/' + encodeURIComponent(lang) + '/';
      else window.history.replaceState({}, '', '/app/' + encodeURIComponent(lang) + '/');
    } catch (e) {}
  }
  function renderLanguageSelect() {
    if (!langSelect || !i18n) return;
    var langs = currentLanguages();
    langSelect.innerHTML = '';
    if (!langs.length) {
      var fallback = document.createElement('option');
      fallback.value = 'en'; fallback.textContent = 'EN';
      langSelect.appendChild(fallback); langSelect.value = 'en';
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
  if (langSelect) {
    langSelect.addEventListener('change', function () {
      if (!i18n) return;
      lang = i18n.normalizeLang(langSelect.value);
      showOverlay();
      try { sessionStorage.setItem('athkar_pending_lang', lang); } catch (e) {}
      syncUrlLang(true);
    });
  }

  function defaults() {
    return {
      site_title: 'Athkar',
      site_short_name: 'Athkar',
      site_description: 'Athkar app with database-driven sections and content.',
      theme_color: '#0b3b2e',
      favicon_url: '',
      app_icon_url: '',
      logo_url: '',
      home_header: '',
      home_footer: '',
      footer_note: 'goAthkar | 2026 | v0.1',
      theme_light_bg: '#f6f3ec',
      theme_light_surface: '#ffffff',
      theme_dark_bg: '#0c1210',
      theme_dark_surface: '#111a16'
    };
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
    if (!el) { el = document.createElement(selector.indexOf('link') === 0 ? 'link' : 'meta'); document.head.appendChild(el); }
    Object.keys(attrs).forEach(function (key) { el.setAttribute(key, attrs[key]); });
  }
  function hasMeaningfulHtml(html) {
    var box = document.createElement('div');
    box.innerHTML = html || '';
    return (box.textContent || '').replace(/\s+/g, '').trim() !== '';
  }
  function applyOptionalHtml(cardEl, innerEl, html) {
    if (!cardEl || !innerEl) return;
    if (hasMeaningfulHtml(html)) {
      innerEl.innerHTML = html;
      cardEl.hidden = false;
    } else {
      innerEl.innerHTML = '';
      cardEl.hidden = true;
    }
  }
  function text(key, fallback, vars) {
    var out = i18n ? i18n.t(key, vars) : fallback;
    if (!out || out === key) return fallback.replace(/\{(\w+)\}/g, function (_, name) { return vars && vars[name] ? vars[name] : ''; });
    return out;
  }
  function applySiteSettings(data) {
    var merged = Object.assign(defaults(), data || {});
    var shortName = merged.site_short_name || merged.site_title || 'Athkar';
    applyThemePalette(merged);
    document.title = merged.site_title || 'Athkar';
    setOrCreateMeta('meta[name="description"]', { name: 'description', content: merged.site_description || '' });
    setOrCreateMeta('meta[name="theme-color"]', { name: 'theme-color', content: merged.theme_color || '#0b3b2e' });
    setOrCreateMeta('meta[name="apple-mobile-web-app-title"]', { name: 'apple-mobile-web-app-title', content: shortName });
    if (merged.favicon_url || merged.app_icon_url) setOrCreateMeta('link[rel="icon"]', { rel: 'icon', href: merged.favicon_url || merged.app_icon_url });
    if (merged.app_icon_url) setOrCreateMeta('link[rel="apple-touch-icon"]', { rel: 'apple-touch-icon', href: merged.app_icon_url });
    setOrCreateMeta('link[rel="manifest"]', { rel: 'manifest', href: '/manifest.php?lang=' + encodeURIComponent(lang) });
    if (heroEyebrowEl) heroEyebrowEl.textContent = text('home_welcome_eyebrow', 'Welcome');
    if (heroTitleEl) heroTitleEl.textContent = text('home_welcome_title', 'Welcome to {app}', { app: shortName });
    if (heroDescriptionEl) heroDescriptionEl.textContent = merged.site_description || text('home_welcome_intro', 'Read your daily athkar in a simple, polished, multilingual experience.');
    if (sectionsTitleEl) sectionsTitleEl.textContent = text('home_sections_title', 'Choose a section');
    if (sectionsDescriptionEl) sectionsDescriptionEl.textContent = text('home_sections_intro', 'Start with one of the athkar sections below.');
    var logoUrl = merged.logo_url || merged.app_icon_url || '';
    if (heroBrandLinkEl) {
      heroBrandLinkEl.href = '/app/' + encodeURIComponent(lang) + '/';
      heroBrandLinkEl.setAttribute('aria-label', shortName + ' home');
    }
    if (heroBrandEl && logoEl) {
      if (logoUrl) {
        logoEl.src = logoUrl;
        logoEl.alt = shortName + ' logo';
        heroBrandEl.hidden = false;
      } else {
        logoEl.removeAttribute('src');
        heroBrandEl.hidden = true;
      }
    }
    applyOptionalHtml(headerCardEl, headerEl, merged.home_header);
    applyOptionalHtml(footerCardEl, footerEl, merged.home_footer);
    if (footerNoteEl) {
      var note = (merged.footer_note || '').trim();
      footerNoteEl.textContent = note;
      footerNoteEl.hidden = !note;
    }
  }
  function loadSiteContent() {
    applySiteSettings(null);
    return fetch('/api/site.php?lang=' + encodeURIComponent(lang))
      .then(function (response) { if (!response.ok) throw new Error('Unable to load site content.'); return response.json(); })
      .then(function (data) {
        if (data && data.lang && i18n) {
          lang = i18n.normalizeLang(data.lang); i18n.setLang(lang); renderLanguageSelect(); syncUrlLang(false);
        }
        applySiteSettings(data);
      }).catch(function () {});
  }
  function fallbackIcon(slug) {
    var map = { morning: '☀️', evening: '🌙', prayer: '🕌', 'after-prayer': '🤲' };
    return map[slug] || '✨';
  }
  function hideStatusCards() { if (loadingCard) loadingCard.hidden = true; if (emptyCard) emptyCard.hidden = true; if (errorCard) errorCard.hidden = true; }
  function createTile(section) {
    var link = document.createElement('a');
    link.className = 'section-tile';
    link.href = '/app/' + encodeURIComponent(lang) + '/section/' + encodeURIComponent(section.slug) + '/';
    var icon = document.createElement('span'); icon.className = 'tile-icon'; icon.textContent = section.icon || fallbackIcon(section.slug);
    var title = document.createElement('span'); title.className = 'tile-title'; title.textContent = section.label;
    var subtitle = document.createElement('span'); subtitle.className = 'tile-subtitle'; subtitle.textContent = section.description || text('lbl_open_section', 'Open section');
    link.appendChild(icon); link.appendChild(title); link.appendChild(subtitle); return link;
  }
  function loadSections() {
    return fetch('/api/sections.php?lang=' + encodeURIComponent(lang))
      .then(function (response) { if (!response.ok) throw new Error('Unable to load sections.'); return response.json(); })
      .then(function (data) {
        if (data && data.lang && i18n) { lang = i18n.normalizeLang(data.lang); i18n.setLang(lang); renderLanguageSelect(); syncUrlLang(false); }
        var sections = (data && data.sections) ? data.sections : [];
        hideStatusCards(); gridEl.innerHTML = '';
        if (!sections.length) {
          emptyCard.hidden = false;
          if (i18n) {
            var h2 = emptyCard.querySelector('h2'); var p = emptyCard.querySelector('p');
            if (h2) h2.textContent = i18n.t('msg_no_active_sections_title');
            if (p) p.textContent = i18n.t('msg_no_active_sections_body');
          }
          return;
        }
        sections.forEach(function (section) { gridEl.appendChild(createTile(section)); });
      }).catch(function () {
        hideStatusCards(); errorCard.hidden = false;
        if (i18n) {
          var h2 = errorCard.querySelector('h2'); var p = errorCard.querySelector('p');
          if (h2) h2.textContent = i18n.t('msg_sections_error_title');
          if (p) p.textContent = i18n.t('msg_sections_error_body');
        }
      });
  }

  showOverlay();
  var pending = 4;
  function doneOne() { pending -= 1; if (pending <= 0) hideOverlay(); }
  var chain = Promise.resolve();
  if (i18n && i18n.loadLanguages) chain = chain.then(function () { return i18n.loadLanguages(); }).then(function () { lang = i18n.getLang(); renderLanguageSelect(); syncUrlLang(false); doneOne(); }, doneOne);
  else doneOne();
  chain.then(function () {
    var uiReady = (i18n && i18n.loadStrings) ? i18n.loadStrings() : Promise.resolve();
    uiReady.then(function () { lang = i18n ? i18n.getLang() : lang; renderLanguageSelect(); syncUrlLang(false); doneOne(); }, doneOne);
    uiReady.then(function () { loadSiteContent().then(doneOne, doneOne); loadSections().then(doneOne, doneOne); });
  });
})();
