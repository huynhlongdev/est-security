<?php
class EST_Security
{
    private static $instance = null;
    private $upload_directory;
    private $base_dir;
    private $defaults = [
        'enable_headers' => 1,
        'enable_xmlrpc' => 0,
        'disable_rest' => 1,
        'block_bots' => 1,
        'login_protect' => 1,
        'integrity_scan' => 1,
        'scan_interval' => 'daily', // hourly, twicedaily, daily
    ];
    private $option_name = 'wp_shc_options';
    private $options = [];
    private $file_editing;

    public function __construct()
    {
        $this->upload_directory = wp_upload_dir();
        $this->base_dir = $this->upload_directory['basedir'];

        $this->options = get_option($this->option_name, []);
        $this->options = wp_parse_args($this->options, $this->defaults);
        register_setting($this->option_name, $this->option_name, [$this, 'sanitize_options']);
        $this->file_editing = get_option('est_disable_file_editing', 1);

        // disable file editor
        if ($this->file_editing == 1) {
            if (!defined('DISALLOW_FILE_EDIT')) define('DISALLOW_FILE_EDIT', true);
        }

        // add security on headers
        if (!is_admin() && !wp_doing_ajax() && get_option('est_prevent_site_display_inside_frame', 1) == 1) {
            add_action('send_headers', array($this, 'set_header'));
        }

        // Remove wp_generator
        add_action('init', [$this, 'remove_wp_generator']);

        // block bad bots
        if ($this->options['block_bots']) add_action('init', [$this, 'block_bad_bots']);

        if (!$this->options['enable_xmlrpc']) {
            add_filter('xmlrpc_enabled', '__return_false');
        }

        /** Disable REST API */
        if ($this->options['disable_rest']) {
            add_filter('rest_authentication_errors', array($this, 'disable_rest_api'));
            add_filter('rest_endpoints', array($this, 'disable_user_endpoint'));
        }

        /** Remove files */
        add_action('wp_ajax_delete_php_files_ajax', array($this, 'delete_php_files_ajax'));
        add_action('wp_ajax_remove_log', array($this, 'delete_log'));
        add_action('parse_request', [$this, 'block_sqli_patterns'], 1);

        // Hook vào admin
        add_action('admin_enqueue_scripts', [$this, 'register_script']);

        if (is_admin()) {
            add_action('init', [$this, 'scan_admin_role']);
        }
    }

