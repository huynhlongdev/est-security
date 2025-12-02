<?php
if (!defined('ABSPATH')) exit;

class Security_Audit_Log_DB
{

    private $table;
    private $per_page = 10;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'est_security_audit_log';

        // Hooks ghi log
        add_action('activated_plugin', [$this, 'log_plugin_activated']);
        add_action('deactivated_plugin', [$this, 'log_plugin_deactivated']);
        add_action('switch_theme', [$this, 'log_theme_switched'], 10, 2);

        // Xử lý hành động xóa log
        add_action('admin_post_enosta_delete_logs', [$this, 'delete_logs']);
    }

    public function delete_logs()
    {
        if (!current_user_can('manage_options')) {
            wp_die('No permission');
        }

        check_admin_referer('enosta_delete_logs_action', 'enosta_delete_logs_nonce');

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->table}");

        wp_redirect(admin_url('admin.php?page=enosta-audit-log&deleted=1'));
        exit;
    }

    private function insert_log($type, $detail)
    {
        global $wpdb;

        $user = wp_get_current_user();
        $username = $user ? $user->user_login : 'system';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $wpdb->insert(
            $this->table,
            [
                'user_login'   => sanitize_text_field($username),
                'ip_address'   => sanitize_text_field($ip),
                'action_type'  => sanitize_text_field($type),
                'action_detail' => sanitize_textarea_field($detail),
                'created_at'   => current_time('mysql')
            ]
        );
    }

    public function log_plugin_activated($plugin)
    {
        $this->insert_log('plugin_activated', "Plugin activated: {$plugin}");
    }

    public function log_plugin_deactivated($plugin)
    {
        $this->insert_log('plugin_deactivated', "Plugin deactivated: {$plugin}");
    }

    public function log_theme_switched($new_name, $new_theme)
    {
        $old_theme = wp_get_theme();
        $this->insert_log(
            'theme_switched',
            sprintf("Theme switched from '%s' to '%s'", $old_theme->get('Name'), $new_name)
        );
    }
}

new Security_Audit_Log_DB();
