const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
const maskAccountNumber = value => String(value ?? '').replace(/.(?=.{4})/g, '*');
const displayAmount = value => {
    const [whole, cents = '00'] = String(value).split('.');
    return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${cents.padEnd(2, '0')}`;
};
const displayDate = value => {
    if (!value) return '—';
    const date = new Date(value.replace(' ', 'T') + 'Z');
    return Number.isNaN(date.valueOf()) ? '—' : date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
};
function showMessage(target, message, error = false) {
    const container = typeof target === 'string' ? document.getElementById(target) : target;
    if (!container) return;
    const alert = document.createElement('div');
    alert.className = `alert ${error ? 'alert-error' : 'alert-success'}`;
    alert.setAttribute('role', error ? 'alert' : 'status');
    alert.textContent = message || 'The request could not be completed.';
    container.replaceChildren(alert);
}
async function withBusy(form, action) {
    if (form.dataset.busy === 'true' || form.dataset.completed === 'true') return;
    form.dataset.busy = 'true';
    form.setAttribute('aria-busy', 'true');
    const buttons = [...form.querySelectorAll('[type="submit"]')];
    buttons.forEach(button => { button.disabled = true; });
    try { await action(); }
    finally {
        form.dataset.busy = 'false';
        form.removeAttribute('aria-busy');
        if (form.dataset.completed !== 'true') buttons.forEach(button => { button.disabled = false; });
    }
}
function reviewRequest(data) {
    return new Promise(resolve => {
        const dialog = document.createElement('dialog');
        dialog.setAttribute('aria-labelledby', 'request-review-title');
        dialog.innerHTML = '<div class="card"><h3 class="card-title" id="request-review-title">Review your request</h3><dl class="review-details"></dl><p class="form-note">An administrator must approve this request before your balance changes.</p><div class="row-actions"><button type="button" class="btn btn-primary" data-confirm>Submit request</button><button type="button" class="btn btn-secondary" data-cancel>Go back</button></div></div>';
        const details = dialog.querySelector('dl');
        const values = [['Type', data.action], ['From account', data.account_number], ['Amount', CURRENCY_SYMBOL + displayAmount(data.amount)]];
        if (data.recipient_account) values.push(['Recipient account', data.recipient_account]);
        if (data.description) values.push(['Description', data.description]);
        values.forEach(([label, value]) => {
            const term = document.createElement('dt'); term.textContent = label;
            const definition = document.createElement('dd'); definition.textContent = value;
            details.append(term, definition);
        });
        dialog.querySelector('[data-confirm]').addEventListener('click', () => dialog.close('confirm'));
        dialog.querySelector('[data-cancel]').addEventListener('click', () => dialog.close('cancel'));
        dialog.addEventListener('close', () => { resolve(dialog.returnValue === 'confirm'); dialog.remove(); }, { once: true });
        document.body.append(dialog);
        dialog.showModal();
    });
}
const accountControl = number => `<div class="account-number-container"><span class="account-number" data-number="${escapeHTML(number)}">${escapeHTML(maskAccountNumber(number))}</span><button type="button" class="toggle-account-btn" data-toggle-account aria-label="Show account number" aria-pressed="false">Show</button></div>`;

document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('.nav-toggle');
    menu?.addEventListener('click', () => {
        const expanded = menu.getAttribute('aria-expanded') !== 'true';
        menu.setAttribute('aria-expanded', String(expanded));
        document.getElementById('primary-nav').classList.toggle('is-open', expanded);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            menu?.setAttribute('aria-expanded', 'false');
            document.getElementById('primary-nav')?.classList.remove('is-open');
        }
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-toggle-account]');
        if (button) {
            const span = button.parentElement.querySelector('[data-number]');
            const show = button.getAttribute('aria-pressed') !== 'true';
            span.textContent = show ? span.dataset.number : maskAccountNumber(span.dataset.number);
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Hide account number' : 'Show account number');
            button.textContent = show ? 'Hide' : 'Show';
        }
        if (event.target.closest('[data-close-dialog]')) event.target.closest('dialog')?.close();
    });
    for (const [id, endpoint, destination] of [
        ['register', 'api/register.php', () => 'login.php'],
        ['login', 'api/login.php', result => result.is_admin ? 'admin.php' : 'dashboard.php'],
        ['settings', 'api/settings.php', () => null]
    ]) {
        const form = document.getElementById(`${id}-form`);
        form?.addEventListener('submit', event => {
            event.preventDefault();
            withBusy(form, async () => {
                const result = await API.post(endpoint, Object.fromEntries(new FormData(form)));
                showMessage(`${id}-alert`, result.error || result.success, !result.success);
                if (result.success) {
                    const next = destination(result);
                    if (next) { form.dataset.completed = 'true'; setTimeout(() => location.href = next, 850); }
                    else if (id === 'settings') { form.dataset.completed = 'true'; setTimeout(() => location.reload(), 850); }
                }
            });
        });
    }
    const dashboard = document.getElementById('dashboard-content');
    async function loadAccounts() {
        dashboard.innerHTML = '<div class="card" role="status">Loading accounts…</div>';
        const result = await API.get('api/accounts.php');
        if (result.error) {
            showMessage(dashboard, result.error, true);
            const retry = document.createElement('button'); retry.className = 'btn btn-secondary'; retry.textContent = 'Retry loading accounts'; retry.addEventListener('click', loadAccounts); dashboard.append(retry);
            return;
        }
        if (!result.accounts.length) { dashboard.innerHTML = '<div class="card">No accounts yet. Open an account to get started.</div>'; return; }
        dashboard.innerHTML = `<div class="accounts-grid">${result.accounts.map(account => `<article class="card account-card"><div class="section-heading"><h3>${escapeHTML(account.account_type)} Account</h3><span class="badge ${account.status === 'APPROVED' ? 'badge-deposit' : account.status === 'PENDING' ? 'badge-transfer_out' : 'badge-withdrawal'}">${escapeHTML(account.status)}</span></div><p class="balance">${escapeHTML(CURRENCY_SYMBOL + displayAmount(account.balance))}</p>${accountControl(account.account_number)}${account.status === 'PENDING' ? '<p class="form-note">Awaiting administrator approval.</p>' : ''}${account.review_reason ? `<p class="form-note">Review: ${escapeHTML(account.review_reason)}</p>` : ''}</article>`).join('')}</div>`;
    }
    if (dashboard) loadAccounts();
    const history = document.getElementById('history-content');
    const filters = document.getElementById('history-filters');
    let historyVersion = 0;
    async function loadHistory(page = 1) {
        const version = ++historyVersion;
        const query = new URLSearchParams(filters ? new FormData(filters) : undefined);
        query.set('page', page);
        history.setAttribute('aria-busy', 'true');
        history.innerHTML = '<div class="card" role="status">Loading transaction history…</div>';
        const result = await API.get(`api/history.php?${query}`);
        if (version !== historyVersion) return;
        history.removeAttribute('aria-busy');
        if (result.error) {
            showMessage(history, result.error, true);
            const retry = document.createElement('button'); retry.className = 'btn btn-secondary'; retry.textContent = 'Retry loading history'; retry.addEventListener('click', () => loadHistory(page)); history.append(retry); return;
        }
        if (!result.history.length) { history.innerHTML = '<div class="card text-secondary">No transactions match these filters.</div>'; return; }
        history.innerHTML = `<div class="table-container" tabindex="0" role="region" aria-label="Transaction history"><table class="history-table"><thead><tr><th scope="col">Reference / Submitted</th><th scope="col">Account</th><th scope="col">Type</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Details</th></tr></thead><tbody>${result.history.map(tx => {
            const credit = ['DEPOSIT', 'TRANSFER_IN'].includes(tx.transaction_type);
            const statusClass = tx.status === 'APPROVED' ? 'badge-deposit' : tx.status === 'PENDING' ? 'badge-transfer_out' : 'badge-withdrawal';
            return `<tr><td><strong>TX-${escapeHTML(tx.id)}</strong><br><span class="text-secondary text-sm">${escapeHTML(displayDate(tx.timestamp))}</span></td><td>${accountControl(tx.account_number)}</td><td>${escapeHTML(tx.transaction_type.replaceAll('_', ' '))}</td><td class="font-bold nowrap ${tx.status === 'APPROVED' ? credit ? 'text-success' : 'text-error' : ''}">${credit ? '+' : '−'}${escapeHTML(CURRENCY_SYMBOL + displayAmount(tx.amount))}</td><td><span class="badge ${statusClass}">${escapeHTML(tx.status)}</span></td><td>${escapeHTML(tx.description || '—')}${tx.reviewed_at ? `<br><span class="text-secondary text-sm">Reviewed ${escapeHTML(displayDate(tx.reviewed_at))}</span>` : ''}${tx.review_reason ? `<br><strong>Reason:</strong> ${escapeHTML(tx.review_reason)}` : ''}</td></tr>`;
        }).join('')}</tbody></table></div><div class="pagination"><button type="button" class="btn btn-secondary" data-prev>Previous</button><span>Page ${result.pagination.page} of ${result.pagination.pages} · ${result.pagination.total} records</span><button type="button" class="btn btn-secondary" data-next>Next</button></div>`;
        const previous = history.querySelector('[data-prev]'); const next = history.querySelector('[data-next]');
        previous.disabled = result.pagination.page <= 1; next.disabled = result.pagination.page >= result.pagination.pages;
        previous.addEventListener('click', () => loadHistory(result.pagination.page - 1));
        next.addEventListener('click', () => loadHistory(result.pagination.page + 1));
    }
    if (history) loadHistory();
    filters?.addEventListener('submit', event => { event.preventDefault(); loadHistory(); });
    filters?.addEventListener('reset', () => setTimeout(() => loadHistory(), 0));

    for (const kind of ['deposit', 'withdraw', 'transfer']) {
        const form = document.getElementById(`${kind}-form`);
        form?.addEventListener('submit', event => {
            event.preventDefault();
            const data = Object.fromEntries(new FormData(form));
            withBusy(form, async () => {
                if (!await reviewRequest(data)) return;
                const scope = document.querySelector('meta[name="csrf-token"]').content;
                const storageKey = 'alphabank:request:' + scope + ':' + JSON.stringify(data);
                let key;
                try { key = sessionStorage.getItem(storageKey); } catch (_) { /* Storage may be unavailable. */ }
                // Retain the key in memory and session storage after an uncertain network response.
                key ||= form.requestKeys?.[storageKey];
                key ||= [...crypto.getRandomValues(new Uint8Array(16))].map(value => value.toString(16).padStart(2, '0')).join('');
                form.requestKeys ||= {}; form.requestKeys[storageKey] = key;
                try { sessionStorage.setItem(storageKey, key); } catch (_) { /* In-memory retry key remains available. */ }
                const result = await API.post('api/transactions.php', { ...data, request_key: key });
                showMessage(`${kind}-alert`, result.error || `${result.reference}: ${result.success}`, !result.success);
                if (result.success) {
                    form.dataset.completed = 'true';
                    try { sessionStorage.removeItem(storageKey); } catch (_) { /* No action needed. */ }
                    setTimeout(() => location.href = 'dashboard.php', 1300);
                }
            });
        });
    }
    const verify = document.getElementById('verify-acc-btn');
    const recipient = document.getElementById('recipient_account');
    recipient?.addEventListener('input', () => { document.getElementById('verify-result').textContent = ''; });
    verify?.addEventListener('click', async () => {
        const number = recipient.value.trim();
        const output = document.getElementById('verify-result');
        if (!number) { output.textContent = 'Enter a recipient account number first.'; return; }
        verify.disabled = true; output.textContent = 'Checking recipient…';
        const result = await API.get('api/verify-account.php?account_number=' + encodeURIComponent(number));
        verify.disabled = false;
        if (recipient.value.trim() === number) output.textContent = result.success ? 'Recipient: ' + result.name : result.error;
    });
    const createDialog = document.getElementById('create-account-modal');
    window.showCreateAccount = () => createDialog.showModal();
    window.hideCreateAccount = () => createDialog.close();
    const createForm = document.getElementById('create-account-form');
    createForm?.addEventListener('submit', event => {
        event.preventDefault();
        withBusy(createForm, async () => {
            const result = await API.post('api/create-account.php', Object.fromEntries(new FormData(createForm)));
            showMessage('create-account-alert', result.error || result.success, !result.success);
            if (result.success) { createForm.dataset.completed = 'true'; setTimeout(() => location.reload(), 850); }
        });
    });
});
