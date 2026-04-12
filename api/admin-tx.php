<?php
// api/admin-tx.php
$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

$tx_id = $_POST['tx_id'] ?? 0;
$action = $_POST['action'] ?? ''; // 'APPROVE' or 'REJECT'

if (!$tx_id || !in_array($action, ['APPROVE', 'REJECT'])) {
    die(json_encode(['error' => 'Invalid parameters']));
}

try {
    $db->beginTransaction();

    // 1. Fetch transaction details
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'PENDING'");
    $stmt->execute([$tx_id]);
    $tx = $stmt->fetch();

    if (!$tx) {
        throw new Exception('Transaction not found or already processed');
    }

    if ($action === 'REJECT') {
        $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$tx_id]);
        $db->commit();
        echo json_encode(['success' => 'Transaction request rejected and removed']);
        exit();
    }

    // 2. Process Approval
    $acc_id = $tx['account_id'];
    $amount = floatval($tx['amount']);
    $type = $tx['transaction_type'];

    // Update Transaction status first
    $stmt = $db->prepare("UPDATE transactions SET status = 'APPROVED' WHERE id = ?");
    $stmt->execute([$tx_id]);

    if ($type === 'DEPOSIT') {
        $stmt = $db->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $acc_id]);
    } elseif ($type === 'WITHDRAWAL') {
        // Check balance again
        $stmt = $db->prepare("SELECT balance FROM accounts WHERE id = ?");
        $stmt->execute([$acc_id]);
        $balance = $stmt->fetchColumn();
        if ($balance < $amount) throw new Exception('Insufficient funds in account');
        
        $stmt = $db->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $acc_id]);
    } elseif ($type === 'TRANSFER_OUT') {
        $recipient_id = $tx['related_account_id'];
        
        if (!$recipient_id) {
            throw new Exception('Recipient account ID missing in transaction record');
        }

        // Fetch sender account number for history
        $stmt_send = $db->prepare("SELECT account_number FROM accounts WHERE id = ?");
        $stmt_send->execute([$acc_id]);
        $sender_acc_num = $stmt_send->fetchColumn();

        // Debit sender
        $stmt = $db->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $acc_id]);

        // Credit recipient
        $stmt = $db->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $recipient_id]);

        // Create a matching TRANSFER_IN record for the recipient (automatically APPROVED)
        $stmt = $db->prepare("INSERT INTO transactions (account_id, transaction_type, amount, related_account_id, description, status) VALUES (?, 'TRANSFER_IN', ?, ?, ?, 'APPROVED')");
        $stmt->execute([$recipient_id, $amount, $acc_id, "(From " . $sender_acc_num . ") " . $tx['description']]);
    }

    $db->commit();
    echo json_encode(['success' => 'Transaction approved and balance updated']);

} catch (Exception $e) {
    $db->rollBack();
    die(json_encode(['error' => $e->getMessage()]));
}
