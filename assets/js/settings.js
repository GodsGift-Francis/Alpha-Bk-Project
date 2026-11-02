document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('open-profile-request')?.addEventListener('click', () => document.getElementById('profile-request-dialog').showModal());
    document.querySelectorAll('[data-settings-form]').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            withBusy(form, async () => {
                const data = Object.fromEntries(new FormData(form));
                if (data.action === 'display') {
                    for (const name of ['reduce_motion','hide_balances']) data[name] = form.elements[name].checked ? '1' : '0';
                }
                const result = await API.post('api/settings.php',data);
                showMessage(`${form.dataset.settingsForm}-alert`,result.error || result.success,!result.success);
                if (!result.success) return;
                if (data.action === 'password') { form.reset(); return; }
                form.dataset.completed = 'true';
                setTimeout(() => location.reload(),850);
            });
        });
    });
});
