<?php
if (!defined('ABSPATH')) exit;

class EST_Password
{
    private $schedule_hook = 'est_admin_password_reset_hook';
    private $interval; // default: monthly 
    private $enable_auto_change;

    public function __construct()
    {

        $this->enable_auto_change = get_option('est_enable_auto_change', 1);
        $this->interval = get_option('est_auto_change_interval', 'monthly');

        // add_filter('password_strength_meter_enqueue_scripts', '__return_false');
        add_action('admin_enqueue_scripts', [$this, 'remove_weak_password_checkbox']);
        add_filter('user_profile_update_errors', [$this, 'validate_password_server_side'], 10, 3);
        add_filter('random_password', [$this, 'generate_strong_password'], 20);

        if (class_exists('WooCommerce')) {
            add_action('woocommerce_register_post', [$this, 'woocommerce_validate_password'], 10, 3);
        }

        add_filter('cron_schedules', [$this, 'add_custom_intervals']);
        if ($this->enable_auto_change == 1) {
            add_action('init', [$this, 'schedule_password_event']);
            add_action($this->schedule_hook, [$this, 'reset_admin_passwords']);
        }
    }

    public function add_custom_intervals($schedules)
    {
        $schedules['monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => 'Every Month',
        ];
        $schedules['quarterly'] = [
            'interval' => 90 * DAY_IN_SECONDS,
            'display'  => 'Every 3 Months',
        ];
        $schedules['semiannually'] = [
            'interval' => 180 * DAY_IN_SECONDS,
            'display'  => 'Every 6 Months',
        ];
        return $schedules;
    }

    public function schedule_password_event()
    {
        if (!wp_next_scheduled($this->schedule_hook)) {
            wp_schedule_event(time(), $this->interval, $this->schedule_hook);
        }
    }

    public function reset_admin_passwords()
    {
        $admins = get_users([
            'role'   => 'administrator',
            'fields' => ['ID', 'user_email', 'user_login']
        ]);

        foreach ($admins as $admin) {
            $new_password = wp_generate_password(16, true, true);

            // Cập nhật mật khẩu
            wp_set_password($new_password, $admin->ID);

            // Gửi email thông báo
            $subject = 'Your WordPress admin password has been reset';
            $message = "Hello {$admin->user_login},\n\n";
            $message .= "Your admin password has been automatically reset for security reasons.\n";
            $message .= "New password: {$new_password}\n\n";
            $message .= "Website:\n";
            $message .= home_url('/');

            wp_mail($admin->user_email, $subject, $message);
        }
    }

    public static function clear_cron()
    {
        $hook = 'est_admin_password_reset_hook';
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }

    public function remove_weak_password_checkbox()
    {
        wp_add_inline_script('user-profile', "
            jQuery(document).ready(function($) {

                function passwordScore(pw) {
                    let score = 0;
                    if (pw.length >= 8) score++;
                    if (/[A-Z]/.test(pw)) score++;
                    if (/[a-z]/.test(pw)) score++;
                    if (/[0-9]/.test(pw)) score++;
                    if (/[^A-Za-z0-9]/.test(pw)) score++;
                    return score;
                }

                function getStrengthLabel(score) {
                    if (score <= 2) return {text: 'Weak', css: 'short'};
                    if (score === 3 || score === 4) return {text: 'Medium', css: 'good'};
                    if (score === 5) return {text: 'Strong', css: 'strong'};
                }

                function updateStrengthUI() {
                    let pw = $('#pass1').val();
                    let box = $('#pass-strength-result');

                    if (pw.length === 0) {
                        box.text('').removeClass().hide();
                        return;
                    }

                    let score = passwordScore(pw);
                    let res = getStrengthLabel(score);

                    box.text(res.text)
                    .removeClass()
                    .addClass(res.css)
                    .show();

                    // Logic block submit
                    if (score >= 5) {
                        // Medium & Strong → Cho phép submit
                        $('[name=\"createuser\"]').prop('disabled', false);
                    } else {
                        // Weak → Khoá submit
                        $('[name=\"createuser\"]').prop('disabled', true);
                    }
                }

                // Trigger real-time
                $(document).on('keyup', '#pass1', updateStrengthUI);
                $('.pw-weak').remove();
            });
        ");
    }

    public function validate_password_server_side($errors, $update, $user)
    {
        if (!empty($_POST['pass1'])) {
            $password = sanitize_text_field($_POST['pass1']);
            $strength = $this->calculate_password_strength($password);

            if ($strength < 3) {
                $errors->add('weak_password', __('Your password is too weak. It must contain:
                <br>• At least 8 characters
                <br>• At least 1 uppercase letter (A–Z)
                <br>• At least 1 lowercase letter (a–z)
                <br>• At least 1 number (0–9)
                <br>• At least 1 special character (!@#$%^&*)'));
            }
        }
    }

    private function calculate_password_strength($password)
    {
        if (strlen($password) < 8) return 0;

        $has_upper   = preg_match('/[A-Z]/', $password);
        $has_lower   = preg_match('/[a-z]/', $password);
        $has_number  = preg_match('/[0-9]/', $password);
        $has_special = preg_match('/[^A-Za-z0-9]/', $password);

        return $has_upper && $has_lower && $has_number && $has_special;
    }

    public function generate_strong_password($password)
    {
        $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower   = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*(){}[]=<>?,.';

        $password = '';
        $password .= $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $all = $upper . $lower . $numbers . $special;
        for ($i = 4; $i < 16; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    public function woocommerce_validate_password($username, $email, $validation_errors)
    {
        if (!isset($_POST['password']) || empty($_POST['password'])) return;

        $pw = sanitize_text_field($_POST['password']);
        if (!$this->is_strong_password($pw)) {
            $validation_errors->add(
                'weak_password',
                __('Your password is too weak. It must contain at least 8 characters, uppercase, lowercase, number, and special character.', 'text-domain')
            );
        }
    }
}

new EST_Password();
