<?php
// api/announcements.php
$db = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch general announcements (user_id is NULL) OR announcements specific to this user
    $stmt = $db->prepare("SELECT * FROM announcements WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $announcements = $stmt->fetchAll();
    echo json_encode(['announcements' => $announcements]);
} catch (PDOException $e) {
    die(json_encode(['error' => $e->getMessage()]));
}
