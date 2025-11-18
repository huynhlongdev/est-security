<?php
if (!defined('ABSPATH')) exit;

class EST_Recaptcha
{
    private $site_key;
    private $secret_key;
    private $version; // v2 | v3
    private $threshold = 0.5;
    private $recaptcha_enabled;

    public function __construct()
    {
        $this->site_key        = get_option('est_recaptcha_site_key', '');
        $this->secret_key      = get_option('est_recaptcha_secret_key', '');
        $this->version         = get_option('est_recaptcha_version', 'v2');
        $this->recaptcha_enabled = get_option('est_recaptcha_enabled', 0);

        // Nếu chưa bật hoặc thiếu key → dừng
        if ($this->recaptcha_enabled != 1 || empty($this->site_key) || empty($this->secret_key)) {
            return;
        }

        // Enqueue script chung cho login, comment, CF7
        add_action('login_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);

        // Login form
        add_action('login_form', [$this, 'render_login_recaptcha']);
        add_filter('authenticate', [$this, 'validate_login'], 99, 3);

        // Comment form
        add_action('comment_form_after_fields', [$this, 'render_comment_recaptcha']);
        add_action('comment_form_logged_in_after', [$this, 'render_comment_recaptcha']);
        add_filter('preprocess_comment', [$this, 'validate_comment']);

        // CF7 validate
        add_filter('wpcf7_validate', [$this, 'validate_cf7'], 20, 2);
    }

    /** Load reCAPTCHA script */
    public function enqueue()
    {
        if ($this->version === 'v2') {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
        } else {
            wp_enqueue_script(
                'google-recaptcha-v3',
                'https://www.google.com/recaptcha/api.js?render=' . $this->site_key,
                [],
                null,
                true
            );
        }
    }

    /** Login form */
    public function render_login_recaptcha()
    {
        echo $this->get_recaptcha_html();
    }

    /** Validate login */
    public function validate_login($user, $username, $password)
    {
        if (is_wp_error($user)) return $user;
        $result = $this->verify_recaptcha();
        if ($result !== true) {
            return new WP_Error('recaptcha_error', '<strong>Lỗi reCAPTCHA:</strong> ' . $result);
        }
        return $user;
    }

    /** Comment form */
    public function render_comment_recaptcha()
    {
        echo $this->get_recaptcha_html();
    }

    /** Validate comment */
    public function validate_comment($commentdata)
    {
        $result = $this->verify_recaptcha();
        if ($result !== true) {
            wp_die('reCAPTCHA không hợp lệ: ' . $result);
        }
        return $commentdata;
    }

    /** CF7: get HTML để chèn thủ công */
    public function get_cf7_recaptcha_html()
    {
        return $this->get_recaptcha_html();
    }

    /** CF7 validate */
    public function validate_cf7($result, $tags)
    {
        $verify = $this->verify_recaptcha();
        if ($verify !== true) {
            $result->invalidate('', 'Lỗi xác thực reCAPTCHA: ' . $verify);
        }
        return $result;
    }

    /** HTML cho reCAPTCHA (login, comment, CF7 thủ công) */
    private function get_recaptcha_html()
    {
        if ($this->version === 'v2') {
            return '<div class="g-recaptcha" data-sitekey="' . esc_attr($this->site_key) . '" style="margin: 15px 0;"></div>';
        }

        // v3 không có UI
        return '
            <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
            <script>
                grecaptcha.ready(function() {
                    grecaptcha.execute("' . $this->site_key . '", {action: "submit"}).then(function(token) {
                        document.getElementById("g-recaptcha-response").value = token;
                    });
                });
            </script>
        ';
    }

    /** Verify chung cho mọi form */
    public function verify_recaptcha()
    {
        $response = $_POST['g-recaptcha-response'] ?? '';
        if (!$response) return 'Bạn chưa xác minh reCAPTCHA.';

        $verify = wp_remote_post("https://www.google.com/recaptcha/api/siteverify", [
            'body' => [
                'secret'   => $this->secret_key,
                'response' => $response,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ]
        ]);

        $data = json_decode(wp_remote_retrieve_body($verify), true);

        if ($this->version === 'v2') {
            return ($data['success'] ?? false) ? true : 'Sai reCAPTCHA.';
        }

        // v3
        if (!($data['success'] ?? false)) return 'Xác minh thất bại.';
        if (($data['score'] ?? 0) < $this->threshold) return 'Điểm reCAPTCHA thấp.';
        return true;
    }
}

new EST_Recaptcha();

/**
 * Shortcode CF7 thủ công
 * Chèn vào form: [recaptcha-html]
 */
add_shortcode('recaptcha-html', function () {
    if (!class_exists('EST_Recaptcha')) return '';
    $recaptcha = new EST_Recaptcha();
    return $recaptcha->get_cf7_recaptcha_html();
});
