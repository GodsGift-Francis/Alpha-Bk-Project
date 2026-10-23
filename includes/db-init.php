<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$db = require __DIR__ . '/../config/database.php';
$schema = require __DIR__ . '/../database/schema.php';
$schema($db);
require_once __DIR__ . '/migrations.php';
runMigrations($db);
echo "Database initialized and migrations applied.\n";
