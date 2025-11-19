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
require_once EST_SECURITY_PLUGIN_DIR . 'class-login-lockout.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-prefix-db.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-two-factor.php';
require_once EST_SECURITY_PLUGIN_DIR . 'class-recaptcha.php';

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

        $lockout = new WP_Login_Lockout();
        $lockout->create_table();

        $audit = new Security_Audit_Log_DB();
        $audit->create_table();
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('est_daily_scan');
    wp_clear_scheduled_hook('est_admin_password_reset_hook');
});
