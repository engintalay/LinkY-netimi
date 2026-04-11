<?php
// update_link_ajax.php
require_once 'includes/db.php';
require_once 'includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

if ($action === 'update_link_info' && $id) {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $image_url = sanitize($_POST['image_url'] ?? '');
    
    if ($title || $description || $image_url) {
        $updates = [];
        $params = [];
        
        if ($title) {
            $updates[] = 'title = ?';
            $params[] = $title;
        }
        
        if ($description) {
            $updates[] = 'description = ?';
            $params[] = $description;
        }
        
        if ($image_url) {
            // Get existing local_image for cleanup
            $stmt = $pdo->prepare("SELECT local_image, image_url FROM links WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            
            $local_image = $existing['local_image'] ?? '';
            if (!empty($image_url) && $image_url !== $existing['image_url']) {
                // New image URL - delete old local image and download new one
                if (!empty($local_image) && file_exists($local_image)) {
                    unlink($local_image);
                }
                $local_image = downloadImage($image_url);
            }
            
            $updates[] = 'image_url = ?';
            $params[] = $image_url;
            $updates[] = 'local_image = ?';
            $params[] = $local_image;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE links SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No data to update']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}
