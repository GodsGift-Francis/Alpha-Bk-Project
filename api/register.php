<?php
require_once __DIR__ . '/../includes/api.php';
apiPost();
$username = inputText('username', 50);
$email = inputText('email', 100);
$name = inputText('full_name', 100);
$password = $_POST['password'] ?? '';
if (!preg_match('/^[A-Za-z0-9_]{3,50}$/D', $username)) throw new InvalidArgumentException('Username must contain 3–50 letters, numbers or underscores.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) throw new InvalidArgumentException('Use a password between 12 and 72 bytes.');
try {
    $db->beginTransaction();
    $db->prepare('INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)')->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $name]);
    $user = $db->lastInsertId();
    $db->prepare("INSERT INTO accounts (user_id, account_number, balance, account_type, status) VALUES (?, ?, 0.00, 'Checking', 'PENDING')")->execute([$user, 'ACC-' . random_int(1000000000, 9999999999)]);
    $db->commit();
    jsonResponse(['success' => 'Registration successful. Your account is awaiting approval.'], 201);
} catch (PDOException $error) {
    if ($db->inTransaction()) $db->rollBack();
    if ($error->getCode() === '23000') jsonResponse(['error' => 'Username or email is unavailable.'], 409);
    throw $error;
}
