<?php
$email = get_option('est_notify_email', '');
$enabled = get_option('est_custom_login_enabled', 1);
$slug = get_option('est_custom_login_slug', est_path());

$est_user_lockout = get_option('est_user_lockout', 0);
$max_attempts = get_option('est_max_attempts', 5);
$lockout_time = get_option('est_lockout_time', 900);

$est_recaptcha_version = get_option('est_recaptcha_version', 'v2');
$recaptcha_enabled = get_option('est_recaptcha_enabled', 0);
$recaptcha_site_key = get_option('est_recaptcha_site_key', '');
$recaptcha_secret_key = get_option('est_recaptcha_secret_key', '');

$est_enable_auto_change = get_option('est_enable_auto_change', 1);
$est_auto_change_interval = get_option('est_auto_change_interval', 'monthly');

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
                    <th>
                        <label>Notification Email</label>
                    </th>
                    <td>
                        <input type="email" name="est_notify_email" id="est_notify_email"
                            value="<?php echo esc_attr($email); ?>" class="regular-text" required>
                        <p class="description">This email will receive notifications when a file is changed.</p>
                    </td>
                </tr>

                <tr>
                    <th>
                        <label>Enable Custom Login URL</label>
                    </th>
                    <td>
                        <input type="checkbox" name="est_custom_login_enabled" id="est_custom_login_enabled" value="1"
                            <?php echo checked(1, $enabled, false) ?>> Enable
                    </td>
                </tr>

                <tr>
                    <th>
                        <label>Custom Slug</label>
                    </th>
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

                <tr>
                    <th colspan="2">
                        <h3>Auto Change Admin Password</h3>
                    </th>
                </tr>
                <tr>
                    <th>
                        Enable Auto Change
                    </th>
                    <td>
                        <input type="checkbox" name="est_enable_auto_change" value="1"
                            <?php echo checked(1, $est_enable_auto_change, false) ?>>
                        Enable automatic password reset for admin users
                    </td>
                </tr>
                <tr>
                    <th>
                        <label>Cron Interval</label>
                    </th>
                    <td>
                        <select name="est_auto_change_interval">
                            <option value="monthly" <?php selected($est_auto_change_interval ?? '', 'monthly'); ?>>Every Month</option>
                            <option value="quarterly" <?php selected($est_auto_change_interval ?? '', 'quarterly'); ?>>Every 3 Months</option>
                            <option value="semiannually" <?php selected($est_auto_change_interval ?? '', 'semiannually'); ?>>Every 6 Months</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>
                        <h3>reCAPTCHA v2</h3>
                    </th>
                    <td>
                        <strong>[recaptcha-html]</strong>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="est_recaptcha_enabled">Enable reCAPTCHA v2</label>
                    </th>
                    <td>
                        <input type="checkbox" name="est_recaptcha_enabled" id="est_recaptcha_enabled" value="1"
                            <?php echo checked(1, $recaptcha_enabled, false); ?>>
                        Enable
                        <p class="description">Protects login form with Google reCAPTCHA v2 (Checkbox).</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="est_recaptcha_version">Version</label>
                    </th>
                    <td>
                        <select name="est_recaptcha_version">
                            <option value="v2" <?php selected($est_recaptcha_version ?? '', 'v2'); ?>>V2</option>
                            <option value="v3" <?php selected($est_recaptcha_version ?? '', 'v3'); ?>>V3</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="est_recaptcha_site_key">Site Key</label></th>
                    <td>
                        <input type="text" name="est_recaptcha_site_key" id="est_recaptcha_site_key"
                            value="<?php echo esc_attr($recaptcha_site_key); ?>" class="regular-text">
                        <p class="description">
                            Generate keys at <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener noreferrer">Google reCAPTCHA admin</a>.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="est_recaptcha_secret_key">Secret Key</label></th>
                    <td>
                        <input type="text" name="est_recaptcha_secret_key" id="est_recaptcha_secret_key"
                            value="<?php echo esc_attr($recaptcha_secret_key); ?>" class="regular-text">
                    </td>
                </tr>

            </table>
            <p><button type="submit" class="button button-primary">Save Settings</button></p>
        </form>
    </div>
</div>