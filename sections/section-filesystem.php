<?php
function get_permission_octal($path)
{
    return substr(sprintf('%o', fileperms($path)), -4);
}

function scan_wp_permissions()
{
    $root = ABSPATH;
    $items = [];

    $check_targets = [
        $root,
        $root . 'wp-includes',
        $root . '.htaccess',
        $root . 'wp-admin/index.php',
        $root . 'wp-admin/js',
        $root . 'wp-content/themes',
        $root . 'wp-content/plugins',
        $root . 'wp-admin',
        $root . 'wp-content',
        $root . 'wp-config.php',
    ];

    foreach ($check_targets as $item) {
        if (!file_exists($item)) continue;

        $is_dir = is_dir($item);
        $current = get_permission_octal($item);

        // Quyền khuyến nghị
        if ($is_dir) {
            $recommended = '0755';
        } else {
            $recommended = '0644';
        }

        // Ngoại lệ đặc biệt
        if (basename($item) === 'wp-config.php') {
            $recommended = '0400';
        }

        if (basename($item) === '.htaccess') {
            $recommended = '0444'; // 0644
        }

        $items[] = [
            'is_dir' => $is_dir ? 'Folder' : 'File',
            'name' => basename($item),
            'path' => $item,
            'current_permission' => $current,
            'recommended_permission' => $recommended,
            'recommended_action' => ($current === $recommended) ? 'No action required' : 'Change permissions',
        ];
    }

    return $items;
}

global $wp_version;
$default_wp_files = get_option('est_default_wp_files', 1);
$copy_protection = get_option('est_copy_protection', 1);
$prevent_site_display_inside_frame = get_option('est_prevent_site_display_inside_frame', 1);
$disable_file_editing = get_option('est_disable_file_editing', 1);

$response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
$show_update_version = false;
$latest = '';
if (!is_wp_error($response)) {
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $latest = $body['offers'][0]['current'] ?? '';

    if ($latest && version_compare($wp_version, $latest, '<')) {
        $show_update_version = true;
    }
}
?>
<div class="wrap">
    <h1>File security</h1>

    <?php if ($show_update_version) : ?>
        <div class="admin-box" style="background-color: #ffe2a852;">
            <h2 style="margin: 10px 0;">A new version of WordPress is available <strong>Version: </strong><?php echo esc_html($wp_version); ?> ->
                <?php echo esc_html($latest); ?></h2>
            <p style="margin-bottom: 10px;">
                Please update your WordPress core to the latest version to ensure better performance, security, and
                compatibility.
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('update-core.php')); ?>" class="button button-primary">
                    Update Now
                </a>
            </p>
        </div>
    <?php endif; ?>

    <div class="admin-box">
        <h2 class="mt-0">File permissions </h2>
        <table class="sfcd-table" id="sfcd_permissions_table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Path</th>
                    <th>Type</th>
                    <th>Current</th>
                    <th>Recommended</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 0;
                $only_issues = '';
                foreach (scan_wp_permissions() as $row) {
                    if ($only_issues && $row['current_permission'] === $row['recommended_permission']) continue;
                    $i++;
                    $is_issue = ($row['current_permission'] !== $row['recommended_permission']);
                    $tr_class = $is_issue ? 'sfcd-issue' : 'sfcd-good';
                    $type = $row['is_dir'];
                    $action_text = $is_issue ? 'Change permissions' : 'No action required';
                ?>
                    <tr class="<?php echo esc_attr($tr_class); ?>">
                        <td><?php echo esc_html($row['name']); ?></td>
                        <td class="sfcd-path"><?php echo esc_html($row['path']); ?></td>
                        <td><?php echo esc_html($type); ?></td>
                        <td><?php echo esc_html($row['current_permission']); ?></td>
                        <td><?php echo esc_html($row['recommended_permission']); ?></td>
                        <td class="sfcd-actions">
                            <?php if ($is_issue): ?>
                                <span class="sfcd-badge sfcd-badge-fix">Fix</span>
                            <?php else: ?>
                                <span class="sfcd-badge sfcd-badge-ok">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                }
                if ($i === 0) {
                    echo '<tr><td colspan="7">No results.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="admin-box">
        <h2 class="mt-0">Scan php file on uploads folder</h2>
        <?php
        $se = new EST_Security();
        $files = $se->scan_directory_for_php($this->base_dir);
        if (empty($files)) {
            echo '<div>File not found</div>';
        } else {
        ?>
            <form id="delete-php-files-form" method="post" style="margin-top:10px;">
                <?php wp_nonce_field('delete_php_files', 'delete_php_files_nonce'); ?>
                <ul class="files-list">
                    <?php
                    foreach ($files as $file) {
                        echo '<li>
                            <label>
                                <input type="checkbox" name="php_files_to_delete[]" value="' . esc_attr($file) . '">
                                ' . esc_html(str_replace(ABSPATH, '', $file)) . '
                            </label>
                        </li>';
                    }
                    ?>
                </ul>
                <?php if (!empty($files)): ?>
                    <button type="button" id="delete-php-files-btn" class="button button-danger">
                        <?php _e('Delete', DOMAIN); ?>
                    </button>
                <?php endif; ?>
            </form>
        <?php } ?>
    </div>

    <div class="admin-box">
        <h2 class="mt-0">File protection </h2>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('est_security_save_config'); ?>
            <input type="hidden" name="action" value="est_security_save_config">

            <table class="form-table">
                <tr>
                    <th>
                        <label>Delete debug.log</label>
                    </th>
                    <td>
                        <button type="button" id="delete_debug_log" class="button button-danger">
                            Detele
                        </button>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label>Delete readme.html, license.txt, and wp-config-sample.php</label>
                    </th>
                    <td>
                        <input type="checkbox" name="est_default_wp_files" value="1"
                            <?php echo checked(1, $default_wp_files, false) ?>>
                        Automatically delete the files after a WP core update.
                    </td>
                </tr>
                <tr>
                    <th>
                        <label>Disable ability to edit PHP files: </label>
                    </th>
                    <td>
                        <input type="checkbox" name="est_disable_file_editing" value="1"
                            <?php echo checked(1, $disable_file_editing, false) ?>>
                        Enable this to remove the ability for people to edit PHP files via the WP dashboard
                    </td>
                </tr>
                <tr>
                    <th>
                        <label>Enable copy protection: </label>
                    </th>
                    <td>
                        <input type="checkbox" name="est_copy_protection" value="1"
                            <?php echo checked(1, $copy_protection, false) ?>>
                        Enable this to disable the "Right click", "Text selection" and "Copy" options on the front end
                        of your site.
                    </td>
                </tr>
                <tr>
                    <th>
                        <label>Enable iFrame protection: </label>
                    </th>
                    <td>
                        <input type="checkbox" name="est_prevent_site_display_inside_frame" value="1"
                            <?php echo checked(1, $prevent_site_display_inside_frame, false) ?>>
                        Enable this to stop other sites from displaying your content in a frame or iframe.
                    </td>
                </tr>

            </table>

            <p><button type="submit" class="button button-primary">Save Settings</button></p>
        </form>
    </div>
</div>