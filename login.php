<?php
// login.php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin.php' : 'dashboard.php'));
    exit();
}
require_once __DIR__ . '/views/layout/header.php';
?>
<div class="card max-w-sm">
    <p class="eyebrow">WELCOME BACK</p>
    <h2 class="card-title text-center">Customer sign in</h2>
    <p class="auth-description">Sign in to see your accounts and pick up where you left off.</p>
    <div id="login-alert" aria-live="polite"></div>
    <form id="login-form">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" maxlength="50" autocomplete="username" placeholder="Enter your username" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-control">
            <input type="password" id="password" name="password" maxlength="72" autocomplete="current-password" placeholder="••••••••" required>
                <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-label="Show password" aria-pressed="false"><svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg data-eye-closed viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" hidden><path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a20 20 0 0 1-3 3.8M6.2 6.2A23 23 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.6M10 10a3 3 0 0 0 4 4"/></svg></button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>
    <div class="text-center mt-4">
        <p style="color: var(--text-secondary); font-size: 0.9rem;">Don't have an account? <a href="register.php" style="color: var(--secondary-color); font-weight: 600; text-decoration: none;">Register here</a></p>
    </div>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';
?>
