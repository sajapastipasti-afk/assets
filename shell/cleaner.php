<?php
/**
 * WordPress Cache Cleaner - AUTO VERSION
 * Sama persis seperti versi asli tapi sudah otomatis berjalan
 * 
 * Cara penggunaan:
 * 1. Upload file ini ke root WordPress
 * 2. Selesai! Langsung auto jalan setiap 5 menit
 */

// ============== KONFIGURASI ==============
define('WP_CACHE_CLEANER_PASSWORD', 'admin123'); // Password (untuk akses manual jika diperlukan)
define('WP_CACHE_CLEANER_INTERVAL', 300); // 5 menit dalam detik
define('WP_CACHE_CLEANER_DEBUG', false);
// =========================================

// Load WordPress
$wp_load = false;
$paths = [
    __DIR__ . '/wp-load.php',
    dirname(__DIR__) . '/wp-load.php',
    dirname(dirname(__DIR__)) . '/wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_load = true;
        break;
    }
}

if (!$wp_load) {
    die('❌ Tidak dapat memuat WordPress. Pastikan file ini berada di root folder WordPress.');
}

// ============== FUNGSI CLEANING (SAMA PERSIS) ==============
function clean_all_caches(&$results) {
    $results['object_cache'] = clear_object_cache();
    $results['transients'] = clear_transients();
    $results['plugins'] = clear_plugin_caches();
    $results['hosting'] = clear_hosting_caches();
    $results['opcache'] = clear_opcache();
    $results['browser'] = clear_browser_cache();
    return $results;
}

function clear_object_cache() {
    try {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            return ['status' => 'success', 'message' => '✅ Object cache berhasil dibersihkan'];
        }
        return ['status' => 'warning', 'message' => '⚠️ Fungsi wp_cache_flush tidak tersedia'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
    }
}

function clear_transients() {
    global $wpdb;
    $results = [];
    
    try {
        $expired = $wpdb->query(
            "DELETE FROM $wpdb->options 
            WHERE option_name LIKE '_transient_timeout%' 
            AND option_value < UNIX_TIMESTAMP()"
        );
        
        $results['expired'] = "🗑️ Menghapus $expired transients expired";
        
        $wpdb->query(
            "DELETE FROM $wpdb->options 
            WHERE option_name LIKE '_transient%' 
            AND option_name NOT LIKE '_transient_timeout%'"
        );
        
        return ['status' => 'success', 'message' => $results];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
    }
}

function clear_plugin_caches() {
    $results = [];
    
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
        $results['wp_rocket'] = '✅ WP Rocket cache dibersihkan';
    }
    
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
        $results['w3_total_cache'] = '✅ W3 Total Cache dibersihkan';
    } elseif (function_exists('w3tc_pgcache_flush')) {
        w3tc_pgcache_flush();
        $results['w3_total_cache'] = '✅ W3 Total Cache page cache dibersihkan';
    }
    
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
        $results['wp_super_cache'] = '✅ WP Super Cache dibersihkan';
    }
    
    if (class_exists('LiteSpeed_Cache_API')) {
        LiteSpeed_Cache_API::purge_all();
        $results['litespeed'] = '✅ LiteSpeed Cache dibersihkan';
    } elseif (function_exists('litespeed_purge_all')) {
        litespeed_purge_all();
        $results['litespeed'] = '✅ LiteSpeed Cache dibersihkan';
    }
    
    if (class_exists('WpFastestCache')) {
        $wpfc = new WpFastestCache();
        $wpfc->deleteCache();
        $results['wp_fastest_cache'] = '✅ WP Fastest Cache dibersihkan';
    }
    
    if (class_exists('Cache_Enabler')) {
        Cache_Enabler::clear_total_cache();
        $results['cache_enabler'] = '✅ Cache Enabler dibersihkan';
    }
    
    if (class_exists('autoptimizeCache')) {
        autoptimizeCache::clearall();
        $results['autoptimize'] = '✅ Autoptimize cache dibersihkan';
    }
    
    if (function_exists('sg_cachepress_purge_cache')) {
        sg_cachepress_purge_cache();
        $results['sg_optimizer'] = '✅ SiteGround Optimizer cache dibersihkan';
    }
    
    if (class_exists('Breeze_PurgeCache')) {
        Breeze_PurgeCache::breeze_cache_flush();
        $results['breeze'] = '✅ Breeze cache dibersihkan';
    }
    
    if (empty($results)) {
        $results['none'] = 'ℹ️ Tidak ada plugin cache terdeteksi';
    }
    
    return $results;
}

