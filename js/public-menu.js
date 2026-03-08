(function () {
  var openBtn = document.querySelector('[data-public-menu-open]');
  var closeBtn = document.querySelector('[data-public-menu-close]');
  var sidebar = document.querySelector('[data-public-sidebar]');
  var backdrop = document.querySelector('[data-public-sidebar-backdrop]');
  if (!openBtn || !sidebar || !backdrop) return;

  function setOpen(open) {
    document.body.classList.toggle('public-sidebar-open', !!open);
    backdrop.hidden = !open;
    openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  openBtn.addEventListener('click', function () { setOpen(true); });
  if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
  backdrop.addEventListener('click', function () { setOpen(false); });
  sidebar.addEventListener('click', function (event) {
    var link = event.target && event.target.closest ? event.target.closest('a') : null;
    if (link) setOpen(false);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') setOpen(false);
  });
})();
