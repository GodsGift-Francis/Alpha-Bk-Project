<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/transactions.php';
$database = $argv[1] ?? '';
if (!preg_match('/^alphabank_test_[a-f0-9]{12}$/D', $database)) exit(2);
$db = require __DIR__ . '/../config/database.php';
$db->exec("USE `$database`");
echo "READY\n"; flush();
try { reviewTransaction($db, 1, (int)$argv[2], 'APPROVE'); echo 'APPROVED'; }
catch (DomainException $error) { echo 'REFUSED'; }
