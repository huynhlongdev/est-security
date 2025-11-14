<?php
if (!defined('ABSPATH')) exit;

class WP_Login_Lockout
{
    private $table;
    private $enabled;
    private $max_attempts;
    private $lockout_time;
    private $capability = 'manage_options';

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'security_login_lockout';
        $this->enabled = get_option('est_user_lockout', 0); // 1 = enabled
        $this->max_attempts = intval(get_option('est_max_attempts', 3));
        // store est_lockout_time as minutes. Default = 5 minutes
        $this->lockout_time = intval(get_option('est_lockout_time', 5)) * 60;

        // If disabled, do nothing
        if ($this->enabled != 1) return;
        add_action('init', [$this, 'create_table']);

        // Hooks
        add_action('wp_login_failed', [$this, 'login_failed']);
        add_filter('authenticate', [$this, 'check_lockout'], 30, 3);
        add_action('wp_login', [$this, 'login_success'], 10, 2);
        add_filter('login_errors', [$this, 'hide_login_errors']);

        // Admin menu
        add_action('admin_post_unlock_user', [$this, 'unlock_user']);
    }

    public function create_table()
    {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") !== $this->table) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_login VARCHAR(100) NOT NULL,
                attempts INT(11) DEFAULT 0,
                last_attempt INT(11) DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY user_login (user_login)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    private function get_user_data($username)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE user_login=%s", strtolower($username)), ARRAY_A);
    }

    private function update_user_data($username, $attempts, $last_attempt)
    {
        global $wpdb;
        $exists = $this->get_user_data($username);
        if ($exists) {
            $wpdb->update(
                $this->table,
                [
                    'attempts' => $attempts,
                    'last_attempt' => $last_attempt
                ],
                ['user_login' => strtolower($username)],
                ['%d', '%d'],
                ['%s']
            );
        } else {
            $wpdb->insert(
                $this->table,
                [
                    'user_login' => strtolower($username),
                    'attempts' => $attempts,
                    'last_attempt' => $last_attempt
                ],
                ['%s', '%d', '%d']
            );
        }
    }

    public function login_failed($username)
    {
        if (empty($username)) return;

        $user_data = $this->get_user_data($username);
        $attempts = $user_data['attempts'] ?? 0;
        $attempts++;
        $last_attempt = time();

        $this->update_user_data($username, $attempts, $last_attempt);

        $attempts_left = $this->max_attempts - $attempts;
        $key = 'login_attempts_msg_' . md5(strtolower($username));

        if ($attempts_left > 0) {
            set_transient($key, sprintf(
                'Incorrect credentials. You have %d attempt(s) remaining.',
                $attempts_left
            ), 30);
        } else {
            set_transient(
                $key,
                sprintf('Your account has been locked for %d minutes.', ceil($this->lockout_time / 60)),
                30
            );
        }
    }

    public function login_success($user_login, $user)
    {
        global $wpdb;
        $wpdb->delete($this->table, ['user_login' => strtolower($user_login)], ['%s']);
        delete_transient('login_attempts_msg_' . md5(strtolower($user_login)));
    }

    public function check_lockout($user, $username, $password)
    {
        if (empty($username)) return $user;

        $user_data = $this->get_user_data($username);

        if ($user_data) {
            $attempts = intval($user_data['attempts']);
            $last_attempt = intval($user_data['last_attempt']);

            if ($attempts >= $this->max_attempts) {
                $remaining = ($last_attempt + $this->lockout_time) - time();
                if ($remaining > 0) {
                    return new WP_Error(
                        'account_locked',
                        sprintf('Your account is locked. Please try again in %d minutes.', ceil($remaining / 60))
                    );
                } else {
                    // Reset sau khi hết thời gian lockout
                    $this->update_user_data($username, 0, 0);
                }
            }
        }

        return $user;
    }

    // Unlock user
    public function unlock_user()
    {
        if (!current_user_can('manage_options') || !isset($_POST['user_login'])) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $user_login = sanitize_text_field($_POST['user_login']);
        $wpdb->delete($this->table, ['user_login' => strtolower($user_login)], ['%s']);

        wp_redirect(admin_url('admin.php?page=login-lockout'));
        exit;
    }

    public function hide_login_errors($error)
    {
        if (!isset($_POST['log'])) return $error;

        $username = sanitize_text_field($_POST['log']);
        $key = 'login_attempts_msg_' . md5(strtolower($username));

        $msg  = get_transient($key);
        if ($msg) {
            delete_transient($key);
            return esc_html($msg);
        }

        // Nếu không có thông báo custom thì hiển thị chung chung
        return __('Invalid username or password.', 'text-domain');
    }
}

new WP_Login_Lockout();

// Optional: display transient messages above login form
add_action('login_notices', function () {
    if (!isset($_POST['log'])) return;
    $username = sanitize_text_field($_POST['log']);
    $msg = get_transient('login_attempts_msg_' . md5(strtolower($username)));
    if ($msg) {
        echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
        delete_transient('login_attempts_msg_' . md5(strtolower($username)));
    }
});

// register_activation_hook(WP_PLUGIN_DIR  . '/est-security/est-security.php', function () {
//     $lockout = new WP_Login_Lockout();
//     $lockout->create_table();
//     error_log('active plugin');
// });

// register_deactivation_hook(WP_PLUGIN_DIR  . '/est-security/est-security.php', function () {
//     error_log('deactive plugin');
// });
