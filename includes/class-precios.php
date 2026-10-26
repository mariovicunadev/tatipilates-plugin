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
     * Values are canonical numeric strings (see normalizar_guardado()) so
     * the decimals typed by the admin survive exactly as entered.
     *
     * @return array<string,string>
     */
    public static function configuracion_default() {
        $config = array(
            'plan_2x'          => '40000',
            'plan_3x'          => '48000',
            'plan_4x'          => '64000',
            'plan_5x'          => '80000',
            'clase_individual' => '8000',
            'clase_mat'        => '3200',
        );

        foreach (self::planes() as $plan) {
            $config[self::clave_usd($plan)] = '0';
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
     * @return array<string,string>
     */
    public static function configuracion() {
        $guardada = get_option(self::OPCION_CONFIG, array());
        $guardada = is_array($guardada) ? $guardada : array();

        $config = wp_parse_args($guardada, self::configuracion_default());

        foreach ($config as $clave => $valor) {
            $config[$clave] = self::normalizar_guardado($valor);
        }

        return $config;
    }

    /**
     * Returns one price, or the full configuration when no key is given.
     *
     * @param string|null $clave Price key, e.g. 'plan_2x'.
     * @return string|array<string,string>
     */
    public static function obtener($clave = null) {
        $config = self::configuracion();

        if (null === $clave) {
            return $config;
        }

        return isset($config[$clave]) ? $config[$clave] : '0';
    }

    /**
     * Converts a stored value to its canonical string form: digits, and
     * optionally a dot followed by the decimals exactly as typed
     * ("40000", "8.5", "8.50", "1.2345").
     *
     * Options saved before decimals existed hold integers; they are read
     * as-is. Anything else non-numeric reads as "0".
     *
     * @param mixed $valor Stored value.
     * @return string
     */
    private static function normalizar_guardado($valor) {
        if (is_int($valor)) {
            return (string) abs($valor);
        }

        if (is_float($valor)) {
            return rtrim(rtrim(sprintf('%.10F', abs($valor)), '0'), '.');
        }

        if (is_string($valor) && preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $valor)) {
            return $valor;
        }

        return '0';
    }

    /**
     * Formats a stored value with dot thousands and comma decimals.
     *
     * The separators are hardcoded (not derived from the WP locale) so the
     * value looks identical in the Elementor editor preview, on the live
     * site and in the admin inputs regardless of the user's language
     * setting. Decimals are shown exactly as they were typed, with no
     * rounding or padding ("8,5", "8,50", "1,2345").
     *
     * @param string $valor Stored value.
     * @return string
     */
    public static function formatear_numero($valor) {
        $partes = explode('.', self::normalizar_guardado($valor), 2);
        $texto  = number_format((int) $partes[0], 0, ',', '.');

        if (isset($partes[1])) {
            $texto .= ',' . $partes[1];
        }

        return $texto;
    }

    /**
     * Formats a stored value as a display price.
     *
     * Prices are shown in reference units ("ref."), not currency, at the
     * client's request.
     *
     * @param string $valor Reference units.
     * @return string
     */
    public static function formatear($valor) {
        return 'ref. ' . self::formatear_numero($valor);
    }

    /**
     * Formats a stored value as a cash-USD display price, e.g. "$8" or "$8,50".
     *
     * @param string $valor US dollars.
     * @return string
     */
    public static function formatear_usd($valor) {
        return '$' . self::formatear_numero($valor);
    }

    /**
     * Normaliza la entrada de un campo de precio.
     *
     * Acepta enteros sin formato, grupos de miles consistentes con punto,
     * coma o espacio, y cualquier cantidad de decimales, que se conservan tal
     * cual se tipearon. Si la entrada usa punto y coma, el ultimo es el
     * separador decimal ("1.234,567" o "1,234.567"). Si usa un solo separador
     * una unica vez seguido de exactamente tres digitos y la parte entera no
     * es 0, se interpreta como miles ("3.200" = 3200); en cualquier otro caso
     * es decimal ("8,5", "8,50", "8,5555", "0,555"). Cualquier signo, letra o
     * agrupacion incompleta invalida el campo para evitar conversiones
     * silenciosas.
     *
     * @param mixed $valor Entrada recibida del formulario.
     * @return string|null Valor canonico ("1234.50") o null cuando la entrada es invalida.
     */
    private static function normalizar_valor($valor) {
        if (!is_scalar($valor)) {
            return null;
        }

        $entrada = trim((string) $valor);

        if ('' === $entrada) {
            return null;
        }

        $miles = '(?:[0-9]+|[0-9]{1,3}(?:%1$s[0-9]{3})+)';

        if (preg_match('/^(?:[0-9]+|[1-9][0-9]{0,2}([., ])[0-9]{3}(?:\1[0-9]{3})*)$/', $entrada)) {
            // Solo entero, con o sin separador de miles.
            $entero    = str_replace(array('.', ',', ' '), '', $entrada);
            $decimales = '';
        } elseif (preg_match('/^(' . sprintf($miles, '[ .]') . '),([0-9]+)$/', $entrada, $partes)
            && self::grupos_consistentes($partes[1])
        ) {
            // Coma decimal: "8,5", "1.234,50", "1 234,5555".
            $entero    = str_replace(array('.', ' '), '', $partes[1]);
            $decimales = $partes[2];
        } elseif (preg_match('/^(' . sprintf($miles, '[ ,]') . ')\.([0-9]+)$/', $entrada, $partes)
            && self::grupos_consistentes($partes[1])
        ) {
            // Punto decimal: "8.5", "1,234.50", "1 234.5555".
            $entero    = str_replace(array(',', ' '), '', $partes[1]);
            $decimales = $partes[2];
        } else {
            return null;
        }

        $entero = ltrim($entero, '0');
        $entero = '' === $entero ? '0' : $entero;

        if (strlen($entero) > 9) {
            return null;
        }

        return '' === $decimales ? $entero : $entero . '.' . $decimales;
    }

    /**
     * Verifica que la parte entera use un unico tipo de separador de miles.
     *
     * @param string $entero Parte entera tal como se tipeo.
     * @return bool
     */
    private static function grupos_consistentes($entero) {
        preg_match_all('/[., ]/', $entero, $separadores);

        return count(array_unique($separadores[0])) <= 1;
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
