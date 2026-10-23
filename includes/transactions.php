<?php
require_once __DIR__ . '/money.php';
function auditEvent(PDO $db, int $actor, string $event, string $entity, int $id, array $details): void {
    $db->prepare('INSERT INTO audit_events (actor_id, event_type, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)')
        ->execute([$actor, $event, $entity, $id, json_encode($details, JSON_THROW_ON_ERROR)]);
}
function requestTransaction(PDO $db, int $user, string $action, string $number, string $amount, string $recipient, string $description, string $key): array {
    if (!in_array($action, ['DEPOSIT', 'WITHDRAWAL', 'TRANSFER'], true)) throw new InvalidArgumentException('Unsupported transaction action.');
    $amount = moneyValue($amount);
    if (!preg_match('/^[a-f0-9]{32,64}$/D', $key)) throw new InvalidArgumentException('A valid request key is required. Refresh the page and try again.');
    if (strlen($description) > 500) throw new InvalidArgumentException('Description must be 500 characters or fewer.');
    if ($number === '' || strlen($number) > 20 || strlen($recipient) > 20) throw new InvalidArgumentException('Invalid account number.');
    if ($action !== 'TRANSFER') $recipient = '';
    $hash = hash('sha256', json_encode([$action, $number, $amount, $recipient, $description], JSON_THROW_ON_ERROR));
    $db->beginTransaction();
    try {
        // Serializes same-source submissions. The unique key also covers cross-account retries.
        $stmt = $db->prepare('SELECT * FROM accounts WHERE account_number = ? AND user_id = ? FOR UPDATE');
        $stmt->execute([$number, $user]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new DomainException('Source account not found.');
        $existing = $db->prepare('SELECT id, status, request_hash FROM transactions WHERE requested_by = ? AND request_key = ?');
        $existing->execute([$user, $key]);
        if ($tx = $existing->fetch(PDO::FETCH_ASSOC)) {
            if (!hash_equals($tx['request_hash'], $hash)) throw new DomainException('This request key was used for different details. Refresh before submitting a new request.');
            $db->commit();
            return ['id' => (int)$tx['id'], 'status' => $tx['status'], 'replayed' => true];
        }
        if ($account['status'] !== 'APPROVED') throw new DomainException('Only approved accounts can submit transactions.');
        if ($action !== 'DEPOSIT' && moneyCents($account['balance']) < moneyCents($amount)) throw new DomainException('Insufficient funds for this request.');
        $related = null;
        if ($action === 'TRANSFER') {
            if ($recipient === '' || $recipient === $number) throw new InvalidArgumentException('Choose a different recipient account.');
            $stmt = $db->prepare("SELECT id FROM accounts WHERE account_number = ? AND status = 'APPROVED'");
            $stmt->execute([$recipient]);
            $related = $stmt->fetchColumn();
            if (!$related) throw new DomainException('Recipient account not found or not active.');
        }
        $stmt = $db->prepare("INSERT INTO transactions (account_id, transaction_type, amount, related_account_id, description, status, requested_by, request_key, request_hash) VALUES (?, ?, ?, ?, ?, 'PENDING', ?, ?, ?)");
        $stmt->execute([$account['id'], $action === 'TRANSFER' ? 'TRANSFER_OUT' : $action, $amount, $related ?: null, $description, $user, $key, $hash]);
        $id = (int)$db->lastInsertId();
        auditEvent($db, $user, 'REQUESTED', 'transaction', $id, ['amount' => $amount, 'type' => $action]);
        $db->commit();
        return ['id' => $id, 'status' => 'PENDING', 'replayed' => false];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        if ($error instanceof PDOException && $error->getCode() === '23000') throw new DomainException('This request was already submitted. Check your history before trying again.');
        throw $error;
    }
}
function reviewTransaction(PDO $db, int $admin, int $id, string $action, string $reason = ''): void {
    if (!in_array($action, ['APPROVE', 'REJECT'], true)) throw new InvalidArgumentException('Invalid review action.');
    if (strlen($reason) > 500 || ($action === 'REJECT' && strlen(trim($reason)) < 3)) throw new InvalidArgumentException('Provide a rejection reason between 3 and 500 characters.');
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx || $tx['status'] !== 'PENDING') throw new DomainException('Request not found or already processed.');
        if (!in_array($tx['transaction_type'], ['DEPOSIT', 'WITHDRAWAL', 'TRANSFER_OUT'], true)) throw new DomainException('Unsupported transaction type.');
        $amount = moneyValue($tx['amount']);
        if ($action === 'APPROVE') {
            $ids = [(int)$tx['account_id']];
            if ($tx['transaction_type'] === 'TRANSFER_OUT') {
                if (!$tx['related_account_id'] || $tx['related_account_id'] == $tx['account_id']) throw new DomainException('Invalid recipient account.');
                $ids[] = (int)$tx['related_account_id'];
            }
            sort($ids, SORT_NUMERIC);
            $accounts = [];
            // Explicit ascending lock acquisition avoids opposite-direction transfer deadlocks.
            foreach ($ids as $accountId) {
                $stmt = $db->prepare('SELECT * FROM accounts WHERE id = ? FOR UPDATE');
                $stmt->execute([$accountId]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$account || $account['status'] !== 'APPROVED') throw new DomainException('Source and recipient accounts must be active at approval.');
                $accounts[$accountId] = $account;
            }
            $source = $accounts[$tx['account_id']];
            if ($tx['transaction_type'] !== 'DEPOSIT') {
                // The predicate is a second safeguard against negative balances.
                $stmt = $db->prepare('UPDATE accounts SET balance = balance - CAST(? AS DECIMAL(15,2)) WHERE id = ? AND balance >= CAST(? AS DECIMAL(15,2))');
                $stmt->execute([$amount, $source['id'], $amount]);
                if ($stmt->rowCount() !== 1) throw new DomainException('Insufficient funds at approval. Request remains pending.');
            }
            if ($tx['transaction_type'] !== 'WITHDRAWAL') {
                $creditId = $tx['transaction_type'] === 'DEPOSIT' ? $source['id'] : $tx['related_account_id'];
                if (moneyCents($accounts[$creditId]['balance']) + moneyCents($amount) > 999999999999999) throw new DomainException('Recipient balance would exceed the supported limit.');
                $db->prepare('UPDATE accounts SET balance = balance + CAST(? AS DECIMAL(15,2)) WHERE id = ?')->execute([$amount, $creditId]);
                if ($tx['transaction_type'] === 'TRANSFER_OUT') {
                    $db->prepare("INSERT INTO transactions (account_id, transaction_type, amount, related_account_id, description, status, source_transaction_id, reviewed_by, reviewed_at) VALUES (?, 'TRANSFER_IN', ?, ?, ?, 'APPROVED', ?, ?, UTC_TIMESTAMP())")
                        ->execute([$creditId, $amount, $source['id'], '(From ' . $source['account_number'] . ') ' . $tx['description'], $id, $admin]);
                }
            }
        }
        $status = $action === 'APPROVE' ? 'APPROVED' : 'REJECTED';
        $db->prepare("UPDATE transactions SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(), review_reason = ? WHERE id = ? AND status = 'PENDING'")
            ->execute([$status, $admin, $reason ?: null, $id]);
        auditEvent($db, $admin, $status, 'transaction', $id, ['previous_status' => 'PENDING', 'reason' => $reason, 'amount' => $amount]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
