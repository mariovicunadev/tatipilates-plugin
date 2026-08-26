<?php
/**
 * Canonical pricing values for the public site.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stores and retrieves the studio's pricing, the single source of truth
 * consumed by wp-admin and by the Elementor dynamic tag.
 */
class TP_Precios {

    /**
     * Option name for stored pricing.
     */
    const OPCION_CONFIG = 'tp_precios_config';

    /**
     * Canonical plan/class keys. The reference-price option for a plan is
     * stored under this bare key; its cash-USD counterpart is stored under
     * the same key with the '_usd' suffix (see clave_usd()).
     *
     * @return string[]
     */
    public static function planes() {
        return array(
            'plan_2x',
            'plan_3x',
            'plan_4x',
            'plan_5x',
            'clase_individual',
            'clase_mat',
        );
    }

    /**
     * Returns the '_usd' counterpart of a plan key, e.g. 'plan_2x' -> 'plan_2x_usd'.
     *
     * @param string $plan Base plan key.
     * @return string
     */
    public static function clave_usd($plan) {
        return $plan . '_usd';
    }

    /**
     * Default pricing. Reference values match the current live site;
     * the cash-USD counterparts have no prior equivalent and default to 0
     * until an admin sets them explicitly.
     *
     * @return array<string,int>
     */
    public static function configuracion_default() {
        $config = array(
            'plan_2x'          => 40000,
            'plan_3x'          => 48000,
            'plan_4x'          => 64000,
            'plan_5x'          => 80000,
            'clase_individual' => 8000,
            'clase_mat'        => 3200,
        );

        foreach (self::planes() as $plan) {
            $config[self::clave_usd($plan)] = 0;
        }

        return $config;
    }

    /**
     * Human-readable label per plan key, used by the admin screen (for
     * both the reference and USD fields) and by the Elementor dynamic
     * tag's "Plan" control.
     *
     * @return array<string,string>
     */
    public static function etiquetas() {
        return array(
            'plan_2x'          => __('2 veces por semana', 'tatipilates'),
            'plan_3x'          => __('3 veces por semana', 'tatipilates'),
            'plan_4x'          => __('4 veces por semana', 'tatipilates'),
            'plan_5x'          => __('5 veces por semana', 'tatipilates'),
            'clase_individual' => __('Clase individual', 'tatipilates'),
            'clase_mat'        => __('Clase de Pilates Mat', 'tatipilates'),
        );
    }

    /**
     * Returns the full pricing configuration, merged with defaults for any
     * key missing from the stored option.
     *
     * @return array<string,int>
     */
    public static function configuracion() {
        $guardada = get_option(self::OPCION_CONFIG, array());
        $guardada = is_array($guardada) ? $guardada : array();

        $config = wp_parse_args($guardada, self::configuracion_default());

        foreach ($config as $clave => $valor) {
            $config[$clave] = absint($valor);
        }

        return $config;
    }

    /**
     * Returns one price, or the full configuration when no key is given.
     *
     * @param string|null $clave Price key, e.g. 'plan_2x'.
     * @return int|array<string,int>
     */
    public static function obtener($clave = null) {
        $config = self::configuracion();

        if (null === $clave) {
            return $config;
        }

        return isset($config[$clave]) ? $config[$clave] : 0;
    }

    /**
     * Formats a stored integer value as a display price.
     *
     * The thousands separator is hardcoded (not derived from the WP
     * locale) so the value looks identical in the Elementor editor
     * preview and on the live site regardless of the admin user's
     * language setting. Prices are shown in reference units ("ref."),
     * not currency, at the client's request.
     *
     * @param int $valor Whole reference units.
     * @return string
     */
    public static function formatear($valor) {
        return 'ref. ' . number_format((int) $valor, 0, ',', '.');
    }

    /**
     * Formats a stored integer value as a cash-USD display price, e.g. "$8".
     *
     * Whole dollars only, no cents — matches how these values are entered.
     *
     * @param int $valor Whole US dollars.
     * @return string
     */
    public static function formatear_usd($valor) {
        return '$' . number_format((int) $valor, 0, ',', '.');
    }

    /**
     * Normaliza la entrada de un campo de precio.
     *
     * Acepta enteros sin formato o grupos de miles consistentes con punto,
     * coma o espacio. Cualquier signo, letra, decimal o agrupacion incompleta
     * invalida el campo para evitar conversiones silenciosas.
     *
     * @param mixed $valor Entrada recibida del formulario.
     * @return int|null Valor normalizado o null cuando la entrada es invalida.
     */
    private static function normalizar_valor($valor) {
        if (!is_scalar($valor)) {
            return null;
        }

        $entrada = trim((string) $valor);

        if (
            '' === $entrada
            || !preg_match('/^(?:[0-9]+|[0-9]{1,3}([., ])[0-9]{3}(?:\\1[0-9]{3})*)$/', $entrada)
        ) {
            return null;
        }

        $solo_digitos = str_replace(array('.', ',', ' '), '', $entrada);
        $entero = absint($solo_digitos);

        if ($entero > 999999999) {
            return null;
        }

        return $entero;
    }

    /**
     * Validates and stores pricing submitted from the admin screen.
     *
     * Each of the six keys is validated independently: an empty,
     * non-numeric, or out-of-range value for one field falls back to the
     * previously stored value for that field only, never to zero and
     * never blocking the other five from saving.
     *
     * @param array<string,mixed> $datos Raw $_POST-like data.
     * @return array{ok: bool, rechazados: string[]} Save result and the keys, if any, that were rejected.
     */
    public static function guardar_configuracion($datos) {
        $actual     = self::configuracion();
        $config     = array();
        $rechazados = array();

        foreach (self::configuracion_default() as $clave => $default) {
            $entrada  = isset($datos[$clave]) ? wp_unslash($datos[$clave]) : '';
            $normal   = self::normalizar_valor($entrada);

            if (null === $normal) {
                $config[$clave] = $actual[$clave];
                $rechazados[]   = $clave;
                continue;
            }

            $config[$clave] = $normal;
        }

        $guardado = update_option(self::OPCION_CONFIG, $config);
        $ok       = (bool) $guardado || $config === $actual;

        if ($ok && function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache(home_url('/'));
        }

        return array(
            'ok'         => $ok,
            'rechazados' => $rechazados,
        );
    }
}
