<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/transactions.php';
$user = apiUser();
apiPost();
$type = inputText('account_type', 50);
if (!in_array($type, ['Checking', 'Savings', 'Business'], true)) throw new InvalidArgumentException('Choose a supported account type.');
$db->beginTransaction();
$db->prepare("INSERT INTO accounts (user_id, account_number, balance, account_type, status) VALUES (?, ?, 0.00, ?, 'PENDING')")->execute([$user, 'ACC-' . random_int(1000000000, 9999999999), $type]);
auditEvent($db, $user, 'REQUESTED', 'account', (int)$db->lastInsertId(), ['account_type' => $type]);
$db->commit();
jsonResponse(['success' => 'Account request submitted. Awaiting admin approval.'], 201);
