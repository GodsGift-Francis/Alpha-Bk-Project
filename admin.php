<?php
// admin.php
require_once __DIR__ . '/views/layout/header.php';
requireAdmin();

$db = require __DIR__ . '/config/database.php';

// Fetch stats
$user_count = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
$account_count = $db->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
$total_balance = $db->query("SELECT SUM(balance) FROM accounts")->fetchColumn() ?: 0;
$tx_count = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();

// Fetch ALL Client Users (Excluding Admins) for Financial Overview
$all_clients = $db->query("
    SELECT u.id, u.username, u.email, u.full_name, u.created_at,
           (SELECT COUNT(*) FROM accounts WHERE user_id = u.id) as account_count,
           (SELECT SUM(balance) FROM accounts WHERE user_id = u.id) as total_client_balance
    FROM users u 
    WHERE u.is_admin = 0 
    ORDER BY u.username ASC
")->fetchAll();

// Fetch All Accounts for Deletion/Management
$all_accounts = $db->query("
    SELECT a.*, u.username 
    FROM accounts a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC
")->fetchAll();

// Fetch Pending Requests
$pending_tx = $db->query("SELECT t.*, a.account_number, u.username FROM transactions t JOIN accounts a ON t.account_id = a.id JOIN users u ON a.user_id = u.id WHERE t.status = 'PENDING' ORDER BY t.timestamp ASC")->fetchAll();
$pending_accounts = $db->query("SELECT a.*, u.username FROM accounts a JOIN users u ON a.user_id = u.id WHERE a.status = 'PENDING' ORDER BY a.created_at ASC")->fetchAll();

// Fetch ALL Recent Transactions (System-wide)
$recent_tx = $db->query("
    SELECT t.*, a.account_number, u.username 
    FROM transactions t 
    JOIN accounts a ON t.account_id = a.id 
    JOIN users u ON a.user_id = u.id 
    ORDER BY t.timestamp DESC 
    LIMIT 15
")->fetchAll();
?>

<div class="dashboard-header">
    <div>
        <h2 class="hero-title" style="font-size: 1.875rem;">Admin Command Center</h2>
        <p class="text-secondary">System-wide management and monitoring</p>
    </div>
</div>

<div class="accounts-grid mb-8">
    <div class="card account-card" style="border-top-color: var(--secondary-color);">
        <h3>Total Clients</h3>
        <p class="balance"><?php echo $user_count; ?></p>
    </div>
    <div class="card account-card" style="border-top-color: var(--accent-color);">
        <h3>Global Deposits</h3>
        <p class="balance"><?php echo getCurrencySymbol(); ?><?php echo number_format($total_balance, 2); ?></p>
    </div>
    <div class="card account-card" style="border-top-color: var(--warning-color);">
        <h3>Total Records</h3>
        <p class="balance"><?php echo $tx_count; ?></p>
    </div>
</div>

<!-- SECTION: PENDING APPROVALS -->
<div class="accounts-grid">
    <div class="card">
        <h3 class="card-title">Pending Accounts</h3>
        <?php if (empty($pending_accounts)): ?>
            <p class="text-secondary text-sm">No new requests.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="history-table">
                    <tbody>
                        <?php foreach ($pending_accounts as $acc): ?>
                        <tr>
                            <td class="text-sm font-semibold"><?php echo htmlspecialchars($acc['username']); ?></td>
                            <td class="text-sm"><?php echo htmlspecialchars($acc['account_type']); ?></td>
                            <td>
                                <button onclick="processAccount(<?php echo $acc['id']; ?>, 'APPROVE')" class="btn btn-primary" style="padding: 2px 8px; font-size: 0.7rem;">Approve</button>
                                <button onclick="processAccount(<?php echo $acc['id']; ?>, 'REJECT')" class="btn btn-secondary" style="padding: 2px 8px; font-size: 0.7rem; background: var(--danger-color);">Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 class="card-title">Pending Transactions</h3>
        <?php if (empty($pending_tx)): ?>
            <p class="text-secondary text-sm">No pending money moves.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="history-table">
                    <tbody>
                        <?php foreach ($pending_tx as $tx): ?>
                        <tr>
                            <td class="text-sm font-semibold"><?php echo htmlspecialchars($tx['username']); ?></td>
                            <td class="text-sm"><?php echo getCurrencySymbol().number_format($tx['amount'], 2); ?></td>
                            <td>
                                <button onclick="processTx(<?php echo $tx['id']; ?>, 'APPROVE')" class="btn btn-primary" style="padding: 2px 8px; font-size: 0.7rem;">Approve</button>
                                <button onclick="processTx(<?php echo $tx['id']; ?>, 'REJECT')" class="btn btn-secondary" style="padding: 2px 8px; font-size: 0.7rem; background: var(--danger-color);">Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SECTION: FINANCIAL OVERVIEW (CLIENT LIST) -->
<div class="card mt-8">
    <h3 class="card-title">Client Financial Overview</h3>
    <div class="table-container">
        <table class="history-table">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Accounts</th>
                    <th>Total Balance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_clients as $c): ?>
                <tr>
                    <td class="font-semibold">
                        <?php echo htmlspecialchars($c['full_name']); ?>
                        <button onclick="openEditModal(<?php echo $c['id']; ?>, 'user', 'full_name', '<?php echo addslashes($c['full_name']); ?>')" style="background:none; border:none; cursor:pointer; font-size:0.8rem; margin-left:5px; color:var(--secondary-color);">✏️</button>
                    </td>
                    <td class="text-secondary"><?php echo htmlspecialchars($c['username']); ?></td>
                    <td><span class="badge badge-transfer_in"><?php echo $c['account_count']; ?> Accounts</span></td>
                    <td class="font-bold"><?php echo getCurrencySymbol().number_format($c['total_client_balance'] ?: 0, 2); ?></td>
                    <td>
                        <button onclick="adminManage(<?php echo $c['id']; ?>, 'user')" class="btn btn-secondary" style="background: var(--danger-color); padding: 5px 10px; font-size: 0.75rem;">Delete User</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION: RECENT TRANSACTIONS (HISTORY) -->
<div class="card mt-8">
    <h3 class="card-title">System-wide Transaction Audit</h3>
    <div class="table-container">
        <table class="history-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>User</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_tx as $tx): ?>
                <tr>
                    <td class="text-sm">
                        <?php echo date('M d, Y H:i', strtotime($tx['timestamp'])); ?>
                        <button onclick="openEditModal(<?php echo $tx['id']; ?>, 'transaction', 'timestamp', '<?php echo $tx['timestamp']; ?>')" style="background:none; border:none; cursor:pointer; font-size:0.8rem; margin-left:5px; color:var(--secondary-color);">✏️</button>
                    </td>
                    <td class="font-semibold"><?php echo htmlspecialchars($tx['username']); ?></td>
                    <td class="text-sm">
                        <div class="account-number-container">
                            <span class="account-number" data-full="<?php echo htmlspecialchars($tx['account_number']); ?>" data-masked="true" style="font-size: 0.85rem;"><?php echo maskAccountNumber($tx['account_number']); ?></span>
                            <button class="toggle-account-btn" onclick="toggleAccountNumber(this)" title="Show account number">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </td>
                    <td><span class="badge badge-<?php echo strtolower($tx['transaction_type']); ?>"><?php echo str_replace('_', ' ', $tx['transaction_type']); ?></span></td>
                    <td class="font-bold"><?php echo getCurrencySymbol().number_format($tx['amount'], 2); ?></td>
                    <td>
                        <span class="badge <?php 
                            echo $tx['status'] === 'APPROVED' ? 'badge-deposit' : ($tx['status'] === 'PENDING' ? 'badge-transfer_out' : 'badge-withdrawal'); 
                        ?>"><?php echo $tx['status']; ?></span>
                    </td>
                    <td>
                        <button onclick="adminManage(<?php echo $tx['id']; ?>, 'transaction')" class="btn btn-secondary" style="background: var(--danger-color); padding: 5px 10px; font-size: 0.75rem;">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION: ANNOUNCEMENTS -->
<div class="card mt-8">
    <h3 class="card-title">Communication Hub</h3>
    <form id="announcement-form">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label>Target Audience</label>
                <select id="ann_target" name="user_id">
                    <option value="">General (All Users)</option>
                    <?php foreach ($all_clients as $c): ?>
                        <option value="<?php echo $c['id']; ?>">Direct to: <?php echo htmlspecialchars($c['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Announcement Title</label>
                <input type="text" id="ann_title" name="title" placeholder="Important Update..." required>
            </div>
        </div>
        <div class="form-group">
            <label>Message Content</label>
            <textarea id="ann_content" name="content" style="width:100%; border:1px solid var(--border-color); border-radius:8px; padding:10px; font-family:inherit;" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Broadcast Message</button>
    </form>
</div>

<!-- EDIT MODAL -->
<div id="admin-edit-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div class="card max-w-sm" style="margin:0;">
        <h3 id="modal-title" class="card-title">Edit Detail</h3>
        <form id="admin-edit-form">
            <input type="hidden" id="edit_id" name="id">
            <input type="hidden" id="edit_type" name="type">
            <input type="hidden" id="edit_field" name="field">
            
            <div class="form-group">
                <label id="modal-label">New Value</label>
                <div id="input-container"></div>
            </div>
            
            <div style="display:flex; gap:1rem;">
                <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary btn-block">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Edit Modal Logic
function openEditModal(id, type, field, currentVal) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_type').value = type;
    document.getElementById('edit_field').value = field;
    
    const container = document.getElementById('input-container');
    document.getElementById('modal-title').innerText = `Update ${type === 'user' ? 'User Name' : 'Transaction Date'}`;
    
    if (field === 'timestamp') {
        container.innerHTML = `<input type="datetime-local" id="edit_value" name="value" value="${currentVal.replace(' ', 'T')}" required style="width:100%; padding:8px;">`;
    } else {
        container.innerHTML = `<input type="text" id="edit_value" name="value" value="${currentVal}" required style="width:100%; padding:8px;">`;
    }
    
    document.getElementById('admin-edit-modal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('admin-edit-modal').style.display = 'none';
}

document.getElementById('admin-edit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const result = await API.post('api/admin-edit.php', {
        id: document.getElementById('edit_id').value,
        type: document.getElementById('edit_type').value,
        field: document.getElementById('edit_field').value,
        value: document.getElementById('edit_value').value
    });
    if(result.success) { alert(result.success); location.reload(); }
    else { alert(result.error); }
});

// Announcement Post Handler
document.getElementById('announcement-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const result = await API.post('api/post-announcement.php', {
        user_id: document.getElementById('ann_target').value,
        title: document.getElementById('ann_title').value,
        content: document.getElementById('ann_content').value
    });
    if(result.success) { alert(result.success); e.target.reset(); } 
    else { alert(result.error); }
});

// Management Handlers (Delete)
async function adminManage(id, type) {
    if(!confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) return;
    const result = await API.post('api/admin-manage.php', { id, type });
    if(result.success) { alert(result.success); location.reload(); }
    else { alert(result.error); }
}

// Approval Handlers
async function processTx(id, action) {
    const result = await API.post('api/admin-tx.php', { tx_id: id, action });
    if(result.success) { alert(result.success); location.reload(); }
    else { alert(result.error); }
}

async function processAccount(id, action) {
    const result = await API.post('api/admin-accounts.php', { account_id: id, action });
    if(result.success) { alert(result.success); location.reload(); }
    else { alert(result.error); }
}
</script>

<?php require_once __DIR__ . '/views/layout/footer.php'; ?>
