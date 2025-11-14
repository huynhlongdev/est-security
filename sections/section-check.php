<?php
// $required = [
//     'strict-transport-security' => 'HSTS to enforce HTTPS',
//     'x-frame-options' => 'Prevent clickjacking',
//     'x-content-type-options' => 'Prevent MIME sniffing',
//     'referrer-policy' => 'Limit referrer information',
//     'content-security-policy' => 'Mitigate XSS & injection attacks',
//     'permissions-policy' => 'Restrict browser feature access'
// ];

// $url = home_url('/');
// $ch = curl_init($url);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_HEADER, true);       // include headers
// curl_setopt($ch, CURLOPT_NOBODY, false);      // GET request
// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // follow redirects
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // allow self-signed
// curl_setopt($ch, CURLOPT_TIMEOUT, 10);
// $response = curl_exec($ch);
// curl_close($ch);

// $headers = [];
// foreach (explode("\n", $response) as $line) {
//     if (strpos($line, ':') !== false) {
//         list($key, $value) = array_map('trim', explode(':', $line, 2));
//         $headers[strtolower($key)] = $value;
//     }
// }

?>

<div class="wrap">
    <h1>WP Security</h1>
    <h2>Headers Checker</h2>
    <table class="widefat" id="est-headers-table">
        <thead>
            <tr>
                <th>Header</th>
                <th>Status</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
    <script>
    jQuery(document).ready(function($) {
        var required = {
            'strict-transport-security': 'HSTS to enforce HTTPS',
            'x-frame-options': 'Prevent clickjacking',
            'x-content-type-options': 'Prevent MIME sniffing',
            'referrer-policy': 'Limit referrer information',
            'content-security-policy': 'Mitigate XSS & injection attacks',
            'permissions-policy': 'Restrict browser feature access'
        };

        var $tbody = $('#est-headers-table tbody');
        var $hint = $('#est-hint');
        var url = '<?php echo home_url('/') ?>';

        // build table rows
        var rows = {};
        $.each(required, function(key, label) {
            var $tr = $('<tr>');
            var $tdKey = $('<td>').html('<code>' + key + '</code>').css('white-space', 'nowrap');
            var $tdStatus = $('<td>').css({
                width: '80px',
                textAlign: 'center'
            }).html('—');
            var $tdValue = $('<td>').html('—').css('word-break', 'break-word');
            $tr.append($tdKey, $tdStatus, $tdValue);
            $tbody.append($tr);
            rows[key] = {
                status: $tdStatus,
                value: $tdValue
            };
        });

        // Ajax GET request
        $.ajax({
            url: url,
            method: 'GET',
            xhrFields: {
                withCredentials: true
            },
            cache: false,
            complete: function(xhr) {
                $hint.text('Headers fetched from ' + url);
                $.each(required, function(key, label) {
                    var val = xhr.getResponseHeader(key);
                    if (val) {
                        rows[key].status.html(
                            '<span style="color:green;font-weight:bold;">✅</span>');
                        rows[key].value.text(val);
                    } else {
                        rows[key].status.html(
                            '<span style="color:orange;font-weight:bold;">⚠</span>');
                        rows[key].value.html('<em>Not present or not exposed to JS</em>');
                    }
                });
            },
            error: function() {
                $hint.css('color', 'red').text('Failed to fetch headers.');
                $.each(required, function(key, label) {
                    rows[key].status.html(
                        '<span style="color:red;font-weight:bold;">❌</span>');
                    rows[key].value.html('<em>Error</em>');
                });
            }
        });
    });
    </script>
</div>