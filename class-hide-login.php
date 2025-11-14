<?php
class WP_Hide_Login_Forbidden
{

    private $login_slug = 'est-login';
    private $enabled;

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

        add_action('wp_logout', [$this, 'custom_logout_redirect'], 999);
    }

    // Trang login mới
    public function load_custom_login()
    {
        $req = trim($_SERVER['REQUEST_URI'], '/');
        if ($req === $this->login_slug) {
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

    /** Logout redirect */
    public function custom_logout_redirect()
    {
        wp_clear_auth_cookie();
        nocache_headers();

        $url = add_query_arg('loggedout', 'true', home_url('/' . $this->login_slug . '/'));

        header('Location: ' . $url);
        exit;
    }
}

new WP_Hide_Login_Forbidden();
