<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = require __DIR__ . '/config/database.php';
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function adminPage(string $name): int {
    $value = $_GET[$name] ?? '1';
    return is_string($value) && preg_match('/^[0-9]+$/D', $value) ? min(100000, max(1, (int)$value)) : 1;
}
function pageLink(string $field, int $page, string $anchor): string {
    $query = array_intersect_key($_GET, array_flip(['clients', 'tx_page', 'account_page', 'audit_page']));
    $query[$field] = $page;
    return 'admin.php?' . http_build_query($query) . '#' . $anchor;
}
$txPage = adminPage('tx_page'); $accountPage = adminPage('account_page'); $auditPage = adminPage('audit_page');
$user_count = $db->query('SELECT COUNT(*) FROM users WHERE is_admin = 0')->fetchColumn();
$total_balance = $db->query('SELECT COALESCE(SUM(balance), 0) FROM accounts')->fetchColumn();
$pending_count = $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'PENDING'")->fetchColumn();
$pending_account_count = $db->query("SELECT COUNT(*) FROM accounts WHERE status = 'PENDING'")->fetchColumn();
$txPage = min($txPage, max(1, (int)ceil($pending_count / 20)));
$accountPage = min($accountPage, max(1, (int)ceil($pending_account_count / 20)));
$pending_tx = $db->query("SELECT t.*, a.account_number, a.balance, u.username, r.account_number AS recipient_number FROM transactions t JOIN accounts a ON t.account_id = a.id JOIN users u ON a.user_id = u.id LEFT JOIN accounts r ON r.id = t.related_account_id WHERE t.status = 'PENDING' ORDER BY t.timestamp, t.id LIMIT 20 OFFSET " . (($txPage - 1) * 20))->fetchAll();
$pending_accounts = $db->query("SELECT a.*, u.username FROM accounts a JOIN users u ON a.user_id = u.id WHERE a.status = 'PENDING' ORDER BY a.created_at, a.id LIMIT 20 OFFSET " . (($accountPage - 1) * 20))->fetchAll();
$search = is_string($_GET['clients'] ?? '') ? substr(trim($_GET['clients'] ?? ''), 0, 100) : '';
$stmt = $db->prepare("SELECT u.id, u.username, u.full_name, (SELECT COUNT(*) FROM accounts WHERE user_id = u.id) AS account_count, (SELECT COALESCE(SUM(balance), 0) FROM accounts WHERE user_id = u.id) AS total_client_balance FROM users u WHERE u.is_admin = 0 AND (u.full_name LIKE ? OR u.username LIKE ?) ORDER BY u.username LIMIT 100");
$stmt->execute(['%' . $search . '%', '%' . $search . '%']); $clients = $stmt->fetchAll();
$tx_count = (int)$db->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
$auditPage = min($auditPage, max(1, (int)ceil($tx_count / 20)));
$recent_tx = $db->query('SELECT t.*, a.account_number, u.username FROM transactions t JOIN accounts a ON t.account_id = a.id JOIN users u ON a.user_id = u.id ORDER BY t.timestamp DESC, t.id DESC LIMIT 20 OFFSET ' . (($auditPage - 1) * 20))->fetchAll();
$events = $db->query('SELECT ev.*, u.username FROM audit_events ev LEFT JOIN users u ON ev.actor_id = u.id ORDER BY ev.id DESC LIMIT 20')->fetchAll();
require_once __DIR__ . '/views/layout/header.php';
?>
<div class="dashboard-header">
<div>
<p class="eyebrow">Operations</p>
<h2 class="hero-title">Admin Command Center</h2>
<p class="text-secondary">Review requests, manage clients, and trace decisions.</p>
</div>
<a href="#pending-transactions" class="btn btn-primary">Review pending requests</a>
</div>
<div id="admin-alert" aria-live="polite">
</div>
<div class="accounts-grid summary-grid">
    <div class="card account-card">
<h3>Clients</h3>
<p class="balance">
<?php echo e($user_count); ?>
</p>
</div>
    <div class="card account-card">
