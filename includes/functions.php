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

function fetchUrlDetails($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; LinkManager/1.0)');

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

    // Images (og:image)
    if (preg_match_all('/<meta property="og:image" content="(.*?)"/i', $html, $matches)) {
        foreach($matches[1] as $img) {
            $data['images'][] = $img;
        }
    }
    // twitter:image
    if (preg_match_all('/<meta name="twitter:image" content="(.*?)"/i', $html, $matches)) {
        foreach($matches[1] as $img) {
            $data['images'][] = $img;
        }
    }
    // link rel="image_src"
    if (preg_match_all('/<link rel="image_src" href="(.*?)"/i', $html, $matches)) {
         foreach($matches[1] as $img) {
            $data['images'][] = $img;
        }
    }

    $data['images'] = array_unique($data['images']);
    $data['images'] = array_values($data['images']);

    return $data;
}
