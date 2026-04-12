<?php
// update-tx-status.php
$db = require_once __DIR__ . '/config/database.php';
try {
    $db->exec("ALTER TABLE transactions ADD COLUMN status VARCHAR(20) DEFAULT 'APPROVED' AFTER description");
    echo "Transactions table updated with status column.";
} catch (Exception $e) {
    echo "Error or already updated: " . $e->getMessage();
}
