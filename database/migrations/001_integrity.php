<?php
return function (PDO $db): void {
    // DDL commits implicitly in MySQL. Each operation is restartable after interruption.
    $column = function (string $table, string $name, string $definition) use ($db) {
        $stmt = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $name]);
        if (!$stmt->fetchColumn()) $db->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
    };
    $column('users', 'status', "VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'");
    $column('announcements', 'user_id', 'INT NULL');
    foreach (['accounts', 'transactions'] as $table) {
        $column($table, 'reviewed_by', 'INT NULL');
        $column($table, 'reviewed_at', 'DATETIME NULL');
        $column($table, 'review_reason', 'VARCHAR(500) NULL');
    }
    $column('transactions', 'requested_by', 'INT NULL');
    $column('transactions', 'request_key', 'VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL');
    $column('transactions', 'request_hash', 'CHAR(64) NULL');
    $column('transactions', 'source_transaction_id', 'INT NULL');
    $db->exec("CREATE TABLE IF NOT EXISTS audit_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, actor_id INT NOT NULL,
        event_type VARCHAR(50) NOT NULL, entity_type VARCHAR(30) NOT NULL,
        entity_id INT NOT NULL, details TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX audit_entity (entity_type, entity_id, id)
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE IF NOT EXISTS notification_reads (
        user_id INT PRIMARY KEY, last_read_id INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE IF NOT EXISTS login_limits (
        bucket CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0,
        window_started DATETIME NOT NULL
    ) ENGINE=InnoDB");
    $indexes = [
        ['transactions', 'request_unique', 'UNIQUE INDEX request_unique (requested_by, request_key)'],
        ['transactions', 'transfer_pair_unique', 'UNIQUE INDEX transfer_pair_unique (source_transaction_id)'],
        ['transactions', 'account_history', 'INDEX account_history (account_id, timestamp, id)'],
        ['transactions', 'pending_queue', 'INDEX pending_queue (status, timestamp, id)'],
        ['accounts', 'user_accounts', 'INDEX user_accounts (user_id, status)'],
        ['announcements', 'user_announcements', 'INDEX user_announcements (user_id, id)'],
    ];
    foreach ($indexes as [$table, $name, $definition]) {
        $stmt = $db->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
        $stmt->execute([$table, $name]);
        if (!$stmt->fetchColumn()) $db->exec("ALTER TABLE `$table` ADD $definition");
    }
};