    public function register_script()
    {
        wp_enqueue_style(
            'est-security',
            plugin_dir_url(__FILE__) . 'assets/style.css',
            [],
            date('YmdHis')
        );
        wp_enqueue_script(
            'est-security',
            plugin_dir_url(__FILE__) . 'assets/script.js',
            [],
            date('YmdHis'),
            true
        );
        wp_localize_script('est-security', 'SecurityDBPrefix', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('change_db_prefix_nonce'),
            'unlock_nonce'    => wp_create_nonce('unlock_nonce')
        ]);
    }

    public function remove_wp_generator()
    {
        remove_action('wp_head', 'wp_generator');
    }

    public function block_sqli_patterns()
    {
        $payload = strtolower($_SERVER['REQUEST_URI'] . ' ' . json_encode($_REQUEST));
        $patterns = ['union select', 'drop table', 'information_schema', 'sleep(', 'benchmark('];
        foreach ($patterns as $p) {
            if (strpos($payload, $p) !== false) {
                status_header(403);
                wp_die('Forbidden', 'Forbidden', array('response' => 403));
            }
        }
    }

    // Thêm header cho request
    function set_header()
    {
        $recaptcha = '';
        $site_key        = get_option('est_recaptcha_site_key', '');
        $secret_key      = get_option('est_recaptcha_secret_key', '');
        $recaptcha_enabled = get_option('est_recaptcha_enabled', 0);

        if ($recaptcha_enabled == 1 && !empty($site_key) && !empty($secret_key)) {
            $recaptcha = ' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/ ';
        }

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://www.google-analytics.com {$recaptcha}",
            "img-src 'self' data: https://www.google-analytics.com https://www.googletagmanager.com {$recaptcha}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "frame-src {$recaptcha}",
            "connect-src 'self' https://www.google-analytics.com https://www.googletagmanager.com {$recaptcha}",
        ];

        $headers = [
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Content-Security-Policy' => implode('; ', $csp) . ';'
        ];

        foreach ($headers as $key => $value) {
            if (!headers_sent()) {
                header("$key: $value");
            }
        }
    }

    // Lưu dữ liệu
    public function sanitize_options($input)
    {
        $out = [];
        $out['enable_headers'] = !empty($input['enable_headers']) ? 1 : 0;
        $out['enable_xmlrpc'] = !empty($input['enable_xmlrpc']) ? 1 : 0;
        $out['disable_rest'] = !empty($input['disable_rest']) ? 1 : 0;
        $out['block_bots'] = !empty($input['block_bots']) ? 1 : 0;
        return $out;
    }

    function init()
    {
        $this->fix_permissions(ABSPATH);
        @chmod(ABSPATH . 'wp-config.php', 0400);
        @chmod(ABSPATH . '.htaccess', 0400);

        $paths = get_option('custom_disabled_permissions', []);

        if (!empty($paths)) {
            foreach ($paths as $path => $value) {
                if ($value == true) {
                    $this->fix_permissions_no_write($path, 0555);
                } else {
                    $this->fix_permissions_no_write($path, 0755);
                }
            }
        }
    }

    // Xóa file php trong wp-upload
    function delete_php_files_ajax()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', DOMAIN));
        }
        check_ajax_referer('delete_php_files', 'delete_php_files_nonce');
        $base_dir = wp_upload_dir()['basedir'];
        $deleted = 0;
        if (!empty($_POST['php_files_to_delete']) && is_array($_POST['php_files_to_delete'])) {
            foreach ($_POST['php_files_to_delete'] as $php_file) {
                $real_file = realpath($php_file);
                if ($real_file && strpos($real_file, $base_dir) === 0 && is_file($real_file)) {
                    if (@unlink($real_file)) {
                        $deleted++;
                    }
                }
            }
        }

        $files = $this->scan_directory_for_php($this->base_dir);

        ob_start();
        if ($files) {
            foreach ($files as $file):
                echo '<li>
                <label>
                    <input type="checkbox" name="php_files_to_delete[]" value="' . esc_attr($file) . '">
                    ' . esc_html(str_replace(ABSPATH, '', $file)) . '</label></li>';
            endforeach;
        }
        $respone = ob_get_clean();

        if ($deleted > 0) {
            wp_send_json_success([
                'message' => sprintf(esc_html__('%d PHP files were deleted.', DOMAIN), $deleted),
                'remaining_files' => $respone
            ]);
        } else {
            wp_send_json_error([
                'message' => esc_html__('No PHP files were deleted.', DOMAIN),
                'remaining_files' => ""
            ]);
        }
    }

    // Xóa file log
    public function delete_log()
    {
        $debug_log = WP_CONTENT_DIR . '/debug.log';

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', DOMAIN));
            exit;
        }

        if (!file_exists($debug_log)) {
            wp_send_json_error(__('debug.log does not exist.', DOMAIN));
            exit;
        }

        if (@unlink($debug_log)) {
            wp_send_json_success(__('debug.log deleted successfully.', DOMAIN));
        } else {
            wp_send_json_error(sprintf(__('Could not delete debug.log. Please check file permissions at: %s', DOMAIN), esc_html($debug_log)));
        }

        exit;
    }

    // Quét các file php
    public function scan_directory_for_php($dir)
    {
        $php_files = [];

        if (empty($dir)) {
            return [];
        }
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $php_files = array_merge($php_files, $this->scan_directory_for_php($path));
            } elseif (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
                $php_files[] = $path;
            }
        }

        return $php_files;
    }

    // Chặn rest api khi chưa login
    function disable_rest_api($result)
    {
        $allowed_routes = [
            '/contact-form-7/v1/contact-forms',
            '/string-locator/v1/get-directory-structure',
        ];

        $current_route = $_SERVER['REQUEST_URI'];

        foreach ($allowed_routes as $route) {
            if (strpos($current_route, $route) !== false) {
                return $result;
            }
        }

        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', __('You must be logged in to access the REST API.', DOMAIN), array('status' => 401));
        }

        return $result;
    }

    // Tắt user api
    function disable_user_endpoint($endpoints)
    {
        unset($endpoints['/wp/v2/users']);
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        return $endpoints;
    }

    // Chặn các request tự động (bot xấu, tool hack, script scan lỗ hổng)
    public function block_bad_bots()
    {
        // Lấy user agent
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower(trim($_SERVER['HTTP_USER_AGENT'])) : '';

        // Nếu không có UA → có thể là request độc hại
        if (empty($ua)) {
            status_header(403);
            wp_die(__('Forbidden: Empty User-Agent is not allowed.', 'domain'), 'Forbidden', ['response' => 403]);
        }

        // Danh sách UA bị chặn
        $bad_agents = [
            'masscan',
            'acunetix',
            'sqlmap',
            'nikto',
            'crawler',        // generic crawler
            'curl',           // curl script
            'python-requests',
            'wget',
            'libwww-perl',
            'scan',
            'dirbuster',
            'nessus',
        ];

        // Danh sách UA hợp pháp để **bỏ qua**
        $allowed_bots = [
            'googlebot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandex',
            'facebookexternalhit',
            'twitterbot',
        ];

        // Nếu user-agent có chứa bot hợp pháp thì bỏ qua
        foreach ($allowed_bots as $good) {
            if (strpos($ua, $good) !== false) {
                return;
            }
        }

        // Kiểm tra UA độc hại
        foreach ($bad_agents as $bad) {
            if (strpos($ua, $bad) !== false) {
                status_header(403);
                nocache_headers();
                wp_die(__('Forbidden: Suspicious user-agent detected.', 'domain'), 'Forbidden', ['response' => 403]);
            }
        }
    }

    function fix_permissions($path)
    {
        if (!file_exists($path)) return;

        if (is_dir($path)) {
            @chmod($path, 0755);
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $this->fix_permissions($path . DIRECTORY_SEPARATOR . $item);
            }
        } else {
            @chmod($path, 0644);
        }
    }

    function fix_permissions_no_write($path, $permissions = 0555)
    {
        if (!file_exists($path)) return;

        if (is_dir($path)) {
            @chmod($path, $permissions);
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $this->fix_permissions_no_write($path . DIRECTORY_SEPARATOR . $item);
            }
        } else {
            @chmod($path, 0444);
        }
    }

    function scan_admin_role()
    {
        $admins = get_users([
            'role' => 'administrator',
            'number' => 1,
            'search' => 'admin',
            'search_columns' => ['user_login'],
        ]);

        if (!empty($admins)) {
            global $wpdb;
            foreach ($admins as $info) {
                $data = $info->data;
                $user_id = $info->ID;
                $email = $data->user_email;
                $new_username = $email;
                if (!username_exists($new_username)) {
                    $wpdb->update(
                        $wpdb->users,
                        ['user_login' => $new_username],
                        ['ID' => $user_id]
                    );
                    clean_user_cache($user_id);

                    $subject = 'Replace username';
                    $message = 'The system has scanned and does not allow setting the username as "admin". The system has automatically updated it to a new username based on your email.';
                    $message .= "Username: {$new_username}\n";
                    $message .= "Website: " . home_url('/');
                    wp_mail($email, $subject, $message);
                }
            }
        }
    }
}

new EST_Security();