<h3>Total Account Balances</h3>
<p class="balance">
<?php echo e(getCurrencySymbol() . number_format($total_balance, 2)); ?>
</p>
<p class="text-secondary text-sm">Display symbol preference only</p>
</div>
    <div class="card account-card">
<h3>Pending Transactions</h3>
<p class="balance">
<?php echo e($pending_count); ?>
</p>
</div>
</div>
<section class="card" id="pending-transactions">
    <div class="section-heading">
<h3 class="card-title">Transaction Approval Queue</h3>
<span class="text-secondary text-sm">Oldest requests first</span>
</div>
    <?php if (!$pending_tx): ?>
<p class="text-secondary">No transactions awaiting review.</p>
<?php else: ?>
    <div class="table-container" tabindex="0" role="region" aria-label="Pending transactions">
<table class="history-table">
<thead>
<tr>
<th>Request</th>
<th>Source &amp; Recipient</th>
<th>Amount</th>
<th>Review</th>
</tr>
</thead>
<tbody>
    <?php foreach ($pending_tx as $tx): $summary = 'TX-' . $tx['id'] . ' · ' . $tx['transaction_type'] . ' · ' . getCurrencySymbol() . $tx['amount'] . ' · ' . $tx['account_number'] . ($tx['recipient_number'] ? ' → ' . $tx['recipient_number'] : ''); ?>
    <tr>
<td>
<strong>
<?php echo e('TX-' . $tx['id'] . ' · ' . $tx['username']); ?>
</strong>
<br>
<span class="text-sm text-secondary">
<?php echo e($tx['transaction_type'] . ' · ' . $tx['timestamp']); ?>
</span>
<br>
<?php echo e($tx['description']); ?>
</td>
        <td>
<?php echo e($tx['account_number']); ?>
<br>
<span class="text-secondary text-sm">Balance: <?php echo e(getCurrencySymbol() . $tx['balance']); ?>
</span>
<?php if ($tx['recipient_number']): ?>
<br>To: <?php echo e($tx['recipient_number']); ?>
<?php endif; ?>
</td>
        <td class="font-bold nowrap">
<?php echo e(getCurrencySymbol() . number_format($tx['amount'], 2)); ?>
</td>
        <td>
<div class="row-actions">
<?php foreach (['APPROVE' => 'Approve', 'REJECT' => 'Reject'] as $action => $label): ?>
<button type="button" class="btn btn-small <?php echo $action === 'APPROVE' ? 'btn-primary' : 'btn-secondary'; ?>" data-review-kind="transaction" data-id="<?php echo e($tx['id']); ?>" data-action="<?php echo $action; ?>" data-summary="<?php echo e($summary); ?>">
<?php echo $label; ?>
</button>
<?php endforeach; ?>
</div>
</td>
</tr>
    <?php endforeach; ?>
</tbody>
</table>
</div>
    <div class="pagination">
<?php if ($txPage > 1): ?>
<a class="btn btn-secondary" href="<?php echo e(pageLink('tx_page', $txPage - 1, 'pending-transactions')); ?>">Previous</a>
<?php endif; ?>
<span>Page <?php echo $txPage; ?>
</span>
<?php if ($txPage * 20 < $pending_count): ?>
<a class="btn btn-secondary" href="<?php echo e(pageLink('tx_page', $txPage + 1, 'pending-transactions')); ?>">Next</a>
<?php endif; ?>
</div>
    <?php endif; ?>
</section>
<section class="card" id="pending-accounts">
<h3 class="card-title">Account Approval Queue <span class="text-secondary">(<?php echo e($pending_account_count); ?>)</span>
</h3>
    <?php if (!$pending_accounts): ?>
<p class="text-secondary">No accounts awaiting review.</p>
<?php else: ?>
    <div class="table-container" tabindex="0" role="region" aria-label="Pending accounts">
<table class="history-table">
<thead>
<tr>
<th>Client</th>
<th>Account</th>
<th>Submitted</th>
<th>Review</th>
</tr>
</thead>
<tbody>
    <?php foreach ($pending_accounts as $acc): ?>
