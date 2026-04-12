<?php
// dashboard.php
require_once __DIR__ . '/views/layout/header.php';
requireLogin();
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
<div id="create-account-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card max-w-sm" style="margin:0;">
        <h3 class="card-title">Open New Account</h3>
        <form id="create-account-form">
            <div class="form-group">
                <label>Account Type</label>
                <select name="account_type" required>
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
</div>

<h2 class="card-title" style="margin-top: 3rem;">Recent Transactions</h2>
<div id="history-content">
    <div class="card"><p>Loading transaction history...</p></div>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';
?>
