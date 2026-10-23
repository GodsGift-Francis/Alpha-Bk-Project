<?php
function runMigrations(PDO $db): void {
    $locked = $db->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':alphabank-migrations'), 15)")->fetchColumn();
    if ((int)$locked !== 1) throw new RuntimeException('Another migration is running.');
    try {
        $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        foreach (glob(__DIR__ . '/../database/migrations/*.php') as $file) {
            $version = basename($file);
            $stmt = $db->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
            $stmt->execute([$version]);
            if ($stmt->fetchColumn()) continue;
            $migration = require $file;
            $migration($db);
            $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
            echo 'Applied ' . $version . PHP_EOL;
        }
    } finally {
        $db->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':alphabank-migrations'))");
    }
}