<tr>
<td>
<?php echo e($acc['username']); ?>
</td>
<td>
<?php echo e($acc['account_number'] . ' · ' . $acc['account_type']); ?>
</td>
<td>
<?php echo e($acc['created_at']); ?>
</td>
<td>
<div class="row-actions">
<?php foreach (['APPROVE' => 'Approve', 'REJECT' => 'Reject'] as $action => $label): ?>
<button type="button" class="btn btn-small <?php echo $action === 'APPROVE' ? 'btn-primary' : 'btn-secondary'; ?>" data-review-kind="account" data-id="<?php echo e($acc['id']); ?>" data-action="<?php echo $action; ?>" data-summary="<?php echo e($acc['username'] . ' · ' . $acc['account_type'] . ' · ' . $acc['account_number']); ?>">
<?php echo $label; ?>
</button>
<?php endforeach; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
    <div class="pagination">
<?php if ($accountPage > 1): ?>
<a class="btn btn-secondary" href="<?php echo e(pageLink('account_page', $accountPage - 1, 'pending-accounts')); ?>">Previous</a>
<?php endif; ?>
<span>Page <?php echo $accountPage; ?>
</span>
<?php if ($accountPage * 20 < $pending_account_count): ?>
<a class="btn btn-secondary" href="<?php echo e(pageLink('account_page', $accountPage + 1, 'pending-accounts')); ?>">Next</a>
<?php endif; ?>
</div>
    <?php endif; ?>
</section>
<section class="card" id="clients">
<h3 class="card-title">Client Financial Overview</h3>
    <form class="filter-bar" method="get" action="admin.php#clients">
<div>
<label for="client-search">Find a client</label>
<input type="search" id="client-search" name="clients" maxlength="100" value="<?php echo e($search); ?>" placeholder="Name or username">
</div>
<button class="btn btn-primary" type="submit">Search</button>
<a class="btn btn-secondary" href="admin.php#clients">Reset</a>
</form>
    <p class="text-secondary text-sm mb-4">Up to 100 matching clients. Refine your search for more specific results.</p>
    <div class="table-container" tabindex="0" role="region" aria-label="Clients">
<table class="history-table">
<thead>
<tr>
<th>Name</th>
<th>Username</th>
<th>Accounts</th>
<th>Total Balance</th>
<th>Manage</th>
</tr>
</thead>
<tbody>
    <?php foreach ($clients as $client): ?>
<tr>
<td>
<?php echo e($client['full_name']); ?>
</td>
<td>
<?php echo e($client['username']); ?>
</td>
<td>
<?php echo e($client['account_count']); ?>
</td>
<td>
<?php echo e(getCurrencySymbol() . number_format($client['total_client_balance'], 2)); ?>
</td>
<td>
<button type="button" class="btn btn-secondary btn-small" data-edit-client="<?php echo e($client['id']); ?>" data-name="<?php echo e($client['full_name']); ?>">Edit name</button>
</td>
</tr>
<?php endforeach; ?>
    <?php if (!$clients): ?>
<tr>
<td colspan="5">No matching clients.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
<section class="card" id="transaction-audit">
<h3 class="card-title">Transaction Records</h3>
<p class="text-secondary mb-4">Posted records and original timestamps are retained. Rejections include the review reason.</p>
    <div class="table-container" tabindex="0" role="region" aria-label="Transaction records">
<table class="history-table">
<thead>
<tr>
<th>Reference / Submitted</th>
<th>Client / Account</th>
<th>Type</th>
<th>Amount</th>
<th>Status / Review</th>
</tr>
</thead>
<tbody>
    <?php foreach ($recent_tx as $tx): ?>
<tr>
<td>
<strong>
<?php echo e('TX-' . $tx['id']); ?>
</strong>
<br>
<?php echo e($tx['timestamp']); ?>
</td>
<td>
<?php echo e($tx['username']); ?>
<br>
<?php echo e(maskAccountNumber($tx['account_number'])); ?>
</td>
<td>
<?php echo e($tx['transaction_type']); ?>
</td>
<td class="nowrap">
<?php echo e(getCurrencySymbol() . number_format($tx['amount'], 2)); ?>
</td>
<td>
<span class="badge <?php echo $tx['status'] === 'APPROVED' ? 'badge-deposit' : ($tx['status'] === 'PENDING' ? 'badge-transfer_out' : 'badge-withdrawal'); ?>">
<?php echo e($tx['status']); ?>
</span>
<br>
<?php echo e($tx['reviewed_at']); ?>
<br>
<?php echo e($tx['review_reason']); ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
    <div class="pagination">
