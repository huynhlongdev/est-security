<?php
class WP_Hide_Login_Forbidden
{

    private $login_slug = 'est-login';
    private $enabled;
    private $max_session_time = 50000;

    public function __construct()
    {
        // Lấy config từ DB
        $this->enabled = get_option('est_custom_login_enabled', 0);
        $this->login_slug = trim(get_option('est_custom_login_slug', 'est-login'), '/');

        // Nếu chưa bật thì dừng, WP hoạt động bình thường
        if (!$this->enabled) return;

        add_action('init', [$this, 'load_custom_login'], 1);
        add_filter('site_url', [$this, 'replace_login_url'], 10, 3);
        add_filter('network_site_url', [$this, 'replace_login_url'], 10, 3);

        // CHẶN wp-admin NGAY TỪ ĐẦU → NGĂN auth_redirect()
        add_action('init', [$this, 'block_wp_admin'], 0);
        add_action('login_init', [$this, 'block_wp_login_direct']);

        add_filter('logout_url', [$this, 'custom_logout_url'], 10, 2);
        add_action('parse_request', [$this, 'handle_logout'], 999);

        // CHECK session timeout
        add_action('init', [$this, 'check_session_timeout']);
        add_action('wp_login', [$this, 'set_login_time'], 10, 2);
    }

    public function set_login_time($user_login, $user)
    {
        update_user_meta($user->ID, '_login_time', current_time('timestamp'));
    }

    public function check_session_timeout()
    {
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $login_time = get_user_meta($user_id, '_login_time', true);
        $current_time = current_time('timestamp');

        if (!$login_time) {
            update_user_meta($user_id, '_login_time', $current_time);
            return;
        } elseif (($current_time - $login_time) > $this->max_session_time) {
            wp_clear_auth_cookie();

            delete_user_meta($user_id, '_login_time');

            $login_url = home_url('/' . $this->login_slug . '/');
            wp_safe_redirect($login_url);
            exit;
        }
    }

    // Trang login mới
    public function load_custom_login()
    {
        $request_uri = $_SERVER['REQUEST_URI'];

        // Parse URL để lấy path
        $parsed_url = parse_url($request_uri);
        $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';

        // Kiểm tra nếu path khớp với login slug
        if ($path === $this->login_slug) {
            $user_login     = isset($_REQUEST['log']) ? wp_unslash($_REQUEST['log']) : '';
            $error          = '';
            $errors         = new WP_Error();
            $interim_login  = false;
            $action         = isset($_REQUEST['action']) ? wp_unslash($_REQUEST['action']) : 'login';
            $redirect_to    = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '';

            $_SERVER['SCRIPT_NAME'] = '/wp-login.php';
            $_SERVER['PHP_SELF'] = '/wp-login.php';
            require_once ABSPATH . 'wp-login.php';
            exit;
        }
    }

    // Convert tất cả link login sang slug mới
    public function replace_login_url($url, $path, $orig_scheme)
    {
        if (strpos($url, 'wp-login.php') !== false) {
            return home_url('/' . $this->login_slug . '/');
        }
        return $url;
    }

    // CHẶN trực tiếp wp-admin trước khi auth_redirect() chạy
    public function block_wp_admin()
    {
        if (!is_user_logged_in() && strpos($_SERVER['REQUEST_URI'], 'wp-admin') !== false) {
            status_header(403);
            nocache_headers();
            wp_die(
                __('Sorry, you are not allowed to access this page.'),
                __('Access Denied'),
                ['response' => 403]
            );
        }
    }

    // Chặn truy cập wp-login.php
    public function block_wp_login_direct()
    {
        $req = trim($_SERVER['REQUEST_URI'], '/');
        if ($req === $this->login_slug) return;

        status_header(403);
        nocache_headers();
        wp_die(
            __('Sorry, you are not allowed to access this page.'),
            __('Access Denied'),
            ['response' => 403]
        );
        exit;
    }

    // Custom link logout
    public function custom_logout_url($logout_url, $redirect)
    {
        return add_query_arg([
            'est_action' => 'logout',
            '_wpnonce'   => wp_create_nonce('est_logout'),
            'redirect_to' => $redirect ? urlencode($redirect) : false
        ], home_url('/'));
    }

    // Xử lý redirect lại form login 
    public function handle_logout()
    {
        if (!isset($_GET['est_action']) || $_GET['est_action'] !== 'logout') {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'est_logout')) {
            wp_die('Invalid logout request');
        }

        wp_clear_auth_cookie();

        $redirect_url = home_url('/' . $this->login_slug . '/');
        wp_safe_redirect($redirect_url);
        exit;
    }
}

new WP_Hide_Login_Forbidden();
