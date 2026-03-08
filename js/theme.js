(function () {
  var STORAGE_KEY = 'athkar_theme';
  var root = document.documentElement;

  function getSavedTheme() {
    try {
      var t = localStorage.getItem(STORAGE_KEY);
      if (t === 'dark' || t === 'light') return t;
    } catch (e) {}
    return null;
  }

  function prefersDark() {
    try {
      return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    } catch (e) {
      return false;
    }
  }

  function getActiveTheme() {
    return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function setTheme(theme) {
    if (theme === 'dark') root.setAttribute('data-theme', 'dark');
    else root.removeAttribute('data-theme');

    try {
      localStorage.setItem(STORAGE_KEY, theme);
    } catch (e) {}
    renderButtons();
  }

  function moonIcon() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" /></svg>';
  }

  function sunIcon() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
  }

  function renderButtons() {
    var theme = getActiveTheme();
    var buttons = document.querySelectorAll('[data-theme-toggle]');
    buttons.forEach(function (btn) {
      // Show the *other* mode icon (what you'll switch to)
      var next = theme === 'dark' ? 'light' : 'dark';
      btn.innerHTML = next === 'dark' ? moonIcon() : sunIcon();
      btn.setAttribute('aria-label', next === 'dark' ? 'Switch to dark mode' : 'Switch to light mode');
      btn.classList.add('theme-toggle');
    });
  }

  function toggleTheme() {
    setTheme(getActiveTheme() === 'dark' ? 'light' : 'dark');
  }

  // Set initial theme if not already applied by early inline script
  var saved = getSavedTheme();
  if (saved) {
    setTheme(saved);
  } else if (prefersDark()) {
    root.setAttribute('data-theme', 'dark');
  }

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-theme-toggle]') : null;
    if (!btn) return;
    e.preventDefault();
    toggleTheme();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderButtons);
  } else {
    renderButtons();
  }
})();