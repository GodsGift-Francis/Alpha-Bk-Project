<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/transactions.php';
$admin = apiUser(true);
apiPost();
$id = inputId('id');
if (inputText('type', 20) !== 'user' || inputText('field', 30) !== 'full_name') {
    throw new DomainException('Posted transaction timestamps are immutable. Only client names may be edited.');
}
$value = inputText('value', 100);
$db->beginTransaction();
$stmt = $db->prepare('SELECT full_name FROM users WHERE id = ? AND is_admin = 0 FOR UPDATE');
$stmt->execute([$id]);
$previous = $stmt->fetchColumn();
if ($previous === false) throw new DomainException('Client not found.');
$db->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$value, $id]);
auditEvent($db, $admin, 'NAME_UPDATED', 'user', $id, ['before' => $previous, 'after' => $value]);
$db->commit();
jsonResponse(['success' => 'Client name updated.']);
