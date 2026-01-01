<?php
// admin/user_permissions.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (!isAdmin()) {
    die("Bu sayfaya erişim yetkiniz yok.");
}

$user_id = $_GET['user_id'] ?? null;
if (!$user_id) {
    header("Location: users.php");
    exit;
}

// Get User Info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    die("Kullanıcı bulunamadı.");
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // First clear all permissions for this user
    $del = $pdo->prepare("DELETE FROM user_category_permissions WHERE user_id = ?");
    $del->execute([$user_id]);

    // Add selected
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        $ins = $pdo->prepare("INSERT INTO user_category_permissions (user_id, category_id) VALUES (?, ?)");
        foreach ($_POST['categories'] as $cat_id) {
            $ins->execute([$user_id, $cat_id]);
        }
    }
    $success = "Yetkiler güncellendi.";
}

// Get All Categories
$catStmt = $pdo->prepare("SELECT * FROM categories ORDER BY name ASC");
$catStmt->execute();
$allCategories = $catStmt->fetchAll();

// Get Current Permissions
$permStmt = $pdo->prepare("SELECT category_id FROM user_category_permissions WHERE user_id = ?");
$permStmt->execute([$user_id]);
$currentPerms = $permStmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yetki Yönetimi - LinkManager</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="container" style="max-width: 600px;">
        <header>
            <h3><i class="fas fa-key"></i> Kategori Yetkileri: <?= htmlspecialchars($targetUser['username']) ?></h3>
            <a href="users.php" class="btn" style="background: #95a5a6;">Geri Dön</a>
        </header>

        <div class="glass-card">
            <?php if (isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

            <form method="POST">
                <p>Kullanıcının görebileceği kategorileri seçin:</p>
                <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                    <?php foreach ($allCategories as $cat): ?>
                        <label style="display: flex; align-items: center; cursor: pointer; background: rgba(255,255,255,0.5); padding: 10px; border-radius: 5px;">
                            <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" 
                                <?= in_array($cat['id'], $currentPerms) ? 'checked' : '' ?> 
                                style="width: auto; margin-right: 10px;">
                            <?= htmlspecialchars($cat['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="btn">Kaydet</button>
            </form>
        </div>
    </div>
</body>
</html>
