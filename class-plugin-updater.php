<?php

if (!defined('ABSPATH')) exit;

class EST_Security_Updater
{

    public $plugin_file;
    public $plugin_basename;
    public $update_url;
    public $current_version;
    public $slug;
    public $checked = 0;

    public function __construct($plugin_file)
    {

        $this->plugin_file     = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->update_url      = 'https://raw.githubusercontent.com/huynhlongdev/est-security/refs/heads/main/update-info.json';

        $plugin_data = get_plugin_data($plugin_file);
        $this->current_version = $plugin_data['Version'];
        $this->slug = dirname($this->plugin_basename);

        // Xóa transient để đảm bảo hook chạy
        add_action('admin_init', function () {
            delete_site_transient('update_plugins');
        });

        // Hook update
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_plugins_transient'));

        // Popup info
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
    }

    /** CHECK UPDATE */
    public function modify_plugins_transient($transient)
    {

        if (empty($transient->checked)) {
            return $transient;
        }

        error_log('>>>Transient:' . print_r($transient, true));

        $response = wp_remote_get($this->update_url, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json']
        ]);

        if (is_wp_error($response)) {
            return $transient;
        }

        $json = json_decode(wp_remote_retrieve_body($response));

        if (!$json || empty($json->version)) {
            return $transient;
        }

        // So sánh version
        if (version_compare($this->current_version, $json->version, '<')) {
            $transient->response[$this->plugin_basename] = (object) [
                'slug'        => $this->slug,
                'plugin'      => $this->plugin_basename,
                'new_version' => $json->version,
                'package'     => $json->download_url,
                'url'         => $json->homepage ?? '',
                'tested'      => '6.7',
                'requires'    => '5.0'
            ];
        }

        return $transient;
    }

    /** PLUGIN INFO POPUP */
    public function plugin_info($res, $action, $args)
    {
        if ($action !== 'plugin_information') return $res;
        if ($args->slug !== $this->slug) return $res;

        $response = wp_remote_get($this->update_url);

        if (is_wp_error($response)) return $res;

        $json = json_decode(wp_remote_retrieve_body($response));

        return (object)[
            'name'          => $json->name,
            'slug'          => $this->slug,
            'version'       => $json->version,
            'download_link' => $json->download_url,
            'sections'      => [
                'description' => $json->sections->description ?? 'No description',
                'changelog' => $json->sections->changelog ?? 'No changelog'
            ],
            'author' => $json->author,
            'tested' => $json->tested,
            'requires' => $json->requires,
        ];
    }
}
