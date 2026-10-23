<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/transactions.php';
$admin = apiUser(true);
apiPost();
$action = inputText('action', 10);
reviewTransaction($db, $admin, inputId('tx_id'), $action, inputText('reason', 500, false));
jsonResponse(['success' => $action === 'APPROVE' ? 'Transaction approved and balance updated.' : 'Request rejected. Its history and reason have been retained.']);
