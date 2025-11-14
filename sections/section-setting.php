<style>
    .tab-content {
        margin-top: 20px;
    }
</style>
<div class="wrap">
    <h1><?php _e('Security Settings', DOMAIN); ?></h1>

    <div class="nav-tab-wrapper">
        <a href="/wp-admin/admin.php?page=custom-security-settings&action=permissions"
            class="nav-tab <?php echo (isset($_GET['action']) && $_GET['action'] === 'permissions') ? 'nav-tab-active' : ''; ?>">
            <?php _e('Permissions', DOMAIN); ?>
        </a>
    </div>
    <!-- nav-tab-wrapper -->

    <div id="permissions" class="tab-content"
        style="<?php echo (isset($_GET['action']) && $_GET['action'] === 'permissions') ? '' : 'display:none;'; ?>">
        <?php
        $saved = get_option('custom_disabled_permissions', []);

        $paths = [
            'wp-admin' => 'wp-admin',
            'wp-includes' => 'wp-includes',
            'wp-content/plugins' => 'wp-content/plugins',
            'wp-content/themes' => 'wp-content/themes',
        ];

        if (isset($_POST['custom_permissions_nonce']) && wp_verify_nonce($_POST['custom_permissions_nonce'], 'save_custom_permissions')) {
            $selected = [];
            if (isset($_POST['disabled_permissions']) && is_array($_POST['disabled_permissions'])) {
                foreach ($_POST['disabled_permissions'] as $key => $value) {
                    $selected[$key] = ($value === 'true') ? true : false;
                }
            }
            update_option('custom_disabled_permissions', $selected);
            $saved = $selected;
            echo '<div class="updated notice"><p>' . esc_html__('Permissions updated.', DOMAIN) . '</p></div>';
        }

        if (!is_array($saved)) $saved = [];
        ?>

        <form method=" post">
            <?php wp_nonce_field('save_custom_permissions', 'custom_permissions_nonce'); ?>
            <h2><?php _e('Disable Write Permissions For:', DOMAIN); ?></h2>
            <table class="form-table">
                <tbody>
                    <?php foreach ($paths as $key => $label) {
                        $current = isset($saved[$key]) ? $saved[$key] : false;
                    ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($label); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="disabled_permissions[<?php echo esc_attr($key); ?>]"
                                        value="true" <?php checked($current === true); ?>>
                                    <?php esc_html_e('True', DOMAIN); ?>
                                </label>
                                &nbsp;
                                <label>
                                    <input type="radio" name="disabled_permissions[<?php echo esc_attr($key); ?>]"
                                        value="false" <?php checked($current === false); ?>>
                                    <?php esc_html_e('False', DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save', DOMAIN); ?></button>
            </p>
        </form>
    </div>


</div>