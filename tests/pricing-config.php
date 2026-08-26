<?php
/**
 * Focused pricing configuration checks.
 *
 * Run with the PHP CLI; WordPress is stubbed intentionally so no real
 * option, cache, or database is touched.
 *
 * @package TatiPilates
 */

define('ABSPATH', __DIR__ . '/');

$tp_precios_test_options = array();
$tp_precios_test_purge_calls = array();
$tp_precios_test_update_failure = false;

/**
 * Reads a test option.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option($name, $default = false) {
    global $tp_precios_test_options;

    return array_key_exists($name, $tp_precios_test_options) ? $tp_precios_test_options[$name] : $default;
}

/**
 * Writes a test option, mirroring WordPress's real behavior of returning
 * false when the new value equals the stored one.
 *
 * @param string $name  Option name.
 * @param mixed  $value Option value.
 * @return bool
 */
function update_option($name, $value) {
    global $tp_precios_test_options, $tp_precios_test_update_failure;

    if ($tp_precios_test_update_failure) {
        return false;
    }

    $changed = !array_key_exists($name, $tp_precios_test_options) || $tp_precios_test_options[$name] !== $value;
    $tp_precios_test_options[$name] = $value;

    return $changed;
}

/**
 * Minimal wp_parse_args implementation for these checks.
 *
 * @param array $args     Raw args.
 * @param array $defaults Defaults.
 * @return array
 */
