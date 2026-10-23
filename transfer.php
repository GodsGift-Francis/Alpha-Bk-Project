<?php
// transfer.php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = require __DIR__ . '/config/database.php';
$stmt = $db->prepare("SELECT account_number, balance FROM accounts WHERE user_id = ? AND status = 'APPROVED'");
$stmt->execute([$_SESSION['user_id']]);
$accounts = $stmt->fetchAll();
require_once __DIR__ . '/views/layout/header.php';
?>
<div class="card max-w-md">
    <h2 class="card-title">Transfer Funds</h2>
    <div id="transfer-alert" aria-live="polite"></div>
    <p class="form-note">Requests require administrator approval. Your balance changes only after approval.</p>
    <form id="transfer-form">
        <input type="hidden" name="action" value="TRANSFER">
        <?php if (!$accounts): ?>
        <div class="alert alert-error">You need an approved account to submit a request. <a href="dashboard.php">View accounts</a></div>
        <?php endif; ?>
        <fieldset <?php echo !$accounts ? 'disabled' : ''; ?>>
        <div class="form-group">
            <label for="account_number">From Account</label>
            <select name="account_number" id="account_number" required>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?php echo htmlspecialchars($acc['account_number']); ?>">
                        <?php echo htmlspecialchars($acc['account_number']); ?> (Balance: <?php echo getCurrencySymbol(); ?><?php echo number_format($acc['balance'], 2); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="recipient_account">Recipient Account Number</label>
            <div style="display:flex; gap:0.5rem;">
                <input type="text" id="recipient_account" name="recipient_account" placeholder="ACC-XXXXXXXXXX" required>
                <button type="button" id="verify-acc-btn" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8rem;">Verify</button>
            </div>
            <p role="status" id="verify-result" class="text-sm mt-2" style="font-weight:600;"></p>
        </div>
        <div class="form-group">
            <label for="amount">Transfer Amount (<?php echo getCurrencySymbol(); ?>)</label>
            <input type="number" step="0.01" id="amount" name="amount" min="0.01" placeholder="0.00" max="9999999999999.99" required>
        </div>
        <div class="form-group">
            <label for="description">Description (Optional)</label>
            <input type="text" id="description" name="description" maxlength="500" placeholder="e.g. Rent, Split bill">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Submit Transfer Request</button>
        </fieldset>
    </form>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';
?>
