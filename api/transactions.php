<?php
// api/transactions.php

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
$action = $_POST['action'] ?? '';
$amount_raw = $_POST['amount'] ?? 0;
$amount = round(floatval($amount_raw), 2);
$account_number = $_POST['account_number'] ?? '';
$description = $_POST['description'] ?? '';

if (!is_numeric($amount_raw) || $amount <= 0) {
    die(json_encode(['error' => 'Valid positive amount is required']));
}

try {
    // Verify account ownership and check if APPROVED
    $stmt = $db->prepare("SELECT id, balance, status FROM accounts WHERE account_number = ? AND user_id = ?");
    $stmt->execute([$account_number, $user_id]);
    $account = $stmt->fetch();

    if (!$account) {
        throw new Exception('Source account not found');
    }

    if ($account['status'] !== 'APPROVED') {
        throw new Exception('Your account is still pending approval. You cannot perform transactions yet.');
    }

    $account_id = $account['id'];
    $current_balance = floatval($account['balance']);

    // For WITHDRAWAL and TRANSFER, check if they have enough balance (prevent pending more than they have)
    if ($action === 'WITHDRAWAL' || $action === 'TRANSFER') {
        if ($current_balance < $amount) {
            throw new Exception('Insufficient funds for this request');
        }
    }

    if ($action === 'TRANSFER') {
        $recipient_acc_num = $_POST['recipient_account'] ?? '';
        if (empty($recipient_acc_num)) {
            throw new Exception('Recipient account number is required');
        }
        if ($recipient_acc_num === $account_number) {
            throw new Exception('Cannot transfer to the same account');
        }

        // Find recipient to verify they exist and are APPROVED
        $stmt_rec = $db->prepare("SELECT id FROM accounts WHERE account_number = ? AND status = 'APPROVED'");
        $stmt_rec->execute([$recipient_acc_num]);
        $recipient = $stmt_rec->fetch();

        if (!$recipient) {
            throw new Exception('Recipient account not found or is not active');
        }

        // Log PENDING Transfer
        $stmt_log = $db->prepare("INSERT INTO transactions (account_id, transaction_type, amount, related_account_id, description, status) VALUES (?, 'TRANSFER_OUT', ?, ?, ?, 'PENDING')");
        $stmt_log->execute([$account_id, $amount, $recipient['id'], $description]);

        echo json_encode(['success' => 'Transfer request submitted. Awaiting admin approval.', 'status' => 'PENDING']);
        exit();
    }

    // Log PENDING Deposit/Withdrawal
    $stmt = $db->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description, status) VALUES (?, ?, ?, ?, 'PENDING')");
    $stmt->execute([$account_id, $action, $amount, $description]);

    echo json_encode(['success' => ucfirst(strtolower($action)) . ' request submitted. Awaiting admin approval.', 'status' => 'PENDING']);

} catch (Exception $e) {
    die(json_encode(['error' => $e->getMessage()]));
}
