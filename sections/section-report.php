<?php

$baseline = get_option($this->option_key, []);
$last_report = get_option($this->report_option, []);

?>
<div class="wrap">
    <h1>File Change Detector Report</h1>
    <div class="admin-box">
        <h2>Baseline files tracked: <strong><?php echo number_format(count($baseline)); ?></strong></h2>
        <h2>Last report</h2>
        <?php if (empty($last_report)): ?>
            <p>No report yet.</p>
        <?php else: ?>
            <p><strong>Scanned at:</strong> <?php echo esc_html($last_report['time']); ?></p>
            <p><strong>Modified files:</strong> <?php echo intval($last_report['counts']['modified']); ?></p>
            <p><strong>New files:</strong> <?php echo intval($last_report['counts']['added']); ?></p>
            <?php if (!empty($last_report['details'])): ?>
                <h3>Details (truncated)</h3>
                <pre style="max-height:400px; overflow:auto; background:#fff;">
        <?php echo esc_html(substr($last_report['details'], 0, 20000)); ?>
    </pre>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>