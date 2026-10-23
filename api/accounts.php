<?php
require_once __DIR__ . '/../includes/api.php';
$user = apiUser();
$stmt = $db->prepare('SELECT account_number, balance, account_type, status, review_reason, reviewed_at FROM accounts WHERE user_id = ? ORDER BY id');
$stmt->execute([$user]);
jsonResponse(['accounts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
