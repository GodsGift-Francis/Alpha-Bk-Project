<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/transactions.php';
$user = apiUser();
apiPost();
$action = inputText('action', 20, false) ?: 'display';
if ($action === 'display') {
    $currency = inputText('currency', 3);
    $appearance = $_POST['appearance'] ?? ($_SESSION['appearance'] ?? 'system');
    $density = $_POST['density'] ?? ($_SESSION['density'] ?? 'comfortable');
    if (!in_array($currency, ['USD','EUR','GBP','NGN','JPY'], true) || !in_array($appearance, ['system','light','dark'], true) || !in_array($density, ['comfortable','compact'], true)) throw new InvalidArgumentException('Choose valid display preferences.');
    $flags = [];
    foreach (['reduce_motion','hide_balances'] as $name) {
        $value = $_POST[$name] ?? ($_SESSION[$name] ?? 0);
        if (!is_scalar($value) || !in_array((string)$value, ['0','1'], true)) throw new InvalidArgumentException('Choose valid privacy and accessibility preferences.');
        $flags[$name] = (int)$value;
    }
    $db->prepare('UPDATE users SET currency = ?, appearance = ?, density = ?, reduce_motion = ?, hide_balances = ? WHERE id = ?')->execute([$currency,$appearance,$density,$flags['reduce_motion'],$flags['hide_balances'],$user]);
    $_SESSION = array_replace($_SESSION, ['currency'=>$currency,'appearance'=>$appearance,'density'=>$density] + $flags);
    jsonResponse(['success'=>'Preferences saved. Currency changes affect the display symbol only.']);
}
if (!in_array($action, ['profile','password'], true)) throw new InvalidArgumentException('Unknown settings action.');
$currentPassword = $_POST['current_password'] ?? '';
if (!is_string($currentPassword) || strlen($currentPassword) > 1024) throw new InvalidArgumentException('Enter your current password.');
$db->beginTransaction();
$query = $db->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE'); $query->execute([$user]); $account = $query->fetch();
if (!$account || $account['status'] !== 'ACTIVE') throw new DomainException('Account unavailable.');
if ($action === 'profile') {
    $name = inputText('full_name',100); $username = inputText('username',50); $email = inputText('email',100);
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/D',$username) || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid username and email address.');
    $identityChanged = $username !== $account['username'] || $email !== $account['email'];
    if ($identityChanged && !password_verify($currentPassword,$account['password_hash'])) jsonResponseAfterRollback($db,'Your current password is required to change your username or email.',403);
    if ($name === $account['full_name'] && $username === $account['username'] && $email === $account['email']) throw new DomainException('Change at least one profile detail before submitting.');
    $pending = $db->prepare("SELECT * FROM profile_change_requests WHERE user_id = ? AND status = 'PENDING' ORDER BY id LIMIT 1 FOR UPDATE");
    $pending->execute([$user]); $existing = $pending->fetch();
    if ($existing) {
        if ($existing['full_name'] === $name && $existing['username'] === $username && $existing['email'] === $email) {
            $db->commit(); jsonResponse(['success'=>'This profile change is already awaiting administrator review.', 'request_id'=>(int)$existing['id']]);
        }
        throw new DomainException('You already have a profile change awaiting review. Wait for the decision before submitting another.');
    }
    $duplicate = $db->prepare('SELECT id FROM users WHERE id <> ? AND (username = ? OR email = ?) LIMIT 1');
    $duplicate->execute([$user,$username,$email]);
    if ($duplicate->fetchColumn()) throw new DomainException('That username or email is already in use.');
    $db->prepare('INSERT INTO profile_change_requests (user_id, previous_full_name, previous_username, previous_email, full_name, username, email) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$user,$account['full_name'],$account['username'],$account['email'],$name,$username,$email]);
    $requestId = (int)$db->lastInsertId();
    auditEvent($db,$user,'REQUESTED','profile_change',$requestId,[]);
    $db->commit();
    jsonResponse(['success'=>'Profile change submitted for administrator approval. Your current details remain unchanged until approval.', 'request_id'=>$requestId],201);
}
if (!password_verify($currentPassword,$account['password_hash'])) jsonResponseAfterRollback($db,'Your current password is incorrect.',403);
$newPassword = $_POST['new_password'] ?? null; $confirm = $_POST['confirm_password'] ?? null;
$minimum = (int)$account['is_admin'] === 1 ? 16 : 4;
if (!is_string($newPassword) || strlen($newPassword) < $minimum || strlen($newPassword) > 72) throw new InvalidArgumentException("Use a password between $minimum and 72 bytes.");
if (!is_string($confirm) || !hash_equals($newPassword,$confirm)) throw new InvalidArgumentException('The new passwords do not match.');
if (password_verify($newPassword,$account['password_hash'])) throw new InvalidArgumentException('Choose a password different from your current password.');
$db->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?')->execute([password_hash($newPassword,PASSWORD_DEFAULT),$user]);
auditEvent($db,$user,'PASSWORD_CHANGED','user',$user,[]);
$db->commit(); session_regenerate_id(true); $_SESSION['session_version']=(int)$account['session_version']+1;
jsonResponse(['success'=>'Password changed. Other signed-in sessions have been signed out.']);
function jsonResponseAfterRollback(PDO $db,string $message,int $status): never { $db->rollBack(); jsonResponse(['error'=>$message],$status); }
