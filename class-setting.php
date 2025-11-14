<?php
class EST_Security_Setting
{
    private $set_after_active_plugin = false;

    public function __construct()
    {
        // Hook khi kích hoạt plugin
        // register_activation_hook(__FILE__, [$this, 'on_plugin_activate']);
        add_action('admin_hook', [$this, 'on_plugin_activate']);

        // Hook xử lý form submit
        add_action('admin_post_sfcd_save_config', [$this, 'save_config']);
        add_action('admin_post_est_security_save_config', [$this, 'est_security_save_config']);
    }

    public function on_plugin_activate()
    {
        if (!get_option('est_security_initialized')) {

            // Tạo giá trị mặc định
            add_option('sfcd_notify_email', get_option('admin_email', ''));
            add_option('est_default_wp_files', 1);
            add_option('est_copy_protection', 1);
            add_option('est_prevent_site_display_inside_frame', 1);
            add_option('est_disable_file_editing', 1);
            add_option('est_custom_login_enabled', 1);
            add_option('est_custom_login_slug', 'est-login');
            add_option('est_custom_login_enabled', 1);

            // Đánh dấu đã init để không chạy lại
            update_option('est_user_lockout', 0);
            update_option('est_max_attempts', 5);
            update_option('est_lockout_time', 300);

            new Generate_File();

            $this->set_after_active_plugin = true;
        }
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

        error_log('>>>save_config');

        // Lấy email và sanitize
        if ($_POST['sfcd_notify_email']) {
            $new_email = isset($_POST['sfcd_notify_email']) ? sanitize_email($_POST['sfcd_notify_email']) : '';
            // Validate email
            if (!is_email($new_email)) {
                wp_safe_redirect(admin_url('admin.php?page=enosta-security-config&error=invalid_email'));
                exit;
            }
            // Lưu email
            update_option('sfcd_notify_email', $new_email);
        }



        $enabled_custom_slug = isset($_POST['est_custom_login_enabled']) ? 1 : 0;
        update_option('est_custom_login_enabled', $enabled_custom_slug);

        // Process custom slug
        $custom_slug = isset($_POST['est_custom_login_slug']) ? sanitize_text_field($_POST['est_custom_login_slug']) : 'est-login';
        $custom_slug = trim($custom_slug, '/');
        $custom_slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $custom_slug);
        update_option('est_custom_login_slug', $custom_slug);

        // Lockout
        update_option('est_user_lockout', isset($_POST['est_user_lockout']) ? 1 : 0);
        update_option('est_lockout_time', isset($_POST['est_lockout_time']) ? intval($_POST['est_lockout_time']) : 300);
        update_option('est_max_attempts', isset($_POST['est_max_attempts']) ? intval($_POST['est_max_attempts']) : 5);

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
