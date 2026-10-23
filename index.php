<?php
// index.php
require_once __DIR__ . '/views/layout/header.php';
?>
<div class="card text-center hero">
    <h2 class="hero-title">Welcome to AlphaBank</h2>
    <p class="hero-subtitle">The future of digital banking is here. Simple, secure, and built for you.</p>
    
    <?php if (isLoggedIn()): ?>
        <p class="mb-8">You are logged in as <strong class="font-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></strong>.</p>
        <a href="<?php echo isAdmin() ? 'admin.php' : 'dashboard.php'; ?>" class="btn btn-primary">Go to Dashboard</a>
    <?php else: ?>
        <p class="mb-8 text-secondary">Manage your accounts and track every request in one place.</p>
        <div class="hero-actions">
            <a href="login.php" class="btn btn-primary">Login to Account</a>
            <a href="register.php" class="btn btn-secondary">Create Free Account</a>
        </div>
    <?php endif; ?>
</div>

<div class="accounts-grid">
    <div class="card">
        <h3 class="card-title">Secure by Design</h3>
        <p>Your security is our top priority. Protected sign-in, request validation, and administrator review support your account workflows.</p>
    </div>
    <div class="card">
        <h3 class="card-title">Transfers with Oversight</h3>
        <p>Request transfers between your accounts or to other AlphaBank users. An administrator reviews each request before funds move.</p>
    </div>
    <div class="card">
        <h3 class="card-title">Clear Transaction History</h3>
        <p>Track submitted, approved, and rejected transactions with searchable history and review details.</p>
    </div>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';
?>
