<?php
return function (PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS profile_change_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        previous_full_name VARCHAR(100) NOT NULL,
        previous_username VARCHAR(50) NOT NULL,
        previous_email VARCHAR(100) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        review_reason VARCHAR(500) NULL,
        INDEX owner_requests (user_id, status, id),
        INDEX pending_requests (status, id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");
};
