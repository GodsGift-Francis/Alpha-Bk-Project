<?php
// Run only from a trusted server console; never expose administrator setup on the web.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$username = $argv[1] ?? '';
$email = $argv[2] ?? '';
$name = $argv[3] ?? '';
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/D', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || trim($name) === '' || strlen($name) > 100) {
    fwrite(STDERR, "Usage: php scripts/provision-admin.php USERNAME EMAIL \"Full Name\"\nSet ALPHABANK_ADMIN_PASSWORD in the process environment first.\n");
    exit(1);
}
$password = getenv('ALPHABANK_ADMIN_PASSWORD');
if (!is_string($password) || strlen($password) < 16 || strlen($password) > 72) {
    fwrite(STDERR, "ALPHABANK_ADMIN_PASSWORD must contain 16 to 72 bytes.\n");
    exit(1);
}
try {
    $db = require __DIR__ . '/../config/database.php';
    // Insert only: never silently promote an unrelated user or replace a password.
    $db->prepare('INSERT INTO users (username, email, full_name, password_hash, is_admin) VALUES (?, ?, ?, ?, 1)')
        ->execute([$username, $email, trim($name), password_hash($password, PASSWORD_DEFAULT)]);
    echo "Administrator created. Sign in at admin-login.php.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Administrator was not created. Check database configuration and whether the username/email already exists.\n");
    exit(1);
}
