<?php
if (!defined('ABSPATH')) exit;

class EST_Menu
{
    private $table_audit;
    private $table_lockout;
    private $per_page = 20;
    private $base_dir;
    private $upload_directory;
    private $option_key = 'sfcd_baseline';
    private $report_option = 'sfcd_last_report';
    public function __construct()
    {
        global $wpdb;
        $this->table_audit = $wpdb->prefix . 'security_audit_log';
        $this->table_lockout = $wpdb->prefix . 'login_lockout';
        $this->upload_directory = wp_upload_dir();
        $this->base_dir = $this->upload_directory['basedir'];
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    public function admin_menu()
    {
        add_menu_page('Security', 'Security', 'manage_options', 'enosta-security', [$this, 'admin_page'], 'dashicons-shield', 61);

        add_submenu_page(
            'enosta-security',               // Slug menu cha
            'Audit Log',                  // Title trong trang
            'Audit Log',                  // Label hiển thị trong menu
            'manage_options',             // Quyền
            'enosta-audit-log',          // Slug menu con
            [$this, 'page_audit']     // Callback hiển thị nội dung
        );

        add_submenu_page(
            'enosta-security',               // Slug menu cha
            'Login Lockout',                  // Title trong trang
            'Login Lockout',                  // Label hiển thị trong menu
            'manage_options',             // Quyền
            'login-lockout',          // Slug menu con
            [$this, 'page_lockout']     // Callback hiển thị nội dung
        );

        add_submenu_page(
            'enosta-security',
            'File security',
            'File security',
            'manage_options',
            'enosta-security-filesystem',
            [$this, 'page_filesystem']
        );

        add_submenu_page(
            'enosta-security',               // Slug menu cha
            'Reports',                  // Title trong trang
            'Reports',                  // Label hiển thị trong menu
            'manage_options',             // Quyền
            'enosta-security-report',          // Slug menu con
            [$this, 'page_report']     // Callback hiển thị nội dung
        );

        add_submenu_page(
            'enosta-security',
            'Settings',
            'Settings',
            'manage_options',
            'enosta-security-config',
            [$this, 'page_config']
        );

        add_menu_page(
            'Security Settings',
            'ENOSTA Security',
            'manage_options',
            'custom-security-settings',
            [$this, 'security_admin_page'],
            'dashicons-shield-alt',
            80
        );
    }


    public function page_config()
    {
        if (!current_user_can('manage_options')) wp_die('No permission');
        require_once __DIR__ . '/sections/section-config.php';
    }

    public function admin_page()
    {
        if (!current_user_can('manage_options')) wp_die('No permission');
        require_once __DIR__ . '/sections/section-check.php';
    }

    public function page_filesystem()
    {
        if (!current_user_can('manage_options')) wp_die('No permission');
        require_once __DIR__ . '/sections/section-filesystem.php';
    }

    public function page_report()
    {
        if (!current_user_can('manage_options')) wp_die('No permission');
        require_once __DIR__ . '/sections/section-report.php';
    }

    public function page_audit()
    {
        if (!current_user_can('manage_options')) wp_die('No permission');
        require_once __DIR__ . '/sections/section-audit.php';
    }

    public function page_lockout()
    {
        global $wpdb;
        $users = $wpdb->get_results("SELECT * FROM {$this->table_lockout} ORDER BY last_attempt DESC", ARRAY_A);
?>
        <div class="wrap">
            <h1>Locked Users</h1>
            <table class="widefat fixed">
                <thead>
                    <tr>
                        <th>User Login</th>
                        <th>Attempts</th>
                        <th>Last Attempt</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user) : ?>
                        <tr>
                            <td><?php echo esc_html($user['user_login']); ?></td>
                            <td><?php echo esc_html($user['attempts']); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', $user['last_attempt']); ?></td>
                            <td>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                    <input type="hidden" name="action" value="unlock_user">
                                    <input type="hidden" name="user_login" value="<?php echo esc_attr($user['user_login']); ?>">
                                    <?php submit_button('Unlock', 'secondary small', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)) : ?>
                        <tr>
                            <td colspan="4">No locked users</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
    }
}
new EST_Menu();
