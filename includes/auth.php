<?php
// includes/auth.php

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true, 'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('APP_SESSION_SECURE') === '1',
        'path' => '/',
    ]);
    session_start();
}

function isLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        // Sync with database to apply any changes (like is_admin or currency) immediately
        global $db;
        if (time() - ($_SESSION['last_activity'] ?? 0) > 1800 || time() - ($_SESSION['signed_in_at'] ?? 0) > 28800) {
            $_SESSION = [];
            session_regenerate_id(true);
            return false;
        }
        try {
            if (!$db) $db = require __DIR__ . '/../config/database.php';
            $stmt = $db->prepare("SELECT is_admin, currency, username FROM users WHERE id = ? AND status = 'ACTIVE'");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['is_admin'] = $user['is_admin'];
                $_SESSION['currency'] = $user['currency'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['last_activity'] = time();
                return true;
            } else {
                // User no longer exists in DB
                $_SESSION = [];
                session_regenerate_id(true);
                return false;
            }
        } catch (Exception $e) {
            error_log('AlphaBank authentication synchronization failed.');
            return false;
        }
    }
    return false;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('HTTP/1.1 403 Forbidden');
        die("Unauthorized access.");
    }
}

function loginUser($user_id, $username, $is_admin = 0, $currency = 'USD') {
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['signed_in_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['is_admin'] = $is_admin;
    $_SESSION['currency'] = $currency;
}

function getCurrencySymbol() {
    $currency = $_SESSION['currency'] ?? 'USD';
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'NGN' => '₦',
        'JPY' => '¥'
    ];
    return $symbols[$currency] ?? '$';
}

function maskAccountNumber($accNum) {
    if (!$accNum) return '';
    $visibleDigits = 4;
    if (strlen($accNum) <= $visibleDigits) return $accNum;
    $visiblePart = substr($accNum, -$visibleDigits);
    $maskedPart = preg_replace('/[a-zA-Z0-9]/', '*', substr($accNum, 0, -$visibleDigits));
    return $maskedPart . $visiblePart;
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        header('HTTP/1.1 403 Forbidden');
        die(json_encode(['error' => 'CSRF validation failed']));
    }
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
    header('Location: login.php');
    exit();
}
