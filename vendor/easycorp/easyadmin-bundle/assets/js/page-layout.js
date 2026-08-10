document.body.classList.add(
    `ea-content-width-${localStorage.getItem('ea/content/width') || document.body.dataset.eaContentWidth}`,
    `ea-sidebar-width-${localStorage.getItem('ea/sidebar/width') || document.body.dataset.eaSidebarWidth}`
);

// ActionMenu (Bootstrap dropdown) close animation.
// The enter animation is pure CSS (.dropdown-menu.show), but Bootstrap removes
// `.show` and destroys Popper synchronously when hiding, so a CSS-only exit can't
// play. We intercept `hide.bs.dropdown` (a native bubbling CustomEvent, so no
// Bootstrap import is needed here), play the CSS exit while `.show` and Popper
// positioning are still in place, then let Bootstrap finish hiding.
(() => {
    const EXIT_CLASS = 'ea-dropdown-hiding';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const menuFor = (toggle) => {
        const wrapper = toggle.closest('.dropdown, .dropstart, .dropend, .dropup');

        return wrapper?.querySelector(':scope > .dropdown-overlay > .dropdown-menu, :scope > .dropdown-menu');
    };

    document.addEventListener('hide.bs.dropdown', (event) => {
        if (reduceMotion || undefined === window.bootstrap) {
            return; // hide instantly
        }

        const menu = menuFor(event.target);
        if (!menu) {
            return;
        }

        // The programmatic hide() re-fires this event; let it pass through.
        if ('1' === menu.dataset.eaAllowHide) {
            delete menu.dataset.eaAllowHide;
            menu.classList.remove(EXIT_CLASS);

            return;
        }

        event.preventDefault();
        menu.classList.add(EXIT_CLASS);

        const finish = () => {
            clearTimeout(fallback);
            menu.removeEventListener('animationend', finish);
            menu.dataset.eaAllowHide = '1';
            window.bootstrap.Dropdown.getOrCreateInstance(event.target).hide();
        };
        const fallback = setTimeout(finish, 250); // safety net if animationend never fires
        menu.addEventListener('animationend', finish, { once: true });
    });
})();
