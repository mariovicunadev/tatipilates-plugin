<?php
/**
 * Focused private updater token configuration checks.
 *
 * Run with the PHP CLI; WordPress is stubbed intentionally so no real token or
 * option is read or modified.
 *
 * @package TatiPilates
 */

define('ABSPATH', __DIR__ . '/');
define('TP_VERSION', 'test');

$tp_updater_test_options = array();
$tp_updater_test_env = getenv('TP_GITHUB_TOKEN');

/**
 * Reads a test option.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option($name, $default = false) {
    global $tp_updater_test_options;

    return array_key_exists($name, $tp_updater_test_options) ? $tp_updater_test_options[$name] : $default;
}

/**
 * Writes a test option.
 *
 * @param string $name     Option name.
 * @param mixed  $value    Option value.
 * @param bool   $autoload Ignored autoload flag.
 * @return bool
 */
function update_option($name, $value, $autoload = true) {
    global $tp_updater_test_options;

    $changed = !array_key_exists($name, $tp_updater_test_options) || $tp_updater_test_options[$name] !== $value;
    $tp_updater_test_options[$name] = $value;

    return $changed;
}

/**
 * Deletes a transient in the isolated test runtime.
 *
 * @param string $name Transient name.
 * @return bool
 */
function delete_transient($name) {
    return true;
}

/**
 * Registers a no-op filter in the isolated test runtime.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted arguments.
 * @return bool
 */
function add_filter($hook, $callback, $priority = 10, $args = 1) {
    return true;
}

/**
 * Registers a no-op action in the isolated test runtime.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted arguments.
 * @return bool
 */
function add_action($hook, $callback, $priority = 10, $args = 1) {
    return true;
}

/**
 * Minimal sanitize_key implementation for these checks.
 *
 * @param mixed $key Raw key.
 * @return string
 */
function sanitize_key($key) {
    return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $key));
}

/**
 * Minimal wp_unslash implementation for these checks.
 *
 * @param mixed $value Raw value.
 * @return mixed
 */
function wp_unslash($value) {
    return $value;
}

/**
 * Fails one check.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function tp_updater_test_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once dirname(__DIR__) . '/includes/class-updater.php';

$asset_url = 'https://api.github.com/repos/mariovicunadev/tatipilates-plugin/releases/assets/123';
$updater = new TP_Updater();
$checks = array();

try {
    putenv('TP_GITHUB_TOKEN');

    $tp_updater_test_options[TP_Updater::OPTION_CONFIG] = array(
        'enabled' => 1,
        'channel' => 'staging',
        'token'   => 'legacy-test-token',
    );

    $checks['legacy_fallback_is_status_only'] = function () use (&$tp_updater_test_options) {
        $config = TP_Updater::configuracion();

        tp_updater_test_assert('legacy' === $config['token_source'], 'No se detecto el fallback legacy.');
        tp_updater_test_assert(true === $config['token_configured'], 'El estado legacy debe indicar token configurado.');
        tp_updater_test_assert(!array_key_exists('token', $config), 'La configuracion publica expuso el token.');
        tp_updater_test_assert('legacy-test-token' === $tp_updater_test_options[TP_Updater::OPTION_CONFIG]['token'], 'El fallback legacy se limpio antes de configurar el servidor.');
    };

    $checks['legacy_fallback_authorizes_download'] = function () use ($updater, $asset_url) {
        $args = $updater->autorizar_descarga_github(array(), $asset_url);

        tp_updater_test_assert(isset($args['headers']['Authorization']), 'El fallback legacy no autorizo la descarga.');
        tp_updater_test_assert('Bearer legacy-test-token' === $args['headers']['Authorization'], 'Se uso un token legacy inesperado.');
    };

    $checks['settings_post_cannot_replace_or_clear_token'] = function () use (&$tp_updater_test_options) {
        TP_Updater::guardar_configuracion(
            array(
                'enabled'     => '1',
                'channel'     => 'stable',
                'token'       => 'posted-token-must-be-ignored',
                'clear_token' => '1',
            )
        );

        $stored = $tp_updater_test_options[TP_Updater::OPTION_CONFIG];
        tp_updater_test_assert('legacy-test-token' === $stored['token'], 'El formulario modifico el token legacy.');
        tp_updater_test_assert('stable' === $stored['channel'], 'El canal no se guardo.');
    };

    $checks['missing_token_does_not_authorize'] = function () use (&$tp_updater_test_options, $updater, $asset_url) {
        unset($tp_updater_test_options[TP_Updater::OPTION_CONFIG]['token']);
        $config = TP_Updater::configuracion();
        $args = $updater->autorizar_descarga_github(array(), $asset_url);

        tp_updater_test_assert('none' === $config['token_source'], 'El estado sin token es incorrecto.');
        tp_updater_test_assert(false === $config['token_configured'], 'El estado sin token debe ser falso.');
        tp_updater_test_assert(empty($args['headers']['Authorization']), 'Se envio autorizacion sin token.');
    };

    $checks['environment_token_cleans_legacy_copy'] = function () use (&$tp_updater_test_options, $updater, $asset_url) {
        $tp_updater_test_options[TP_Updater::OPTION_CONFIG] = array(
            'enabled' => 1,
            'channel' => 'staging',
            'token'   => 'legacy-to-remove',
        );
        putenv('TP_GITHUB_TOKEN=environment-test-token');

        $config = TP_Updater::configuracion();
        $stored = $tp_updater_test_options[TP_Updater::OPTION_CONFIG];
        $args = $updater->autorizar_descarga_github(array(), $asset_url);

        tp_updater_test_assert('server' === $config['token_source'], 'No se detecto la variable de entorno.');
        tp_updater_test_assert(!array_key_exists('token', $stored), 'No se elimino la copia legacy.');
        tp_updater_test_assert(1 === $stored['enabled'] && 'staging' === $stored['channel'], 'La limpieza altero la configuracion operativa.');
        tp_updater_test_assert('Bearer environment-test-token' === $args['headers']['Authorization'], 'No se uso el token de entorno.');
    };

    $checks['constant_precedes_environment'] = function () use (&$tp_updater_test_options, $updater, $asset_url) {
        $tp_updater_test_options[TP_Updater::OPTION_CONFIG]['token'] = 'legacy-second-copy';
        define('TP_GITHUB_TOKEN', 'constant-test-token');

        $config = TP_Updater::configuracion();
        $args = $updater->autorizar_descarga_github(array(), $asset_url);

        tp_updater_test_assert('server' === $config['token_source'], 'La constante no se detecto como fuente de servidor.');
        tp_updater_test_assert('Bearer constant-test-token' === $args['headers']['Authorization'], 'La constante no tuvo precedencia sobre el entorno.');
        tp_updater_test_assert(!array_key_exists('token', $tp_updater_test_options[TP_Updater::OPTION_CONFIG]), 'La constante no limpio la copia legacy.');
    };

    foreach ($checks as $name => $check) {
        $check();
        echo 'PASS ' . $name . PHP_EOL;
    }

    echo 'PASS total=' . count($checks) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (false === $tp_updater_test_env) {
        putenv('TP_GITHUB_TOKEN');
    } else {
        putenv('TP_GITHUB_TOKEN=' . $tp_updater_test_env);
    }
}