function wp_parse_args($args, $defaults) {
    return array_merge($defaults, (array) $args);
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
 * Minimal absint implementation for these checks.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function absint($value) {
    return abs((int) $value);
}

/**
 * Minimal translation no-op for these checks.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function __($text, $domain = 'default') {
    return $text;
}

/**
 * Minimal home_url implementation for these checks.
 *
 * @param string $path Path.
 * @return string
 */
function home_url($path = '/') {
    return 'https://tatipilates.test' . $path;
}

/**
 * Test double for the SG Optimizer cache purge helper. Its presence lets
 * TP_Precios::guardar_configuracion() call it via function_exists().
 *
 * @param string $url URL to purge.
 * @return void
 */
function sg_cachepress_purge_cache($url) {
    global $tp_precios_test_purge_calls;

    $tp_precios_test_purge_calls[] = $url;
}

/**
 * Fails one check.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function tp_precios_test_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once dirname(__DIR__) . '/includes/class-precios.php';

$checks = array();

try {
    $checks['defaults_match_current_live_values'] = function () {
        $config = TP_Precios::configuracion();

        tp_precios_test_assert(40000 === $config['plan_2x'], 'Default de plan_2x incorrecto.');
        tp_precios_test_assert(48000 === $config['plan_3x'], 'Default de plan_3x incorrecto.');
        tp_precios_test_assert(64000 === $config['plan_4x'], 'Default de plan_4x incorrecto.');
        tp_precios_test_assert(80000 === $config['plan_5x'], 'Default de plan_5x incorrecto.');
        tp_precios_test_assert(8000 === $config['clase_individual'], 'Default de clase_individual incorrecto.');
        tp_precios_test_assert(3200 === $config['clase_mat'], 'Default de clase_mat incorrecto.');
    };

    $checks['valid_input_round_trips'] = function () use (&$tp_precios_test_options) {
        $tp_precios_test_options = array();

        $resultado = TP_Precios::guardar_configuracion(
            array_merge(
                TP_Precios::configuracion_default(),
                array('plan_2x' => '45000')
            )
        );

        tp_precios_test_assert(true === $resultado['ok'], 'El guardado valido no se reporto exitoso.');
        tp_precios_test_assert(array() === $resultado['rechazados'], 'Un guardado valido no debe rechazar campos.');
        tp_precios_test_assert(45000 === TP_Precios::obtener('plan_2x'), 'El nuevo valor de plan_2x no se guardo.');
    };

    $checks['typed_thousands_separator_normalizes'] = function () use (&$tp_precios_test_options) {
        $tp_precios_test_options = array();

        TP_Precios::guardar_configuracion(
            array_merge(
                TP_Precios::configuracion_default(),
                array(
                    'clase_mat'        => '3.200',
                    'plan_2x'          => '40,000',
                    'plan_3x'          => '48 000',
                )
            )
        );

        tp_precios_test_assert(3200 === TP_Precios::obtener('clase_mat'), 'El separador de miles tipeado a mano no se normalizo.');
        tp_precios_test_assert(40000 === TP_Precios::obtener('plan_2x'), 'El separador de miles con coma no se normalizo.');
        tp_precios_test_assert(48000 === TP_Precios::obtener('plan_3x'), 'El separador de miles con espacio no se normalizo.');
    };

    $checks['invalid_field_falls_back_without_blocking_others'] = function () use (&$tp_precios_test_options) {
        $tp_precios_test_options = array(
            TP_Precios::OPCION_CONFIG => TP_Precios::configuracion_default(),
        );

        $resultado = TP_Precios::guardar_configuracion(
            array_merge(
                TP_Precios::configuracion_default(),
                array(
                    'plan_2x' => '',
                    'plan_3x' => '55000',
                )
            )
        );

        tp_precios_test_assert(array('plan_2x') === $resultado['rechazados'], 'plan_2x vacio debio quedar rechazado.');
        tp_precios_test_assert(40000 === TP_Precios::obtener('plan_2x'), 'plan_2x vacio no conservo el valor anterior.');
        tp_precios_test_assert(55000 === TP_Precios::obtener('plan_3x'), 'plan_3x valido no se guardo pese al rechazo de otro campo.');
    };

    $checks['malformed_input_falls_back'] = function () use (&$tp_precios_test_options) {
        $tp_precios_test_options = array(
            TP_Precios::OPCION_CONFIG => TP_Precios::configuracion_default(),
        );

        $resultado = TP_Precios::guardar_configuracion(
            array_merge(
                TP_Precios::configuracion_default(),
                array(
                    'clase_individual' => '-500',
                    'clase_mat'        => 'no-es-numero',
                    'plan_2x'          => 'abc123',
                    'plan_3x'          => '1e3',
                )
            )
        );

        tp_precios_test_assert(in_array('clase_individual', $resultado['rechazados'], true), 'Un valor negativo debio quedar rechazado.');
        tp_precios_test_assert(in_array('clase_mat', $resultado['rechazados'], true), 'Texto no numerico debio quedar rechazado.');
        tp_precios_test_assert(in_array('plan_2x', $resultado['rechazados'], true), 'Texto con digitos intercalados debio quedar rechazado.');
        tp_precios_test_assert(in_array('plan_3x', $resultado['rechazados'], true), 'La notacion cientifica debio quedar rechazada.');
        tp_precios_test_assert(8000 === TP_Precios::obtener('clase_individual'), 'El valor negativo no conservo el precio anterior.');
        tp_precios_test_assert(3200 === TP_Precios::obtener('clase_mat'), 'clase_mat invalida no conservo el valor anterior.');
        tp_precios_test_assert(40000 === TP_Precios::obtener('plan_2x'), 'El texto con digitos no conservo el precio anterior.');
        tp_precios_test_assert(48000 === TP_Precios::obtener('plan_3x'), 'La notacion cientifica no conservo el precio anterior.');
    };

    $checks['storage_failure_is_reported_without_purging_cache'] = function () use (&$tp_precios_test_options, &$tp_precios_test_update_failure) {
        global $tp_precios_test_purge_calls;

        $tp_precios_test_options = array(
            TP_Precios::OPCION_CONFIG => TP_Precios::configuracion_default(),
        );
        $tp_precios_test_purge_calls = array();
        $tp_precios_test_update_failure = true;

        $config = TP_Precios::configuracion_default();
        $config['plan_2x'] = '45000';
        $resultado = TP_Precios::guardar_configuracion($config);

        $tp_precios_test_update_failure = false;

        tp_precios_test_assert(false === $resultado['ok'], 'El fallo de persistencia debio reportarse.');
        tp_precios_test_assert(40000 === TP_Precios::obtener('plan_2x'), 'El fallo de persistencia altero el valor guardado.');
        tp_precios_test_assert(array() === $tp_precios_test_purge_calls, 'No se debe purgar cache cuando falla la persistencia.');
    };

    $checks['save_purges_sg_optimizer_cache'] = function () use (&$tp_precios_test_options) {
        global $tp_precios_test_purge_calls;

        $tp_precios_test_options = array();
        $tp_precios_test_purge_calls = array();

        TP_Precios::guardar_configuracion(TP_Precios::configuracion_default());

        tp_precios_test_assert(
            in_array('https://tatipilates.test/', $tp_precios_test_purge_calls, true),
            'El guardado no disparo la purga de cache de SG Optimizer.'
        );
    };

    $checks['formatear_produces_dot_thousands_separator'] = function () {
        tp_precios_test_assert('ref. 40.000' === TP_Precios::formatear(40000), 'Formato incorrecto para 40000.');
        tp_precios_test_assert('ref. 0' === TP_Precios::formatear(0), 'Formato incorrecto para 0.');
        tp_precios_test_assert('ref. 1.234.567' === TP_Precios::formatear(1234567), 'Formato incorrecto para un valor de 7 digitos.');
    };

    $checks['clave_usd_maps_each_plan_to_its_suffixed_key'] = function () {
        tp_precios_test_assert('plan_2x_usd' === TP_Precios::clave_usd('plan_2x'), 'clave_usd no mapeo plan_2x correctamente.');
        tp_precios_test_assert('clase_mat_usd' === TP_Precios::clave_usd('clase_mat'), 'clave_usd no mapeo clase_mat correctamente.');
    };

    $checks['usd_defaults_are_zero_with_no_live_equivalent'] = function () {
        $config = TP_Precios::configuracion();

        foreach (TP_Precios::planes() as $plan) {
            tp_precios_test_assert(0 === $config[TP_Precios::clave_usd($plan)], 'Default USD de ' . $plan . ' deberia ser 0.');
        }
    };

    $checks['formatear_usd_produces_dollar_sign_no_decimals'] = function () {
        tp_precios_test_assert('$8' === TP_Precios::formatear_usd(8), 'Formato USD incorrecto para 8.');
        tp_precios_test_assert('$0' === TP_Precios::formatear_usd(0), 'Formato USD incorrecto para 0.');
        tp_precios_test_assert('$1.250' === TP_Precios::formatear_usd(1250), 'Formato USD incorrecto para un valor de 4 digitos.');
    };

    $checks['usd_field_saves_independently_of_ref_field'] = function () use (&$tp_precios_test_options) {
        $tp_precios_test_options = array(
            TP_Precios::OPCION_CONFIG => TP_Precios::configuracion_default(),
        );

        $resultado = TP_Precios::guardar_configuracion(
            array_merge(
                TP_Precios::configuracion_default(),
                array('plan_2x_usd' => '8')
            )
        );

        tp_precios_test_assert(array() === $resultado['rechazados'], 'Un valor USD valido no debe rechazarse.');
        tp_precios_test_assert(8 === TP_Precios::obtener('plan_2x_usd'), 'El precio USD de plan_2x no se guardo.');
        tp_precios_test_assert(40000 === TP_Precios::obtener('plan_2x'), 'Guardar el USD no debe alterar el precio de referencia.');
    };

    $checks['empty_usd_field_falls_back_without_blocking_ref_or_other_fields'] = function () use (&$tp_precios_test_options) {
        $config = TP_Precios::configuracion_default();
        $config['plan_2x_usd'] = 8;
        $tp_precios_test_options = array(TP_Precios::OPCION_CONFIG => $config);

        $resultado = TP_Precios::guardar_configuracion(
            array_merge(
                $config,
                array(
                    'plan_2x_usd' => '',
                    'plan_2x'     => '42000',
                )
            )
        );

        tp_precios_test_assert(array('plan_2x_usd') === $resultado['rechazados'], 'plan_2x_usd vacio debio quedar rechazado.');
        tp_precios_test_assert(8 === TP_Precios::obtener('plan_2x_usd'), 'plan_2x_usd vacio no conservo el valor anterior.');
        tp_precios_test_assert(42000 === TP_Precios::obtener('plan_2x'), 'El campo de referencia no se guardo pese al rechazo del USD.');
    };

    foreach ($checks as $name => $check) {
        $check();
        echo 'PASS ' . $name . PHP_EOL;
    }

    echo 'PASS total=' . count($checks) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
