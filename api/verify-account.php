<?php
// api/verify-account.php
$db = require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$acc_num = trim($_GET['account_number'] ?? '');

if (empty($acc_num)) {
    die(json_encode(['error' => 'Account number is required']));
}

try {
    // Check if account exists at all first
    $stmt = $db->prepare("SELECT status, user_id FROM accounts WHERE account_number = ?");
    $stmt->execute([$acc_num]);
    $account = $stmt->fetch();

    if (!$account) {
        die(json_encode(['error' => 'Account number not found in our records']));
    }

    if ($account['status'] !== 'APPROVED') {
        die(json_encode(['error' => 'Account found but is currently PENDING approval']));
    }

    // Account exists and is approved, get the name
    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$account['user_id']]);
    $name = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'name' => $name]);

} catch (PDOException $e) {
    die(json_encode(['error' => 'Server error: ' . $e->getMessage()]));
}
