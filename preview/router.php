<?php
// Router for PHP's development server only. Never use as a production web server.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$secret = getenv('PREVIEW_ACCESS_CODE');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
if (!$secret || strlen($secret) < 24) { http_response_code(503); exit('Preview access has not been configured.'); }
$secure = getenv('PREVIEW_HTTPS') === '1';
$cookie = $_COOKIE['alphabank_preview'] ?? '';
$authorized = false;
if (is_string($cookie) && preg_match('/^([0-9]{10})\.([a-f0-9]{64})$/D', $cookie, $parts)) {
    $authorized = (int)$parts[1] >= time() && (int)$parts[1] <= time() + 14400
        && hash_equals(hash_hmac('sha256', $parts[1], $secret), $parts[2]);
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '';
if (!$authorized) {
    $submitted = $_POST['preview_access_code'] ?? '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && is_string($submitted) && hash_equals($secret, $submitted)) {
        $expiry = (string)(time() + 14400);
        setcookie('alphabank_preview', $expiry . '.' . hash_hmac('sha256', $expiry, $secret), [
            'expires' => (int)$expiry, 'path' => '/', 'secure' => $secure,
            'httponly' => true, 'samesite' => 'Strict',
        ]);
        header('Location: /login.php', true, 303);
        exit;
    }
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");
    $error = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? '<p role="alert">The access code is incorrect.</p>' : '';
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AlphaBank test preview</title><style>body{font:16px system-ui;background:#f8fafc;color:#0f172a;margin:0;padding:24px}main{max-width:420px;margin:10vh auto;background:white;border:1px solid #cbd5e1;border-radius:16px;padding:28px}label,input,button{display:block;width:100%;box-sizing:border-box}input,button{font:inherit;padding:12px;border:1px solid #94a3b8;border-radius:8px;margin:12px 0}button{background:#2563eb;color:white;cursor:pointer}p{line-height:1.5}</style><main><h1>AlphaBank test preview</h1><p>This isolated instance contains synthetic test data. Enter the preview access code to continue.</p>' . $error . '<form method="post" action="/login.php"><label for="code">Preview access code</label><input type="password" id="code" name="preview_access_code" autocomplete="off" required><button type="submit">Open test app</button></form></main></html>';
    exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'POST'], true)) { http_response_code(405); header('Allow: GET, HEAD, POST'); exit; }
if ($path === '/') $path = '/index.php';
$pages = ['index.php', 'login.php', 'register.php', 'logout.php', 'dashboard.php', 'deposit.php', 'withdraw.php', 'transfer.php', 'settings.php', 'admin.php'];
$allowed = in_array(ltrim($path, '/'), $pages, true)
    || preg_match('#^/api/[a-z-]+\.php$#D', $path)
    || preg_match('#^/assets/(css/[a-z-]+\.css|js/[a-z-]+\.js)$#D', $path)
    || $path === '/logo.jpg';
$root = realpath(__DIR__ . '/..');
$target = $root . $path;
$resolved = str_contains($path, "\0") ? false : realpath($target);
if (!$allowed || !$resolved || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) || !is_file($resolved)) {
    http_response_code(404); exit('Not found');
}
if (str_ends_with($resolved, '.php')) { require $resolved; return true; }
return false;
