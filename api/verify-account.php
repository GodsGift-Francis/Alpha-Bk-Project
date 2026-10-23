<?php
require_once __DIR__ . '/../includes/api.php';
$user = apiUser();
$number = $_GET['account_number'] ?? '';
if (!is_string($number) || strlen($number) > 20 || trim($number) === '') throw new InvalidArgumentException('Enter a valid recipient account number.');
$bucket = hash('sha256', 'lookup:' . $user);
$db->prepare("INSERT INTO login_limits (bucket, attempts, window_started) VALUES (?, 1, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts = IF(window_started < UTC_TIMESTAMP() - INTERVAL 15 MINUTE, 1, attempts + 1), window_started = IF(window_started < UTC_TIMESTAMP() - INTERVAL 15 MINUTE, UTC_TIMESTAMP(), window_started)")->execute([$bucket]);
$stmt = $db->prepare('SELECT attempts FROM login_limits WHERE bucket = ?');
$stmt->execute([$bucket]);
if ((int)$stmt->fetchColumn() > 100) { header('Retry-After: 900'); jsonResponse(['error' => 'Too many lookups. Please try again later.'], 429); }
$stmt = $db->prepare("SELECT u.full_name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.account_number = ? AND a.status = 'APPROVED' AND u.status = 'ACTIVE'");
$stmt->execute([trim($number)]);
$name = $stmt->fetchColumn();
if ($name === false) jsonResponse(['error' => 'Recipient account not found or unavailable.'], 404);
jsonResponse(['success' => true, 'name' => $name]);
