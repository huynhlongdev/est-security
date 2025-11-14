<?php
if (!defined('ABSPATH')) exit;

class Security_DB_Prefix_Ajax
{
    private $wpdb;
    private $option_key = 'security_db_prefix_last';

    public function __construct()
    {
        if (!is_admin()) {
            return;
        }

        global $wpdb;
        $this->wpdb = $wpdb;

        add_action('wp_ajax_change_db_prefix', [$this, 'ajax_change_prefix']);
    }

    public function ajax_change_prefix()
    {
        check_ajax_referer('change_db_prefix_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $old_prefix = $wpdb->prefix;
        $new_prefix = $this->generate_random_prefix();

        try {
            // Đổi tên bảng
            $this->rename_tables($old_prefix, $new_prefix);

            // Cập nhật prefix trong wp-config.php
            $this->update_wp_config_prefix($new_prefix);

            // Gán lại prefix cho $wpdb trong runtime để tránh lỗi các hàm WP
            $wpdb->set_prefix($new_prefix);

            // Cập nhật các key trong option và usermeta
            $this->update_meta_keys($old_prefix, $new_prefix);

            // Lưu lại prefix cuối cùng
            update_option($this->option_key, $new_prefix);

            wp_send_json_success([
                'message' => "✅ Database prefix successfully changed to <code>{$new_prefix}</code>."
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => '❌ ' . $e->getMessage()]);
        }
    }

    /**
     * Generate random prefix (e.g. ab12cd_)
     */
    private function generate_random_prefix($length = 6)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $rand = '';
        for ($i = 0; $i < $length; $i++) {
            $rand .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $rand . '_';
    }

    /**
     * Rename all tables with old prefix -> new prefix
     */
    private function rename_tables($old_prefix, $new_prefix)
    {
        $tables = $this->wpdb->get_col("SHOW TABLES LIKE '{$old_prefix}%'");
        if (empty($tables)) {
            throw new Exception("No tables found with prefix '{$old_prefix}'");
        }

        foreach ($tables as $old_table) {
            $new_table = preg_replace('/^' . preg_quote($old_prefix, '/') . '/', $new_prefix, $old_table, 1);
            $sql = "RENAME TABLE `{$old_table}` TO `{$new_table}`";
            $this->wpdb->query($sql);
        }

        return true;
    }

    /**
     * Update wp-config.php table_prefix
     */
    private function update_wp_config_prefix($new_prefix)
    {
        $config_path = ABSPATH . 'wp-config.php';

        if (!file_exists($config_path)) {
            throw new Exception('wp-config.php not found.');
        }

        $current_perms = fileperms($config_path) & 0777;

        // Mở quyền ghi tạm thời
        if (!is_writable($config_path)) {
            @chmod($config_path, 0644);
        }

        $config = file_get_contents($config_path);
        if ($config === false) {
            throw new Exception('Failed to read wp-config.php.');
        }

        $updated = preg_replace(
            '/(\$table_prefix\s*=\s*[\'"])([^\'"]+)([\'"]\s*;)/',
            '${1}' . $new_prefix . '${3}',
            $config
        );

        if ($updated === null) {
            throw new Exception('Regex replacement failed.');
        }

        if (file_put_contents($config_path, $updated) === false) {
            throw new Exception('Failed to update wp-config.php.');
        }

        // Set lại quyền bảo mật
        @chmod($config_path, $current_perms ?: 0444);

        return true;
    }

    /**
     * Update keys inside new prefix tables
     */
    private function update_meta_keys($old_prefix, $new_prefix)
    {
        $options_table = $new_prefix . 'options';
        $usermeta_table = $new_prefix . 'usermeta';

        // Kiểm tra bảng có tồn tại không
        $opt_exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $options_table
        ));
        $meta_exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $usermeta_table
        ));

        if ($opt_exists) {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE `$options_table` SET option_name = REPLACE(option_name, %s, %s) WHERE option_name LIKE %s",
                $old_prefix,
                $new_prefix,
                $old_prefix . '%'
            ));
        }

        if ($meta_exists) {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE `$usermeta_table` SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s",
                $old_prefix,
                $new_prefix,
                $old_prefix . '%'
            ));
        }

        return true;
    }
}

new Security_DB_Prefix_Ajax();
