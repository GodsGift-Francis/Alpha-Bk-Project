<?php
// api/admin-accounts.php
$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

$account_id = $_POST['account_id'] ?? 0;
$action = $_POST['action'] ?? ''; // 'APPROVE' or 'REJECT'

if (!$account_id || !in_array($action, ['APPROVE', 'REJECT'])) {
    die(json_encode(['error' => 'Invalid parameters']));
}

try {
    if ($action === 'APPROVE') {
        $stmt = $db->prepare("UPDATE accounts SET status = 'APPROVED' WHERE id = ?");
        $stmt->execute([$account_id]);
        echo json_encode(['success' => 'Account approved successfully']);
    } else {
        // If rejected, delete the account entirely so it's removed from both sides
        $stmt = $db->prepare("DELETE FROM accounts WHERE id = ?");
        $stmt->execute([$account_id]);
        echo json_encode(['success' => 'Account request rejected and removed']);
    }
} catch (PDOException $e) {
    die(json_encode(['error' => $e->getMessage()]));
}
