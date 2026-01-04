<?php
// ajax_fetch_title.php
require_once 'includes/functions.php';

// Allow access only if logged in (for security, prevent open proxy usage)
if (!isLoggedIn()) {
    http_response_code(403);
    die("Unauthorized");
}

$url = $_GET['url'] ?? '';

if ($url) {
    header('Content-Type: application/json');
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
         echo json_encode(['error' => 'Geçersiz URL']);
    } else {
        echo json_encode(fetchUrlDetails($url));
    }
} else {
    echo json_encode(['error' => 'No URL provided']);
}
