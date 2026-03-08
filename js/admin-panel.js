(() => {
  const shell = document.querySelector('.admin-app-shell');
  if (!shell) return;

  const desktopToggle = document.querySelector('[data-admin-sidebar-toggle]');
  const mobileOpen = document.querySelector('[data-admin-mobile-open]');
  const mobileClose = document.querySelector('[data-admin-mobile-close]');
  const backdrop = document.querySelector('[data-admin-sidebar-backdrop]');
  const storageKey = 'athkar_admin_sidebar_collapsed';
  const mobileQuery = window.matchMedia('(max-width: 1100px)');

  const applyCollapsed = (collapsed) => {
    shell.classList.toggle('is-collapsed', collapsed && !mobileQuery.matches);
    if (desktopToggle) {
      desktopToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      desktopToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
      desktopToggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    }
  };

  const setSidebarOpen = (open) => {
    shell.classList.toggle('is-sidebar-open', open && mobileQuery.matches);
    if (mobileOpen) {
      mobileOpen.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (mobileClose) {
      mobileClose.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    document.body.classList.toggle('admin-sidebar-open', open && mobileQuery.matches);
  };

  const syncResponsiveState = () => {
    if (mobileQuery.matches) {
      shell.classList.remove('is-collapsed');
      setSidebarOpen(false);
    } else {
      document.body.classList.remove('admin-sidebar-open');
      try {
        applyCollapsed(localStorage.getItem(storageKey) === '1');
      } catch (e) {
        applyCollapsed(false);
      }
    }
  };

  try {
    applyCollapsed(localStorage.getItem(storageKey) === '1');
  } catch (e) {
    applyCollapsed(false);
  }
  syncResponsiveState();

  if (desktopToggle) {
    desktopToggle.addEventListener('click', () => {
      if (mobileQuery.matches) {
        setSidebarOpen(false);
        return;
      }
      const collapsed = !shell.classList.contains('is-collapsed');
      applyCollapsed(collapsed);
      try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (e) {}
    });
  }

  if (mobileOpen) mobileOpen.addEventListener('click', () => setSidebarOpen(true));
  if (mobileClose) mobileClose.addEventListener('click', () => setSidebarOpen(false));
  if (backdrop) backdrop.addEventListener('click', () => setSidebarOpen(false));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && mobileQuery.matches) {
      setSidebarOpen(false);
    }
  });

  const mqHandler = () => syncResponsiveState();
  if (typeof mobileQuery.addEventListener === 'function') {
    mobileQuery.addEventListener('change', mqHandler);
  } else if (typeof mobileQuery.addListener === 'function') {
    mobileQuery.addListener(mqHandler);
  }
})();
