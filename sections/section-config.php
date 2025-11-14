<?php
$email = get_option('sfcd_notify_email', '');
$enabled = get_option('est_custom_login_enabled', 1);
$slug = get_option('est_custom_login_slug', 'est-login');

$est_user_lockout = get_option('est_user_lockout', 0);
$max_attempts = get_option('est_max_attempts', 5);
$lockout_time = get_option('est_lockout_time', 900);

if (isset($_GET['saved'])) {
    echo '<div class="updated notice"><p>Settings saved successfully.</p></div>';
}

if (isset($_GET['error']) && $_GET['error'] === 'invalid_email') {
    echo '<div class="error notice"><p>Invalid email address.</p></div>';
}
?>
<div class="wrap">
    <h1>Settings</h1>

    <div class="admin-box">
        <div id="db_prefix">
            <table class="form-table">
                <tr>
                    <th>Use randomised database prefix (not “wp_”)</th>
                    <td><button id="change-prefix-btn" type="button" class="button button-primary">Generate</button></td>
                </tr>
            </table>
        </div>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sfcd_save_config'); ?>
            <input type="hidden" name="action" value="sfcd_save_config">

            <table class="form-table">
                <tr>
                    <th><label for="sfcd_notify_email">Notification Email</label></th>
                    <td>
                        <input type="email" name="sfcd_notify_email" id="sfcd_notify_email"
                            value="<?php echo esc_attr($email); ?>" class="regular-text" required>
                        <p class="description">This email will receive notifications when a file is changed.</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="sfcd_notify_email">Enable Custom Login URL</label></th>
                    <td>
                        <input type="checkbox" name="est_custom_login_enabled" id="est_custom_login_enabled" value="1"
                            <?php echo checked(1, $enabled, false) ?>> Enable
                    </td>
                </tr>

                <tr>
                    <th><label for="sfcd_notify_email">Custom Slug</label></th>
                    <td>
                        <input type="text" name="est_custom_login_slug" id="est_custom_login_slug"
                            value="<?php echo esc_attr($slug) ?>" class="regular-text">
                        <p class="description">Example: <code>wp-admin</code></p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2">
                        <h3>File protection</h3>
                    </th>
                </tr>

                <tr>
                    <th>
                        <label>
                            Enable User Lockout:
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" name="est_user_lockout" value="1"
                            <?php echo checked(1, $est_user_lockout, false) ?>>
                        Enable
                        <div class="mt-30" style="margin-top: 15px">
                            <p>
                                <label>Maximum failed login attempts</label><br>
                                <input type="text" name="est_max_attempts" id="est_max_attempts"
                                    value="<?php echo esc_attr($max_attempts) ?>" class="regular-text">
                            </p>
                            <p>
                                <label>Lockout time in seconds (15 minutes)</label><br>
                                <input type="text" name="est_lockout_time" id="est_lockout_time"
                                    value="<?php echo esc_attr($lockout_time) ?>" class="regular-text">
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary">Save Settings</button></p>
        </form>
    </div>
</div>