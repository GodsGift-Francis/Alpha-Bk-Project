<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Uses a disposable database, never the configured application's tables.
require_once __DIR__ . '/../includes/transactions.php';
require_once __DIR__ . '/../includes/migrations.php';
$db = require __DIR__ . '/../config/database.php';
$database = 'alphabank_test_' . bin2hex(random_bytes(6));
$db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
$db->exec("USE `$database`");
$count = 0;
function check(bool $condition, string $message): void {
    global $count;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $count++;
    echo "PASS: $message\n";
}
function refused(callable $action, string $message): void {
    try { $action(); } catch (InvalidArgumentException | DomainException $error) { check(true, $message); return; }
    check(false, $message);
}
function balance(PDO $db, int $id): string {
    $stmt = $db->prepare('SELECT balance FROM accounts WHERE id = ?'); $stmt->execute([$id]); return $stmt->fetchColumn();
}
function request(PDO $db, string $action, string $amount, string $recipient = '', ?string $key = null): array {
    return requestTransaction($db, 2, $action, 'ACC-1000000001', $amount, $recipient, 'Integration test', $key ?? bin2hex(random_bytes(16)));
}
function parallelReviews(PDO $db, string $database, array $ids): array {
    $command = [PHP_BINARY];
    if (!php_ini_loaded_file()) array_push($command, '-n', '-d', 'extension_dir=' . ini_get('extension_dir'), '-d', 'extension=pdo', '-d', 'extension=mysqlnd', '-d', 'extension=pdo_mysql');
    $jobs = [];
    $db->beginTransaction();
    $db->query('SELECT id FROM accounts WHERE id = 1 FOR UPDATE');
    foreach ($ids as $id) {
        $pipes = [];
        $process = proc_open([...$command, __DIR__ . '/review-worker.php', $database, (string)$id], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start review worker.');
        stream_set_timeout($pipes[1], 5);
        if (trim(fgets($pipes[1]) ?: '') !== 'READY') throw new RuntimeException('Worker failed to become ready.');
        $jobs[] = [$process, $pipes];
    }
    $db->commit();
    $results = [];
    foreach ($jobs as [$process, $pipes]) {
        $results[] = trim(stream_get_contents($pipes[1]));
        $errors = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) fclose($pipe);
        if (proc_close($process) !== 0) throw new RuntimeException('Worker failed: ' . $errors);
    }
    return $results;
}
try {
    $schema = require __DIR__ . '/../database/schema.php'; $schema($db); runMigrations($db); runMigrations($db);
    check((int)$db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 1, 'fresh schema and repeat migration');
    $db->exec("INSERT INTO users (id, username, email, password_hash, full_name, is_admin) VALUES (1, 'admin', 'admin@example.test', 'unused', 'Admin', 1), (2, 'sender', 'sender@example.test', 'unused', 'Sender', 0), (3, 'recipient', 'recipient@example.test', 'unused', 'Recipient', 0)");
    $db->exec("INSERT INTO accounts (id, user_id, account_number, account_type, balance, status) VALUES (1, 2, 'ACC-1000000001', 'Checking', 100.00, 'APPROVED'), (2, 3, 'ACC-1000000002', 'Checking', 0.00, 'APPROVED')");
    check(moneyValue('0.1') === '0.10' && moneyCents('9999999999999.99') === 999999999999999, 'exact decimal normalization and maximum precision');
    foreach (['0', '-1', '1.001', '1e2', 'NaN', '10000000000000', '1,000', ' 1.00'] as $bad) refused(fn() => moneyValue($bad), 'invalid amount rejected: ' . $bad);
    refused(fn() => request($db, 'TRANSFER_IN', '10.00'), 'unsupported action rejected');
    refused(fn() => requestTransaction($db, 3, 'DEPOSIT', 'ACC-1000000001', '10.00', '', '', bin2hex(random_bytes(16))), 'account ownership enforced');
    $key = bin2hex(random_bytes(16));
    $first = request($db, 'DEPOSIT', '0.10', '', $key); $retry = request($db, 'DEPOSIT', '0.10', '', $key);
    check($first['id'] === $retry['id'] && $retry['replayed'], 'same request retry returns original transaction');
    refused(fn() => request($db, 'DEPOSIT', '0.20', '', $key), 'request key cannot be reused with different details');
    check(balance($db, 1) === '100.00', 'submission does not change balances');
    $results = parallelReviews($db, $database, [$first['id'], $first['id']]);
    check(count(array_filter($results, fn($result) => $result === 'APPROVED')) === 1 && balance($db, 1) === '100.10', 'concurrent approvals post a request exactly once');
    $a = request($db, 'TRANSFER', '80.00', 'ACC-1000000002');
    $b = request($db, 'TRANSFER', '80.00', 'ACC-1000000002');
    $results = parallelReviews($db, $database, [$a['id'], $b['id']]);
    check(count(array_filter($results, fn($result) => $result === 'APPROVED')) === 1, 'concurrent transfers cannot overspend the source balance');
    check(balance($db, 1) === '20.10' && balance($db, 2) === '80.00', 'transfer conserves exact funds');
    check((int)$db->query("SELECT COUNT(*) FROM transactions WHERE transaction_type = 'TRANSFER_IN'")->fetchColumn() === 1, 'single linked recipient posting');
    $pending = (int)$db->query("SELECT id FROM transactions WHERE transaction_type = 'TRANSFER_OUT' AND status = 'PENDING'")->fetchColumn();
    reviewTransaction($db, 1, $pending, 'REJECT', 'Duplicate instruction');
    $row = $db->query('SELECT * FROM transactions WHERE id = ' . $pending)->fetch();
    check($row['status'] === 'REJECTED' && $row['review_reason'] === 'Duplicate instruction' && (int)$row['reviewed_by'] === 1, 'rejection retains record, reason and actor');
    refused(fn() => reviewTransaction($db, 1, $pending, 'APPROVE'), 'rejected request cannot be approved');
    $withdrawal = request($db, 'WITHDRAWAL', '20.10');
    reviewTransaction($db, 1, $withdrawal['id'], 'APPROVE');
    check(balance($db, 1) === '0.00', 'exact full-balance withdrawal');
    $deposit = request($db, 'DEPOSIT', '10.00'); reviewTransaction($db, 1, $deposit['id'], 'APPROVE');
    $transfer = request($db, 'TRANSFER', '1.00', 'ACC-1000000002');
    $db->exec("UPDATE accounts SET status = 'REJECTED' WHERE id = 2");
    refused(fn() => reviewTransaction($db, 1, $transfer['id'], 'APPROVE'), 'recipient status rechecked at approval');
    check(balance($db, 1) === '10.00', 'failed approval rolls back source debit');
    $db->exec("UPDATE accounts SET status = 'APPROVED', balance = 9999999999999.99 WHERE id = 2");
    refused(fn() => reviewTransaction($db, 1, $transfer['id'], 'APPROVE'), 'recipient overflow rejected');
    check(balance($db, 1) === '10.00', 'overflow rolls back debit atomically');
    check((int)$db->query("SELECT COUNT(*) FROM audit_events WHERE event_type = 'APPROVED'")->fetchColumn() === 4, 'successful approvals recorded once in audit trail');
    $db->exec("UPDATE accounts SET balance = 9999999999999.99 WHERE id = 1");
    $tiny = request($db, 'WITHDRAWAL', '0.01'); reviewTransaction($db, 1, $tiny['id'], 'APPROVE');
    check(balance($db, 1) === '9999999999999.98', 'SQL debit preserves cents at maximum balance');
    $tiny = request($db, 'DEPOSIT', '0.01'); reviewTransaction($db, 1, $tiny['id'], 'APPROVE');
    check(balance($db, 1) === '9999999999999.99', 'SQL credit preserves cents at maximum balance');
    echo "Integrity checks passed: $count\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
    $db->exec("DROP DATABASE `$database`");
}
