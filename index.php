<?php
// index.php
require_once __DIR__ . '/views/layout/header.php';
?>
<div class="card text-center hero">
    <h2 class="hero-title">Welcome to AlphaBank</h2>
    <p class="hero-subtitle">The future of digital banking is here. Simple, secure, and built for you.</p>
    
    <?php if (isLoggedIn()): ?>
        <p class="mb-8">You are logged in as <strong class="font-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></strong>.</p>
        <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
    <?php else: ?>
        <p class="mb-8 text-secondary">Join thousands of users who trust AlphaBank with their finances.</p>
        <div class="hero-actions">
            <a href="login.php" class="btn btn-primary">Login to Account</a>
            <a href="register.php" class="btn btn-secondary">Create Free Account</a>
        </div>
    <?php endif; ?>
</div>

<div class="accounts-grid">
    <div class="card">
        <h3 class="card-title">Secure by Design</h3>
        <p>Your security is our top priority. We use industry-standard encryption and CSRF protection to keep your data safe.</p>
    </div>
    <div class="card">
        <h3 class="card-title">Instant Transfers</h3>
        <p>Move money between accounts or to other AlphaBank users instantly with zero hidden fees.</p>
    </div>
    <div class="card">
        <h3 class="card-title">Real-time History</h3>
        <p>Keep track of every transaction with our real-time history and detailed account statements.</p>
    </div>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';
?>
