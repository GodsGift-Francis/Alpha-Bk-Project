<?php
return function (PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS synthetic_history_batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        created_by INT NOT NULL,
        description VARCHAR(1000) NOT NULL,
        dataset_hash CHAR(64) NOT NULL,
        dataset LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY one_batch_per_account (account_id),
        FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};

