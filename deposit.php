<?php
// deposit.php
require_once __DIR__ . '/views/layout/header.php';
requireLogin();

$db = require __DIR__ . '/config/database.php';
$stmt = $db->prepare("SELECT account_number, balance FROM accounts WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$accounts = $stmt->fetchAll();
?>
<div class="card max-w-md">
    <h2 class="card-title">Make a Deposit</h2>
    <div id="deposit-alert"></div>
    <form id="deposit-form">
        <input type="hidden" name="action" value="DEPOSIT">
        <div class="form-group">
            <label for="account_number">Select Account</label>
            <select name="account_number" id="account_number" required>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?php echo htmlspecialchars($acc['account_number']); ?>">
                        <?php echo htmlspecialchars($acc['account_number']); ?> (Balance: <?php echo getCurrencySymbol(); ?><?php echo number_format($acc['balance'], 2); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="amount">Deposit Amount (<?php echo getCurrencySymbol(); ?>)</label>
            <input type="number" step="0.01" id="amount" name="amount" min="0.01" placeholder="0.00" required>
        </div>
        <div class="form-group">
            <label for="description">Description (Optional)</label>
            <input type="text" id="description" name="description" placeholder="e.g. Salary, Gift">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Confirm Deposit</button>
    </form>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';
?>
