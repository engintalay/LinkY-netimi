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
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Instagram için özel ayarlar
    if (strpos($url, 'instagram.com') !== false) {
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
        ]);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; LinkManager/1.0)');
    }

    $html = curl_exec($ch);
    
    $data = [
        'title' => '',
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
        $data['title'] = trim($matches[1]);
    } else {
        $data['title'] = parse_url($url, PHP_URL_HOST);
    }

    // Get base URL for resolving relative URLs
    $baseUrl = $url;

    // Images (og:image)
    if (preg_match_all('/<meta property="og:image" content="(.*?)"/i', $html, $matches)) {
        foreach($matches[1] as $img) {
            $imgUrl = makeAbsoluteUrl($img, $baseUrl);
            
            // Instagram: Try to get higher resolution version
            if (strpos($url, 'instagram.com') !== false && strpos($imgUrl, 'scontent') !== false) {
                // Replace size parameters for higher resolution
                $imgUrl = preg_replace('/\/s\d+x\d+\//', '/s1080x1080/', $imgUrl);
                $imgUrl = preg_replace('/\?.*$/', '', $imgUrl); // Remove query params
            }
            
            $data['images'][] = $imgUrl;
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
