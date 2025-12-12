<?php
if (!defined('ABSPATH')) exit;

class EST_Two_Factor
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'est_2fa_codes';

        add_action('init', [$this, 'create_table']);
        add_action('wp_login', [$this, 'require_2fa'], 10, 2);
        add_action('login_form_2fa', [$this, 'show_2fa_form']);
        add_action('login_form_2fa', [$this, 'process_2fa']);
        add_filter('authenticate', [$this, 'check_2fa'], 30, 3);
    }

    public function create_table()
    {
        global $wpdb;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") !== $this->table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                secret_key VARCHAR(32) NOT NULL,
                backup_codes TEXT,
                enabled TINYINT(1) DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY user_id (user_id)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    private function generate_secret()
    {
        return base32_encode(random_bytes(16));
    }

    private function generate_backup_codes($count = 10)
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = wp_generate_password(8, false, false);
        }
        return $codes;
    }

    private function get_or_create_user_2fa($user_id)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id=%d",
            $user_id
        ), ARRAY_A);

        if (!$row) {
            $secret = $this->generate_secret();
            $backup_codes = $this->generate_backup_codes();
            $wpdb->insert(
                $this->table,
                [
                    'user_id' => $user_id,
                    'secret_key' => $secret,
                    'backup_codes' => json_encode($backup_codes),
                    'enabled' => 1
                ],
                ['%d', '%s', '%s', '%d']
            );
            $row = [
                'user_id' => $user_id,
                'secret_key' => $secret,
                'backup_codes' => $backup_codes,
                'enabled' => 1
            ];
        } else {
            $row['backup_codes'] = json_decode($row['backup_codes'], true) ?: [];
        }
        return $row;
    }

    public function require_2fa($user_login, $user)
    {
        global $wpdb;

        // Kiểm tra user đã verify 2FA chưa
        if (get_user_meta($user->ID, '_2fa_verified', true)) {
            return;
        }

        $user_2fa = $this->get_or_create_user_2fa($user->ID);

        $token = wp_generate_password(32, false);
        set_transient('est_2fa_' . $token, $user->ID, 300);

        wp_logout();
        wp_redirect(wp_login_url() . '?action=2fa&token=' . $token);
        exit;
    }

    public function show_2fa_form()
    {
        $token = sanitize_text_field($_GET['token'] ?? '');
        if (!$token) wp_die('Invalid request');

        $user_id = get_transient('est_2fa_' . $token);
        if (!$user_id) wp_die('Token expired');

?>
        <div class="login">
            <h1><?php _e('Two-Factor Authentication'); ?></h1>
            <form method="post" action="<?php echo esc_url(wp_login_url() . '?action=2fa'); ?>">
                <?php wp_nonce_field('est_2fa_verify', 'est_2fa_nonce'); ?>
                <p>
                    <label><?php _e('Enter 6-digit code:'); ?></label>
                    <input type="text" name="2fa_code" class="input" size="20" autocomplete="off" autofocus>
                </p>
                <p>
                    <label><?php _e('Or backup code:'); ?></label>
                    <input type="text" name="backup_code" class="input" size="20" autocomplete="off">
                </p>
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Verify">
                </p>
            </form>
        </div>
<?php
    }

    public function process_2fa()
    {
        if (empty($_POST['token'])) return;
        check_admin_referer('est_2fa_verify', 'est_2fa_nonce');

        $token = sanitize_text_field($_POST['token']);
        $user_id = get_transient('est_2fa_' . $token);
        if (!$user_id) wp_die('Token expired');

        delete_transient('est_2fa_' . $token);

        $user_2fa = $this->get_or_create_user_2fa($user_id);

        $valid = false;

        // Backup code
        if (!empty($_POST['backup_code'])) {
            $code = sanitize_text_field($_POST['backup_code']);
            if (in_array($code, $user_2fa['backup_codes'])) {
                $valid = true;
                $user_2fa['backup_codes'] = array_diff($user_2fa['backup_codes'], [$code]);
                global $wpdb;
                $wpdb->update(
                    $this->table,
                    ['backup_codes' => json_encode(array_values($user_2fa['backup_codes']))],
                    ['user_id' => $user_id],
                    ['%s'],
                    ['%d']
                );
            }
        }

        // TOTP
        if (!$valid && !empty($_POST['2fa_code'])) {
            if ($this->verify_totp($user_2fa['secret_key'], sanitize_text_field($_POST['2fa_code']))) {
                $valid = true;
            }
        }

        if ($valid) {
            update_user_meta($user_id, '_2fa_verified', 1);
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            wp_redirect(admin_url());
            exit;
        } else {
            wp_die('Invalid 2FA code');
        }
    }

    private function verify_totp($secret, $code, $window = 1)
    {
        $time = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->generate_totp($secret, $time + $i), $code)) return true;
        }
        return false;
    }

    private function generate_totp($secret, $time)
    {
        $key = base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    public function check_2fa($user, $username, $password)
    {
        return $user;
    }
    public function get_qr_code_url($email, $secret)
    {
        $issuer = get_bloginfo('name');
        $label = urlencode($issuer . ':' . $email);
        $url = 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . urlencode($issuer);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url);
    }
}

// Base32 helpers
if (!function_exists('base32_encode')) {
    function base32_encode($data)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = '';
        $bits = 0;
        $value = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $value = ($value << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $encoded .= $chars[($value >> ($bits - 5)) & 31];
                $bits -= 5;
            }
        }
        if ($bits > 0) $encoded .= $chars[($value << (5 - $bits)) & 31];
        return $encoded;
    }
}
if (!function_exists('base32_decode')) {
    function base32_decode($data)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $map = array_flip(str_split($chars));
        $decoded = '';
        $bits = 0;
        $value = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $c = strtoupper($data[$i]);
            if (!isset($map[$c])) continue;
            $value = ($value << 5) | $map[$c];
            $bits += 5;
            if ($bits >= 8) {
                $decoded .= chr(($value >> ($bits - 8)) & 255);
                $bits -= 8;
            }
        }
        return $decoded;
    }
}

// new EST_Two_Factor();
