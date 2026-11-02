<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/transactions.php';
$admin = apiUser(true);
apiPost();
$id = inputId('request_id'); $action = inputText('action',10); $reason = inputText('reason',500,false);
if (!in_array($action,['APPROVE','REJECT'],true)) throw new InvalidArgumentException('Invalid review action.');
if ($action === 'REJECT' && strlen($reason) < 3) throw new InvalidArgumentException('Provide a rejection reason.');
$lookup = $db->prepare('SELECT user_id FROM profile_change_requests WHERE id = ?'); $lookup->execute([$id]); $ownerId = $lookup->fetchColumn();
if (!$ownerId) throw new DomainException('Profile request not found.');
$db->beginTransaction();
// Follow submission's user-then-request lock order to serialize reviews and retries.
$owner = $db->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE'); $owner->execute([$ownerId]); $account = $owner->fetch();
$query = $db->prepare('SELECT * FROM profile_change_requests WHERE id = ? FOR UPDATE'); $query->execute([$id]); $request = $query->fetch();
if (!$request || $request['status'] !== 'PENDING') throw new DomainException('Profile request not found or already reviewed.');
if ($action === 'APPROVE') {
    if (!$account || $account['status'] !== 'ACTIVE') throw new DomainException('The account must be active before approval.');
    foreach (['full_name','username','email'] as $field) {
        if ($account[$field] !== $request['previous_'.$field]) throw new DomainException('The current profile changed after this request. Reject it and ask for a new request.');
    }
    try { $db->prepare('UPDATE users SET full_name = ?, username = ?, email = ? WHERE id = ?')->execute([$request['full_name'],$request['username'],$request['email'],$ownerId]); }
    catch (PDOException $error) { if ($error->getCode() === '23000') throw new DomainException('The requested username or email is now in use. Reject this request and ask for another.'); throw $error; }
}
$status = $action === 'APPROVE' ? 'APPROVED' : 'REJECTED';
$db->prepare('UPDATE profile_change_requests SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(), review_reason = ? WHERE id = ?')->execute([$status,$admin,$reason ?: null,$id]);
$db->prepare('INSERT INTO announcements (user_id,title,content) VALUES (?, ?, ?)')->execute([$ownerId,'Profile change '.strtolower($status),'Your profile change PR-'.$id.' has been '.strtolower($status).'.'.($action === 'APPROVE' ? ' Use your approved username the next time you sign in.' : ' Reason: '.$reason)]);
auditEvent($db,$admin,$status,'profile_change',$id,['reason'=>$reason]);
$db->commit();
jsonResponse(['success'=>'Profile change '.strtolower($status).'.']);
