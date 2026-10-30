document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('app-sidebar');
    const sidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const backdrop = document.getElementById('mobile-sidebar-backdrop');

    const closeSidebar = () => {
        sidebar?.classList.remove('is-open');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
        if (backdrop) backdrop.hidden = true;
    };

    sidebarToggle?.addEventListener('click', () => {
        const open = sidebar?.classList.toggle('is-open') ?? false;
        sidebarToggle.setAttribute('aria-expanded', String(open));
        if (backdrop) backdrop.hidden = !open;
    });
    backdrop?.addEventListener('click', closeSidebar);
    sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
    window.addEventListener('resize', () => { if (window.innerWidth >= 1024) closeSidebar(); });

    const menus = [
        ['profile-menu-toggle', 'profile-menu'],
        ['notification-menu-toggle', 'notification-menu'],
    ];

    menus.forEach(([toggleId, menuId]) => {
        const toggle = document.getElementById(toggleId);
        const menu = document.getElementById(menuId);
        if (!toggle || !menu) return;

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = menu.hidden;
            document.querySelectorAll('[data-topbar-menu]').forEach((item) => { item.hidden = true; });
            document.querySelectorAll('[data-menu-toggle]').forEach((item) => { item.setAttribute('aria-expanded', 'false'); });
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('[data-topbar-menu]').forEach((menu) => { menu.hidden = true; });
        document.querySelectorAll('[data-menu-toggle]').forEach((toggle) => { toggle.setAttribute('aria-expanded', 'false'); });
    });
});
