<?php
require_once __DIR__.'/includes/auth.php';
requireAdmin();
require_once __DIR__.'/includes/synthetic-history.php';
require_once __DIR__.'/includes/transactions.php';
$db = require __DIR__.'/config/database.php';
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error = ''; $message = ''; $preview = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'preview') {
            unset($_SESSION['history_preview']);
            $accountId = filter_var($_POST['account_id'] ?? '', FILTER_VALIDATE_INT);
            $description = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');
            if (!$accountId || !$description || strlen($description) > 1000) throw new InvalidArgumentException('Select an account and enter a description (up to 1,000 bytes).');
            $stmt = $db->prepare('SELECT a.* FROM accounts a JOIN users u ON u.id=a.user_id WHERE a.id=? AND u.is_admin=0'); $stmt->execute([$accountId]); $account = $stmt->fetch();
            if (!$account) throw new InvalidArgumentException('Select an existing customer account.');
            if (($_POST['source'] ?? 'upload') === 'generate') {
                $count=filter_var($_POST['count']??'',FILTER_VALIDATE_INT);
                if (!$count || !is_string($_POST['start']??null) || !is_string($_POST['end']??null)) throw new InvalidArgumentException('Enter a valid count and date range.');
                $data=generateSyntheticHistory(moneyCents($account['balance']),$count,$_POST['start'],$_POST['end']);
            } else {
                $file = $_FILES['dataset'] ?? [];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 2000000) throw new InvalidArgumentException('Upload a JSON file under 2 MB.');
                $data = validateSyntheticHistory(file_get_contents($file['tmp_name']));
            }
            if (!$account || moneyCents($account['balance']) !== $data['closing_balance_cents']) throw new InvalidArgumentException('Dataset closing balance must equal the selected account balance. No balances have been changed.');
            $preview = ['account_id'=>$accountId,'description'=>$description,'data'=>$data,'token'=>bin2hex(random_bytes(24)), 'expires'=>time()+900];
            $_SESSION['history_preview'] = $preview;
        } elseif ($action === 'save') {
            $p = $_SESSION['history_preview'] ?? null;
            if (!$p || $p['expires'] < time() || !is_string($_POST['preview_token'] ?? null) || !hash_equals($p['token'], $_POST['preview_token'])) throw new InvalidArgumentException('Preview expired. Upload again.');
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT balance FROM accounts WHERE id=? FOR UPDATE'); $stmt->execute([$p['account_id']]); $balance = $stmt->fetchColumn();
            if ($balance === false || moneyCents($balance) !== $p['data']['closing_balance_cents']) throw new InvalidArgumentException('Account balance changed. Preview the dataset again.');
            $json = json_encode($p['data'], JSON_THROW_ON_ERROR);
            $db->prepare('INSERT INTO synthetic_history_batches (account_id,created_by,description,dataset_hash,dataset) VALUES (?,?,?,?,?)')->execute([$p['account_id'],$_SESSION['user_id'],$p['description'],hash('sha256',$json),$json]);
            auditEvent($db,(int)$_SESSION['user_id'],'SYNTHETIC_IMPORTED','synthetic_batch',(int)$db->lastInsertId(),['account_id'=>$p['account_id'],'count'=>count($p['data']['transactions'])]);
            $db->commit(); unset($_SESSION['history_preview']);
            header('Location: admin-history.php?saved=1'); exit;
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['batch_id'] ?? '',FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Invalid batch.');
            $db->beginTransaction();
            $stmt = $db->prepare('DELETE FROM synthetic_history_batches WHERE id=?'); $stmt->execute([$id]);
            if ($stmt->rowCount()) auditEvent($db,(int)$_SESSION['user_id'],'SYNTHETIC_DELETED','synthetic_batch',$id,[]);
            $db->commit(); header('Location: admin-history.php?removed=1'); exit;
        } else throw new InvalidArgumentException('Invalid action.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Unable to save. An account can have only one dataset; remove its existing batch before importing another.';
    }
}
$accounts = $db->query('SELECT a.id,a.account_number,a.balance,u.full_name FROM accounts a JOIN users u ON u.id=a.user_id WHERE u.is_admin=0 ORDER BY u.full_name,a.id')->fetchAll();
$batches = $db->query('SELECT b.id,b.description,a.account_number FROM synthetic_history_batches b JOIN accounts a ON a.id=b.account_id ORDER BY b.id DESC')->fetchAll();
require __DIR__.'/views/layout/header.php';
?>
<div class="dashboard-header"><div><p class="eyebrow">Admin tools</p><h1>Dataset history</h1><p class="text-secondary">Describe, preview and publish a separate history dataset. Actual transactions and balances remain unchanged.</p></div><a class="btn" href="admin.php">Admin center</a></div>
<?php if ($error): ?><p role="alert"><?=h($error)?></p><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><p role="status">Dataset saved and available in the customer's dataset history.</p><?php endif; ?>
<?php if (isset($_GET['removed'])): ?><p role="status">Dataset removed.</p><?php endif; ?>
<section class="card"><h2>Dataset description</h2><form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?=h(getCsrfToken())?>"><input type="hidden" name="action" value="preview">
<div class="form-group"><label for="history-account">Customer account</label><select id="history-account" name="account_id" required><option value="">Select account</option><?php foreach ($accounts as $a): ?><option value="<?=(int)$a['id']?>"><?=h($a['full_name'].' · '.$a['account_number'].' · USD '.$a['balance'])?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="dataset-description">Describe this dataset</label><textarea id="dataset-description" name="description" maxlength="1000" rows="4" required placeholder="Purpose and context for this batch; visible alongside its history."></textarea></div>
<div class="form-group"><label for="dataset-source">Data source</label><select id="dataset-source" name="source"><option value="upload">Upload JSON dataset</option><option value="generate">Generate random dataset</option></select></div>
<div class="form-group"><label for="history-file">Transaction dataset (for upload, maximum 2 MB)</label><input id="history-file" type="file" name="dataset" accept=".json,application/json"></div>
<fieldset><legend>Random generation options</legend><p class="text-secondary">Used only when generating. An opening balance is calculated so the batch reconciles to the current account balance. This may include a negative opening or running balance.</p><div class="form-group"><label for="history-count">Transaction count</label><input id="history-count" name="count" type="number" min="1" max="1000" value="100"></div><div class="form-group"><label for="history-start">Start date</label><input id="history-start" name="start" type="date" min="2001-01-01" max="<?=gmdate('Y-m-d')?>" value="2001-01-01"></div><div class="form-group"><label for="history-end">End date</label><input id="history-end" name="end" type="date" min="2001-01-01" max="<?=gmdate('Y-m-d')?>" value="<?=gmdate('Y-m-d')?>"></div></fieldset><button class="btn btn-primary">Validate and preview</button></form></section>
<?php if ($preview): $d=$preview['data']; ?><section class="card"><h2>Import preview</h2><p><?=h($preview['description'])?></p><p><?=count($d['transactions'])?> transactions · <?=h($d['transactions'][0]['date'])?> to <?=h($d['transactions'][count($d['transactions'])-1]['date'])?></p><p>Opening: USD <?=h(syntheticMoney($d['opening_balance_cents']))?> · Closing: USD <?=h(syntheticMoney($d['closing_balance_cents']))?></p><p>All running balances reconcile. A discreet Synthetic data note appears beneath history and on documents.</p><form method="post"><input type="hidden" name="csrf_token" value="<?=h(getCsrfToken())?>"><input type="hidden" name="preview_token" value="<?=h($preview['token'])?>"><input type="hidden" name="action" value="save"><button class="btn btn-primary">Save dataset to selected account</button> <a href="admin-history.php">Cancel</a></form></section><?php endif; ?>
<section class="card"><h2>Saved batches</h2><?php if (!$batches): ?><p>No datasets imported.</p><?php endif; ?><?php foreach ($batches as $b): ?><article><h3><?=h($b['account_number'])?></h3><p><?=h($b['description'])?></p><a href="dataset-history.php?batch=<?=(int)$b['id']?>">View history</a><form method="post"><input type="hidden" name="csrf_token" value="<?=h(getCsrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="batch_id" value="<?=(int)$b['id']?>"><button class="btn">Remove dataset batch</button></form></article><?php endforeach; ?></section>
<?php require __DIR__.'/views/layout/footer.php'; ?>

