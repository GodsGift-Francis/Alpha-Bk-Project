<?php
require_once __DIR__ . '/../../includes/auth.php';
$logged_in = isLoggedIn();
$current_page = basename($_SERVER['PHP_SELF']);
$admin_view = $logged_in && isAdmin();
$auth_page = in_array($current_page, ['login.php', 'register.php', 'admin-login.php'], true);
$public_home = $current_page === 'index.php';
preg_match('/\S/u', $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'A', $initialMatch);
$profileInitial = strtoupper($initialMatch[0] ?? 'A');
$view_mode = $logged_in ? ($admin_view ? 'mode-admin' : 'mode-customer') : 'mode-public';
function uiIcon(string $name): string {
    $paths = [
        'home' => 'M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z',
        'deposit' => 'M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4',
        'withdraw' => 'M12 16V4m-5 5 5-5 5 5M4 17v4h16v-4',
        'transfer' => 'M3 7h17m-5-5 5 5-5 5M21 17H4m5-5-5 5 5 5',
        'settings' => 'M4 6h16M4 12h16M4 18h16M8 3v6m8 0v6M9 15v6',
        'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
        'shield' => 'M12 3 3 7v5c0 5 9 9 9 9s9-4 9-9V7ZM8 12l3 3 5-6',
        'arrow' => 'M4 12h16m-6-6 6 6-6 6',
        'receipt' => 'M5 3h14v19l-4-2-3 2-3-2-4 2ZM9 8h6M9 12h6',
    ];
    $path = $paths[$name] ?? $paths['home'];
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $path . '"/></svg>';
}
function uiText($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$titles = ['dashboard.php'=>'Your overview', 'admin.php'=>'Operations overview', 'deposit.php'=>'Deposit request', 'withdraw.php'=>'Withdrawal request', 'transfer.php'=>'Transfer funds', 'settings.php'=>'Preferences', 'invoice.php'=>'Transaction invoice'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#123e35">
    <title><?php echo uiText($titles[$current_page] ?? ($current_page === 'index.php' ? 'Banking, thoughtfully simple' : 'Welcome')); ?> | AlphaBank</title>
    <meta name="csrf-token" content="<?php echo getCsrfToken(); ?>">
    <script>const CURRENCY_SYMBOL = <?php echo json_encode($logged_in ? getCurrencySymbol() : '$'); ?>;</script>
    <link rel="icon" type="image/svg+xml" sizes="any" href="assets/favicon.svg?v=2">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/design.css">
</head>
<body class="<?php echo $view_mode . ($auth_page ? ' auth-page' : '') . ($public_home ? ' home-page' : ''); ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <div class="container">
        <a class="brand" href="<?php echo $logged_in ? ($admin_view ? 'admin.php' : 'dashboard.php') : 'index.php'; ?>" aria-label="AlphaBank home"><span class="brand-mark" aria-hidden="true">A<span></span></span><span>AlphaBank<span class="brand-period">.</span></span></a>
        <?php if ($logged_in || $current_page !== 'admin-login.php'): ?><button class="nav-toggle" type="button" aria-controls="primary-nav" aria-expanded="false">Menu</button><?php endif; ?>
        <nav id="primary-nav" aria-label="Main navigation">
        <?php if ($logged_in): ?>
            <span class="nav-group-label"><?php echo $admin_view ? 'WORKSPACE' : 'YOUR BANKING'; ?></span>
            <?php $links = $admin_view ? [['admin.php','home','Overview'],['admin.php#pending-transactions','transfer','Transaction reviews'],['admin.php#pending-accounts','shield','Account requests'],['admin.php#clients','home','Clients'],['admin.php#transaction-audit','receipt','Transaction records']] : [['dashboard.php','home','Overview'],['deposit.php','deposit','Deposit'],['withdraw.php','withdraw','Withdraw'],['transfer.php','transfer','Transfer']]; ?>
            <?php foreach ($links as [$href,$icon,$label]): ?><a href="<?php echo $href; ?>" class="<?php echo $href === $current_page ? 'active' : ''; ?>" <?php echo $href === $current_page ? 'aria-current="page"' : ''; ?>><?php echo uiIcon($icon); ?><span><?php echo $label; ?></span></a><?php endforeach; ?>
            <span class="nav-group-label nav-secondary">ACCOUNT</span>
            <a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>"><?php echo uiIcon('settings'); ?><span>Preferences</span></a>
            <?php if (!$admin_view): ?>
            <div class="notification-wrapper" id="notification-trigger">
                <button type="button" class="notification-bell-btn" aria-label="Notifications" aria-expanded="false" aria-controls="notification-dropdown"><?php echo uiIcon('bell'); ?><span>Notifications</span></button><span class="notification-badge" id="nav-badge">0</span>
                <div class="notification-dropdown" id="notification-dropdown"><div class="notification-header">Notifications <button type="button" id="mark-read" class="text-button">Mark all as read</button></div><div class="notification-list" id="notification-list"><div class="empty-notifications">Loading notificationsâ€¦</div></div></div>
            </div>
            <?php endif; ?>
            <div class="sidebar-note"><?php echo uiIcon('shield'); ?><p><?php echo $admin_view ? 'Every decision.<br>A clear audit trail.' : 'Your money.<br>A clearer perspective.'; ?></p></div>
            <form action="logout.php" method="post" class="logout-form"><input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>"><button type="submit" class="btn-logout">Sign out <span aria-hidden="true">â†—</span></button></form>
        <?php elseif ($current_page !== 'admin-login.php'): ?>
            <a href="index.php#accounts">Our accounts</a><a href="index.php#how-it-works">How it works</a><a href="login.php" class="<?php echo $current_page === 'login.php' ? 'active' : ''; ?>">Sign in</a><a href="register.php" class="nav-cta">Open an account <?php echo uiIcon('arrow'); ?></a>
        <?php endif; ?>
        </nav>
    </div>
</header>
<?php if ($logged_in): ?>
<div class="workspace-bar"><span><?php echo $admin_view ? 'ALPHABANK / OPERATIONS' : 'PERSONAL BANKING / ' . strtoupper($titles[$current_page] ?? 'HOME'); ?></span><div><span class="workspace-role"><?php echo $admin_view ? 'Administrator' : 'Personal account'; ?></span><span class="avatar"><?php echo uiText($profileInitial); ?></span></div></div>
<?php endif; ?>
<main class="container" id="main-content">
<?php if ($auth_page): ?>
<section class="auth-story"><p class="eyebrow"><?php echo $current_page === 'admin-login.php' ? 'OPERATIONS WORKSPACE' : 'A CLEARER WAY TO BANK'; ?></p><h1><?php echo $current_page === 'admin-login.php' ? 'Clarity behind<br>every decision.' : ($current_page === 'register.php' ? 'A fresh start.<br>A clearer view.' : 'Your next chapter<br>starts here.'); ?></h1><p><?php echo $current_page === 'admin-login.php' ? 'A focused workspace for account reviews, transaction decisions and client oversight.' : 'A thoughtful space for your everyday finances. Your accounts, requests and transaction records, all together.'; ?></p><div class="auth-emblem" aria-hidden="true"><span>A</span></div><div class="auth-assurance"><?php echo uiIcon('shield'); ?><span><?php echo $current_page === 'admin-login.php' ? 'Authorized administrators only' : 'Protected sign-in. Clear transaction records.'; ?></span></div></section>
<?php elseif (in_array($current_page,['deposit.php','withdraw.php','transfer.php','settings.php'],true)): ?>
<div class="task-heading"><p class="eyebrow"><?php echo $current_page === 'settings.php' ? 'MAKE IT YOURS' : 'YOUR MONEY, IN MOTION'; ?></p><h1><?php echo $current_page === 'settings.php' ? 'The details that matter.' : 'Make your next move.'; ?></h1><p>Simple steps. A clear record. Always in your control.</p></div>
<?php endif; ?>
