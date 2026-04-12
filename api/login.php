<?php
// api/login.php

$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

verifyCsrfToken($_POST['csrf_token'] ?? '');

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    die(json_encode(['error' => 'Username and password are required']));
}

try {
    $stmt = $db->prepare("SELECT id, username, password_hash, is_admin, currency FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        loginUser($user['id'], $user['username'], $user['is_admin'], $user['currency']);
        echo json_encode(['success' => 'Login successful', 'is_admin' => $user['is_admin']]);
    } else {
        echo json_encode(['error' => 'Invalid username or password']);
    }
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
