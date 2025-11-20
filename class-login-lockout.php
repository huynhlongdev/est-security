<?php
if (!defined('ABSPATH')) exit;

class WP_Login_Lockout
{
    private $table;
    private $ip_table;
    private $enabled;
    private $max_attempts;
    private $lockout_time;
    private $capability = 'manage_options';

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'est_security_login_lockout';
        $this->ip_table = $wpdb->prefix . 'est_security_login_lockout_ip';
        $this->enabled = get_option('est_user_lockout', 0);
        $this->max_attempts = intval(get_option('est_max_attempts', 3));
        $this->lockout_time = intval(get_option('est_lockout_time', 5)) * 60;

        // If disabled, do nothing
        if ($this->enabled != 1) return;
        add_action('init', [$this, 'create_table']);

        // Hooks
        add_action('wp_login_failed', [$this, 'login_failed']);
        add_filter('authenticate', [$this, 'check_lockout'], 99, 3);
        add_action('wp_login', [$this, 'login_success'], 10, 2);
        add_filter('login_errors', [$this, 'hide_login_errors']);

        // Admin menu
        add_action('admin_post_unlock_user', [$this, 'unlock_user']);
        add_action('admin_post_unlock_ip', [$this, 'unlock_ip']);

        // Hook AJAX
        add_action('wp_ajax_unlock_user_ajax', [$this, 'unlock_user']);
        add_action('wp_ajax_unlock_ip_ajax', [$this, 'unlock_ip']);
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

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->ip_table}'") !== $this->ip_table) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS {$this->ip_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                attempts INT(11) DEFAULT 0,
                last_attempt INT(11) DEFAULT 0,
                locked_until INT(11) DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY ip_address (ip_address)
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
        if (!username_exists($username)) {
            return;
        }

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

        error_log('>>>>login_failed');

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($username)) return;

        if (isset($_SESSION['est_recaptcha_error']) && !empty($_SESSION['est_recaptcha_error'])) {
            unset($_SESSION['est_recaptcha_error']);
            return;
        }


        global $wpdb;

        $user_data = $this->get_user_data($username);
        $attempts = $user_data['attempts'] ?? 0;
        $attempts++;
        $last_attempt = time();

        // if (!empty($ip_data)) {
        //     if ($ip_data['locked_until'] != 0) {
        //         wp_clear_auth_cookie();
        //         nocache_headers();
        //         $url =  home_url('/');
        //         header('Location: ' . $url);
        //         exit;
        //     }
        // }

        $this->update_user_data($username, $attempts, $last_attempt);

        // Track by IP address
        $ip = $this->get_client_ip();
        $this->record_ip_attempt($ip);

        // Log the failed attempt
        $this->log_failed_login($username, $ip);

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

    private function get_client_ip()
    {
        return EST_Security_Helpers::get_client_ip();
    }

    private function record_ip_attempt($ip)
    {
        global $wpdb;
        $now = time();

        $ip_data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->ip_table} WHERE ip_address = %s", $ip),
            ARRAY_A
        );

        if ($ip_data) {
            $attempts = intval($ip_data['attempts']) + 1;
            $locked_until = 0;

            if ($attempts >= $this->max_attempts) {
                $locked_until = $now + $this->lockout_time;
            }

            $wpdb->update(
                $this->ip_table,
                [
                    'attempts' => $attempts,
                    'last_attempt' => $now,
                    'locked_until' => $locked_until
                ],
                ['ip_address' => $ip],
                ['%d', '%d', '%d'],
                ['%s']
            );
        } else {
            $wpdb->insert(
                $this->ip_table,
                [
                    'ip_address' => $ip,
                    'attempts' => 1,
                    'last_attempt' => $now,
                    'locked_until' => 0
                ],
                ['%s', '%d', '%d', '%d']
            );
        }
    }

    private function log_failed_login($username, $ip)
    {

        global $wpdb;
        $table = $wpdb->prefix . 'est_security_audit_log';

        $wpdb->insert(
            $table,
            [
                'user_login' => sanitize_text_field($username),
                'ip_address' => sanitize_text_field($ip),
                'action_type' => 'failed_login',
                'action_detail' => sprintf('Failed login attempt for username: %s', $username),
                'created_at' => current_time('mysql')
            ]
        );
    }

    public function login_success($user_login, $user)
    {
        global $wpdb;
        // Reset username lockout
        $wpdb->delete($this->table, ['user_login' => strtolower($user_login)], ['%s']);
        delete_transient('login_attempts_msg_' . md5(strtolower($user_login)));

        // Reset IP lockout on successful login
        $ip = $this->get_client_ip();
        $wpdb->update(
            $this->ip_table,
            ['attempts' => 0, 'locked_until' => 0],
            ['ip_address' => $ip],
            ['%d', '%d'],
            ['%s']
        );

        // Log successful login
        $this->log_successful_login($user_login, $ip);
    }

    private function log_successful_login($username, $ip)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'est_security_audit_log';

        $wpdb->insert(
            $table,
            [
                'user_login' => sanitize_text_field($username),
                'ip_address' => sanitize_text_field($ip),
                'action_type' => 'successful_login',
                'action_detail' => sprintf('Successful login for username: %s', $username),
                'created_at' => current_time('mysql')
            ]
        );
    }

    public function check_lockout($user, $username, $password)
    {
        if (empty($username)) return $user;

        // error_log(print_r('check_lockout 1', true));

        // Check IP lockout first
        $ip = $this->get_client_ip();
        $ip_locked = $this->check_ip_lockout($ip);
        if ($ip_locked) {
            return $ip_locked;
        }

        // Check username lockout
        // $user_data = $this->get_user_data($username);

        // if ($user_data) {
        //     $attempts = intval($user_data['attempts']);
        //     $last_attempt = intval($user_data['last_attempt']);

        //     if ($attempts >= $this->max_attempts) {
        //         $remaining = ($last_attempt + $this->lockout_time) - time();
        //         if ($remaining > 0) {
        //             return new WP_Error(
        //                 'account_locked',
        //                 sprintf('Your account is locked. Please try again in %d minutes.', ceil($remaining / 60))
        //             );
        //         } else {
        //             // Reset sau khi hết thời gian lockout
        //             $this->update_user_data($username, 0, 0);
        //         }
        //     }
        // }

        return $user;
    }

    private function check_ip_lockout($ip)
    {
        global $wpdb;
        $ip_data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->ip_table} WHERE ip_address = %s", $ip),
            ARRAY_A
        );

        if ($ip_data) {
            $locked_until = intval($ip_data['locked_until']);
            $now = time();

            if ($locked_until > $now) {
                $remaining = $locked_until - $now;
                return new WP_Error(
                    'ip_locked',
                    sprintf('Your IP address is temporarily blocked. Please try again in %d minutes.', ceil($remaining / 60))
                );
            } elseif ($locked_until > 0 && $locked_until <= $now) {
                // Reset after lockout period
                $wpdb->update(
                    $this->ip_table,
                    ['attempts' => 0, 'locked_until' => 0],
                    ['ip_address' => $ip],
                    ['%d', '%d'],
                    ['%s']
                );
            }
        }

        return false;
    }

    // Unlock user
    public function unlock_user()
    {
        $user = sanitize_text_field($_POST['user_login'] ?? '');
        if (!$user) {
            wp_send_json_error('Missing user login');
        }

        global $wpdb;
        $deleted = $wpdb->delete($this->table, ['user_login' => $user]);

        if ($deleted !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to unlock user');
        }
    }

    // Unlock IP
    public function unlock_ip()
    {
        $ip = sanitize_text_field($_POST['ip_address'] ?? '');
        if (!$ip) {
            wp_send_json_error('Missing IP address');
        }

        global $wpdb;
        $deleted = $wpdb->delete($this->ip_table, ['ip_address' => $ip]);

        if ($deleted !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to unlock IP');
        }
    }

    public function hide_login_errors($error)
    {

        error_log('>>>>hide_login_errors');

        if (!isset($_POST['log'])) return $error;

        $username = sanitize_text_field($_POST['log']);
        $key = 'login_attempts_msg_' . md5(strtolower($username));

        $msg  = get_transient($key);
        if ($msg) {
            delete_transient($key);
            return esc_html($msg);
        }

        if (strpos($error, 'validate reCAPTCHA')) {
            return __("You have not completed the reCAPTCHA verification.", 'est-security');
        }
        if (strpos($error, 'Incorrect reCAPTCHA')) {
            return __("Incorrect reCAPTCHA.", 'est-security');
        }

        // Prevent username enumeration - generic error message
        if (
            strpos($error, 'Invalid username') !== false ||
            strpos($error, 'Unknown username') !== false ||
            strpos($error, 'incorrect username') !== false
        ) {
            return __('Invalid username or password.', 'est-security');
        }

        // Nếu không có thông báo custom thì hiển thị chung chung
        return __('Invalid username or password.', 'est-security');
    }
}

new WP_Login_Lockout();


add_filter('authenticate', function ($user, $username, $password) {
    global $wpdb;

    // Chặn user vì đăng nhập sai quá nhiều lần
    $lockout = $wpdb->prefix . 'est_security_login_lockout';
    $lockout_data = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `{$lockout}` WHERE user_login = %s", $username),
        ARRAY_A
    );

    if (!empty($lockout_data)) {
        if ($lockout_data['attempts'] >= 8) {
            wp_clear_auth_cookie();
            nocache_headers();

            wp_die('Too many failed login attempts have been detected. Your account has been locked to protect against unauthorized access', 'Account Locked');
            exit;
        }
    }

    return $user;
}, 20, 3);
