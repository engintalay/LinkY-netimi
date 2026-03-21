<?php
// categories.php - Kategori Yönetimi
require_once 'includes/db.php';
require_once 'includes/functions.php';

requireLogin();
if (!isAdmin()) {
    die("Yetkisiz işlem.");
}

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $visible = isset($_POST['visible']) ? 1 : 0;
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, user_id, visible) VALUES (?, ?, ?)");
            $stmt->execute([$name, $_SESSION['user_id'], $visible]);
            $success = 'Kategori eklendi.';
        } else {
            $error = 'Kategori adı boş olamaz.';
        }
    }

    if ($act === 'edit') {
        $name = sanitize($_POST['name'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        if ($name && $id) {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $success = 'Kategori güncellendi.';
        }
    }

    if ($act === 'toggle_visible') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE categories SET visible = CASE WHEN visible = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
            $success = 'Görünürlük güncellendi.';
        }
    }

    if ($act === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM links WHERE category_id = ?");
            $chk->execute([$id]);
            if ($chk->fetchColumn() > 0) {
                $error = 'Bu kategori dolu olduğu için silinemez. Önce linkleri silin veya taşıyın.';
            } else {
                $pdo->prepare("DELETE FROM user_category_permissions WHERE category_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
                $success = 'Kategori silindi.';
            }
        }
    }
}

// Fetch all categories with link counts
$cats = $pdo->query("SELECT c.*, COUNT(l.id) as link_count 
                      FROM categories c 
                      LEFT JOIN links l ON l.category_id = c.id 
                      GROUP BY c.id 
                      ORDER BY c.name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Yönetimi - LinkManager</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .cat-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .cat-table td { padding: 12px 15px; background: #f0f3f7; vertical-align: middle; }
        .cat-table tr td:first-child { border-radius: 10px 0 0 10px; }
        .cat-table tr td:last-child { border-radius: 0 10px 10px 0; }
        .cat-table .cat-name-input {
            background: transparent; box-shadow: none; border: none; font-size: 16px;
            padding: 5px; width: 100%; pointer-events: none; color: var(--text-color);
        }
        .cat-table .cat-name-input.editing {
            pointer-events: auto; background: #fff; border-radius: 8px;
            box-shadow: inset 2px 2px 5px #d1d9e6, inset -2px -2px 5px #ffffff;
        }
        .icon-btn {
            background: none; box-shadow: none; border: none; cursor: pointer;
            font-size: 16px; padding: 6px 10px; border-radius: 8px; transition: 0.2s;
        }
        .icon-btn:hover { background: #e0e5ec; transform: none; box-shadow: none; }
        .icon-btn.save { color: #1dd1a1; display: none; }
        .icon-btn.cancel { color: #95a5a6; display: none; }
        .icon-btn.edit { color: var(--secondary-color); }
        .icon-btn.delete { color: #ff6b6b; }
        .add-row { display: flex; gap: 10px; margin-top: 15px; }
        .add-row input { flex: 1; }
        .badge { background: var(--primary-color); color: #fff; padding: 3px 10px; border-radius: 20px; font-size: 0.8em; }
    </style>
</head>
<body>
<div class="container" style="max-width: 700px;">
    <header>
        <h1><i class="fas fa-folder-open"></i> Kategori Yönetimi</h1>
        <a href="dashboard.php" class="btn" style="background: #95a5a6;">Geri Dön</a>
    </header>

    <div class="glass-card">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if (count($cats) === 0): ?>
            <p style="text-align:center; color:#888;">Henüz kategori yok.</p>
        <?php else: ?>
        <table class="cat-table">
            <?php foreach ($cats as $cat): ?>
            <tr data-id="<?= $cat['id'] ?>">
                <td style="width:100%;">
                    <form class="edit-form" method="POST" style="display:inline;">
                        <input type="hidden" name="act" value="edit">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <input type="text" name="name" class="cat-name-input" value="<?= htmlspecialchars($cat['name']) ?>" required>
                    </form>
                </td>
                <td><span class="badge"><?= $cat['link_count'] ?> link</span></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="act" value="toggle_visible">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="icon-btn" title="<?= $cat['visible'] ? 'Gizle' : 'Göster' ?>" style="color: <?= $cat['visible'] ? '#1dd1a1' : '#ccc' ?>;">
                            <i class="fas fa-<?= $cat['visible'] ? 'eye' : 'eye-slash' ?>"></i>
                        </button>
                    </form>
                </td>
                <td style="white-space:nowrap;">
                    <button class="icon-btn edit" title="Düzenle" onclick="startEdit(this)"><i class="fas fa-pen"></i></button>
                    <button class="icon-btn save" title="Kaydet" onclick="saveEdit(this)"><i class="fas fa-check"></i></button>
                    <button class="icon-btn cancel" title="İptal" onclick="cancelEdit(this)"><i class="fas fa-times"></i></button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bu kategoriyi silmek istediğine emin misin?')">
                        <input type="hidden" name="act" value="delete">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="icon-btn delete" title="Sil"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <form method="POST" class="add-row">
            <input type="hidden" name="act" value="add">
            <input type="text" name="name" placeholder="Yeni kategori adı..." required>
            <label style="display:flex; align-items:center; gap:5px; white-space:nowrap; font-size:0.85em; cursor:pointer;">
                <input type="checkbox" name="visible" checked style="width:auto; box-shadow:none;"> Görünür
            </label>
            <button type="submit" class="btn"><i class="fas fa-plus"></i> Ekle</button>
        </form>
    </div>
</div>

<script>
function startEdit(btn) {
    var tr = btn.closest('tr');
    var input = tr.querySelector('.cat-name-input');
    input.classList.add('editing');
    input.dataset.original = input.value;
    input.focus();
    tr.querySelector('.save').style.display = 'inline-block';
    tr.querySelector('.cancel').style.display = 'inline-block';
    btn.style.display = 'none';
}
function cancelEdit(btn) {
    var tr = btn.closest('tr');
    var input = tr.querySelector('.cat-name-input');
    input.value = input.dataset.original;
    input.classList.remove('editing');
    tr.querySelector('.save').style.display = 'none';
    tr.querySelector('.cancel').style.display = 'none';
    tr.querySelector('.edit').style.display = 'inline-block';
}
function saveEdit(btn) {
    btn.closest('tr').querySelector('.edit-form').submit();
}
</script>
</body>
</html>
