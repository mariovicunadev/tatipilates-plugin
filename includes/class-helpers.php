<?php
/**
 * Shared plugin helpers.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logs plugin events when WordPress debug mode is enabled.
 *
 * @param string              $message Log message.
 * @param array<string,mixed> $context Additional structured context.
 * @param string              $level   Log level.
 * @return void
 */
function tp_log($message, $context = array(), $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[TatiPilates][' . $level . '] ' . $message . ' ' . wp_json_encode($context));
    }
}

/**
 * Miscellaneous helpers shared across plugin classes.
 */
class TP_Helpers {

    /**
     * Normalizes a date string to Y-m-d.
     *
     * @param string $fecha   Raw date.
     * @param mixed  $default Value returned when the date is invalid.
     * @return string|null
     */
    public static function normalizar_fecha($fecha, $default = '') {
        $fecha = sanitize_text_field(wp_unslash($fecha));

        if ('' === $fecha) {
            return $default;
        }

        $timestamp = strtotime($fecha);

        if (!$timestamp) {
            return $default;
        }

        return gmdate('Y-m-d', $timestamp);
    }

    /**
     * Calculates the expiration date for a recovery credit.
     *
     * @param string $fecha_origen Source date.
     * @return string
     */
    public static function fecha_vencimiento_recuperacion($fecha_origen) {
        $fecha = self::normalizar_fecha($fecha_origen);

        return $fecha ? gmdate('Y-m-d', strtotime($fecha . ' +3 months')) : '';
    }

    /**
     * Returns ISO week start and end dates.
     *
     * @param string $fecha Date inside the week.
     * @return array{inicio:string,fin:string}
     */
    public static function rango_semana($fecha) {
        $fecha     = self::normalizar_fecha($fecha, gmdate('Y-m-d', current_time('timestamp')));
        $timestamp = strtotime($fecha);
        $dia_iso   = (int) gmdate('N', $timestamp);
        $inicio    = gmdate('Y-m-d', strtotime('-' . ($dia_iso - 1) . ' days', $timestamp));

        return array(
            'inicio' => $inicio,
            'fin'    => gmdate('Y-m-d', strtotime($inicio . ' +6 days')),
        );
    }

    /**
     * Moves a week start date by a number of weeks.
     *
     * @param string $inicio Week start date.
     * @param int    $offset Week offset.
     * @return string
     */
    public static function mover_semanas($inicio, $offset) {
        $inicio = self::normalizar_fecha($inicio, gmdate('Y-m-d', current_time('timestamp')));

        return gmdate('Y-m-d', strtotime($inicio . ' +' . ((int) $offset * 7) . ' days'));
    }

    /**
     * Returns the weekly class limit for a plan.
     *
     * @param string $plan Plan slug.
     * @return int
     */
    public static function clases_por_plan($plan) {
        return TP_Reservas::CLASES_POR_PLAN[$plan] ?? 0;
    }

    /**
     * Formats reservation date/time details from a reservation row.
     *
     * @param object $reserva Reservation row.
     * @return array{fecha:string,hora:string}
     */
    public static function detalle_reserva($reserva) {
        $hora = '';

        if (!empty($reserva->hora_inicio)) {
            $hora = TP_Horarios::formatear_hora($reserva->hora_inicio);
        } elseif (!empty($reserva->horario_id)) {
            $horario = TP_Horarios::obtener((int) $reserva->horario_id);
            $hora    = $horario ? TP_Horarios::formatear_hora($horario->hora_inicio) : '';
        }

        return array(
            'fecha' => TP_Pagos::formatear_fecha($reserva->fecha),
            'hora'  => $hora ?: __('hora pendiente', 'tatipilates'),
        );
    }

    /**
     * Builds a plain text label for one reservation in agenda exports.
     *
     * @param object $reserva Reservation row.
     * @return string
     */
    public static function etiqueta_reserva_agenda($reserva) {
        $marcas = array();

        if ('recuperacion' === $reserva->tipo) {
            $marcas[] = 'recuperacion';
        }

        if ('asistio' === $reserva->estado) {
            $marcas[] = 'asistio';
        } elseif ('falto' === $reserva->estado) {
            $marcas[] = 'falto';
        }

        return $reserva->display_name . ($marcas ? ' (' . implode(', ', $marcas) . ')' : '');
    }

    /**
     * Logs the latest database error when WordPress debug logging is enabled.
     *
     * @param string $contexto Context where the DB operation failed.
     * @return void
     */
    public static function log_db_error($contexto) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            global $wpdb;

            if ($wpdb->last_error) {
                error_log('[TatiPilates] Error DB en ' . $contexto . ': ' . $wpdb->last_error);
            }
        }
    }
}
