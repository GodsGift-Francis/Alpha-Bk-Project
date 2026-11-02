<?php
return function (PDO $db): void {
    $columns = [
        'appearance' => "VARCHAR(10) NOT NULL DEFAULT 'system'",
        'density' => "VARCHAR(12) NOT NULL DEFAULT 'comfortable'",
        'reduce_motion' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'hide_balances' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'session_version' => 'INT UNSIGNED NOT NULL DEFAULT 1',
    ];
    foreach ($columns as $name => $definition) {
        $check = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = ?");
        $check->execute([$name]);
        if (!$check->fetchColumn()) $db->exec("ALTER TABLE users ADD `$name` $definition");
    }
};