function clear_hosting_caches() {
    $results = [];
    
    if (function_exists('wpengine_purge_varnish_cache')) {
        wpengine_purge_varnish_cache();
        $results['wpengine'] = '✅ WP Engine Varnish cache dibersihkan';
    } elseif (class_exists('WpeCommon')) {
        WpeCommon::purge_memcached();
        WpeCommon::purge_varnish_cache();
        $results['wpengine'] = '✅ WP Engine cache dibersihkan';
    }
    
    if (class_exists('Kinsta\Cache_Purge')) {
        $kinsta_cache = new Kinsta\Cache_Purge();
        $kinsta_cache->purge_complete_cache();
        $results['kinsta'] = '✅ Kinsta cache dibersihkan';
    } elseif (function_exists('kinsta_cache_purge')) {
        kinsta_cache_purge();
        $results['kinsta'] = '✅ Kinsta cache dibersihkan';
    }
    
    if (function_exists('pantheon_wp_clear_edge_all')) {
        pantheon_wp_clear_edge_all();
        $results['pantheon'] = '✅ Pantheon edge cache dibersihkan';
    }
    
    if (function_exists('gd_system_purge_cache')) {
        gd_system_purge_cache();
        $results['godaddy'] = '✅ GoDaddy cache dibersihkan';
    }
    
    return $results;
}

function clear_opcache() {
    if (!function_exists('opcache_reset')) {
        return ['status' => 'info', 'message' => 'ℹ️ OPcache tidak tersedia di server ini'];
    }
    
    try {
        $result = opcache_reset();
        if ($result) {
            return ['status' => 'success', 'message' => '✅ OPcache berhasil dibersihkan'];
        }
        return ['status' => 'error', 'message' => '❌ Gagal membersihkan OPcache'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
    }
}

function clear_browser_cache() {
    $htaccess_path = ABSPATH . '.htaccess';
    
    if (file_exists($htaccess_path) && is_writable($htaccess_path)) {
        $htaccess_content = file_get_contents($htaccess_path);
        
        if (strpos($htaccess_content, '# Cache Cleaner') === false) {
            $cache_control = "\n# Cache Cleaner - Force no cache\n";
            $cache_control .= "<FilesMatch \"\.(html|php)$\">\n";
            $cache_control .= "Header set Cache-Control \"no-cache, no-store, must-revalidate\"\n";
            $cache_control .= "Header set Pragma \"no-cache\"\n";
            $cache_control .= "Header set Expires 0\n";
            $cache_control .= "</FilesMatch>\n";
            
            file_put_contents($htaccess_path, $cache_control, FILE_APPEND);
            return ['status' => 'success', 'message' => '✅ Browser cache header ditambahkan ke .htaccess'];
        }
        
        return ['status' => 'info', 'message' => 'ℹ️ Browser cache header sudah ada'];
    }
    
    return ['status' => 'warning', 'message' => '⚠️ Tidak dapat mengakses .htaccess untuk browser cache'];
}

// ============== AUTO CLEAN EXECUTION ==============
function auto_clean_execute() {
    $results = [];
    clean_all_caches($results);
    
    // Log ke file
    $log_file = WP_CONTENT_DIR . '/cache-auto-cleaner-log.txt';
    $log_data = date('Y-m-d H:i:s') . " - " . json_encode($results) . "\n";
    @file_put_contents($log_file, $log_data, FILE_APPEND);
    
    // Simpan last run
    update_option('cache_auto_cleaner_last_run', current_time('mysql'));
    
    return $results;
}

// ============== SETUP CRON OTOMATIS ==============
// Tambahkan interval 5 menit
function auto_add_cron_interval($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => WP_CACHE_CLEANER_INTERVAL,
        'display' => __('Setiap 5 Menit')
    );
    return $schedules;
}
add_filter('cron_schedules', 'auto_add_cron_interval');

// Hook untuk auto clean
add_action('auto_cache_cleaner_event', 'auto_clean_execute');

