<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
set_exception_handler(function (Throwable $error) {
    global $db;
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    if ($error instanceof InvalidArgumentException) jsonResponse(['error' => $error->getMessage()], 422);
    if ($error instanceof DomainException) jsonResponse(['error' => $error->getMessage()], 409);
    error_log('AlphaBank API: ' . $error->getMessage());
    jsonResponse(['error' => 'The request could not be completed. Please try again.'], 500);
});
$db = require __DIR__ . '/../config/database.php';
function apiUser(bool $admin = false): int {
    if (!isLoggedIn()) jsonResponse(['error' => 'Your session has expired. Please sign in.'], 401);
    if ($admin && !isAdmin()) jsonResponse(['error' => 'Administrator access required.'], 403);
    return (int)$_SESSION['user_id'];
}
function apiPost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        jsonResponse(['error' => 'POST required.'], 405);
    }
    verifyCsrfToken($_POST['csrf_token'] ?? '');
}
function inputText(string $field, int $max, bool $required = true): string {
    $value = $_POST[$field] ?? '';
    if (!is_string($value)) throw new InvalidArgumentException('Invalid ' . $field . '.');
    $value = trim($value);
    if (($required && $value === '') || strlen($value) > $max) {
        throw new InvalidArgumentException('Please provide a valid ' . str_replace('_', ' ', $field) . '.');
    }
    return $value;
}
function inputId(string $field): int {
    $value = $_POST[$field] ?? '';
    if (!is_scalar($value) || !preg_match('/^[0-9]+$/D', (string)$value) || (int)$value < 1 || strlen((string)$value) > 10) {
        throw new InvalidArgumentException('Invalid identifier.');
    }
    return (int)$value;
}
