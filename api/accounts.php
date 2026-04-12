<?php
// api/accounts.php

$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT account_number, balance, account_type, status FROM accounts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $accounts = $stmt->fetchAll();

    echo json_encode(['accounts' => $accounts]);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Failed to fetch accounts: ' . $e->getMessage()]));
}
