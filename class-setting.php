<?php
class EST_Security_Setting
{

    public function __construct()
    {
        // Hook xử lý form submit
        add_action('admin_post_sfcd_save_config', [$this, 'save_config']);
        add_action('admin_post_est_security_save_config', [$this, 'est_security_save_config']);
    }

    public function save_config()
    {
        // Kiểm tra quyền
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        // Kiểm tra nonce
        check_admin_referer('sfcd_save_config');

        new Generate_File();

        wp_clear_scheduled_hook('est_admin_password_reset_hook');

        // Lấy email và sanitize
        if ($_POST['est_notify_email']) {
            $new_email = isset($_POST['est_notify_email']) ? sanitize_email($_POST['est_notify_email']) : '';
            // Validate email
            if (!is_email($new_email)) {
                wp_safe_redirect(admin_url('admin.php?page=enosta-security-config&error=invalid_email'));
                exit;
            }
            // Lưu email
            update_option('est_notify_email', $new_email);
        }

        $enabled_custom_slug = isset($_POST['est_custom_login_enabled']) ? 1 : 0;
        update_option('est_custom_login_enabled', $enabled_custom_slug);

        // Process custom slug
        $custom_slug = isset($_POST['est_custom_login_slug']) ? sanitize_text_field($_POST['est_custom_login_slug']) : 'est-login';
        $custom_slug = trim($custom_slug, '/');
        $custom_slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $custom_slug);
        update_option('est_custom_login_slug', $custom_slug);

        // reCAPTCHA
        update_option('est_recaptcha_version', isset($_POST['est_recaptcha_version']) ? sanitize_text_field($_POST['est_recaptcha_version']) : 'v2');
        update_option('est_recaptcha_enabled', isset($_POST['est_recaptcha_enabled']) ? 1 : 0);
        update_option('est_recaptcha_site_key', isset($_POST['est_recaptcha_site_key']) ? sanitize_text_field($_POST['est_recaptcha_site_key']) : '');
        update_option('est_recaptcha_secret_key', isset($_POST['est_recaptcha_secret_key']) ? sanitize_text_field($_POST['est_recaptcha_secret_key']) : '');

        // Lockout
        update_option('est_user_lockout', isset($_POST['est_user_lockout']) ? 1 : 0);
        update_option('est_lockout_time', isset($_POST['est_lockout_time']) ? intval($_POST['est_lockout_time']) : 300);
        update_option('est_max_attempts', isset($_POST['est_max_attempts']) ? intval($_POST['est_max_attempts']) : 5);

        update_option('est_enable_auto_change', isset($_POST['est_enable_auto_change']) ? 1 : 0);
        update_option('est_auto_change_interval', isset($_POST['est_auto_change_interval']) ? $_POST['est_auto_change_interval'] : 'monthly');

        // Auto logout
        update_option('est_user_auto_logout', isset($_POST['est_user_auto_logout']) ? 1 : 0);
        update_option('est_auto_logout_time', isset($_POST['est_auto_logout_time']) ? $_POST['est_auto_logout_time'] : 2);

        if ($timestamp = wp_next_scheduled('est_admin_password_reset_hook')) {
            wp_unschedule_event($timestamp, 'est_admin_password_reset_hook');
        }

        if (!wp_next_scheduled('est_admin_password_reset_hook')) {
            $interval = get_option('est_auto_change_interval', 'monthly');
            $now = current_time('timestamp');

            switch ($interval) {
                case 'monthly':
                    $next_run = strtotime('first day of next month 00:00', $now);
                    break;

                case 'quarterly':
                    $month = date('n', $now);
                    $next_quarter_month = $month + (3 - ($month - 1) % 3); // tháng đầu quý tiếp theo
                    $year = date('Y', $now);
                    if ($next_quarter_month > 12) {
                        $next_quarter_month -= 12;
                        $year++;
                    }
                    $next_run = strtotime("{$year}-{$next_quarter_month}-01 00:00");
                    break;

                case 'semiannually':
                    $month = date('n', $now);
                    $next_semi_month = $month <= 6 ? 7 : 1;
                    $year = date('Y', $now);
                    if ($next_semi_month == 1) $year++;
                    $next_run = strtotime("{$year}-{$next_semi_month}-01 00:00");
                    break;

                default:
                    $next_run = $now + 30 * DAY_IN_SECONDS;
            }

            wp_schedule_event($next_run, $interval, 'est_admin_password_reset_hook');
        }

        // Redirect để tránh post lại
        wp_safe_redirect(admin_url('admin.php?page=enosta-security-config&saved=1'));
        exit;
    }

    public function est_security_save_config()
    {
        // Kiểm tra quyền
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        // Kiểm tra nonce
        check_admin_referer('est_security_save_config');

        new Generate_File();

        // Process custom login enabled
        update_option('est_default_wp_files', isset($_POST['est_default_wp_files']) ? 1 : 0);
        update_option('est_copy_protection', isset($_POST['est_copy_protection']) ? 1 : 0);
        update_option('est_prevent_site_display_inside_frame', isset($_POST['est_prevent_site_display_inside_frame']) ? 1 : 0);
        update_option('est_disable_file_editing', isset($_POST['est_disable_file_editing']) ? 1 : 0);

        // Redirect để tránh post lại
        wp_safe_redirect(admin_url('admin.php?page=enosta-security-filesystem&saved=1'));
        exit;
    }
}

new EST_Security_Setting();
