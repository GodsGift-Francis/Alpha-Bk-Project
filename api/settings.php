<?php
require_once __DIR__ . '/../includes/api.php';
$user = apiUser();
apiPost();
$currency = inputText('currency', 3);
if (!in_array($currency, ['USD', 'EUR', 'GBP', 'NGN', 'JPY'], true)) throw new InvalidArgumentException('Invalid display currency.');
$db->prepare('UPDATE users SET currency = ? WHERE id = ?')->execute([$currency, $user]);
$_SESSION['currency'] = $currency;
jsonResponse(['success' => 'Display preference saved. No currency conversion was performed.']);
