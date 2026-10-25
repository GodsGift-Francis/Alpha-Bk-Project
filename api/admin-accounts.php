<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/transactions.php';
$admin = apiUser(true);
apiPost();
$id = inputId('account_id');
$action = inputText('action', 10);
$reason = inputText('reason', 500, false);
if (!in_array($action, ['APPROVE', 'REJECT'], true)) throw new InvalidArgumentException('Invalid action.');
if ($action === 'REJECT' && strlen($reason) < 3) throw new InvalidArgumentException('Please provide a rejection reason.');
$db->beginTransaction();
$stmt = $db->prepare('SELECT status FROM accounts WHERE id = ? FOR UPDATE');
$stmt->execute([$id]);
if ($stmt->fetchColumn() !== 'PENDING') throw new DomainException('Account request not found or already processed.');
$status = $action === 'APPROVE' ? 'APPROVED' : 'REJECTED';
$db->prepare('UPDATE accounts SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(), review_reason = ? WHERE id = ?')->execute([$status, $admin, $reason ?: null, $id]);
$notice = $db->prepare('SELECT user_id, account_number FROM accounts WHERE id = ?');
$notice->execute([$id]);
$owner = $notice->fetch();
$db->prepare('INSERT INTO announcements (user_id, title, content) VALUES (?, ?, ?)')->execute([
    $owner['user_id'], 'Account ' . strtolower($status),
    'Your account ending ' . substr($owner['account_number'], -4) . ' has been ' . strtolower($status) . '.' . ($reason ? ' Reason: ' . $reason : '')
]);
auditEvent($db, $admin, $status, 'account', $id, ['reason' => $reason]);
$db->commit();
jsonResponse(['success' => 'Account request ' . strtolower($status) . '.']);
