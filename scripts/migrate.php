<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$db = require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/migrations.php';
runMigrations($db);
