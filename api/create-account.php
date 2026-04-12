<?php
// api/create-account.php
$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

verifyCsrfToken($_POST['csrf_token'] ?? '');
$user_id = $_SESSION['user_id'];
$type = $_POST['account_type'] ?? 'Savings';

try {
    // Generate unique account number
    do {
        $account_number = 'ACC-' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        $stmt_check = $db->prepare("SELECT id FROM accounts WHERE account_number = ?");
        $stmt_check->execute([$account_number]);
    } while ($stmt_check->fetch());

    $stmt = $db->prepare("INSERT INTO accounts (user_id, account_number, balance, account_type, status) VALUES (?, ?, 0.00, ?, 'PENDING')");
    $stmt->execute([$user_id, $account_number, $type]);
    
    echo json_encode(['success' => 'Account request submitted. Awaiting admin approval.', 'account_number' => $account_number]);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Failed to create account: ' . $e->getMessage()]));
}
