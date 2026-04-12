<?php
// api/history.php

$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch all transactions for all accounts belonging to the user
    $stmt = $db->prepare("
        SELECT t.*, a.account_number 
        FROM transactions t
        JOIN accounts a ON t.account_id = a.id
        WHERE a.user_id = ?
        ORDER BY t.timestamp DESC
    ");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll();

    echo json_encode(['history' => $history]);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Failed to fetch history: ' . $e->getMessage()]));
}
