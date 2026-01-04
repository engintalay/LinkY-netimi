<?php
// includes/db.php

$dbFile = __DIR__ . '/../database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Initial Setup: Create Tables
    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            failed_attempts INTEGER DEFAULT 0,
            last_failed_attempt DATETIME,
            locked_until DATETIME,
            daily_lock_count INTEGER DEFAULT 0,
            last_lock_date DATE,
            is_permanently_locked INTEGER DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            user_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            category_id INTEGER,
            url TEXT NOT NULL,
            title TEXT,
            description TEXT,
            visit_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(category_id) REFERENCES categories(id)
        )",
        "CREATE TABLE IF NOT EXISTS user_category_permissions (
            user_id INTEGER,
            category_id INTEGER,
            PRIMARY KEY (user_id, category_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS blocked_ips (
            ip_address TEXT PRIMARY KEY,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    // Auto-Migration: Check for missing columns in 'users' table and add them if necessary
    // This handles the case where the table exists (from older version) but lacks new fields.
    $columnsToCheck = [
        'failed_attempts' => 'INTEGER DEFAULT 0',
        'last_failed_attempt' => 'DATETIME',
        'locked_until' => 'DATETIME',
        'daily_lock_count' => 'INTEGER DEFAULT 0',
        'last_lock_date' => 'DATE',
        'is_permanently_locked' => 'INTEGER DEFAULT 0',
        'role' => "TEXT DEFAULT 'user'"
    ];

    $existingColumns = [];
    $stmt = $pdo->query("PRAGMA table_info(users)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['name'];
    }

    foreach ($columnsToCheck as $colName => $colType) {
        if (!in_array($colName, $existingColumns)) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN $colName $colType");
            } catch (PDOException $e) {
                // Ignore error if column already exists (race condition or other DB issue), 
                // but log if needed. For now, silent fail is safer than crashing.
                // explicitly silencing to avoid fatal errors on deployment if state is weird
            }
        }
    }

    // Create default admin user if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('admin', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')");
        $insert->execute([$password]);
    }

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