<?php if ($auditPage > 1): ?>
<a class="btn btn-secondary" href="<?php echo e(pageLink('audit_page', $auditPage - 1, 'transaction-audit')); ?>">Previous</a>
<?php endif; ?>
<span>Page <?php echo $auditPage; ?>
</span>
<?php if ($auditPage * 20 < $tx_count): ?>
<a class="btn btn-secondary" href="<?php echo e(pageLink('audit_page', $auditPage + 1, 'transaction-audit')); ?>">Next</a>
<?php endif; ?>
</div>
</section>
<section class="card">
<h3 class="card-title">Recent Audit Events</h3>
<div class="table-container" tabindex="0" role="region" aria-label="Audit events">
<table class="history-table">
<thead>
<tr>
<th>Time</th>
<th>Actor</th>
<th>Event</th>
<th>Record</th>
</tr>
</thead>
<tbody>
<?php foreach ($events as $event): ?>
<tr>
<td>
<?php echo e($event['created_at']); ?>
</td>
<td>
<?php echo e($event['username'] ?? $event['actor_id']); ?>
</td>
<td>
<?php echo e($event['event_type']); ?>
</td>
<td>
<?php echo e($event['entity_type'] . ' #' . $event['entity_id']); ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$events): ?>
<tr>
<td colspan="4">No recorded events yet.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
<section class="card">
<h3 class="card-title">Communication Hub</h3>
<div id="announcement-alert" aria-live="polite">
</div>
    <form id="announcement-form">
<div class="form-columns">
<div class="form-group">
<label for="ann-target">Audience</label>
<select id="ann-target" name="user_id">
<option value="">All users</option>
<?php foreach ($clients as $client): ?>
<option value="<?php echo e($client['id']); ?>">
<?php echo e($client['username']); ?>
</option>
<?php endforeach; ?>
</select>
<p class="text-secondary text-sm">Find a client above to narrow the recipient list.</p>
</div>
<div class="form-group">
<label for="ann-title">Title</label>
<input type="text" id="ann-title" name="title" maxlength="255" required>
</div>
</div>
<div class="form-group">
<label for="ann-content">Message</label>
<textarea id="ann-content" name="content" rows="4" maxlength="4000" required>
</textarea>
</div>
<button class="btn btn-primary" type="submit">Send announcement</button>
</form>
</section>
<dialog id="review-modal" aria-labelledby="review-title">
<div class="card">
<h3 id="review-title" class="card-title">Review request</h3>
<p id="review-summary" class="mb-4">
</p>
<p class="form-note" id="review-note">
</p>
<div id="review-alert" aria-live="polite">
</div>
<form id="review-form">
<div class="form-group" id="review-reason-group">
<label for="review-reason">Rejection reason</label>
<textarea id="review-reason" minlength="3" maxlength="500" rows="3">
</textarea>
</div>
<div class="row-actions">
<button class="btn btn-primary" type="submit" id="review-confirm">Confirm</button>
<button class="btn btn-secondary" type="button" data-close-dialog>Cancel</button>
</div>
</form>
</div>
</dialog>
<dialog id="admin-edit-modal" aria-labelledby="edit-title">
<div class="card">
<h3 id="edit-title" class="card-title">Edit client name</h3>
<div id="edit-alert" aria-live="polite">
</div>
<form id="admin-edit-form">
<div class="form-group">
<label for="edit-value">Full name</label>
<input type="text" id="edit-value" maxlength="100" required>
</div>
<div class="row-actions">
<button class="btn btn-primary" type="submit">Save name</button>
<button class="btn btn-secondary" type="button" data-close-dialog>Cancel</button>
</div>
</form>
</div>
</dialog>
<script src="assets/js/admin.js" defer>
</script>
<?php require_once __DIR__ . '/views/layout/footer.php'; ?>
