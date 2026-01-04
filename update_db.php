<?php
require_once 'includes/db.php';

echo "<h2>Veritabanı Güncelleme Aracı</h2>";

try {
    // 1. Check and Update 'users' table columns
    $columnsToAdd = [
        'failed_attempts' => 'INTEGER DEFAULT 0',
        'last_failed_attempt' => 'DATETIME',
        'locked_until' => 'DATETIME',
        'daily_lock_count' => 'INTEGER DEFAULT 0',
        'last_lock_date' => 'DATE',
        'is_permanently_locked' => 'INTEGER DEFAULT 0',
        'role' => "TEXT DEFAULT 'user'"
    ];

    // Get existing columns
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['name'];
    }

    foreach ($columnsToAdd as $colName => $colType) {
        if (!in_array($colName, $existingColumns)) {
            echo "Kolon ekleniyor: <strong>$colName</strong>... ";
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN $colName $colType");
                echo "<span style='color:green'>BAŞARILI</span><br>";
            } catch (PDOException $e) {
                echo "<span style='color:red'>HATA: " . $e->getMessage() . "</span><br>";
            }
        } else {
            echo "Kolon zaten var: $colName <span style='color:gray'>(Atlandı)</span><br>";
        }
    }

    // 2. Check and Create 'blocked_ips' table
    echo "<br>Tablo kontrolü: <strong>blocked_ips</strong>... ";
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blocked_ips'");
    if (!$stmt->fetch()) {
        try {
            $pdo->exec("CREATE TABLE blocked_ips (
                ip_address TEXT PRIMARY KEY,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            echo "<span style='color:green'>OLUŞTURULDU</span><br>";
        } catch (PDOException $e) {
            echo "<span style='color:red'>HATA: " . $e->getMessage() . "</span><br>";
        }
    } else {
        echo "<span style='color:gray'>Zaten var (Atlandı)</span><br>";
    }

    echo "<br><h3>İşlem Tamamlandı!</h3>";
    echo "<p>Artık bu dosyayı silebilir ve giriş yapmayı deneyebilirsiniz.</p>";
    echo "<a href='login.php'>Giriş Sayfasına Git</a>";

} catch (PDOException $e) {
    echo "<h1>Kritik Hata</h1>";
    echo "Veritabanı hatası: " . $e->getMessage();
}
