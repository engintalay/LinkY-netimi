<?php
// admin/login_logs.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (!isAdmin()) {
    die("Bu sayfaya erişim yetkiniz yok.");
}

$stmt = $pdo->prepare("SELECT * FROM login_logs ORDER BY created_at DESC LIMIT 100");
$stmt->execute();
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Logları - LinkManager</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <div class="container">
        <header>
            <h1><i class="fas fa-history"></i> Giriş Logları</h1>
            <div>
                <a href="users.php" class="btn" style="background: #3498db; margin-right:5px;">Kullanıcılar</a>
                <a href="../dashboard.php" class="btn" style="background: #95a5a6;">Geri Dön</a>
            </div>
        </header>

        <div class="glass-card">
            <h3>Son 100 Giriş Hareketi</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid #ccc;">
                        <th style="padding: 10px;">Zaman</th>
                        <th style="padding: 10px;">Kullanıcı Adı</th>
                        <th style="padding: 10px;">IP Adresi</th>
                        <th style="padding: 10px;">Durum</th>
                        <th style="padding: 10px;">Mesaj</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                            <td style="padding: 10px; font-size: 0.9em; color: #555;">
                                <?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td style="padding: 10px; font-weight: bold;">
                                <?= htmlspecialchars($log['username'] ?? '-') ?>
                            </td>
                            <td style="padding: 10px; font-family: monospace;">
                                <?= htmlspecialchars($log['ip_address']) ?>
                            </td>
                            <td style="padding: 10px;">
                                <?php if ($log['status'] == 'success'): ?>
                                    <span class="tag" style="background: #2ecc71; color: white;">Başarılı</span>
                                <?php elseif ($log['status'] == 'blocked'): ?>
                                    <span class="tag" style="background: #2c3e50; color: white;">Engellendi</span>
                                <?php else: ?>
                                    <span class="tag" style="background: #e74c3c; color: white;">Başarısız</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px; color: #555;">
                                <?= htmlspecialchars($log['message']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
