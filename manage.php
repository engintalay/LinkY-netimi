<?php
// manage.php
require_once 'includes/db.php';
require_once 'includes/functions.php';

requireLogin();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle Delete Categories
if ($action == 'delete_category' && $id) {
    if (!isAdmin()) {
        die("Yetkisiz işlem.");
    }
    // Check if links exist
    $chk = $pdo->prepare("SELECT COUNT(*) FROM links WHERE category_id = ?");
    $chk->execute([$id]);
    if ($chk->fetchColumn() > 0) {
        die("Bu kategori dolu olduğu için silinemez. Önce linkleri silin veya taşıyın.");
    }

    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: dashboard.php");
    exit;
}

// Handle Delete Links
if ($action == 'delete_link' && $id) {
    $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: dashboard.php");
    exit;
}

// Logic for Forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // New/Edit Category
    if ($action == 'new_category' || $action == 'edit_category') {
        $name = sanitize($_POST['name']);

        if ($name) {
            if ($id) {
                // Edit (Admin only?) Let's say yes for categories to keep it simple, or user specific. 
                // For this implementation, categories are global.
                $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, user_id) VALUES (?, ?)");
                $stmt->execute([$name, $_SESSION['user_id']]);
            }
            header("Location: dashboard.php");
            exit;
        }
    }

    // New/Edit Link
    if ($action == 'new_link' || $action == 'edit_link') {
        $url = trim($_POST['url']);
        $cat_id = $_POST['category_id'] ?: null;
        $title = sanitize($_POST['title']);
        $desc = sanitize($_POST['description']);

        // Auto fetch title if empty
        if (empty($title) && !empty($url)) {
            $title = fetchUrlTitle($url);
        }

        if ($url) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE links SET url=?, title=?, description=?, category_id=? WHERE id=?");
                $stmt->execute([$url, $title, $desc, $cat_id, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO links (user_id, category_id, url, title, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $cat_id, $url, $title, $desc]);
            }
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "URL gereklidir.";
        }
    }
}

// Fetch Data for Edit
$editData = null;
if ($id) {
    if (strpos($action, 'link') !== false) {
        $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ?");
        $stmt->execute([$id]);
        $editData = $stmt->fetch();
    } elseif (strpos($action, 'category') !== false) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $editData = $stmt->fetch();
    }
}

// Fetch All Categories for Select
if (isAdmin()) {
    $catStmt = $pdo->prepare("SELECT * FROM categories ORDER BY name ASC");
    $catStmt->execute();
} else {
    $catStmt = $pdo->prepare("SELECT c.* FROM categories c 
                              JOIN user_category_permissions p ON c.id = p.category_id 
                              WHERE p.user_id = ? 
                              ORDER BY c.name ASC");
    $catStmt->execute([$_SESSION['user_id']]);
}
$allCategories = $catStmt->fetchAll();

$pageTitle = "Yönetim";
if ($action == 'new_link')
    $pageTitle = "Yeni Link Ekle";
if ($action == 'edit_link')
    $pageTitle = "Link Düzenle";
if ($action == 'new_category')
    $pageTitle = "Yeni Kategori";

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $pageTitle ?> - LinkManager
    </title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

    <div class="container" style="max-width: 600px;">
        <header>
            <h1>
                <?= $pageTitle ?>
            </h1>
            <a href="dashboard.php" class="btn" style="background: #95a5a6;">Geri Dön</a>
        </header>

        <div class="glass-card">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if ($action == 'new_link' || $action == 'edit_link'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>URL (Başında http:// veya https:// olmalı)</label>
                        <input type="url" name="url" value="<?= $editData['url'] ?? '' ?>" required
                            placeholder="https://example.com">
                    </div>

                    <div class="form-group">
                        <label>Başlık (Boş bırakılırsa otomatik çekilir)</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="title" id="titleInput" value="<?= $editData['title'] ?? '' ?>">
                            <button type="button" id="fetchBtn" style="padding: 10px;" title="Başlığı Çek"><i
                                    class="fas fa-sync"></i> Çek</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="category_id">
                            <option value="">Kategori Seçin...</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($editData['category_id']) && $editData['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea name="description" rows="3"><?= $editData['description'] ?? '' ?></textarea>
                    </div>

                    <button type="submit" class="btn">Kaydet</button>
                </form>
            <?php elseif ($action == 'new_category' || $action == 'edit_category'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Kategori Adı</label>
                        <input type="text" name="name" value="<?= $editData['name'] ?? '' ?>" required>
                    </div>
                    <button type="submit" class="btn">Kaydet</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Simple script to async fetch title if button clicked (Optimistic UI)
        // Actually our PHP handles it on POST, but let's make it usable for the user to see before saving if they want.
        // For simplicity, I'll let PHP handle it on save as requested "Başlıklarını mümkğnse sayfaya bağlanııp alacak", which implies automagically.
        // But a button to test is nice. Not implementing full AJAX for simplicity unless user asks.
    </script>
</body>

</html>