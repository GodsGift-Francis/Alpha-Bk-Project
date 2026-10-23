<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/transactions.php';
$user = apiUser();
apiPost();
$result = requestTransaction($db, $user, inputText('action', 20), inputText('account_number', 20), inputText('amount', 20), inputText('recipient_account', 20, false), inputText('description', 500, false), inputText('request_key', 64));
jsonResponse($result + ['reference' => 'TX-' . $result['id'], 'success' => $result['replayed'] ? 'This request was already received. See your history for its status.' : 'Request submitted. Awaiting admin approval.'], $result['replayed'] ? 200 : 201);
