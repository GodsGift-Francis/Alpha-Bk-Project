<?php
// login.php
require_once __DIR__ . '/views/layout/header.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<div class="card max-w-sm">
    <h2 class="card-title text-center">Login to AlphaBank</h2>
    <div id="login-alert"></div>
    <form id="login-form">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Enter your username" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
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