// Setup cron otomatis (hanya sekali)
if (!wp_next_scheduled('auto_cache_cleaner_event')) {
    wp_schedule_event(time(), 'every_five_minutes', 'auto_cache_cleaner_event');
    update_option('cache_auto_cleaner_installed', true);
    update_option('cache_auto_cleaner_installed_time', current_time('mysql'));
}

// ============== CEK APAKAH ADA PERINTAH KHUSUS ==============
if (isset($_GET['force_clean']) && $_GET['force_clean'] === WP_CACHE_CLEANER_PASSWORD) {
    // Force clean via browser
    $results = auto_clean_execute();
    // Tampilkan hasil seperti script asli
    display_results($results);
    exit;
}

// Jika akses normal - tampilkan status
if (!defined('DOING_CRON') && php_sapi_name() !== 'cli' && !isset($_GET['cron'])) {
    // Tampilkan status cleaner
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>WordPress Cache Cleaner - Auto Running</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 40px 20px;
            }
            
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 28px;
                margin-bottom: 10px;
            }
            
            .header p {
                opacity: 0.9;
                font-size: 14px;
            }
            
            .content {
                padding: 30px;
            }
            
            .status-box {
                background: #f0f4ff;
                border: 2px solid #667eea;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .badge {
                display: inline-block;
                background: #10b981;
                color: white;
                padding: 8px 20px;
                border-radius: 20px;
                font-weight: bold;
                font-size: 16px;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 15px;
                margin: 20px 0;
            }
            
            .info-item {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 10px;
                text-align: center;
            }
            
            .info-item .label {
                font-size: 12px;
                color: #999;
                display: block;
            }
            
            .info-item .value {
                font-size: 18px;
                font-weight: bold;
                color: #333;
                margin-top: 5px;
            }
            
            .footer {
                background: #f8f9fa;
                padding: 20px;
                text-align: center;
                color: #666;
                font-size: 12px;
                border-top: 1px solid #e0e0e0;
            }
            
            .button {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                transition: background 0.3s;
                margin: 5px;
            }
            
            .button:hover {
                background: #5a67d8;
            }
            
            .log-preview {
                background: #1a1a2e;
                color: #00ff9d;
                padding: 15px;
                border-radius: 8px;
                font-family: monospace;
                font-size: 12px;
                max-height: 150px;
                overflow-y: auto;
                margin-top: 10px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🧹 WordPress Cache Cleaner - AUTO</h1>
                <p>Otomatis membersihkan cache setiap 5 menit</p>
            </div>
            
            <div class="content">
                <div class="status-box">
                    <div class="badge">🟢 ACTIVE - AUTO CLEANING</div>
                    <p style="margin-top: 15px; color: #666; font-size: 14px;">
                        Script berjalan otomatis, tidak perlu login atau klik apapun!
                    </p>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">⏱️ Interval</span>
                        <span class="value">5 Menit</span>
                    </div>
                    <div class="info-item">
                        <span class="label">📅 Installed</span>
                        <span class="value"><?php 
                            $installed = get_option('cache_auto_cleaner_installed_time', 'Belum');
                            echo $installed !== 'Belum' ? date('d M Y', strtotime($installed)) : 'Belum';
                        ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">📝 Last Clean</span>
                        <span class="value"><?php 
                            $last = get_option('cache_auto_cleaner_last_run', 'Belum');
                            echo $last !== 'Belum' ? date('H:i:s', strtotime($last)) : 'Belum';
                        ?></span>
                    </div>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?force_clean=<?php echo WP_CACHE_CLEANER_PASSWORD; ?>" class="button">
                        🔄 Force Clean Sekarang
                    </a>
                    <a href="<?php echo home_url(); ?>" class="button" style="background: #6b7280;">
                        🏠 Kembali ke Website
                    </a>
                </div>
                
                <?php if (file_exists(WP_CONTENT_DIR . '/cache-auto-cleaner-log.txt')): ?>
                    <div>
                        <h3 style="margin-bottom: 10px; color: #333;">📝 Log Terakhir</h3>
                        <div class="log-preview">
                            <?php 
                            $log = @file_get_contents(WP_CONTENT_DIR . '/cache-auto-cleaner-log.txt');
                            if ($log) {
                                $lines = explode("\n", $log);
                                $last_lines = array_slice($lines, -5);
                                echo htmlspecialchars(implode("\n", $last_lines));
                            } else {
                                echo "Belum ada log";
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px;">
                    <p style="font-size: 13px; color: #856404;">
                        🔑 Password: <code><?php echo WP_CACHE_CLEANER_PASSWORD; ?></code>
                    </p>
                </div>
            </div>
            
            <div class="footer">
                <strong>WordPress Cache Cleaner - Auto</strong> | Upload dan langsung jalan<br>
                <span style="font-size: 11px;">Ganti password di baris 11 untuk keamanan</span>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// ============== FUNGSI TAMPIL HASIL (SAMA PERSIS) ==============
function display_results($results) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>WordPress Cache Cleaner - Hasil</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 40px 20px;
            }
            
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 28px;
                margin-bottom: 10px;
            }
            
            .header p {
                opacity: 0.9;
                font-size: 14px;
            }
            
            .content {
                padding: 30px;
            }
            
            .result-section {
                margin-bottom: 30px;
                border: 1px solid #e0e0e0;
                border-radius: 10px;
                overflow: hidden;
            }
            
            .section-title {
                background: #f8f9fa;
                padding: 15px 20px;
                font-weight: bold;
                font-size: 18px;
                border-bottom: 1px solid #e0e0e0;
                color: #333;
            }
            
            .section-content {
                padding: 20px;
            }
            
            .message-item {
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
                font-size: 14px;
            }
            
            .message-item:last-child {
                border-bottom: none;
            }
            
            .success {
                color: #10b981;
            }
            
            .error {
                color: #ef4444;
            }
            
            .warning {
                color: #f59e0b;
            }
            
            .info {
                color: #3b82f6;
            }
            
            .button {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                transition: background 0.3s;
                margin-top: 20px;
            }
            
            .button:hover {
                background: #5a67d8;
            }
            
            .footer {
                background: #f8f9fa;
                padding: 20px;
                text-align: center;
                color: #666;
                font-size: 12px;
                border-top: 1px solid #e0e0e0;
            }
            
            .time-info {
                background: #e0e7ff;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 14px;
                color: #4338ca;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🧹 WordPress Cache Cleaner</h1>
                <p>Semua cache WordPress telah dibersihkan</p>
            </div>
            
            <div class="content">
                <div class="time-info">
                    ⏱️ Waktu eksekusi: <?php echo date('d F Y H:i:s'); ?>
                </div>
                
                <?php foreach ($results as $category => $data): ?>
                    <div class="result-section">
                        <div class="section-title">
                            <?php 
                            $icons = [
                                'object_cache' => '🗄️',
                                'transients' => '⏱️',
                                'plugins' => '🔌',
                                'hosting' => '🏢',
                                'opcache' => '⚡',
                                'browser' => '🌐'
                            ];
                            $titles = [
                                'object_cache' => 'Object Cache',
                                'transients' => 'Transients',
                                'plugins' => 'Plugin Cache',
                                'hosting' => 'Hosting Cache',
                                'opcache' => 'PHP OPcache',
                                'browser' => 'Browser Cache'
                            ];
                            echo ($icons[$category] ?? '📦') . ' ' . ($titles[$category] ?? ucfirst(str_replace('_', ' ', $category)));
                            ?>
                        </div>
                        <div class="section-content">
                            <?php if (is_array($data) && isset($data['status'])): ?>
                                <div class="message-item <?php echo $data['status']; ?>">
                                    <?php echo $data['message']; ?>
                                </div>
                            <?php elseif (is_array($data)): ?>
                                <?php foreach ($data as $key => $message): ?>
                                    <div class="message-item">
                                        <?php echo $message; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="message-item">
                                    <?php echo $data; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align: center;">
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="button">
                        🔄 Lihat Status
                    </a>
                    <a href="<?php echo home_url(); ?>" class="button" style="background: #6b7280; margin-left: 10px;">
                        🏠 Kembali ke Website
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <strong>WordPress Cache Cleaner - Auto</strong> | Membersihkan cache setiap 5 menit<br>
                <span style="font-size: 11px;">Ganti password di baris 11 file ini untuk keamanan</span>
            </div>
        </div>
    </body>
    </html>
    <?php
}
