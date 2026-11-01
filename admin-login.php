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
            <div class="password-control">
            <input type="password" id="admin-password" name="password" maxlength="72" autocomplete="current-password" required>
                <button type="button" class="password-toggle" data-password-toggle aria-controls="admin-password" aria-label="Show password" aria-pressed="false"><svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg data-eye-closed viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" hidden><path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a20 20 0 0 1-3 3.8M6.2 6.2A23 23 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.6M10 10a3 3 0 0 0 4 4"/></svg></button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in to administration</button>
    </form>
</div>
<?php require_once __DIR__ . '/views/layout/footer.php'; ?>
