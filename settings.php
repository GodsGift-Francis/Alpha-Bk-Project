<?php
// settings.php
require_once __DIR__ . '/views/layout/header.php';
requireLogin();
?>

<div class="card max-w-md">
    <h2 class="card-title">User Settings</h2>
    <div id="settings-alert"></div>
    <form id="settings-form">
        <div class="form-group">
            <label for="currency">Primary Currency</label>
            <p class="text-secondary text-sm mb-2">Select the currency symbol used across your dashboard.</p>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(settingsForm);
            const data = Object.fromEntries(formData.entries());
            
            const result = await API.post('api/settings.php', data);
            const alertDiv = document.getElementById('settings-alert');
            
            if (result.success) {
                alertDiv.innerHTML = `<div class="alert alert-success">${result.success}! Reloading...</div>`;
                setTimeout(() => window.location.reload(), 1500);
            } else {
                alertDiv.innerHTML = `<div class="alert alert-error">${result.error}</div>`;
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/views/layout/footer.php'; ?>
