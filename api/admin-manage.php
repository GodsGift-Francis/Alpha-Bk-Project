<?php
// api/admin-manage.php
$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

verifyCsrfToken($_POST['csrf_token'] ?? '');

$id = $_POST['id'] ?? 0;
$type = $_POST['type'] ?? ''; // 'user', 'account', 'transaction'

if (!$id || !$type) {
    die(json_encode(['error' => 'Invalid parameters']));
}

try {
    if ($type === 'user') {
        // Safety: Don't delete the current admin or other admins via this simple tool
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
        $stmt->execute([$id]);
        $msg = "User and all their data removed.";
    } elseif ($type === 'account') {
        $stmt = $db->prepare("DELETE FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Account removed.";
    } elseif ($type === 'transaction') {
        $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Transaction record deleted.";
    } else {
        throw new Exception("Unknown type.");
    }

    echo json_encode(['success' => $msg]);
} catch (Exception $e) {
    die(json_encode(['error' => $e->getMessage()]));
}
