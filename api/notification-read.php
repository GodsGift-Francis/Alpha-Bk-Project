<?php
require_once __DIR__ . '/../includes/api.php';
$user = apiUser();
apiPost();
$id = inputId('last_read_id');
$stmt = $db->prepare('SELECT id FROM announcements WHERE id = ? AND (user_id IS NULL OR user_id = ?)');
$stmt->execute([$id, $user]);
if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Notification not found.');
$db->prepare('INSERT INTO notification_reads (user_id, last_read_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_read_id = GREATEST(last_read_id, VALUES(last_read_id))')->execute([$user, $id]);
jsonResponse(['success' => 'Notifications marked as read.']);
