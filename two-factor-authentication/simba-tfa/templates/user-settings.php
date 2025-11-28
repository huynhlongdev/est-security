<?php

if (!defined('ABSPATH')) die('Access denied.');

global $current_user;
$totp_controller = $simba_tfa->get_controller('totp');

?>
<div class="wrap">

	<h2><?php echo esc_html__('Two Factor Authentication', 'est-security') . ' ' . esc_html__('Settings', 'est-security'); ?></h2>

	<?php
	if (!empty($totp_controller->were_settings_saved())) {
		echo '<div class="updated notice is-dismissible">' . "<p><strong>" . esc_html__('Settings saved.', 'est-security') . "</strong></p></div>";
	}

	?>
	<form method="post" action="<?php print esc_url(add_query_arg('settings-updated', 'true', isset($_SERVER['REQUEST_URI']) ? sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])) : '')); ?>">

		<?php wp_nonce_field('tfa_activate', '_tfa_activate_nonce', false, true); ?>

		<h2><?php esc_html_e('Activate two factor authentication', 'est-security'); ?></h2>
		<p>
			<?php
			$utc_date = gmdate('Y-m-d H:i:s');
			$date_now = get_date_from_gmt($utc_date, 'Y-m-d H:i:s');
			/* translators: 1. UTC date. 2. Now date. */
			echo sprintf(esc_html__('N.B. Getting your TFA app/device to generate the correct code depends upon a) you first setting it up by entering or scanning the code below into it, and b) upon your web-server and your TFA app/device agreeing upon the UTC time (within a minute or so). The current UTC time according to the server when this page loaded: %1$s, and in the time-zone you have configured in your WordPress settings: %2$s', 'est-security'), esc_html($utc_date), esc_html($date_now));
			?>
		</p>
		<p>
			<?php
			$simba_tfa->paint_enable_tfa_radios($current_user->ID);
			?></p>
		<?php submit_button(); ?>
	</form>

	<?php

	$totp_controller->current_codes_box();

	$totp_controller->advanced_settings_box();

	do_action('simba_tfa_user_settings_after_advanced_settings');

	?>

</div>