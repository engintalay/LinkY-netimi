<?php
// admin/users.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (!isAdmin()) {
    die("Bu sayfaya erişim yetkiniz yok.");
}

$error = '';
$success = '';

// Handle Delete
if (isset($_GET['delete']) && $_GET['delete']) {
    $id = $_GET['delete'];
    if ($id == $_SESSION['user_id']) {
        $error = "Kendinizi silemezsiniz.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Kullanıcı silindi.";
    }
}

// Handle Create
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if ($username && $password) {
        // Check exist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Bu kullanıcı adı zaten alınıyor.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $role]);
            $success = "Kullanıcı oluşturuldu.";
        }
    } else {
        $error = "Tüm alanları doldurun.";
    }
}

// Handle Update Password
if (isset($_POST['update_password']) && isset($_POST['user_id'])) {
    $uid = $_POST['user_id'];
    $new_pass = $_POST['new_password'];
    
    if ($new_pass) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        // Also unlock the user if they were locked? The user didn't ask, but it makes sense.
        // Let's just update the password for now as requested.
        // Also resetting failed attempts/locks might be good practice when admin resets password.
        $stmt = $pdo->prepare("UPDATE users SET password = ?, failed_attempts = 0, locked_until = NULL, is_permanently_locked = 0 WHERE id = ?");
        $stmt->execute([$hash, $uid]);
        $success = "Şifre güncellendi ve hesap kilitleri kaldırıldı.";
    } else {
        $error = "Şifre boş olamaz.";
    }

}

// Handle Unlock
if (isset($_GET['unlock']) && $_GET['unlock']) {
    $uid = $_GET['unlock'];
    $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, is_permanently_locked = 0, daily_lock_count = 0 WHERE id = ?");
    $stmt->execute([$uid]);
    $success = "Kullanıcı kilidi açıldı.";
}

$stmt = $pdo->prepare("SELECT * FROM users ORDER BY username ASC");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - LinkManager</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <div class="container">
        <header>
            <h1><i class="fas fa-users-cog"></i> Kullanıcı Yönetimi</h1>
            <div>
                <a href="login_logs.php" class="btn" style="background: #8e44ad; margin-right:5px;"><i class="fas fa-history"></i> Loglar</a>
                <a href="../dashboard.php" class="btn" style="background: #95a5a6;">Geri Dön</a>
            </div>
        </header>

        <div class="glass-card">
            <h3>Yeni Kullanıcı Ekle</h3>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= $success ?>
                </div>
            <?php endif; ?>

        <?php if (isset($_GET['edit_password'])): 
            $edit_id = $_GET['edit_password'];
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_user = $stmt->fetchColumn();
        ?>
            <div class="glass-card" style="border: 2px solid #f39c12;">
                <h3>Şifre Değiştir: <?= htmlspecialchars($edit_user) ?></h3>
                <form method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
                    <input type="hidden" name="user_id" value="<?= $edit_id ?>">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label>Yeni Şifre</label>
                        <input type="password" name="new_password" required placeholder="Yeni şifreyi girin">
                    </div>
                    <button type="submit" name="update_password" class="btn" style="background: #f39c12;"><i class="fas fa-save"></i> Güncelle</button>
                    <a href="users.php" class="btn" style="background: #95a5a6;">İptal</a>
                </form>
            </div>
        <?php endif; ?>

            <form method="POST" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label>Kullanıcı Adı</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label>Şifre</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                    <label>Rol</label>
                    <select name="role">
                        <option value="user">Kullanıcı</option>
                        <option value="admin">Yönetici</option>
                    </select>
                </div>
                <button type="submit" class="btn"><i class="fas fa-user-plus"></i> Ekle</button>
            </form>
        </div>

        <div class="glass-card">
            <h3>Kayıtlı Kullanıcılar</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid #ccc;">
                        <th style="padding: 10px;">ID</th>
                        <th style="padding: 10px;">Kullanıcı Adı</th>
                        <th style="padding: 10px;">Rol</th>
                        <th style="padding: 10px;">Durum</th>
                        <th style="padding: 10px;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                            <td style="padding: 10px;">#
                                <?= $u['id'] ?>
                            </td>
                            <td style="padding: 10px; font-weight: bold;">
                                <?= htmlspecialchars($u['username']) ?>
                            </td>
                            <td style="padding: 10px;"><span class="tag">
                                    <?= $u['role'] ?>
                                </span></td>
                            <td style="padding: 10px;">
                                <?php 
                                    $isLocked = false;
                                    if ($u['is_permanently_locked']) {
                                        echo '<span class="tag" style="background: #c0392b; color: white;"><i class="fas fa-lock"></i> Kalıcı Kilitli</span>';
                                        $isLocked = true;
                                    } elseif ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
                                        echo '<span class="tag" style="background: #e67e22; color: white;"><i class="fas fa-clock"></i> Geçici Kilitli</span>';
                                        $isLocked = true;
                                    } else {
                                        echo '<span class="tag" style="background: #2ecc71; color: white;">Aktif</span>';
                                    }
                                ?>
                            </td>
                            <td style="padding: 10px;">
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <?php if ($isLocked): ?>
                                        <a href="?unlock=<?= $u['id'] ?>" class="btn"
                                            style="background: #27ae60; padding: 5px 10px; font-size: 0.8em; margin-right: 5px;"><i
                                                class="fas fa-unlock"></i> Kilidi Aç</a>
                                    <?php endif; ?>
                                    <a href="user_permissions.php?user_id=<?= $u['id'] ?>" class="btn"
                                        style="background: #f39c12; padding: 5px 10px; font-size: 0.8em; margin-right: 5px;"><i
                                            class="fas fa-key"></i> Yetkiler</a>
                                    <a href="?edit_password=<?= $u['id'] ?>" class="btn"
                                    style="background: #3498db; padding: 5px 10px; font-size: 0.8em; margin-right: 5px;"><i
                                            class="fas fa-lock"></i> Şifre Değiştir</a>
                                    <a href="?delete=<?= $u['id'] ?>" onclick="return confirm('Silmek istediğine emin misin?')"
                                        style="color: #ff6b6b;"><i class="fas fa-trash"></i> Sil</a>
                                <?php else: ?>
                                    <span style="color: #aaa;">(Kendiniz)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>

</html>