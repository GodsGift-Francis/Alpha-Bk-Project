<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
    http_response_code(404); exit('Invoice not found.');
}
$db = require __DIR__ . '/config/database.php';
$stmt = $db->prepare('SELECT t.*, a.account_number, a.user_id, u.full_name FROM transactions t JOIN accounts a ON a.id = t.account_id JOIN users u ON u.id = a.user_id WHERE t.id = ?' . (isAdmin() ? '' : ' AND a.user_id = ?'));
$stmt->execute(isAdmin() ? [$id] : [$id, $_SESSION['user_id']]);
$tx = $stmt->fetch();
if (!$tx) { http_response_code(404); exit('Invoice not found.'); }
function invoiceEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
require __DIR__ . '/views/layout/header.php';
?>
<div class="section-heading invoice-actions">
    <a href="<?php echo isAdmin() ? 'admin.php' : 'dashboard.php'; ?>" class="btn btn-secondary">Back to overview</a>
    <button type="button" class="btn btn-primary" id="print-invoice">Print / save PDF</button>
</div>
<article class="card invoice-sheet">
    <div class="section-heading"><div><p class="eyebrow">AlphaBank · transaction record</p><h2 class="hero-title">Transaction invoice</h2></div><strong class="invoice-reference">TX-<?php echo invoiceEscape($tx['id']); ?></strong></div>
    <p class="invoice-amount"><?php echo invoiceEscape(getCurrencySymbol() . $tx['amount']); ?></p>
    <p class="text-secondary">Display currency: <?php echo invoiceEscape($_SESSION['currency'] ?? 'USD'); ?>. Your currency preference does not convert the amount.</p>
    <dl class="invoice-details">
        <dt>Account holder</dt><dd><?php echo invoiceEscape($tx['full_name']); ?></dd>
        <dt>Account</dt><dd><?php echo invoiceEscape(maskAccountNumber($tx['account_number'])); ?></dd>
        <dt>Transaction type</dt><dd><?php echo invoiceEscape(str_replace('_', ' ', $tx['transaction_type'])); ?></dd>
        <dt>Current status</dt><dd><span class="badge"><?php echo invoiceEscape($tx['status']); ?></span></dd>
        <dt>Submitted (UTC)</dt><dd><?php echo invoiceEscape($tx['timestamp']); ?></dd>
        <dt>Reviewed (UTC)</dt><dd><?php echo invoiceEscape($tx['reviewed_at'] ?: 'Not yet reviewed'); ?></dd>
        <dt>Description</dt><dd><?php echo invoiceEscape($tx['description'] ?: '—'); ?></dd>
        <?php if ($tx['review_reason']): ?><dt>Review reason</dt><dd><?php echo invoiceEscape($tx['review_reason']); ?></dd><?php endif; ?>
    </dl>
    <p class="form-note"><?php echo $tx['status'] === 'APPROVED' ? 'Posted to the account ledger.' : ($tx['status'] === 'PENDING' ? 'Awaiting review. This request has not changed the account balance.' : 'This request was rejected. No funds were posted.'); ?> This is a transaction record, not a tax invoice or proof of external payment settlement. Status reflects the record when this page was opened.</p>
</article>
<script src="assets/js/invoice.js"></script>
<?php require __DIR__ . '/views/layout/footer.php'; ?>
