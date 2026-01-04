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
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
         echo "Geçersiz URL";
    } else {
        echo fetchUrlTitle($url);
    }
} else {
    echo "";
}
