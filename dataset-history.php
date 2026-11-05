<?php
require_once __DIR__.'/includes/auth.php';
requireLogin();
require_once __DIR__.'/includes/synthetic-history.php';
$db = require __DIR__.'/config/database.php';
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$stmt = $db->prepare('SELECT b.*,a.account_number FROM synthetic_history_batches b JOIN accounts a ON a.id=b.account_id WHERE '.(isAdmin() ? '1=1' : 'a.user_id=?').' ORDER BY b.id DESC');
$stmt->execute(isAdmin() ? [] : [(int)$_SESSION['user_id']]); $batches=$stmt->fetchAll();
$id=filter_var($_GET['batch'] ?? '',FILTER_VALIDATE_INT); $batch=null;
foreach ($batches as $b) if ((int)$b['id']===$id) $batch=$b;
if (isset($_GET['batch']) && !$batch) { http_response_code(404); exit('Dataset not found.'); }
$data=$batch ? json_decode($batch['dataset'],true,64,JSON_THROW_ON_ERROR) : null;
if ($data && ($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json'); header('Content-Disposition: attachment; filename="synthetic-history.json"');
    $data['description']=$batch['description']; $data['notice']='Synthetic data';
    echo json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR); exit;
}
$invoice=null;
if (isset($_GET['invoice'])) {
    $index=filter_var($_GET['invoice'],FILTER_VALIDATE_INT);
    if (!$data || $index===false || !isset($data['transactions'][$index])) { http_response_code(404); exit('Document not found.'); }
    $invoice=$data['transactions'][$index];
}
require __DIR__.'/views/layout/header.php';
?>
<div class="dashboard-header"><div><p class="eyebrow">Account records</p><h1><?= $invoice ? 'Transaction document' : 'Dataset history' ?></h1></div><a class="btn" href="<?=isAdmin()?'admin-history.php':'dashboard.php'?>">Back to overview</a></div>
<?php if (!$batch): ?><section class="card"><h2>Available datasets</h2><?php if (!$batches): ?><p>No datasets available.</p><?php endif; ?><?php foreach ($batches as $b): ?><p><a href="?batch=<?=(int)$b['id']?>"><?=h($b['account_number'].' · '.$b['description'])?></a></p><?php endforeach; ?></section>
<?php elseif ($invoice): ?><section class="card"><h2><?=h($invoice['reference'])?></h2><dl><?php foreach (['date'=>'Date','type'=>'Type','direction'=>'Direction','counterparty'=>'Counterparty','description'=>'Description'] as $key=>$label): ?><dt><?=h($label)?></dt><dd><?=h($invoice[$key])?></dd><?php endforeach; ?><dt>Amount</dt><dd>USD <?=h(syntheticMoney($invoice['amount_cents']))?></dd><dt>Status</dt><dd>Generated record; no payment processed</dd></dl><p class="text-secondary text-sm">Synthetic data · <?=h($batch['description'])?></p><a href="?batch=<?=(int)$batch['id']?>">Back to history</a></section>
<?php else:
$page=max(1,min((int)($_GET['page']??1),(int)ceil(count($data['transactions'])/25)));
$rows=array_slice($data['transactions'],($page-1)*25,25,true);
?><section class="card"><h2>Account <?=h($batch['account_number'])?></h2><p><?=h($batch['description'])?></p><p>Opening balance USD <?=h(syntheticMoney($data['opening_balance_cents']))?> · Closing balance USD <?=h(syntheticMoney($data['closing_balance_cents']))?></p><p class="text-secondary text-sm">Balance snapshot at import; subsequent account activity is recorded separately.</p><div class="table-container" tabindex="0" role="region" aria-label="Dataset transactions"><table class="history-table"><thead><tr><th>Date</th><th>Counterparty / description</th><th>Type</th><th>Amount (USD)</th><th>Running balance</th><th>Document</th></tr></thead><tbody><?php foreach ($rows as $index=>$row): ?><tr><td><?=h($row['date'])?></td><td><?=h($row['counterparty'])?><br><?=h($row['description'])?></td><td><?=h($row['type'])?></td><td><?= $row['direction']==='debit' ? '−' : '+' ?><?=h(syntheticMoney($row['amount_cents']))?></td><td><?=h(syntheticMoney($row['running_balance_cents']))?></td><td><a href="?batch=<?=(int)$batch['id']?>&amp;invoice=<?=(int)$index?>">View document</a></td></tr><?php endforeach; ?></tbody></table></div><p class="text-secondary text-sm">Synthetic data</p><nav aria-label="Dataset pages"><?php if ($page>1): ?><a href="?batch=<?=(int)$batch['id']?>&amp;page=<?=$page-1?>">Previous</a><?php endif; ?> <span>Page <?=$page?> of <?=(int)ceil(count($data['transactions'])/25)?></span> <?php if ($page*25<count($data['transactions'])): ?><a href="?batch=<?=(int)$batch['id']?>&amp;page=<?=$page+1?>">Next</a><?php endif; ?></nav><p><a href="?batch=<?=(int)$batch['id']?>&amp;export=json">Download JSON</a></p></section><?php endif; ?>
<?php require __DIR__.'/views/layout/footer.php'; ?>

