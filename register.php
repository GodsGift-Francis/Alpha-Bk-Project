<?php
// register.php
require_once __DIR__ . '/views/layout/header.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<div class="card max-w-md">
    <h2 class="card-title text-center">Create your AlphaBank account</h2>
    <div id="register-alert"></div>
    <form id="register-form">
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" placeholder="John Doe" required>
        </div>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="johndoe123" required>
        </div>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="john@example.com" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Min. 8 characters" required>
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
