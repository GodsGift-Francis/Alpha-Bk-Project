<?php
require_once __DIR__ . '/../includes/api.php';
$user = apiUser();
function queryText(string $field, int $max = 100): string {
    $value = $_GET[$field] ?? '';
    if (!is_string($value) || strlen($value) > $max) throw new InvalidArgumentException('Invalid history filter.');
    return trim($value);
}
$pageRaw = queryText('page', 6);
if ($pageRaw !== '' && (!preg_match('/^[0-9]+$/D', $pageRaw) || (int)$pageRaw < 1)) throw new InvalidArgumentException('Invalid page.');
$page = max(1, (int)$pageRaw);
$limit = 20;
$where = ['a.user_id = ?'];
$params = [$user];
$status = queryText('status', 20);
if ($status !== '') {
    if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) throw new InvalidArgumentException('Invalid status filter.');
    $where[] = 't.status = ?'; $params[] = $status;
}
$type = queryText('type', 20);
if ($type !== '') {
    if (!in_array($type, ['DEPOSIT', 'WITHDRAWAL', 'TRANSFER_OUT', 'TRANSFER_IN'], true)) throw new InvalidArgumentException('Invalid type filter.');
    $where[] = 't.transaction_type = ?'; $params[] = $type;
}
$account = queryText('account', 20);
if ($account !== '') { $where[] = 'a.account_number = ?'; $params[] = $account; }
$q = queryText('q');
if ($q !== '') {
    $where[] = '(t.description LIKE ? OR a.account_number LIKE ? OR CONCAT(\'TX-\', t.id) = ?)';
    array_push($params, '%' . $q . '%', '%' . $q . '%', $q);
}
foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
    $date = queryText($field, 10);
    if ($date !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Invalid date filter.');
        $where[] = "t.timestamp $operator ?";
        $params[] = $date . ($field === 'from' ? ' 00:00:00' : ' 23:59:59');
    }
}
$join = ' FROM transactions t JOIN accounts a ON a.id = t.account_id WHERE ' . implode(' AND ', $where);
$stmt = $db->prepare('SELECT COUNT(*)' . $join); $stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));
$page = min($page, $pages);
$stmt = $db->prepare('SELECT t.id, t.transaction_type, t.amount, t.description, t.status, t.timestamp, t.reviewed_at, t.review_reason, a.account_number' . $join . ' ORDER BY t.timestamp DESC, t.id DESC LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit));
$stmt->execute($params);
jsonResponse(['history' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $limit]]);
