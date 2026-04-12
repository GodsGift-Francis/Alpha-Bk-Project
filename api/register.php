<?php
// api/register.php

$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

verifyCsrfToken($_POST['csrf_token'] ?? '');

$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$full_name = $_POST['full_name'] ?? '';

if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
    die(json_encode(['error' => 'All fields are required']));
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $db->beginTransaction();

    // Insert user
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $password_hash, $full_name]);
    $user_id = $db->lastInsertId();

    // Generate a unique account number
    do {
        $account_number = 'ACC-' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        $stmt_check = $db->prepare("SELECT id FROM accounts WHERE account_number = ?");
        $stmt_check->execute([$account_number]);
    } while ($stmt_check->fetch());

    // Create a default Checking account
    $stmt = $db->prepare("INSERT INTO accounts (user_id, account_number, balance, account_type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $account_number, 0.00, 'Checking']);

    $db->commit();
    echo json_encode(['success' => 'Registration successful', 'user_id' => $user_id]);
} catch (PDOException $e) {
    $db->rollBack();
    if ($e->getCode() == 23000) { // Unique constraint violation
        die(json_encode(['error' => 'Username or Email already exists']));
    }
    die(json_encode(['error' => 'Registration failed: ' . $e->getMessage()]));
}
