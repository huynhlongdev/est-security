<?php
/*
Plugin Name: ENOSTA Security
Description: Tự động phát hiện thay đổi file và gửi về mail để báo cáo. Xóa các file nguy hiểm.
Version: 1.0
Author: Long Huynh
*/

define('EST_SECURITY_VERSION', '1.0.0');
define('EST_SECURITY_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once EST_SECURITY_PLUGIN_DIR . 'est-function.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-security-helpers.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-security.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-menu.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-hide-login.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-setting.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-password.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-malware-scan.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-generate.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-audit_log.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-recaptcha.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-login-lockout.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-prefix-db.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-two-factor.php';


register_activation_hook(__FILE__, function () {

    new Generate_File();

    if (!get_option('est_security')) {
        add_option('est_security', 1);

        // Tạo giá trị mặc định
        add_option('est_default_wp_files', 1);
        add_option('est_disable_file_editing', 1);
        add_option('est_copy_protection', 1);
        add_option('est_prevent_site_display_inside_frame', 1);
        add_option('est_notify_email', get_option('admin_email', ''));
        add_option('est_custom_login_enabled', 1);
        add_option('est_custom_login_slug', est_path());
        add_option('est_user_lockout', 0);
        add_option('est_max_attempts', 5);
        add_option('est_lockout_time', 300);
        add_option('est_enable_auto_change', 1);
        add_option('est_auto_change_interval', 'monthly');
        add_option('est_recaptcha_version', 'v2');
        add_option('est_recaptcha_enabled', 0);
        add_option('est_recaptcha_site_key', '');
        add_option('est_recaptcha_secret_key', '');
    }

    // Tạo table Lockout
    global $wpdb;
    $table = $wpdb->prefix . 'est_security_login_lockout';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                login_ip VARCHAR(50) NOT NULL,
                username VARCHAR(100) NOT NULL,
                login_attempts INT(11) NOT NULL,
                attempt_time DATETIME,
                locked_time VARCHAR(100) NOT NULL,
                lockout_count INT NOT NULL DEFAULT 0,
                last_attempt DATETIME NULL,
                PRIMARY KEY (id),
                KEY ip_user (login_ip, username)
            ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Tạo table Audit
    $table = $wpdb->prefix . 'est_security_audit_log';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_login VARCHAR(100) DEFAULT '',
                ip_address VARCHAR(45) DEFAULT '',
                action_type VARCHAR(50) NOT NULL,
                action_detail TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('est_daily_scan');
    wp_clear_scheduled_hook('est_admin_password_reset_hook');
});
