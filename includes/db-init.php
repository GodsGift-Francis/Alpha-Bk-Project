<?php
// includes/db-init.php

$db = require_once __DIR__ . '/../config/database.php';

try {
    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        is_admin TINYINT(1) DEFAULT 0,
        currency VARCHAR(10) DEFAULT 'USD',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create accounts table
    $db->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        account_number VARCHAR(20) UNIQUE NOT NULL,
        balance DECIMAL(15, 2) DEFAULT 0.00,
        account_type VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'PENDING',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Create transactions table
    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        transaction_type VARCHAR(50) NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        related_account_id INT DEFAULT NULL,
        description TEXT,
        status VARCHAR(20) DEFAULT 'PENDING',
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Create announcements table
    $db->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    echo "MySQL Database initialized successfully.\n";
} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage());
}
