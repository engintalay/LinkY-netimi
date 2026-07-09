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
    // Get link data to delete local image
    $stmt = $pdo->prepare("SELECT local_image FROM links WHERE id = ?");
    $stmt->execute([$id]);
    $link = $stmt->fetch();
    if ($link && !empty($link['local_image']) && file_exists($link['local_image'])) {
        unlink($link['local_image']);
    }
    
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
        $image_url = sanitize($_POST['image_url'] ?? '');
        $pasted_image_data = $_POST['pasted_image_data'] ?? '';

        // Auto fetch title if empty
        if (empty($title) && !empty($url)) {
            $title = fetchUrlTitle($url);
        }

        if ($url) {
            if ($id) {
                // Get existing local_image for cleanup
                $stmt = $pdo->prepare("SELECT local_image, image_url FROM links WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                
                $local_image = $existing['local_image'] ?? '';
                if (!empty($pasted_image_data)) {
                    if (!empty($local_image) && file_exists($local_image)) {
                        unlink($local_image);
                    }
                    $local_image = saveDataUrlImage($pasted_image_data);
                    $image_url = '';
                } elseif (!empty($image_url) && $image_url !== $existing['image_url']) {
                    // New image URL - delete old local image and download new one
                    if (!empty($local_image) && file_exists($local_image)) {
                        unlink($local_image);
                    }
                    $local_image = downloadImage($image_url);
                }
                
                $stmt = $pdo->prepare("UPDATE links SET url=?, title=?, description=?, image_url=?, local_image=?, category_id=? WHERE id=?");
                $stmt->execute([$url, $title, $desc, $image_url, $local_image, $cat_id, $id]);
            } else {
                // Check if same user already has this URL
                $chk = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ? AND url = ?");
                $chk->execute([$_SESSION['user_id'], $url]);
                
                if ($chk->fetchColumn() > 0) {
                    $error = "Bu linki zaten eklemişsin.";
                } else {
                    $local_image = !empty($pasted_image_data)
                        ? saveDataUrlImage($pasted_image_data)
                        : downloadImage($image_url);
                    if (!empty($pasted_image_data)) {
                        $image_url = '';
                    }
                    $stmt = $pdo->prepare("INSERT INTO links (user_id, category_id, url, title, description, image_url, local_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $cat_id, $url, $title, $desc, $image_url, $local_image]);
                    
                    // Remember last used category
                    if ($cat_id) {
                        $_SESSION['last_category_id'] = $cat_id;
                    }
                    
                    header("Location: dashboard.php");
                    exit;
                }
            }
            if (!$error) {
                header("Location: dashboard.php");
                exit;
            }
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
                            <button type="button" id="fetchBtn" style="padding: 10px;" title="Bilgileri Çek"><i
                                    class="fas fa-magic"></i> Çek</button>
                        </div>
                        <small style="color: #666; font-size: 0.8em;">Not: Instagram gibi siteler bot koruması nedeniyle otomatik çekilemeyebilir.</small>
                    </div>

                    <!-- Image Selection -->
                    <input type="hidden" name="image_url" id="imageUrlInput" value="<?= $editData['image_url'] ?? '' ?>">
                    <input type="hidden" name="pasted_image_data" id="pastedImageData" value="">
                    <div class="form-group">
                        <label>Dışarıdan Resim Ekle</label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="url" id="manualImageUrl" value="<?= $editData['image_url'] ?? '' ?>"
                                placeholder="https://example.com/resim.jpg" style="flex: 1; min-width: 260px;">
                            <button type="button" onclick="addManualImage()" style="padding: 10px;">Resim Ekle</button>
                        </div>
                        <div id="pasteImageArea" tabindex="0" style="margin-top: 10px; padding: 14px; border: 2px dashed rgba(52, 152, 219, 0.45); border-radius: 10px; background: rgba(255,255,255,0.35); color: #4a5568; text-align: center; cursor: text;">
                            Resmi kopyalayıp buraya Ctrl+V ile yapıştırın
                        </div>
                        <small style="color: #666; font-size: 0.8em;">Otomatik çekim çalışmazsa doğrudan görsel bağlantısı yapıştırabilir veya ekran görüntüsünü panodan ekleyebilirsiniz.</small>
                    </div>

                    <div class="form-group" id="imageSelectionArea" style="<?= (($editData['image_url'] ?? '') || ($editData['local_image'] ?? '')) ? '' : 'display:none;' ?>">
                        <label>Görsel Seçimi</label>
                        <?php $currentImage = $editData['image_url'] ?? ($editData['local_image'] ?? ''); ?>
                        <?php if($currentImage): ?>
                            <div style="margin-bottom:10px;" id="currentImagePreview">
                                <img src="<?= htmlspecialchars_decode($currentImage) ?>" style="height: 100px; border-radius: 5px; border: 2px solid #3498db;">
                                <p style="font-size:0.8em; color:#666;">Seçili Görsel</p>
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom:10px; display:none;" id="currentImagePreview"></div>
                        <?php endif; ?>

                        <div id="fetchedImagesGrid" style="display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px;">
                            <!-- Images will be injected here via JS -->
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="category_id">
                            <option value="">Kategori Seçin...</option>
                            <?php 
                            $defaultCategoryId = $editData['category_id'] ?? ($_SESSION['last_category_id'] ?? '');
                            foreach ($allCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($defaultCategoryId == $cat['id']) ? 'selected' : '' ?>>
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
        document.getElementById('fetchBtn').addEventListener('click', function() {
            var url = document.querySelector('input[name="url"]').value;
            var titleInput = document.getElementById('titleInput');
            var descInput = document.querySelector('textarea[name="description"]');
            var imageUrlInput = document.getElementById('imageUrlInput');
            var pastedImageDataInput = document.getElementById('pastedImageData');
            var manualImageInput = document.getElementById('manualImageUrl');
            var imageArea = document.getElementById('imageSelectionArea');
            var preview = document.getElementById('currentImagePreview');
            var grid = document.getElementById('fetchedImagesGrid');
            var btn = this;

            if (!url) {
                alert('Lütfen önce URL girin.');
                return;
            }

            var originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            fetch('ajax_fetch_title.php?url=' + encodeURIComponent(url))
                .then(response => response.json())
                .then(data => {
                    if(data.error) {
                        alert(data.error);
                    } else {
                        // Set Title
                        if(data.title) {
                            titleInput.value = data.title;
                        }

                        // Set Description (always update)
                        if(data.description) {
                            descInput.value = data.description;
                        }

                        // Show debug info if available
                        if(data.debug && data.debug.length > 0) {
                            console.log('Debug Info:', data.debug);
                            // Uncomment next line to see debug as alert
                            // alert('Debug: ' + data.debug.join(', '));
                        }

                        // Set Images
                        grid.innerHTML = '';
                        if(data.images && data.images.length > 0) {
                            imageArea.style.display = 'block';
                            data.images.forEach(imgUrl => {
                                var imgDiv = document.createElement('div');
                                imgDiv.style.cursor = 'pointer';
                                imgDiv.style.border = '2px solid transparent';
                                imgDiv.style.borderRadius = '5px';
                                imgDiv.style.padding = '2px';
                                imgDiv.innerHTML = '<img src="' + imgUrl + '" style="height: 80px; display:block; border-radius: 3px;">';
                                
                                imgDiv.onclick = function() {
                                    // Deselect all
                                    Array.from(grid.children).forEach(c => {
                                        c.style.borderColor = 'transparent';
                                        c.style.opacity = '0.7';
                                    });
                                    // Select current
                                    this.style.borderColor = '#3498db';
                                    this.style.opacity = '1';
                                    imageUrlInput.value = imgUrl;
                                    pastedImageDataInput.value = '';
                                    manualImageInput.value = imgUrl;
                                    preview.style.display = 'none';
                                };

                                grid.appendChild(imgDiv);
                            });
                            // Select first one by default if no existing image
                            if(!imageUrlInput.value && grid.children.length > 0) {
                                grid.children[0].click();
                            }
                        } else {
                            if(!imageUrlInput.value) imageArea.style.display = 'none';
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Bir hata oluştu.');
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        });
        
        function addManualImage() {
            var manualUrl = document.getElementById('manualImageUrl').value;
            var grid = document.getElementById('fetchedImagesGrid');
            var imageArea = document.getElementById('imageSelectionArea');
            var imageUrlInput = document.getElementById('imageUrlInput');
            var pastedImageDataInput = document.getElementById('pastedImageData');
            var preview = document.getElementById('currentImagePreview');
            
            if (!manualUrl) {
                alert('Lütfen resim URL\'si girin.');
                return;
            }

            try {
                new URL(manualUrl);
            } catch (error) {
                alert('Lütfen gecerli bir resim URL\'si girin.');
                return;
            }
            
            imageArea.style.display = 'block';
            preview.style.display = 'none';
            pastedImageDataInput.value = '';
            
            var imgDiv = document.createElement('div');
            imgDiv.style.cursor = 'pointer';
            imgDiv.style.border = '2px solid #3498db';
            imgDiv.style.borderRadius = '5px';
            imgDiv.style.padding = '2px';
            imgDiv.innerHTML = '<img src="' + manualUrl + '" style="height: 80px; display:block; border-radius: 3px;">';
            
            imgDiv.onclick = function() {
                Array.from(grid.children).forEach(c => {
                    c.style.borderColor = 'transparent';
                    c.style.opacity = '0.7';
                });
                this.style.borderColor = '#3498db';
                this.style.opacity = '1';
                imageUrlInput.value = manualUrl;
            };
            
            grid.appendChild(imgDiv);
            imgDiv.click(); // Auto-select
            document.getElementById('manualImageUrl').value = '';
        }

        document.getElementById('pasteImageArea').addEventListener('paste', function(event) {
            var items = event.clipboardData && event.clipboardData.items ? Array.from(event.clipboardData.items) : [];
            var imageItem = items.find(function(item) {
                return item.type && item.type.indexOf('image/') === 0;
            });

            if (!imageItem) {
                return;
            }

            event.preventDefault();

            var file = imageItem.getAsFile();
            if (!file) {
                alert('Panodaki resim okunamadi.');
                return;
            }

            var reader = new FileReader();
            reader.onload = function(loadEvent) {
                var dataUrl = loadEvent.target.result;
                var imageArea = document.getElementById('imageSelectionArea');
                var preview = document.getElementById('currentImagePreview');
                var imageUrlInput = document.getElementById('imageUrlInput');
                var pastedImageDataInput = document.getElementById('pastedImageData');
                var manualImageInput = document.getElementById('manualImageUrl');
                var grid = document.getElementById('fetchedImagesGrid');

                pastedImageDataInput.value = dataUrl;
                imageUrlInput.value = '';
                manualImageInput.value = '';
                grid.innerHTML = '';
                imageArea.style.display = 'block';
                preview.style.display = 'block';
                preview.innerHTML = '<img src="' + dataUrl + '" style="height: 100px; border-radius: 5px; border: 2px solid #3498db;"><p style="font-size:0.8em; color:#666;">Panodan Eklenen Gorsel</p>';
            };

            reader.readAsDataURL(file);
        });
    </script>
</body>

</html>