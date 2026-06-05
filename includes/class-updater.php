<?php
/**
 * Private plugin updater.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds private GitHub release updates with staging/stable channels.
 */
class TP_Updater {

    const OPTION_CONFIG  = 'tp_updater_config';
    const OWNER          = 'wefefino';
    const REPO           = 'tatipilates-plugin';
    const MANIFEST_PATH  = 'updates.json';
    const MANIFEST_REF   = 'main';
    const CACHE_MANIFEST = 'tp_updater_manifest';

    /**
     * Registers updater hooks.
     */
    public function __construct() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'inyectar_actualizacion'));
        add_filter('plugins_api', array($this, 'informacion_plugin'), 10, 3);
        add_filter('http_request_args', array($this, 'autorizar_descarga_github'), 10, 2);
        add_action('upgrader_process_complete', array($this, 'limpiar_cache_actualizacion'), 10, 2);
    }

    /**
     * Returns updater configuration.
     *
     * @return array<string,mixed>
     */
    public static function configuracion() {
        $config = get_option(self::OPTION_CONFIG, array());
        $config = is_array($config) ? $config : array();

        return wp_parse_args(
            $config,
            array(
                'enabled' => 0,
                'channel' => 'stable',
                'token'   => '',
            )
        );
    }

    /**
     * Saves updater configuration.
     *
     * @param array<string,mixed> $datos Raw settings.
     * @return bool
     */
    public static function guardar_configuracion($datos) {
        $actual = self::configuracion();
        $canal  = isset($datos['channel']) ? sanitize_key(wp_unslash($datos['channel'])) : 'stable';

        if (!in_array($canal, array('stable', 'staging'), true)) {
            $canal = 'stable';
        }

        $token = $actual['token'];

        if (!empty($datos['clear_token'])) {
            $token = '';
        } elseif (isset($datos['token']) && '' !== trim((string) wp_unslash($datos['token']))) {
            $token = sanitize_text_field(wp_unslash($datos['token']));
        }

        $guardado = update_option(
            self::OPTION_CONFIG,
            array(
                'enabled' => !empty($datos['enabled']) ? 1 : 0,
                'channel' => $canal,
                'token'   => $token,
            ),
            false
        );

        self::limpiar_cache();

        return (bool) $guardado;
    }

    /**
     * Clears cached manifest data.
     *
     * @return void
     */
    public static function limpiar_cache() {
        delete_transient(self::CACHE_MANIFEST);
    }

    /**
     * Injects plugin update data into WordPress updates.
     *
     * @param object $transient Update transient.
     * @return object
     */
    public function inyectar_actualizacion($transient) {
        if (!is_object($transient)) {
            return $transient;
        }

        $config = self::configuracion();

        if (empty($config['enabled']) || empty($config['token'])) {
            return $transient;
        }

        $release = $this->release_para_canal($config['channel']);

        if (!$release || empty($release['version']) || !version_compare($release['version'], TP_VERSION, '>')) {
            return $transient;
        }

        $package = $this->url_asset_release($release);

        if (!$package) {
            return $transient;
        }

        $plugin_file = plugin_basename(TP_PLUGIN_FILE);
        $transient->response[$plugin_file] = (object) array(
            'id'            => 'tatipilates',
            'slug'          => 'tatipilates',
            'plugin'        => $plugin_file,
            'new_version'   => $release['version'],
            'url'           => 'https://github.com/' . self::OWNER . '/' . self::REPO,
            'package'       => $package,
            'requires'      => isset($release['requires']) ? $release['requires'] : '6.0',
            'tested'        => isset($release['tested']) ? $release['tested'] : '',
            'requires_php'  => isset($release['requires_php']) ? $release['requires_php'] : '8.0',
            'upgrade_notice' => isset($release['notes']) ? $release['notes'] : '',
        );

        return $transient;
    }

    /**
     * Provides the modal information shown in wp-admin.
     *
     * @param false|object|array $result Existing result.
     * @param string             $action Current API action.
     * @param object             $args   Request args.
     * @return false|object|array
     */
    public function informacion_plugin($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || 'tatipilates' !== $args->slug) {
            return $result;
        }

        $config  = self::configuracion();
        $release = $this->release_para_canal($config['channel']);

        if (!$release) {
            return $result;
        }

        return (object) array(
            'name'          => 'Tati Pilates',
            'slug'          => 'tatipilates',
            'version'       => $release['version'],
            'author'        => 'Tati Pilates',
            'homepage'      => 'https://tatipilates.com',
            'requires'      => isset($release['requires']) ? $release['requires'] : '6.0',
            'tested'        => isset($release['tested']) ? $release['tested'] : '',
            'requires_php'  => isset($release['requires_php']) ? $release['requires_php'] : '8.0',
            'sections'      => array(
                'description' => 'Plugin privado de gestion para Tati Pilates.',
                'changelog'   => isset($release['notes']) && $release['notes'] ? esc_html($release['notes']) : 'Sin notas publicadas.',
            ),
            'download_link' => $this->url_asset_release($release),
        );
    }

    /**
     * Adds GitHub authorization headers to private asset downloads.
     *
     * @param array<string,mixed> $args HTTP args.
     * @param string              $url  Request URL.
     * @return array<string,mixed>
     */
    public function autorizar_descarga_github($args, $url) {
        if (false === strpos($url, 'api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/releases/assets/')) {
            return $args;
        }

        $config = self::configuracion();

        if (empty($config['token'])) {
            return $args;
        }

        $args['headers'] = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : array();
        $args['headers']['Authorization'] = 'Bearer ' . $config['token'];
        $args['headers']['Accept'] = 'application/octet-stream';
        $args['headers']['User-Agent'] = 'TatiPilates-Updater/' . TP_VERSION;

        return $args;
    }

    /**
     * Clears update cache after plugin upgrades.
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array       $hook_extra Extra data.
     * @return void
     */
    public function limpiar_cache_actualizacion($upgrader, $hook_extra) {
        if (!empty($hook_extra['plugins']) && in_array(plugin_basename(TP_PLUGIN_FILE), (array) $hook_extra['plugins'], true)) {
            self::limpiar_cache();
        }
    }

    /**
     * Gets the manifest entry for a channel.
     *
     * @param string $canal stable|staging.
     * @return array<string,mixed>|null
     */
    private function release_para_canal($canal) {
        $manifest = $this->manifest();

        if (!$manifest || empty($manifest['channels'][$canal]) || !is_array($manifest['channels'][$canal])) {
            return null;
        }

        return $manifest['channels'][$canal];
    }

    /**
     * Downloads and caches the updater manifest.
     *
     * @return array<string,mixed>|null
     */
    private function manifest() {
        $cached = get_transient(self::CACHE_MANIFEST);

        if (is_array($cached)) {
            return $cached;
        }

        $config = self::configuracion();

        if (empty($config['token'])) {
            return null;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/contents/%s?ref=%s',
            rawurlencode(self::OWNER),
            rawurlencode(self::REPO),
            rawurlencode(self::MANIFEST_PATH),
            rawurlencode(self::MANIFEST_REF)
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'headers' => $this->github_headers('application/vnd.github+json'),
            )
        );

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body || empty($body['content'])) {
            return null;
        }

        $json = base64_decode((string) $body['content'], true);
        $manifest = $json ? json_decode($json, true) : null;

        if (!is_array($manifest)) {
            return null;
        }

        set_transient(self::CACHE_MANIFEST, $manifest, 30 * MINUTE_IN_SECONDS);

        return $manifest;
    }

    /**
     * Resolves the GitHub asset API URL for a release entry.
     *
     * @param array<string,mixed> $release Manifest release entry.
     * @return string
     */
    private function url_asset_release($release) {
        if (empty($release['tag']) || empty($release['asset'])) {
            return '';
        }

        $tag = sanitize_text_field($release['tag']);
        $asset_name = sanitize_file_name($release['asset']);
        $cache_key = 'tp_updater_asset_' . md5($tag . '|' . $asset_name);
        $cached = get_transient($cache_key);

        if (is_string($cached) && $cached) {
            return $cached;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/tags/%s',
            rawurlencode(self::OWNER),
            rawurlencode(self::REPO),
            rawurlencode($tag)
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'headers' => $this->github_headers('application/vnd.github+json'),
            )
        );

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return '';
        }

        $release_data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($release_data['assets']) || !is_array($release_data['assets'])) {
            return '';
        }

        foreach ($release_data['assets'] as $asset) {
            if (!empty($asset['name']) && $asset_name === $asset['name'] && !empty($asset['url'])) {
                set_transient($cache_key, $asset['url'], 30 * MINUTE_IN_SECONDS);
                return $asset['url'];
            }
        }

        return '';
    }

    /**
     * Builds GitHub API headers.
     *
     * @param string $accept Accept header.
     * @return array<string,string>
     */
    private function github_headers($accept) {
        $config = self::configuracion();

        return array(
            'Authorization' => 'Bearer ' . $config['token'],
            'Accept'        => $accept,
            'User-Agent'    => 'TatiPilates-Updater/' . TP_VERSION,
        );
    }
}
