<?php
// dashboard.php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) { header('Location: admin.php'); exit; }
require_once __DIR__ . '/views/layout/header.php';
?>
<div class="dashboard-header">
    <div>
        <h2 class="hero-title" style="font-size: 1.875rem;">Financial Overview</h2>
        <p class="text-secondary">Welcome back, <strong class="font-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button onclick="showCreateAccount()" class="btn btn-secondary">Open New Account</button>
        <a href="transfer.php" class="btn btn-primary">Transfer Funds</a>
    </div>
</div>

<div id="dashboard-content">
    <div class="card"><p>Loading your accounts...</p></div>
</div>

<!-- Modal for Create Account (Hidden by default) -->
<dialog id="create-account-modal" aria-labelledby="create-account-title">
    <div class="card max-w-sm" style="margin:0;">
        <h3 class="card-title" id="create-account-title">Open New Account</h3>
        <div id="create-account-alert" aria-live="polite"></div>
        <form id="create-account-form">
            <div class="form-group">
                <label for="account-type">Account Type</label>
                <select id="account-type" name="account_type" required>
                    <option value="Checking">Checking Account</option>
                    <option value="Savings">Savings Account</option>
                    <option value="Business">Business Account</option>
                </select>
            </div>
            <div style="display:flex; gap:1rem;">
                <button type="submit" class="btn btn-primary btn-block">Create</button>
                <button type="button" onclick="hideCreateAccount()" class="btn btn-secondary btn-block">Cancel</button>
            </div>
        </form>
    </div>
</dialog>

<div class="section-heading"><h2 class="card-title">Transaction History</h2><span class="text-secondary text-sm">Open an invoice for status and review details</span></div>
<form id="history-filters" class="filter-bar" role="search" aria-label="Filter transaction history">
    <div><label for="history-query">Search</label><input type="search" id="history-query" name="q" maxlength="100" placeholder="Reference, account or description"></div>
    <div><label for="history-type">Type</label><select id="history-type" name="type"><option value="">All types</option><option value="DEPOSIT">Deposit</option><option value="WITHDRAWAL">Withdrawal</option><option value="TRANSFER_OUT">Transfer out</option><option value="TRANSFER_IN">Transfer in</option></select></div>
    <div><label for="history-from">From</label><input type="date" id="history-from" name="from"></div>
    <div><label for="history-to">To</label><input type="date" id="history-to" name="to"></div>
    <button class="btn btn-primary" type="submit">Apply filters</button>
    <button class="btn btn-secondary" type="reset">Reset</button>
</form>
<div id="history-content" aria-live="polite">
    <div class="card"><p>Loading transaction history...</p></div>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';
?>
