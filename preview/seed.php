<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$database = $argv[1] ?? '';
if (!preg_match('/^alphabank_preview_[a-f0-9]{12}$/D', $database)) throw new RuntimeException('Use a uniquely named preview database.');
$password = getenv('PREVIEW_ACCOUNT_PASSWORD');
if (!$password || strlen($password) < 16) throw new RuntimeException('Set a strong test account password.');
$db = require __DIR__ . '/../config/database.php';
// CREATE without IF NOT EXISTS ensures existing data can never be overwritten.
$db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
try {
    $db->exec("USE `$database`");
    $schema = require __DIR__ . '/../database/schema.php'; $schema($db);
    require_once __DIR__ . '/../includes/migrations.php'; runMigrations($db);
    $stmt = $db->prepare('INSERT INTO users (id, username, email, password_hash, full_name, is_admin) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ([[1, 'preview_admin', 'Preview Administrator', 1], [2, 'preview_user', 'Alex Morgan', 0], [3, 'preview_recipient', 'Sam Taylor', 0]] as [$id, $username, $name, $admin]) {
        $stmt->execute([$id, $username, $username . '@example.test', password_hash($password, PASSWORD_DEFAULT), $name, $admin]);
    }
    $db->exec("INSERT INTO accounts (id, user_id, account_number, balance, account_type, status) VALUES (1, 2, 'ACC-1000000001', 0.00, 'Checking', 'APPROVED'), (2, 3, 'ACC-1000000002', 0.00, 'Savings', 'APPROVED'), (3, 2, 'ACC-1000000003', 0.00, 'Savings', 'PENDING')");
    require_once __DIR__ . '/../includes/transactions.php';
    $tx = requestTransaction($db, 2, 'DEPOSIT', 'ACC-1000000001', '1500.00', '', 'Synthetic opening deposit', bin2hex(random_bytes(16)));
    reviewTransaction($db, 1, $tx['id'], 'APPROVE');
    requestTransaction($db, 2, 'TRANSFER', 'ACC-1000000001', '25.00', 'ACC-1000000002', 'Sample pending transfer', bin2hex(random_bytes(16)));
    requestTransaction($db, 2, 'WITHDRAWAL', 'ACC-1000000001', '50.00', '', 'Sample withdrawal request', bin2hex(random_bytes(16)));
    $db->exec("INSERT INTO announcements (title, content) VALUES ('Welcome to the test preview', 'All balances and identities in this instance are synthetic. Try submitting a request, then sign in as preview_admin to approve or reject it.')");
    echo "Synthetic preview database ready.\n";
} catch (Throwable $error) {
    $db->exec("DROP DATABASE `$database`");
    throw $error;
}
