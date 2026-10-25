<?php
// register.php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin.php' : 'dashboard.php'));
    exit();
}
require_once __DIR__ . '/views/layout/header.php';
?>
<div class="card max-w-md">
    <p class="eyebrow">MAKE ROOM FOR WHAT'S NEXT</p>
    <h2 class="card-title text-center">Create your AlphaBank account</h2>
    <p class="auth-description">Create your profile to begin your account request.</p>
    <div id="register-alert" aria-live="polite"></div>
    <form id="register-form">
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" maxlength="100" autocomplete="name" placeholder="John Doe" required>
        </div>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" maxlength="50" autocomplete="username" placeholder="johndoe123" required>
        </div>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" maxlength="100" autocomplete="email" placeholder="john@example.com" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="72" autocomplete="new-password" minlength="12" placeholder="At least 12 characters" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
    </form>
    <div class="text-center mt-4">
        <p style="color: var(--text-secondary); font-size: 0.9rem;">Already have an account? <a href="login.php" style="color: var(--secondary-color); font-weight: 600; text-decoration: none;">Login here</a></p>
    </div>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';
?>
