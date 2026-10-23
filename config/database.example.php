<?php
// Copy to config/database.php. Supply secrets through the deployment environment.
$dsn = getenv('DB_DSN');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
if (!$dsn || $user === false || $password === false) {
    throw new RuntimeException('Database configuration is incomplete.');
}
$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$db->exec("SET time_zone = '+00:00'");
return $db;
