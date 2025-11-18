<?php
global $wpdb;

$table = $this->table_audit;
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($paged - 1) * $this->per_page;

$total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $this->per_page,
        $offset
    )
);
$total_pages = ceil($total / $this->per_page);
?>
<div class="wrap">
    <h1>Audit Log</h1>
    <div class="admin-box">
        <?php

        // Kiểm tra và hiển thị thông báo
        if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>All logs have been deleted successfully.</p></div>';
        }

        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')) ?>">
            <?php wp_nonce_field('enosta_delete_logs_action', 'enosta_delete_logs_nonce'); ?>
            <input type="hidden" name="action" value="enosta_delete_logs">
            <p>
                <input type="submit" class="button button-secondary" value="Delete All Logs" onclick="return confirm('Are you sure you want to delete all logs?')">
            </p>
        </form>

        <table class="widefat striped" style="margin-top:20px">
            <thead>
                <tr>
                    <th>User</th>
                    <th>IP</th>
                    <th>Type</th>
                    <th>Detail</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!$logs) {
                    echo '<tr><td colspan="6"><p>No logs found.</p></td></tr>';
                } else {
                    foreach ($logs as $i => $log) {
                ?>
                        <tr>
                            <td><strong class="color-black"><?php echo esc_html($log->user_login) ?></strong></td>
                            <td><?php echo esc_html($log->ip_address) ?></td>
                            <td><?php echo esc_html($log->action_type) ?></td>
                            <td><?php echo esc_html($log->action_detail) ?></td>
                            <td><?php echo esc_html($log->created_at) ?></td>
                        </tr>
                <?php
                    }
                }
                ?>
            </tbody>
        </table>
        <?php
        // Pagination
        if ($total_pages > 1) {
            $base_url = admin_url('admin.php?page=enosta-audit-log');
            echo '<div class="tablenav-pages" style="margin-top:15px">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active = ($i == $paged) ? 'font-weight:bold;color:#2271b1' : '';
                printf(
                    '<a href="%s" style="margin-right:5px;%s">%d</a>',
                    esc_url(add_query_arg('paged', $i, $base_url)),
                    $active,
                    $i
                );
            }
            echo '</div>';
        }
        ?>
    </div>
</div>