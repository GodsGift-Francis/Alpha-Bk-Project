<?php
// api/admin-edit.php
$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

verifyCsrfToken($_POST['csrf_token'] ?? '');

$id = $_POST['id'] ?? 0;
$type = $_POST['type'] ?? ''; // 'user' or 'transaction'
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';

if (!$id || !$type || !$field) {
    die(json_encode(['error' => 'Invalid parameters']));
}

try {
    if ($type === 'user' && $field === 'full_name') {
        $stmt = $db->prepare("UPDATE users SET full_name = ? WHERE id = ? AND is_admin = 0");
        $stmt->execute([$value, $id]);
        $msg = "User name updated successfully.";
    } elseif ($type === 'transaction' && $field === 'timestamp') {
        // Ensure date format is valid for SQL
        $date = date('Y-m-d H:i:s', strtotime($value));
        $stmt = $db->prepare("UPDATE transactions SET timestamp = ? WHERE id = ?");
        $stmt->execute([$date, $id]);
        $msg = "Transaction date updated successfully.";
    } else {
        throw new Exception("Unauthorized edit attempt or invalid field.");
    }

    echo json_encode(['success' => $msg]);
} catch (Exception $e) {
    die(json_encode(['error' => $e->getMessage()]));
}
