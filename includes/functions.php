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
