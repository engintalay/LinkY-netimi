<?php
// dashboard.php
require_once 'includes/db.php';
require_once 'includes/functions.php';

requireLogin();

// Fetch Categories
if (isAdmin()) {
    $catStmt = $pdo->prepare("SELECT * FROM categories ORDER BY name ASC");
    $catStmt->execute();
} else {
    // Only permitted categories
    $catStmt = $pdo->prepare("SELECT c.* FROM categories c 
                              JOIN user_category_permissions p ON c.id = p.category_id 
                              WHERE p.user_id = ? 
                              ORDER BY c.name ASC");
    $catStmt->execute([$_SESSION['user_id']]);
}
$categories = $catStmt->fetchAll();
$categoryIds = array_column($categories, 'id');

// Fetch Links (Filtered)
$where = "WHERE 1=1";
$params = [];

if (isset($_GET['category']) && $_GET['category'] != '') {
    $where .= " AND category_id = ?";
    $params[] = $_GET['category'];
}

if (isset($_GET['q'])) {
    $where .= " AND (title LIKE ? OR url LIKE ?)";
    $term = "%" . $_GET['q'] . "%";
    $params[] = $term;
    $params[] = $term;
}

$linkSql = "SELECT links.*, categories.name as category_name 
            FROM links 
            LEFT JOIN categories ON links.category_id = categories.id 
            $where";

// For non-admins, restrict links to allowed categories
if (!isAdmin()) {
    if (empty($categoryIds)) {
        // User has no category permissions, show nothing
        $linkSql .= " AND 1=0";
    } else {
        $in = str_repeat('?,', count($categoryIds) - 1) . '?';
        $linkSql .= " AND links.category_id IN ($in)";
        $params = array_merge($params, $categoryIds);
    }
}

$linkSql .= " ORDER BY links.created_at DESC";
$linkStmt = $pdo->prepare($linkSql);
$linkStmt->execute($params);
$links = $linkStmt->fetchAll();

$userInitial = strtoupper(substr($_SESSION['username'], 0, 1));
$view = $_GET['view'] ?? 'grid';
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Link Yöneticisi</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <div class="container">
        <header>
            <div class="logo">
                <h2><i class="fas fa-link"></i> LinkManager</h2>
            </div>

            <div class="user-menu" style="display: flex; align-items: center; gap: 15px;">
                <span>Merhaba, <b>
                        <?= htmlspecialchars($_SESSION['username']) ?>
                    </b></span>
                <?php if (isAdmin()): ?>
                    <a href="admin/users.php" class="btn" style="background: #6a11cb; padding: 8px 15px;">Kullanıcılar</a>
                <?php endif; ?>
                <a href="logout.php" class="btn" style="background: #ff6b6b; padding: 8px 15px;">Çıkış</a>
            </div>
        </header>

        <section class="glass-card">
            <div
                style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <form action="" method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 600px;">
                    <input type="text" name="q" placeholder="Link ara..."
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                    <select name="category">
                        <option value="">Tüm Kategoriler</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><i class="fas fa-search"></i></button>
                    <?php if (isset($_GET['q']) || isset($_GET['category'])): ?>
                        <a href="dashboard.php" class="btn"
                            style="background: #95a5a6; display: flex; align-items: center;">X</a>
                    <?php endif; ?>
                </form>

                <div class="actions">
                    <div style="display: inline-flex; background: #e0e5ec; border-radius: 10px; padding: 2px; margin-right: 10px; vertical-align: middle;">
                        <a href="?view=grid<?= isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : '' ?><?= isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : '' ?>" 
                           title="Kutu Görünümü"
                           style="padding: 8px 12px; border-radius: 8px; background: <?= $view == 'grid' ? '#fff' : 'transparent' ?>; color: <?= $view == 'grid' ? '#6a11cb' : '#777' ?>; box-shadow: <?= $view == 'grid' ? '0 2px 5px rgba(0,0,0,0.1)' : 'none' ?>;">
                            <i class="fas fa-th-large"></i>
                        </a>
                        <a href="?view=list<?= isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : '' ?><?= isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : '' ?>" 
                           title="Liste Görünümü"
                           style="padding: 8px 12px; border-radius: 8px; background: <?= $view == 'list' ? '#fff' : 'transparent' ?>; color: <?= $view == 'list' ? '#6a11cb' : '#777' ?>; box-shadow: <?= $view == 'list' ? '0 2px 5px rgba(0,0,0,0.1)' : 'none' ?>;">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>

                    <a href="manage.php?action=new_link" class="btn"><i class="fas fa-plus"></i> Yeni Link</a>
                    <a href="manage.php?action=new_category" class="btn" style="background: var(--secondary-color);"><i
                            class="fas fa-folder-plus"></i> Kategori</a>
                </div>
            </div>
        </section>

        <div class="link-grid <?= $view == 'list' ? 'view-list' : '' ?>">
            <?php foreach ($links as $link): ?>
                <div class="glass-card link-item" style="position: relative;">
                    <div style="position: absolute; top: 15px; right: 15px; background: white; padding: 2px 5px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <a href="manage.php?action=edit_link&id=<?= $link['id'] ?>" style="color: #666;"><i
                                class="fas fa-edit"></i></a>
                        <a href="manage.php?action=delete_link&id=<?= $link['id'] ?>"
                            onclick="return confirm('Silmek istediğine emin misin?')"
                            style="color: #ff6b6b; margin-left: 10px;"><i class="fas fa-trash"></i></a>
                    </div>
                    
                    <?php if(!empty($link['image_url'])): ?>
                        <div style="height: 150px; overflow: hidden; border-radius: 10px; margin-bottom: 10px;">
                             <img src="<?= htmlspecialchars($link['image_url']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    <?php endif; ?>

                    <span class="tag" style="font-size: 0.8em; color: #666; background: #eef2f7;">
                        <i class="fas fa-folder" style="color: #f39c12; margin-right: 5px;"></i>
                        <?= htmlspecialchars($link['category_name'] ?? 'Genel') ?>
                    </span>

                    <h3 style="margin: 10px 0 5px 0;">
                        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank"
                            title="<?= htmlspecialchars($link['url']) ?>">
                            <?php
                            $title = $link['title'] ?: $link['url'];
                            echo mb_strlen($title) > 40 ? mb_substr($title, 0, 40) . '...' : $title;
                            ?>
                        </a>
                    </h3>
                    <p style="font-size: 0.9em; color: #777; margin-bottom: 15px;">
                        <?= htmlspecialchars($link['description'] ?? '') ?>
                    </p>

                    <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank"
                        style="font-size: 0.9em; word-break: break-all; opacity: 0.7;">
                        <i class="fas fa-external-link-alt"></i>
                        <?= parse_url($link['url'], PHP_URL_HOST) ?>
                    </a>
                </div>
            <?php endforeach; ?>

            <?php if (count($links) == 0): ?>
                <div class="glass-card" style="grid-column: 1 / -1; text-align: center; color: #888;">
                    <p>Henüz hiç link eklenmemiş.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</body>

</html>