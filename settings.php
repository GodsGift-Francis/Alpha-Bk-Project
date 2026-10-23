<?php
// settings.php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/views/layout/header.php';
?>

<div class="card max-w-md">
    <h2 class="card-title">User Settings</h2>
    <div id="settings-alert" aria-live="polite"></div>
    <form id="settings-form">
        <div class="form-group">
            <label for="currency">Display Currency Symbol</label>
            <p class="text-secondary text-sm mb-2">This preference changes the display symbol only. It does not convert balances or create multi-currency accounts.</p>
            <select name="currency" id="currency" required>
                <option value="USD" <?php echo $_SESSION['currency'] == 'USD' ? 'selected' : ''; ?>>USD ($) - US Dollar</option>
                <option value="EUR" <?php echo $_SESSION['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR (€) - Euro</option>
                <option value="GBP" <?php echo $_SESSION['currency'] == 'GBP' ? 'selected' : ''; ?>>GBP (£) - British Pound</option>
                <option value="NGN" <?php echo $_SESSION['currency'] == 'NGN' ? 'selected' : ''; ?>>NGN (₦) - Nigerian Naira</option>
                <option value="JPY" <?php echo $_SESSION['currency'] == 'JPY' ? 'selected' : ''; ?>>JPY (¥) - Japanese Yen</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Save Preferences</button>
    </form>
</div>



<?php require_once __DIR__ . '/views/layout/footer.php'; ?>
