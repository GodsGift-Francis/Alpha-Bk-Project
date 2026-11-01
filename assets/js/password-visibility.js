(() => {
    const controls = () => document.querySelectorAll('[data-password-toggle]');
    function setVisible(button, visible) {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        button.querySelector('[data-eye-open]').hidden = visible;
        button.querySelector('[data-eye-closed]').hidden = !visible;
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-password-toggle]');
        if (button) setVisible(button, button.getAttribute('aria-pressed') !== 'true');
    });
    const conceal = () => controls().forEach(button => setVisible(button, false));
    window.addEventListener('pagehide', conceal);
    document.addEventListener('visibilitychange', () => { if (document.hidden) conceal(); });
})();
