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
        $this->table = $wpdb->prefix . 'est_security_login_lockout';

        $this->enabled = get_option('est_user_lockout', 0);
        $this->max_attempts = intval(get_option('est_max_attempts', 3));
        $this->lockout_time = intval(get_option('est_lockout_time', 5)) * 60;

        // If disabled, do nothing
        if ($this->enabled != 1) return;

        // Hooks
        add_action('wp_login', [$this, 'login_success'], 10, 2);
        add_action('wp_login_failed', [$this, 'login_failed']);
        // add_action('admin_init', [$this, 'reset_attempts_after_login']);
        add_filter('login_errors', [$this, 'filter_login_error']);
        add_filter('authenticate', [$this, 'block_when_locked'], 30, 3);

        // Hook AJAX
        add_action('wp_ajax_unlock_user_ajax', [$this, 'unlock_user']);
    }

    public function login_success($user_login, $user)
    {
        if (!is_user_logged_in()) return;
        global $wpdb;
        $ip = $this->get_ip();

        // Xóa toàn bộ lịch sử khóa của IP này
        $wpdb->delete($this->table, ['login_ip' => $ip, 'username' => $user_login]);
    }

    public function create_table()
    {
        global $wpdb;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") !== $this->table) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                login_ip VARCHAR(50) NOT NULL,
                login_attempts INT(11) NOT NULL,
                attempt_time DATETIME,
                locked_time VARCHAR(100) NOT NULL,
                lockout_count INT NOT NULL DEFAULT 0,
                last_attempt DATETIME NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    private function set_error_message($username, $msg)
    {
        $key = 'login_attempts_msg_' . md5($username);
        set_transient($key, $msg, 30);
    }

    private function get_ip()
    {
        return EST_Security_Helpers::get_client_ip();
    }

    public function login_failed($username)
    {

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($username)) return;

        if (isset($_SESSION['est_recaptcha_error']) && !empty($_SESSION['est_recaptcha_error']) && get_option('est_recaptcha_enabled') == 1) {
            unset($_SESSION['est_recaptcha_error']);
            return;
        }

        global $wpdb;
        $ip = $this->get_ip();
        $tablerows = $wpdb->get_row("SELECT * FROM  {$this->table} WHERE login_ip = '$ip' AND username = '$username' ORDER BY `id` DESC LIMIT 1 ");

        /** -----------------------------------------------------------
         * CASE 1: User bị chặn vĩnh viễn
         * ----------------------------------------------------------- */
        if ($tablerows && intval($tablerows->lockout_count) >= 2) {
            $this->set_error_message($username, "Your IP has been permanently blocked.");
            return;
        }

        /** -----------------------------------------------------------
         * CASE 2: Đang bị khóa lần 1 (count = 1)
         * ----------------------------------------------------------- */
        if ($tablerows && $tablerows->locked_time > time()) {

            $remain = ceil(($tablerows->locked_time - time()) / 60);
            $this->set_error_message($username, "Too many attempts. Try again in {$remain} minutes.");
            return;
        }

        /** -----------------------------------------------------------
         * CASE 3: Hết lockout → reset attempts
         * ----------------------------------------------------------- */
        if ($tablerows && $tablerows->locked_time != 0 && $tablerows->locked_time <= time()) {
            $wpdb->update($this->table, [
                'login_attempts' => 0,
                'locked_time' => 0
            ], ['id' => $tablerows->id, 'username' => $username]);

            $tablerows->login_attempts = 0;
        }

        /** -----------------------------------------------------------
         * COUNT FAILED ATTEMPT
         * ----------------------------------------------------------- */
        if (!$tablerows) {
            $wpdb->insert($this->table, [
                'login_ip' => $ip,
                'username' => $username,
                'login_attempts' => 1,
                'locked_time' => 0,
                'lockout_count' => 0,
                'last_attempt' => current_time('mysql')
            ]);

            $remain = $this->max_attempts - 1;
            $this->set_error_message($username, "{$remain} attempts remaining.");
            return;
        }

        // Increase attempts
        $attempts = $tablerows->login_attempts + 1;

        /** -----------------------------------------------------------
         * CASE 4: Chạm ngưỡng max attempt → LOCKOUT
         * ----------------------------------------------------------- */
        if ($attempts >= $this->max_attempts) {

            $lockout_count = $tablerows->lockout_count + 1;

            // Lần 2 → block vĩnh viễn
            if ($lockout_count >= 2) {
                $wpdb->update($this->table, [
                    'lockout_count' => $lockout_count,
                    'locked_time' => PHP_INT_MAX
                ], ['id' => $tablerows->id, 'username' => $username]);

                $this->set_error_message($username, "You have been permanently blocked.");
                return;
            }

            // Lần 1 → khóa tạm thời
            $wpdb->update($this->table, [
                'login_attempts' => $attempts,
                'locked_time' => time() + $this->lockout_time,
                'lockout_count' => $lockout_count
            ], ['id' => $tablerows->id, 'username' => $username]);

            $minutes = intval($this->lockout_time / 60);

            $this->set_error_message($username, "Too many attempts. Locked for {$minutes} minutes.");
            return;
        }

        /** -----------------------------------------------------------
         * CASE 5: CHƯA ĐẠT GIỚI HẠN → TIẾP TỤC ĐẾM
         * ----------------------------------------------------------- */
        $wpdb->update($this->table, [
            'login_attempts' => $attempts,
            'last_attempt' => current_time('mysql')
        ], ['id' => $tablerows->id, 'username' => $username]);

        $remain = $this->max_attempts - $attempts;
        $this->set_error_message($username, "{$remain} attempts remaining.");
    }

    public function filter_login_error($error)
    {
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

    public function block_when_locked($user, $username, $password)
    {
        global $wpdb;
        $ip = $this->get_ip();
        if (empty($ip)) return $user;

        $ip_blocked_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE login_ip = %s",
                $ip
            )
        );

        $link = home_url("/?reset=$ip");
        $reset_ip = sprintf(
            'This IP has been permanently blocked.<a href="%s">Reset IP</a>',
            esc_url($link)
        );

        if ($ip_blocked_count >= 4) {
            $this->set_error_message($username, __("This IP has been permanently blocked1.", 'est-security'));
            return new WP_Error('est_ip_blocked', $reset_ip);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE login_ip = %s AND username = %s LIMIT 1", $ip, $username));
        if (!$row) return $user;

        // If permanently blocked
        if (intval($row->lockout_count) >= 2 || intval($row->locked_time) === PHP_INT_MAX) {
            $this->set_error_message($username, __("Your IP has been permanently blocked3.", 'est-security'));
            return new WP_Error('est_blocked', $reset_ip);
        }

        // If currently locked temporarily
        if (intval($row->locked_time) > time()) {
            $remain = ceil((intval($row->locked_time) - time()) / 60);
            $this->set_error_message($username, sprintf(__("Too many attempts. Try again in %d minutes.", 'est-security'), $remain));
            return new WP_Error('est_locked', sprintf(__("Too many attempts. Try again in %d minutes.", 'est-security'), $remain));
        }

        return $user;
    }

    public function unlock_user()
    {
        $user_login = sanitize_text_field($_POST['username'] ?? '');
        $ip_address = sanitize_text_field($_POST['login_ip'] ?? '');

        if (!$ip_address && !$user_login) {
            wp_send_json_error('Missing IP address or username');
        }

        global $wpdb;
        $deleted = $wpdb->delete($this->table, ['login_ip' => $ip_address, 'username' => $user_login]);

        if ($deleted !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to unlock user');
        }
    }
}

$lockout = new WP_Login_Lockout();
