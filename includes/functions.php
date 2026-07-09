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

function fetchUrlContentWithCurl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Upgrade-Insecure-Requests: 1'
    ]);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_ENCODING, '');

    $response = curl_exec($ch);
    $error = curl_errno($ch) ? curl_error($ch) : '';
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    curl_close($ch);

    if ($response === false) {
        return [
            'html' => '',
            'headers' => '',
            'http_code' => $httpCode,
            'effective_url' => $effectiveUrl,
            'error' => $error,
        ];
    }

    return [
        'html' => substr($response, $headerSize),
        'headers' => substr($response, 0, $headerSize),
        'http_code' => $httpCode,
        'effective_url' => $effectiveUrl,
        'error' => $error,
    ];
}

function isBotChallengePage($html, $headers = '', $httpCode = 0)
{
    $challengeMarkers = [
        'cf-mitigated: challenge',
        'Just a moment...',
        'Enable JavaScript and cookies to continue',
        '/cdn-cgi/challenge-platform/',
    ];

    $haystack = strtolower($headers . "\n" . $html);
    foreach ($challengeMarkers as $marker) {
        if (strpos($haystack, strtolower($marker)) !== false) {
            return true;
        }
    }

    return $httpCode === 403 && strpos($haystack, 'cloudflare') !== false;
}

function findHeadlessBrowserBinary()
{
    static $browserBinary = null;
    static $resolved = false;

    if ($resolved) {
        return $browserBinary;
    }

    $resolved = true;

    if (!function_exists('shell_exec')) {
        return null;
    }

    $candidates = ['chromium-browser', 'chromium', 'google-chrome', 'google-chrome-stable', 'microsoft-edge'];
    foreach ($candidates as $candidate) {
        $path = trim((string) shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null'));
        if ($path !== '') {
            $browserBinary = $path;
            break;
        }
    }

    return $browserBinary;
}

function fetchUrlContentWithBrowser($url)
{
    $browserBinary = findHeadlessBrowserBinary();
    if (!$browserBinary) {
        return '';
    }

    $command = escapeshellarg($browserBinary)
        . ' --headless --disable-gpu --no-sandbox --virtual-time-budget=12000 --dump-dom '
        . escapeshellarg($url)
        . ' 2>/dev/null';

    $html = shell_exec($command);
    if (!is_string($html) || trim($html) === '') {
        return '';
    }

    return $html;
}

function parseHtmlDetails($html, $baseUrl, $fallbackTitle = '')
{
    $data = [
        'title' => $fallbackTitle,
        'description' => '',
        'images' => []
    ];

    if (trim($html) === '') {
        return $data;
    }

    $previousUseInternalErrors = libxml_use_internal_errors(true);
    $dom = new DOMDocument();

    if (!@$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET)) {
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);
        return $data;
    }

    $xpath = new DOMXPath($dom);

    $titleCandidates = [
        '//meta[@property="og:title"]/@content',
        '//meta[@name="twitter:title"]/@content',
        '//title',
    ];

    foreach ($titleCandidates as $query) {
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            $value = trim($nodes->item(0)->nodeValue);
            if ($value !== '') {
                $data['title'] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                break;
            }
        }
    }

    $descriptionCandidates = [
        '//meta[@name="description"]/@content',
        '//meta[@property="og:description"]/@content',
        '//meta[@name="twitter:description"]/@content',
    ];

    foreach ($descriptionCandidates as $query) {
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            $value = trim($nodes->item(0)->nodeValue);
            if ($value !== '') {
                $data['description'] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                break;
            }
        }
    }

    $imageQueries = [
        '//meta[@property="og:image"]/@content',
        '//meta[@name="twitter:image"]/@content',
        '//link[@rel="image_src"]/@href',
        '//img/@src',
    ];

    foreach ($imageQueries as $query) {
        $nodes = $xpath->query($query);
        if (!$nodes) {
            continue;
        }

        foreach ($nodes as $node) {
            $imageUrl = makeAbsoluteUrl(trim($node->nodeValue), $baseUrl);
            if ($imageUrl !== '') {
                $data['images'][] = $imageUrl;
            }
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previousUseInternalErrors);

    $data['images'] = array_values(array_unique($data['images']));

    return $data;
}

function fetchUrlTitle($url)
{
    $details = fetchUrlDetails($url);
    return $details['title'] ?: parse_url($url, PHP_URL_HOST);
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
    
    $data = [
        'title' => '',
        'description' => '',
        'images' => [],
        'error' => ''
    ];

    $fallbackTitle = parse_url($url, PHP_URL_HOST);
    $response = fetchUrlContentWithCurl($url);
    $html = $response['html'];
    $baseUrl = $response['effective_url'] ?: $url;

    if ($response['error'] !== '') {
        $data['title'] = $fallbackTitle;
        return $data;
    }

    $challengeDetected = isBotChallengePage($html, $response['headers'], (int) $response['http_code']);
    if ($challengeDetected) {
        $browserHtml = fetchUrlContentWithBrowser($url);
        if ($browserHtml !== '') {
            $html = $browserHtml;
        }
    }

    if (isBotChallengePage($html)) {
        $data['error'] = 'Bu site bot korumasi kullandigi icin baslik ve aciklama otomatik olarak cekilemiyor.';
        return $data;
    }

    $data = parseHtmlDetails($html, $baseUrl, $fallbackTitle);
    $data['error'] = '';

    if ($data['title'] === '') {
        $data['title'] = $fallbackTitle;
    }

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

    function saveDataUrlImage($dataUrl, $imagesDir = 'images/')
    {
        if (empty($dataUrl) || strpos($dataUrl, 'data:image/') !== 0) {
            return '';
        }

        if (!preg_match('/^data:image\/(png|jpeg|jpg|gif|webp);base64,(.+)$/i', $dataUrl, $matches)) {
            return '';
        }

        $extension = strtolower($matches[1]);
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $binary = base64_decode(str_replace(' ', '+', $matches[2]), true);
        if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
            return '';
        }

        $imageInfo = @getimagesizefromstring($binary);
        if ($imageInfo === false || empty($imageInfo['mime']) || strpos($imageInfo['mime'], 'image/') !== 0) {
            return '';
        }

        $filename = uniqid('img_', true) . '.' . $extension;
        $filepath = $imagesDir . $filename;

        if (file_put_contents($filepath, $binary) === false) {
            return '';
        }

        return $filepath;
    }
