<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        // Sync with database to apply any changes (like is_admin or currency) immediately
        global $db;
        if (!$db) {
            $db = require __DIR__ . '/../config/database.php';
        }
        
        try {
            $stmt = $db->prepare("SELECT is_admin, currency, username FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['is_admin'] = $user['is_admin'];
                $_SESSION['currency'] = $user['currency'];
                $_SESSION['username'] = $user['username'];
                return true;
            } else {
                // User no longer exists in DB
                session_destroy();
                return false;
            }
        } catch (Exception $e) {
            return true; // Fallback to session if DB fails
        }
    }
    return false;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
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
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        header('HTTP/1.1 403 Forbidden');
        die(json_encode(['error' => 'CSRF validation failed']));
    }
}

function logoutUser() {
    session_destroy();
    header('Location: login.php');
    exit();
}
