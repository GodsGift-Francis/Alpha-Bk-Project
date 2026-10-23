<?php
require_once __DIR__ . '/../includes/api.php';
$user = apiUser();
$stmt = $db->prepare('SELECT last_read_id FROM notification_reads WHERE user_id = ?');
$stmt->execute([$user]);
$lastRead = (int)$stmt->fetchColumn();
$stmt = $db->prepare('SELECT id, title, content, created_at FROM announcements WHERE user_id IS NULL OR user_id = ? ORDER BY id DESC LIMIT 20');
$stmt->execute([$user]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $db->prepare('SELECT COUNT(*) FROM announcements WHERE (user_id IS NULL OR user_id = ?) AND id > ?');
$stmt->execute([$user, $lastRead]);
jsonResponse(['announcements' => $items, 'last_read_id' => $lastRead, 'unread_count' => (int)$stmt->fetchColumn()]);
