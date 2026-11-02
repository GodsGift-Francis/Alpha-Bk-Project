(() => {
    const root = document.documentElement;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const apply = () => { root.dataset.theme = root.dataset.colorMode === 'system' ? (media.matches ? 'dark' : 'light') : root.dataset.colorMode || 'light'; };
    apply();
    media.addEventListener('change', apply);
})();
