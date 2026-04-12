<?php
// api/settings.php
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
$currency = $_POST['currency'] ?? 'USD';

$allowed_currencies = ['USD', 'EUR', 'GBP', 'NGN', 'JPY'];
if (!in_array($currency, $allowed_currencies)) {
    die(json_encode(['error' => 'Invalid currency selection']));
}

try {
    $stmt = $db->prepare("UPDATE users SET currency = ? WHERE id = ?");
    $stmt->execute([$currency, $user_id]);
    
    $_SESSION['currency'] = $currency;
    echo json_encode(['success' => 'Settings updated successfully']);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Failed to update settings: ' . $e->getMessage()]));
}
