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
    <h2 class="card-title text-center">Customer sign in</h2>
    <div id="login-alert" aria-live="polite"></div>
    <form id="login-form">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" maxlength="50" autocomplete="username" placeholder="Enter your username" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="72" autocomplete="current-password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>
    <p class="text-center mt-4"><a href="admin-login.php">Administrator sign in</a></p>
    <div class="text-center mt-4">
        <p style="color: var(--text-secondary); font-size: 0.9rem;">Don't have an account? <a href="register.php" style="color: var(--secondary-color); font-weight: 600; text-decoration: none;">Register here</a></p>
    </div>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';
?>
