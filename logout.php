<?php
require_once __DIR__ . '/includes/auth.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
verifyCsrfToken($_POST['csrf_token'] ?? '');
logoutUser();
