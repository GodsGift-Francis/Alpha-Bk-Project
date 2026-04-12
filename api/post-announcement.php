<?php
// api/post-announcement.php
$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';
$target_user_id = $_POST['user_id'] ?? null; // Can be a specific ID or null for 'General'

if (empty($title) || empty($content)) {
    die(json_encode(['error' => 'Title and content are required']));
}

try {
    $stmt = $db->prepare("INSERT INTO announcements (user_id, title, content) VALUES (?, ?, ?)");
    $stmt->execute([$target_user_id ?: null, $title, $content]);
    echo json_encode(['success' => 'Announcement posted successfully']);
} catch (PDOException $e) {
    die(json_encode(['error' => $e->getMessage()]));
}
