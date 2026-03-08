(function () {
  var LANG_KEY = 'athkar_lang';
  var cache = {
    lang: 'en',
    dir: 'ltr',
    strings: {},
    loaded: false,
    languages: [
      { code: 'en', label: 'English', native_label: 'English', dir: 'ltr', is_active: 1, display_order: 1 },
      { code: 'ar', label: 'Arabic', native_label: 'العربية', dir: 'rtl', is_active: 1, display_order: 2 }
    ]
  };

  function detectLang() {
    try {
      var qp = new URLSearchParams(window.location.search);
      var ql = qp.get('lang');
      if (ql) return normalizeLang(ql);
    } catch (e) {}
    try {
      var path = window.location.pathname || '';
      var appMatch = path.match(/^\/app\/([a-zA-Z]{2,8})(?:\/|$)/);
      if (appMatch) return normalizeLang(appMatch[1]);
      var seoMatch = path.match(/^\/([a-zA-Z]{2,8})(?:\/|$)/);
      if (seoMatch) return normalizeLang(seoMatch[1]);
    } catch (e) {}
    try {
      var stored = localStorage.getItem(LANG_KEY);
      if (stored) return normalizeLang(stored);
    } catch (e) {}
    var nav = (navigator.language || navigator.userLanguage || 'en').toLowerCase();
    return normalizeLang(nav);
  }

  function normalizeLang(lang) {
    lang = (lang || '').toLowerCase().trim();
    if (!lang) return 'en';
    return lang.split('-')[0];
  }

  function activeLanguages() {
    return (cache.languages || []).filter(function (lang) {
      return Number(lang.is_active) === 1;
    });
  }

  function getLanguageMeta(code) {
    code = normalizeLang(code);
    var langs = cache.languages || [];
    for (var i = 0; i < langs.length; i += 1) {
      if (normalizeLang(langs[i].code) === code) return langs[i];
    }
    return null;
  }

  function localizeDigits(str) {
    try {
      return String(str).replace(/\d+/g, function (chunk) {
        return new Intl.NumberFormat(cache.lang || 'en', { useGrouping: false }).format(Number(chunk));
      });
    } catch (e) {
      if (cache.lang === 'ar' || cache.lang === 'ur' || cache.lang === 'fa') {
        var maps = {
          ar: '٠١٢٣٤٥٦٧٨٩',
          ur: '۰۱۲۳۴۵۶۷۸۹',
          fa: '۰۱۲۳۴۵۶۷۸۹'
        };
        var digits = maps[cache.lang] || maps.ar;
        return String(str).replace(/\d/g, function (d) { return digits[Number(d)]; });
      }
      return String(str);
    }
  }

  function setLang(lang) {
    lang = normalizeLang(lang);
    var meta = getLanguageMeta(lang);
    if (!meta) {
      var active = activeLanguages();
      meta = active.length ? active[0] : { code: 'en', dir: 'ltr' };
      lang = normalizeLang(meta.code);
    }
    cache.lang = lang;
    cache.dir = (meta.dir === 'rtl') ? 'rtl' : 'ltr';
    applyHtmlLangDir();
    try {
      localStorage.setItem(LANG_KEY, lang);
    } catch (e) {}
  }

  function getLang() {
    return cache.lang;
  }

  function getDir() {
    return cache.dir;
  }

  function getLanguages() {
    return cache.languages.slice();
  }

  function applyHtmlLangDir() {
    document.documentElement.setAttribute('lang', cache.lang);
    document.documentElement.setAttribute('dir', cache.dir);
  }

  function fmtNumber(value) {
    var n = Number(value);
    if (!isFinite(n)) return localizeDigits(String(value));
    try {
      return new Intl.NumberFormat(cache.lang || 'en').format(n);
    } catch (e) {
      return localizeDigits(String(n));
    }
  }

  function interpolate(template, vars) {
    if (!vars) return template;
    return template.replace(/\{(\w+)\}/g, function (_, key) {
      return Object.prototype.hasOwnProperty.call(vars, key) ? String(vars[key]) : '{' + key + '}';
    });
  }

  function t(key, vars) {
    var v = cache.strings && cache.strings[key];
    if (typeof v !== 'string') v = key;
    return interpolate(v, vars);
  }

  function loadLanguages() {
    return fetch('/api/languages.php')
      .then(function (r) {
        if (!r.ok) throw new Error('languages load failed');
        return r.json();
      })
      .then(function (data) {
        if (data && Array.isArray(data.languages) && data.languages.length) {
          cache.languages = data.languages;
        }
        setLang(detectLang());
      })
      .catch(function () {
        setLang(detectLang());
      });
  }

  function loadStrings() {
    var lang = cache.lang;
    return fetch('/api/ui.php?lang=' + encodeURIComponent(lang))
      .then(function (r) {
        if (!r.ok) throw new Error('ui load failed');
        return r.json();
      })
      .then(function (data) {
        cache.strings = (data && data.strings) ? data.strings : {};
        cache.loaded = true;
        if (data && data.dir) {
          cache.dir = data.dir;
          applyHtmlLangDir();
        }
      })
      .catch(function () {
        cache.strings = cache.strings || {};
        cache.loaded = true;
      });
  }

  function init() {
    cache.lang = detectLang();
    setLang(cache.lang);
  }

  function toggleLang() {
    var langs = activeLanguages();
    if (!langs.length) return;
    var idx = 0;
    for (var i = 0; i < langs.length; i += 1) {
      if (normalizeLang(langs[i].code) === cache.lang) {
        idx = i;
        break;
      }
    }
    var next = langs[(idx + 1) % langs.length];
    if (!next) return;
    setLang(next.code);
    window.location.reload();
  }

  window.AthkarI18n = {
    init: init,
    loadLanguages: loadLanguages,
    loadStrings: loadStrings,
    t: t,
    fmtNumber: fmtNumber,
    getLang: getLang,
    getDir: getDir,
    setLang: setLang,
    toggleLang: toggleLang,
    normalizeLang: normalizeLang,
    getLanguages: getLanguages,
    getLanguageMeta: getLanguageMeta
  };
})();
