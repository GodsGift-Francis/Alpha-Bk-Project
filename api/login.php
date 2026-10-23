<?php
require_once __DIR__ . '/../includes/api.php';
apiPost();
$username = inputText('username', 50);
$password = $_POST['password'] ?? '';
if (!is_string($password) || strlen($password) > 1024) throw new InvalidArgumentException('Invalid password.');
// Rate limits are shared by sessions/workers. Do not trust client-supplied forwarding headers.
$buckets = [hash('sha256', 'user:' . strtolower($username)), hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))];
foreach ($buckets as $index => $bucket) {
    $db->prepare("INSERT INTO login_limits (bucket, attempts, window_started) VALUES (?, 1, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts = IF(window_started < UTC_TIMESTAMP() - INTERVAL 15 MINUTE, 1, attempts + 1), window_started = IF(window_started < UTC_TIMESTAMP() - INTERVAL 15 MINUTE, UTC_TIMESTAMP(), window_started)")->execute([$bucket]);
    $stmt = $db->prepare('SELECT attempts FROM login_limits WHERE bucket = ?');
    $stmt->execute([$bucket]);
    if ((int)$stmt->fetchColumn() > ($index === 0 ? 10 : 50)) {
        header('Retry-After: 900');
        jsonResponse(['error' => 'Too many sign-in attempts. Please try again in 15 minutes.'], 429);
    }
}
$stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'ACTIVE'");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
// Fixed-cost verification also applies to unknown users.
$valid = password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
if (!$user || !$valid) jsonResponse(['error' => 'Invalid username or password.'], 401);
$db->prepare('DELETE FROM login_limits WHERE bucket = ?')->execute([$buckets[0]]);
loginUser($user['id'], $user['username'], $user['is_admin'], $user['currency']);
jsonResponse(['success' => 'Login successful', 'is_admin' => (int)$user['is_admin']]);
