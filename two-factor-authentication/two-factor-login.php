<?php

require dirname(__FILE__) . '/simba-tfa/simba-tfa.php';

if (!class_exists('Simba_Two_Factor_Authentication_Plugin')):

	class Simba_Two_Factor_Authentication_Plugin extends Simba_Two_Factor_Authentication
	{

		const PHP_REQUIRED = '5.6';
		public function __construct()
		{

			if (!empty($abort)) return;

			// Menu entries
			add_action('admin_menu', array($this, 'menu_entry_for_admin'));
			add_action('admin_menu', array($this, 'menu_entry_for_user'));

			// Add settings link in plugin list
			$plugin = plugin_basename(__FILE__);
			add_filter("plugin_action_links_$plugin", array($this, 'add_plugin_settings_link'));

			$this->set_user_settings_page_slug('two-factor-auth-user');

			$this->set_site_wide_administration_url(admin_url('options-general.php?page=two-factor-auth'));

			parent::__construct();
		}

		/**
		 * Runs upon the WP filters plugin_action_links_(plugin) and network_plugin_action_links_(plugin)
		 *
		 * @param Array $links
		 *
		 * @return Array
		 */
		public function add_plugin_settings_link($links)
		{

			$link = $this->get_settings_link();
			array_unshift($links, $link);

			$link2 = '<a href="admin.php?page=two-factor-auth-user">' . __('User settings', 'est-security') . '</a>';
			array_unshift($links, $link2);

			return $links;
		}

		/**
		 * Get 2FA settings anchor tag link.
		 *
		 * @return string 2FA settings anchor tag link.
		 */
		private function get_settings_link()
		{
			return '<a href="' . admin_url('options-general.php') . '?page=two-factor-auth">' . __('Plugin settings', 'est-security') . '</a>';
		}

		/**
		 * Runs upon the WP actions admin_menu and network_admin_menu
		 */
		public function menu_entry_for_user()
		{

			global $current_user;
			if ($this->is_activated_for_user($current_user->ID)) {

				add_submenu_page(
					'enosta-security',
					'2FA',
					'2FA',
					'manage_options',
					'two-factor-auth-user',
					array($this, 'show_dashboard_user_settings_page')
				);
			}
		}

		/**
		 * Runs upon the WP action admin_menu
		 */
		public function menu_entry_for_admin()
		{
			add_options_page(
				__('Two Factor Authentication', 'est-security'),
				__('Two Factor Authentication', 'est-security'),
				$this->get_management_capability(),
				'two-factor-auth',
				array($this, 'show_admin_settings_page')
			);
		}

		/**
		 * Include the admin settings page code
		 */
		public function show_admin_settings_page()
		{

			if (!is_admin() || !current_user_can($this->get_management_capability())) return;

			$this->include_template('admin-settings.php', array(
				'settings_page_heading' => $this->get_settings_page_heading(),
			));
		}
	}
endif;

$GLOBALS['simba_two_factor_authentication'] = new Simba_Two_Factor_Authentication_Plugin();
