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
const Feedback = {
    show(title, detail) {
        let overlay = document.getElementById('action-progress');
        if (!overlay) {
            overlay = document.createElement('div'); overlay.id = 'action-progress'; overlay.className = 'action-progress';
            overlay.innerHTML = '<div class="progress-panel" role="status" aria-live="polite" aria-atomic="true"><img src="assets/brand-mark.svg" alt="" width="48" height="48"><span class="progress-spinner" aria-hidden="true"></span><h2></h2><p></p><div class="progress-track" aria-hidden="true"><span></span></div></div>';
            document.body.append(overlay);
        }
        overlay.querySelector('h2').textContent = title; overlay.querySelector('p').textContent = detail; overlay.hidden = false;
    },
    hide() { const overlay = document.getElementById('action-progress'); if (overlay) overlay.hidden = true; }
};
function showRequestReceipt(kind, data, result) {
    const container = document.getElementById(`${kind}-alert`);
    const label = {deposit:'Deposit', withdraw:'Withdrawal', transfer:'Transfer'}[kind];
    const pending = result.status === 'PENDING' || (!result.status && !result.replayed);
    const card = document.createElement('section'); card.className = 'request-receipt'; card.tabIndex = -1;
    card.setAttribute('role','status'); card.setAttribute('aria-label',`${label} request received`);
    card.innerHTML = '<div class="receipt-symbol" aria-hidden="true">✓</div><p class="eyebrow">REQUEST RECEIVED</p><h3></h3><p class="receipt-copy"></p><dl><div><dt>Reference</dt><dd data-reference></dd></div><div><dt>Amount</dt><dd data-amount></dd></div><div><dt>Account</dt><dd data-account></dd></div></dl><div class="review-banner"><span class="review-dot" aria-hidden="true"></span><div><strong></strong><p></p></div></div><div class="receipt-actions"><a class="btn btn-primary" data-invoice>View invoice <span aria-hidden="true">↗</span></a><a href="dashboard.php" class="btn btn-secondary">Back to overview</a></div>';
    card.querySelector('h3').textContent = pending ? `${label} request submitted.` : 'This request was already received.';
    card.querySelector('.receipt-copy').textContent = pending ? 'Your request is with our team. You can follow its status on your invoice.' : 'No duplicate request was created. Open the invoice for the current review decision.';
    card.querySelector('[data-reference]').textContent = result.reference || `TX-${result.id}`;
    card.querySelector('[data-amount]').textContent = CURRENCY_SYMBOL + displayAmount(data.amount);
    card.querySelector('[data-account]').textContent = maskAccountNumber(data.account_number);
    card.querySelector('.review-banner strong').textContent = pending ? 'Awaiting administrator review' : 'Review details available';
    card.querySelector('.review-banner p').textContent = pending ? 'Your balance changes only after approval. We’ll notify you when a decision is made.' : 'The invoice shows the latest status and any review notes.';
    const invoice = card.querySelector('[data-invoice]');
    if (/^\d+$/.test(String(result.id))) invoice.href = 'invoice.php?id=' + encodeURIComponent(result.id);
    else invoice.remove();
    container.replaceChildren(card); card.focus({preventScroll:true}); card.scrollIntoView({behavior:'auto',block:'nearest'});
}
async function withBusy(form, action) {
    if (form.dataset.busy === 'true' || form.dataset.completed === 'true') return;
    form.dataset.busy = 'true';
    form.setAttribute('aria-busy', 'true');
    const buttons = [...form.querySelectorAll('[type="submit"]')];
    const originals = buttons.map(button => [...button.childNodes].map(node => node.cloneNode(true)));
    const label = {'login-form':'Signing in', 'admin-login-form':'Signing in', 'register-form':'Creating profile', 'deposit-form':'Preparing request', 'withdraw-form':'Preparing request', 'transfer-form':'Preparing request'}[form.id] || 'Saving changes';
    buttons.forEach(button => { button.disabled = true; button.classList.add('button-busy'); button.innerHTML = '<span class="button-spinner" aria-hidden="true"></span><span class="button-busy-label"></span>'; button.querySelector('.button-busy-label').textContent = label; });
    try { await action(); }
    finally {
        form.dataset.busy = 'false';
        form.removeAttribute('aria-busy');
        buttons.forEach((button,index) => { button.classList.remove('button-busy'); button.replaceChildren(...originals[index]); if (form.dataset.completed !== 'true') button.disabled = false; });
    }
}
function reviewRequest(data) {
    return new Promise(resolve => {
        const dialog = document.createElement('dialog');
        dialog.setAttribute('aria-labelledby', 'request-review-title');
        dialog.className = 'request-review';
        dialog.innerHTML = '<div class="card"><p class="eyebrow">CHECK THE DETAILS</p><h3 class="card-title" id="request-review-title">Review your request</h3><dl class="review-details"></dl><p class="form-note">An administrator must approve this request before your balance changes.</p><div class="row-actions"><button type="button" class="btn btn-primary" data-confirm>Submit request</button><button type="button" class="btn btn-secondary" data-cancel>Go back</button></div></div>';
        dialog.querySelector('h3').textContent = {DEPOSIT:'Review your deposit',WITHDRAWAL:'Review your withdrawal',TRANSFER:'Review your transfer'}[data.action] || 'Review your request';
        const details = dialog.querySelector('dl');
        const values = [['Type', {DEPOSIT:'Deposit',WITHDRAWAL:'Withdrawal',TRANSFER:'Transfer'}[data.action] || data.action], [data.action === 'TRANSFER' ? 'From account' : 'Account', data.account_number], ['Amount', CURRENCY_SYMBOL + displayAmount(data.amount)]];
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
        ['admin-login', 'api/admin-login.php', () => 'admin.php'],

    ]) {
        const form = document.getElementById(`${id}-form`);
        form?.addEventListener('submit', event => {
            event.preventDefault();
            withBusy(form, async () => {
                const signingIn = id === 'login' || id === 'admin-login';
                if (signingIn) Feedback.show('Signing you in', 'Opening your Crestivale workspace.');
                let result;
                try { result = await API.post(endpoint, Object.fromEntries(new FormData(form))); }
                catch (_) { result = {error:'Unable to sign in. Please try again.'}; }
                if (result.success) {
                    form.dataset.completed = 'true';
                    if (signingIn) { Feedback.show('Welcome back', 'Your workspace is ready.'); location.assign(destination(result)); }
                    else { showMessage(`${id}-alert`, result.success); location.assign(destination(result)); }
                } else { Feedback.hide(); showMessage(`${id}-alert`, result.error, true); }
            });
        });
    }
    document.querySelectorAll('.logout-form').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.busy === 'true') { event.preventDefault(); return; }
        form.dataset.busy = 'true'; form.setAttribute('aria-busy','true');
        Feedback.show('Signing you out', 'Closing your Crestivale session.');
    }));
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
        dashboard.innerHTML = `<div class="accounts-grid">${result.accounts.map(account => `<article class="card account-card"><div class="section-heading"><h3><span class="account-glyph" aria-hidden="true">↗</span>${escapeHTML(account.account_type)} Account</h3>${account.status === 'APPROVED' ? '' : `<span class="badge">${escapeHTML(account.status)}</span>`}</div><p class="balance-caption">Account balance</p><p class="balance">${escapeHTML(CURRENCY_SYMBOL + displayAmount(account.balance))}</p>${accountControl(account.account_number)}${account.status === 'PENDING' ? '<p class="form-note">Awaiting administrator approval.</p>' : ''}${account.review_reason ? `<p class="form-note">Review: ${escapeHTML(account.review_reason)}</p>` : ''}</article>`).join('')}</div>`;
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
        history.innerHTML = '<ol class="transaction-history-list" aria-label="Transaction history">' + result.history.map(tx => {
            const id = escapeHTML(tx.id);
            const type = tx.transaction_type.replaceAll('_', ' ');
            const badgeType = tx.transaction_type.toLowerCase();
            return '<li><article class="card transaction-history-item"><div class="transaction-history-topline"><div class="transaction-history-identity"><strong class="transaction-reference">TX-' + id + '</strong><span class="text-secondary text-sm">' + escapeHTML(displayDate(tx.timestamp)) + '</span></div><span class="badge badge-' + escapeHTML(badgeType) + ' transaction-history-type">' + escapeHTML(type) + '</span><strong class="transaction-history-amount">' + escapeHTML(CURRENCY_SYMBOL + displayAmount(tx.amount)) + '</strong></div><dl class="transaction-history-meta"><div><dt>Account</dt><dd>' + accountControl(tx.account_number) + '</dd></div><div><dt>Details</dt><dd>' + escapeHTML(tx.description || '—') + '</dd></div><div><dt>Invoice</dt><dd><a class="btn btn-secondary btn-small" href="invoice.php?id=' + encodeURIComponent(tx.id) + '" aria-label="View invoice TX-' + id + '">View invoice</a></dd></div></dl></article></li>';
        }).join('') + '</ol><div class="pagination"><button type="button" class="btn btn-secondary" data-prev>Previous</button><span>Page ' + result.pagination.page + ' of ' + result.pagination.pages + ' · ' + result.pagination.total + ' records</span><button type="button" class="btn btn-secondary" data-next>Next</button></div>';
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
                form.querySelectorAll('.button-busy-label').forEach(label => { label.textContent = 'Submitting request'; });
                const result = await API.post('api/transactions.php', { ...data, request_key: key });
                if (!result.success) showMessage(`${kind}-alert`, result.error, true);
                if (result.success) {
                    form.dataset.completed = 'true';
                    try { sessionStorage.removeItem(storageKey); } catch (_) { /* No action needed. */ }
                    form.hidden = true;
                    if (form.previousElementSibling?.matches('.form-note')) form.previousElementSibling.hidden = true;
                    showRequestReceipt(kind, data, result);
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

window.addEventListener('pageshow', () => {
    Feedback.hide();
    document.querySelectorAll('.logout-form').forEach(form => { delete form.dataset.busy; form.removeAttribute('aria-busy'); });
    document.querySelectorAll('#login-form, #admin-login-form').forEach(form => { delete form.dataset.completed; form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = false; }); });
});
