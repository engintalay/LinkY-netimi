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
            role TEXT DEFAULT 'user'
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
        )"
    ];

    foreach ($queries as $query) {
        $pdo->exec($query);
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
