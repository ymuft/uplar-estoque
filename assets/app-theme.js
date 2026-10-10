(function () {
    const storageKey = 'uplar-theme';
    const root = document.documentElement;
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    let themeTransitionRunning = false;

    function preferredTheme() {
        const saved = localStorage.getItem(storageKey);
        if (saved === 'light' || saved === 'dark') return saved;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function updateThemeToggle(theme, animate) {
        const toggle = document.getElementById('themeToggle');
        if (!toggle) return;

        const dark = theme === 'dark';
        toggle.setAttribute('aria-label', dark ? 'Ativar modo claro' : 'Ativar modo escuro');
        toggle.setAttribute('title', dark ? 'Mudar para modo claro' : 'Mudar para modo escuro');
        const icon = toggle.querySelector('.material-symbols-outlined');
        if (!icon) return;

        if (animate && !reducedMotionQuery.matches) {
            icon.classList.remove('theme-icon-changing');
            void icon.offsetWidth;
            icon.classList.add('theme-icon-changing');
            window.setTimeout(() => icon.classList.remove('theme-icon-changing'), 220);
        }
        icon.textContent = dark ? 'light_mode' : 'dark_mode';
    }

    function applyTheme(theme, persist, animateIcon) {
        root.dataset.theme = theme;
        if (persist) localStorage.setItem(storageKey, theme);
        updateThemeToggle(theme, animateIcon);
        window.dispatchEvent(new CustomEvent('uplar:themechange', {detail: {theme}}));
    }

    function themeOrigin(toggle) {
        const rect = toggle.getBoundingClientRect();
        const x = rect.left + rect.width / 2;
        const y = rect.top + rect.height / 2;
        const width = window.innerWidth;
        const height = window.innerHeight;
        const radius = Math.ceil(Math.max(
            Math.hypot(x, y),
            Math.hypot(width - x, y),
            Math.hypot(x, height - y),
            Math.hypot(width - x, height - y)
        ));

        return {x, y, radius};
    }

    function changeTheme(toggle) {
        if (themeTransitionRunning) return;
        const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        const shouldAnimate = !reducedMotionQuery.matches;
        const transitionApi = document.startViewTransition;

        if (!shouldAnimate || typeof transitionApi !== 'function') {
            root.classList.add('theme-fallback-transition');
            applyTheme(nextTheme, true, shouldAnimate);
            window.setTimeout(() => root.classList.remove('theme-fallback-transition'), 260);
            return;
        }

        const origin = themeOrigin(toggle);
        root.style.setProperty('--theme-origin-x', `${origin.x}px`);
        root.style.setProperty('--theme-origin-y', `${origin.y}px`);
        root.style.setProperty('--theme-reveal-radius', `${origin.radius}px`);

        themeTransitionRunning = true;
        try {
            const transition = document.startViewTransition(() => applyTheme(nextTheme, true, true));
            transition.finished.finally(() => { themeTransitionRunning = false; });
        } catch (error) {
            themeTransitionRunning = false;
            root.classList.add('theme-fallback-transition');
            applyTheme(nextTheme, true, true);
            window.setTimeout(() => root.classList.remove('theme-fallback-transition'), 260);
        }
    }

    applyTheme(preferredTheme(), false, false);

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(root.dataset.theme || preferredTheme(), false, false);
        document.getElementById('themeToggle')?.addEventListener('click', function () {
            changeTheme(this);
        });
        document.getElementById('trigCfg')?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.click();
            }
        });
        document.getElementById('logoutButton')?.addEventListener('click', function () {
            if (window.confirm('Sair do sistema?')) document.getElementById('lf')?.submit();
        });
    });
})();
