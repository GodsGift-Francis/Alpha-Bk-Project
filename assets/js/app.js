// assets/js/app.js
const escapeHTML = (str) => {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
};

const maskAccountNumber = (accNum) => {
    if (!accNum) return '';
    const visibleDigits = 4;
    const maskedPart = accNum.slice(0, -visibleDigits).replace(/[a-zA-Z0-9]/g, '*');
    const visiblePart = accNum.slice(-visibleDigits);
    return maskedPart + visiblePart;
};

const EYE_OPEN_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
const EYE_CLOSED_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;

window.toggleAccountNumber = (btn) => {
    const container = btn.closest('.account-number-container');
    const span = container.querySelector('.account-number');
    const isMasked = span.getAttribute('data-masked') === 'true';
    const fullNumber = span.getAttribute('data-full');
    
    if (isMasked) {
        span.textContent = fullNumber;
        span.setAttribute('data-masked', 'false');
        btn.innerHTML = EYE_CLOSED_SVG;
        btn.title = 'Hide account number';
    } else {
        span.textContent = maskAccountNumber(fullNumber);
        span.setAttribute('data-masked', 'true');
        btn.innerHTML = EYE_OPEN_SVG;
        btn.title = 'Show account number';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // Registration Form
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(registerForm);
            const data = Object.fromEntries(formData.entries());
            
            const result = await API.post('api/register.php', data);
            const alertDiv = document.getElementById('register-alert');
            
            if (result.success) {
                alertDiv.innerHTML = `<div class="alert alert-success">${result.success}. Redirecting to login...</div>`;
                setTimeout(() => window.location.href = 'login.php', 2000);
            } else {
                alertDiv.innerHTML = `<div class="alert alert-error">${result.error}</div>`;
            }
        });
    }

    // Login Form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(loginForm);
            const data = Object.fromEntries(formData.entries());
            
            const result = await API.post('api/login.php', data);
            const alertDiv = document.getElementById('login-alert');
            
            if (result.success) {
                alertDiv.innerHTML = `<div class="alert alert-success">${result.success}. Redirecting...</div>`;
                setTimeout(() => window.location.href = 'dashboard.php', 1000);
            } else {
                alertDiv.innerHTML = `<div class="alert alert-error">${result.error}</div>`;
            }
        });
    }

    // Dashboard Account Loading
    const dashboardContent = document.getElementById('dashboard-content');
    if (dashboardContent) {
        (async () => {
            const result = await API.get('api/accounts.php');
            if (result.accounts && result.accounts.length > 0) {
                let html = '<div class="accounts-grid">';
                result.accounts.forEach(acc => {
                    const statusBadge = acc.status === 'APPROVED' ? '' : `<span class="badge ${acc.status === 'PENDING' ? 'badge-transfer_out' : 'badge-withdrawal'}" style="margin-left: 10px;">${acc.status}</span>`;
                    html += `
                        <div class="card account-card">
                            <h3>${escapeHTML(acc.account_type)} Account ${statusBadge}</h3>
                            <p class="balance">${CURRENCY_SYMBOL}${parseFloat(acc.balance).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                            <div class="account-number-container">
                                <span class="account-number" data-full="${escapeHTML(acc.account_number)}" data-masked="true">${maskAccountNumber(acc.account_number)}</span>
                                <button class="toggle-account-btn" onclick="toggleAccountNumber(this)" title="Show account number">${EYE_OPEN_SVG}</button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                dashboardContent.innerHTML = html;
            } else {
                dashboardContent.innerHTML = '<div class="card text-center"><p>No accounts found.</p></div>';
            }
        })();
    }

    // Dashboard History Loading
    const historyContent = document.getElementById('history-content');
    if (historyContent) {
        (async () => {
            const result = await API.get('api/history.php');
            if (result.history && result.history.length > 0) {
                let html = `
                    <div class="table-container">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Account</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                result.history.forEach(tx => {
                    const date = new Date(tx.timestamp).toLocaleDateString('en-US', {
                        month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
                    });
                    const isCredit = (tx.transaction_type === 'DEPOSIT' || tx.transaction_type === 'TRANSFER_IN');
                    const amountClass = isCredit ? 'text-success' : 'text-error';
                    const prefix = isCredit ? '+' : '-';
                    
                    const statusClass = tx.status === 'APPROVED' ? 'badge-deposit' : (tx.status === 'PENDING' ? 'badge-transfer_out' : 'badge-withdrawal');
                    
                    html += `
                        <tr>
                            <td>${date}</td>
                            <td>
                                <div class="account-number-container">
                                    <span class="account-number" data-full="${escapeHTML(tx.account_number)}" data-masked="true" style="font-size: 0.85rem;">${maskAccountNumber(tx.account_number)}</span>
                                    <button class="toggle-account-btn" onclick="toggleAccountNumber(this)" title="Show account number">${EYE_OPEN_SVG}</button>
                                </div>
                            </td>
                            <td><span class="badge badge-${tx.transaction_type.toLowerCase()}">${escapeHTML(tx.transaction_type.replace('_', ' '))}</span></td>
                            <td class="${amountClass}" style="font-weight: 600;">${prefix}${CURRENCY_SYMBOL}${parseFloat(tx.amount).toFixed(2)}</td>
                            <td><span class="badge ${statusClass}">${tx.status}</span></td>
                            <td style="color: var(--text-secondary);">${escapeHTML(tx.description) || '<span style="color: #cbd5e1">—</span>'}</td>
                        </tr>
                    `;
                });
                html += '</tbody></table></div>';
                historyContent.innerHTML = html;
            } else {
                historyContent.innerHTML = '<div class="card text-center"><p style="color: var(--text-secondary);">No transaction history available yet.</p></div>';
            }
        })();
    }

    // Deposit Form
    const depositForm = document.getElementById('deposit-form');
    if (depositForm) {
        depositForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(depositForm);
            const data = Object.fromEntries(formData.entries());
            
            const result = await API.post('api/transactions.php', data);
            const alertDiv = document.getElementById('deposit-alert');
            
            if (result.success) {
                alertDiv.innerHTML = `<div class="alert alert-success">${result.success}! Redirecting to dashboard...</div>`;
                setTimeout(() => window.location.href = 'dashboard.php', 1500);
            } else {
                alertDiv.innerHTML = `<div class="alert alert-error">${result.error}</div>`;
            }
        });
    }

    // Withdraw Form
    const withdrawForm = document.getElementById('withdraw-form');
    if (withdrawForm) {
        withdrawForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(withdrawForm);
            const data = Object.fromEntries(formData.entries());
            
            const result = await API.post('api/transactions.php', data);
            const alertDiv = document.getElementById('withdraw-alert');
            
            if (result.success) {
                alertDiv.innerHTML = `<div class="alert alert-success">${result.success}! Redirecting to dashboard...</div>`;
                setTimeout(() => window.location.href = 'dashboard.php', 1500);
            } else {
                alertDiv.innerHTML = `<div class="alert alert-error">${result.error}</div>`;
            }
        });
    }

    // Transfer Form
    const transferForm = document.getElementById('transfer-form');
    if (transferForm) {
        const verifyBtn = document.getElementById('verify-acc-btn');
        const verifyResult = document.getElementById('verify-result');
        const recipientInput = document.getElementById('recipient_account');

        verifyBtn.addEventListener('click', async () => {
            const accNum = recipientInput.value;
            if (!accNum) return alert('Enter account number first');
            
            verifyResult.innerText = 'Checking...';
            verifyResult.className = 'text-sm mt-2';
            verifyResult.style.color = 'inherit';
            
            const result = await API.get(`api/verify-account.php?account_number=${accNum}`);
            if (result.success) {
                verifyResult.innerText = `Recipient: ${result.name} (Verified ✅)`;
                verifyResult.style.color = 'var(--accent-color)';
            } else {
                verifyResult.innerText = `❌ ${result.error}`;
                verifyResult.style.color = 'var(--danger-color)';
            }
        });

        transferForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(transferForm);
            const data = Object.fromEntries(formData.entries());
            
            const result = await API.post('api/transactions.php', data);
            const alertDiv = document.getElementById('transfer-alert');
            
            if (result.success) {
                alertDiv.innerHTML = `<div class="alert alert-success">${result.success}! Redirecting to dashboard...</div>`;
                setTimeout(() => window.location.href = 'dashboard.php', 1500);
            } else {
                alertDiv.innerHTML = `<div class="alert alert-error">${result.error}</div>`;
            }
        });
    }

    // Create Account Modal Logic
    window.showCreateAccount = () => {
        document.getElementById('create-account-modal').style.display = 'flex';
    };
    window.hideCreateAccount = () => {
        document.getElementById('create-account-modal').style.display = 'none';
    };

    const createAccForm = document.querySelector('#create-account-form');
    if (createAccForm) {
        createAccForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const account_type = createAccForm.querySelector('select[name="account_type"]').value;
            const result = await API.post('api/create-account.php', { account_type });
            if (result.success) {
                alert(result.success);
                window.location.reload();
            } else {
                alert(result.error);
            }
        });
    }
});
