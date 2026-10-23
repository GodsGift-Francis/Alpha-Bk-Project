<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/transactions.php';
$admin = apiUser(true);
apiPost();
$title = inputText('title', 255);
$content = inputText('content', 4000);
$target = inputText('user_id', 10, false);
if ($target !== '') {
    $target = inputId('user_id');
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ? AND is_admin = 0');
    $stmt->execute([$target]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Recipient not found.');
}
$db->beginTransaction();
$db->prepare('INSERT INTO announcements (user_id, title, content) VALUES (?, ?, ?)')->execute([$target ?: null, $title, $content]);
auditEvent($db, $admin, 'ANNOUNCEMENT_POSTED', 'announcement', (int)$db->lastInsertId(), ['target_user_id' => $target ?: null]);
$db->commit();
jsonResponse(['success' => 'Announcement posted.'], 201);
