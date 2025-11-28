<?php
class WP_Hide_Login_Forbidden
{

    private $login_slug;
    private $enabled;
    private $max_session_time = 5000000;

    public function __construct()
    {
        // Lấy config từ DB
        $this->enabled = get_option('est_custom_login_enabled', 0);
        $this->login_slug = trim(get_option('est_custom_login_slug', est_path()), '/');

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
        $req = $_SERVER['REQUEST_URI'];

        // Cho phép admin-ajax.php
        if (strpos($req, 'admin-ajax.php') !== false) {
            return;
        }

        if (!is_user_logged_in() && strpos($_SERVER['REQUEST_URI'], 'wp-admin') !== false) {
            status_header(403);
            nocache_headers();
            wp_die(
                __('Sorry, you are not allowed to access this page.'),
                __('Access Denied'),
                ['response' => 403]
            );
            exit;
        }
    }

    // Chặn truy cập wp-login.php
    public function block_wp_login_direct()
    {
        $req = trim($_SERVER['REQUEST_URI'], '/');
        if ($req === $this->login_slug) return;

        // Cho phép AJAX login
        if (strpos($req, 'admin-ajax.php') !== false) {
            return;
        }

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
            exit;
        }

        wp_clear_auth_cookie();

        $redirect_url = home_url('/' . $this->login_slug . '/');
        wp_safe_redirect($redirect_url);
        exit;
    }
}

new WP_Hide_Login_Forbidden();

add_action('init', function () {
    add_rewrite_rule('^est-captcha/?$', 'index.php?est_captcha=1', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'est_captcha';
    return $vars;
});

add_action('template_redirect', function () {
    if (get_query_var('est_captcha')) {

        if (!session_id()) session_start();

        header("Content-Type: image/png");

        $im = imagecreatefrompng(__DIR__ . "/images/white-wave.png");
        $black = imagecolorallocate($im, 0, 0, 0);

        $text = strtoupper(substr(md5(microtime()), rand(0, 26), 6));
        $_SESSION["wp_limit_captcha"] = $text;

        $font = __DIR__ . "/images/coolvetica.ttf";

        $font_size = 20;
        $angle     = 5;
        $x         = 20; // bắt đầu
        $y         = 45;

        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];
            imagettftext($im, $font_size, $angle, $x, $y, $black, $font, $char);
            $x += 20; // khoảng cách giữa các ký tự (giãn ra)
        }

        imagepng($im);
        imagedestroy($im);

        session_write_close();
        exit;
    }
});

function est_verify_captcha()
{
    if (!session_id()) session_start();

    $input = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';

    if (empty($input)) {
        wp_send_json_error([
            'message' => __('Please enter the captcha.', 'est-plugin')
        ]);
    }

    if (!isset($_SESSION['wp_limit_captcha'])) {
        wp_send_json_error([
            'message' => __('Captcha does not exist.', 'est-plugin')
        ]);
    }

    if (strtolower($input) !== strtolower($_SESSION['wp_limit_captcha'])) {
        wp_send_json_error([
            'message' => __('Incorrect captcha.', 'est-plugin')
        ]);
    }

    // set a browser cookie to mark captcha verified (1 hour)
    $expire   = time() + 3600;
    $path     = defined('COOKIEPATH') ? COOKIEPATH : '/';
    $domain   = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    $secure   = is_ssl();
    $httponly = true;

    setcookie('est_captcha_verified', '1', $expire, $path, $domain, $secure, $httponly);

    wp_send_json_success([
        'message' => __('Verification successful.', 'est-plugin')
    ]);
}
add_action('wp_ajax_est_verify_captcha', 'est_verify_captcha');
add_action('wp_ajax_nopriv_est_verify_captcha', 'est_verify_captcha');

// add_action('login_head', 'wp_limit_login_head');
function wp_limit_login_head()
{
    if (!isset($_COOKIE["est_captcha_verified"]) && empty($_COOKIE["est_captcha_verified"])) {
?>
        <style>
            .popup {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 100%;
                background: #f0f0f1;
                z-index: 1;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .popup_box {
                border: 1px solid #ededed;
                padding: 20px;
                border-radius: 8px;
                background: #ffffff;
            }
        </style>
        <script>
            document.addEventListener("DOMContentLoaded", function() {

                const btn = document.querySelector(".verify_btn");
                const msg = document.querySelector(".captcha_msg");

                btn.addEventListener("click", function() {
                    let value = document.querySelector(".captcha_input").value.trim();

                    msg.innerHTML = "<?php echo esc_js(__('Checking...', 'est-plugin')); ?>";

                    fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            },
                            body: "action=est_verify_captcha&captcha=" + encodeURIComponent(value)
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                msg.style.color = "green";
                                msg.innerHTML = data.data.message;

                                // Ẩn popup sau 700ms
                                setTimeout(() => {
                                    document.querySelector(".popup").style.display = "none";
                                }, 700);
                            } else {
                                msg.style.color = "red";
                                msg.innerHTML = data.data.message;

                                // Refresh captcha
                                document.querySelector(".captcha").src = "<?php echo site_url('/est-captcha?v='); ?>" + Date.now();
                            }
                        });
                });

            });
        </script>

        <div class='popup'>
            <div class='popup_box'>
                <input type="text" class="captcha_input" placeholder="Enter here.." name="captcha">
                <img class="captcha" height="55" src="<?php echo site_url('/est-captcha?v=' . time()); ?>" />
                <input class="submit button button-primary verify_btn" type="submit" value="Verify">
                <p class="captcha_msg" style="color:red; margin-top:10px;"></p>
            </div>
        </div>
<?php
    };
}
