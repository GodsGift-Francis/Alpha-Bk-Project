<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin.php' : 'dashboard.php'));
    exit();
}
require_once __DIR__ . '/views/layout/header.php';
?>
<div class="card max-w-sm" >
    <p class="eyebrow">AUTHORIZED ACCESS</p>
    <h2 class="card-title text-center">Administrator sign in</h2>
    <p class="auth-description">Sign in to your operations workspace.</p>
    <p class="form-note">Manage account reviews, transaction approvals and bank operations. Access is restricted to authorized administrators.</p>
    <div id="admin-login-alert" aria-live="polite"></div>
    <form id="admin-login-form">
        <div class="form-group">
            <label for="admin-username">Administrator username</label>
            <input type="text" id="admin-username" name="username" maxlength="50" autocomplete="username" required>
        </div>
        <div class="form-group">
            <label for="admin-password">Password</label>
            <input type="password" id="admin-password" name="password" maxlength="72" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in to administration</button>
    </form>
</div>
<?php require_once __DIR__ . '/views/layout/footer.php'; ?>
