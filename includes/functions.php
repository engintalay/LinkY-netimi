<?php
// includes/functions.php

session_start();

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

function fetchUrlTitle($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; LinkManager/1.0)');

    $html = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return parse_url($url, PHP_URL_HOST); // Fallback to domain
    }

    curl_close($ch);

    if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
        return trim($matches[1]);
    }

    return parse_url($url, PHP_URL_HOST); // Fallback
}

function makeAbsoluteUrl($url, $base)
{
    if (empty($url)) return '';
    // Already absolute URL (including data: URLs)
    if (preg_match('/^[a-z]+:\/\//i', $url)) return $url;
    // Data URLs
    if (strpos($url, 'data:') === 0) return $url;
    // Absolute path
    if ($url[0] === '/') {
        $baseParts = parse_url($base);
        return $baseParts['scheme'] . '://' . $baseParts['host'] . $url;
    }
    // Relative path
    $baseParts = parse_url($base);
    $path = dirname($baseParts['path']);
    return $baseParts['scheme'] . '://' . $baseParts['host'] . $path . '/' . $url;
}

function fetchUrlDetails($url)
{
    // Handle Instagram URLs specially
    if (strpos($url, 'instagram.com') !== false) {
        return fetchInstagramDetails($url);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; LinkManager/1.0)');

    $html = curl_exec($ch);
    
    $data = [
        'title' => '',
        'description' => '',
        'images' => []
    ];

    if (curl_errno($ch)) {
        curl_close($ch);
        $data['title'] = parse_url($url, PHP_URL_HOST);
        return $data;
    }

    curl_close($ch);

    // Title
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
        $data['title'] = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } else {
        $data['title'] = parse_url($url, PHP_URL_HOST);
    }

    // Get base URL for resolving relative URLs
    $baseUrl = $url;

    // Description
    if (preg_match('/<meta name="description" content="(.*?)"/i', $html, $matches)) {
        $data['description'] = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif (preg_match('/<meta property="og:description" content="(.*?)"/i', $html, $matches)) {
        $data['description'] = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif (preg_match('/<meta name="twitter:description" content="(.*?)"/i', $html, $matches)) {
        $data['description'] = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Images (og:image)
    if (preg_match_all('/<meta property="og:image" content="(.*?)"/i', $html, $matches)) {
        foreach($matches[1] as $img) {
            $data['images'][] = makeAbsoluteUrl($img, $baseUrl);
        }
    }
    // twitter:image
    if (preg_match_all('/<meta name="twitter:image" content="(.*?)"/i', $html, $matches)) {
        foreach($matches[1] as $img) {
            $data['images'][] = makeAbsoluteUrl($img, $baseUrl);
        }
    }
    // link rel="image_src"
    if (preg_match_all('/<link rel="image_src" href="(.*?)"/i', $html, $matches)) {
         foreach($matches[1] as $img) {
            $data['images'][] = makeAbsoluteUrl($img, $baseUrl);
        }
    }
    // Fallback: Find all img tags
    if (preg_match_all('/<img[^>]+src="([^"]+)"/i', $html, $matches)) {
        foreach($matches[1] as $img) {
            $absoluteUrl = makeAbsoluteUrl($img, $baseUrl);
            if ($absoluteUrl) {
                $data['images'][] = $absoluteUrl;
            }
        }
    }

    $data['images'] = array_unique($data['images']);
    $data['images'] = array_values($data['images']);

    return $data;
}

function fetchInstagramDetails($url)
{
    $data = [
        'title' => '',
        'description' => '',
        'images' => []
    ];
    
    // Try to extract username from URL (for profile links like instagram.com/username/)
    if (preg_match('/instagram\.com\/([a-zA-Z0-9_.]+)(?:\/|$)/', $url, $matches)) {
        $username = $matches[1];
        // Skip common paths
        if (!in_array($username, ['p', 'reels', 'stories', 'tv', 'explore', 'accounts'])) {
            $data['title'] = '@' . $username;
        }
    }
    
    // If no username found, try to get post ID
    $post_id = '';
    if (preg_match('/instagram\.com\/(?:p|reels)\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $post_id = $matches[1];
    }
    
    // If still no title, use post ID
    if (empty($data['title']) && $post_id) {
        $data['title'] = 'Instagram Post';
    }
    
    // If still no title, use hostname
    if (empty($data['title'])) {
        $data['title'] = parse_url($url, PHP_URL_HOST);
    }
    
    // Fetch HTML to get image
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; LinkManager/1.0)');
    
    $html = curl_exec($ch);
    curl_close($ch);
    
    // Look for og:image in HTML
    if (preg_match('/<meta property="og:image" content="([^"]+)"/i', $html, $matches)) {
        $data['images'][] = makeAbsoluteUrl($matches[1], $url);
    }
    
    // If still no image, use a placeholder
    if (empty($data['images'])) {
        $data['images'][] = 'https://placehold.co/600x400/6a11cb/ffffff?text=Instagram+Link';
    }
    
    return $data;
}

function downloadImage($imageUrl, $imagesDir = 'images/')
{
    if (empty($imageUrl)) return '';
    
    // Validate URL
    if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) return '';
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $imageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; LinkManager/1.0)');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($response)) return '';
    
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Check content type
    if (!preg_match('/Content-Type:\s*image\/([a-z0-9]+)/i', $header, $contentTypeMatch)) {
        return '';
    }
    
    $extension = strtolower($contentTypeMatch[1]);
    if (!in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'webp'])) {
        return '';
    }
    
    // Limit file size (5MB)
    if (strlen($body) > 5 * 1024 * 1024) return '';
    
    // Generate unique filename
    $filename = uniqid('img_', true) . '.' . $extension;
    $filepath = $imagesDir . $filename;
    
    // Save file
    if (file_put_contents($filepath, $body) === false) {
        return '';
    }
    
    return $filepath;
}
