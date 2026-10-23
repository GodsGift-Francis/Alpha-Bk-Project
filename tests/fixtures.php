<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$database = $argv[2] ?? '';
if (!preg_match('/^alphabank_test_[a-f0-9]{12}$/D', $database)) exit(2);
$db = require __DIR__ . '/../config/database.php';
if (($argv[1] ?? '') === 'drop') { $db->exec("DROP DATABASE IF EXISTS `$database`"); exit; }
$db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
$db->exec("USE `$database`");
$schema = require __DIR__ . '/../database/schema.php'; $schema($db);
require_once __DIR__ . '/../includes/migrations.php'; runMigrations($db);
$password = 'AlphaTest-pass-2026!';
$stmt = $db->prepare('INSERT INTO users (id, username, email, password_hash, full_name, is_admin) VALUES (?, ?, ?, ?, ?, ?)');
foreach ([[1, 'testadmin', 'Administrator', 1], [2, 'testsender', 'Eve "><img src=x onerror="window.injected=true">', 0], [3, 'testrecipient', 'Recipient', 0]] as [$id, $username, $name, $admin]) {
    $stmt->execute([$id, $username, $username . '@example.test', password_hash($password, PASSWORD_DEFAULT), $name, $admin]);
}
$db->exec("INSERT INTO accounts (id, user_id, account_number, balance, account_type, status) VALUES (1, 2, 'ACC-1000000001', 100.00, 'Checking', 'APPROVED'), (2, 3, 'ACC-1000000002', 0.00, 'Savings', 'APPROVED'), (3, 2, 'ACC-1000000003', 0.00, 'Savings', 'PENDING')");
$db->exec("INSERT INTO transactions (id, account_id, transaction_type, amount, description, status) VALUES (1, 1, 'DEPOSIT', 5.00, 'Pending test deposit', 'PENDING'), (2, 1, 'WITHDRAWAL', 1.00, 'Pending test withdrawal', 'PENDING'), (3, 2, 'DEPOSIT', 10.00, 'Private recipient transaction', 'PENDING')");
for ($i = 0; $i < 22; $i++) $db->exec("INSERT INTO transactions (account_id, transaction_type, amount, description, status, review_reason) VALUES (1, 'DEPOSIT', 0.01, 'Retained rejected sample', 'REJECTED', 'Test record')");
$db->exec("INSERT INTO announcements (id, user_id, title, content) VALUES (1, NULL, 'General announcement', 'For everyone'), (2, 3, 'Private announcement', 'Recipient only')");
echo json_encode(['password' => $password, 'sender' => 'testsender', 'admin' => 'testadmin', 'recipient' => 'testrecipient']) . PHP_EOL;
