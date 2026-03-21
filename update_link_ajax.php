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
    
    if ($title || $description) {
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
