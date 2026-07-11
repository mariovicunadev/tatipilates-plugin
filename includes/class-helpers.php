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

    const OPTION_ADMIN_EVENTS = 'tp_admin_events';

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
     * Stores a short operational event for administrators.
     *
     * @param string              $tipo    Event type.
     * @param string              $mensaje Human-readable event message.
     * @param array<string,mixed> $context Safe context without secrets.
     * @param string              $level   info|warning|error.
     * @return void
     */
    public static function registrar_evento_admin($tipo, $mensaje, $context = array(), $level = 'info') {
        $eventos = get_option(self::OPTION_ADMIN_EVENTS, array());

        if (!is_array($eventos)) {
            $eventos = array();
        }

        array_unshift(
            $eventos,
            array(
                'tipo'       => sanitize_key($tipo),
                'level'      => sanitize_key($level),
                'mensaje'    => sanitize_text_field($mensaje),
                'context'    => self::sanitizar_contexto_evento($context),
                'created_at' => gmdate('c', current_time('timestamp')),
            )
        );

        update_option(self::OPTION_ADMIN_EVENTS, array_slice($eventos, 0, 20), false);
    }

    /**
     * Stores one email failure event and mirrors it to debug logs.
     *
     * @param string              $contexto Operation context.
     * @param string              $destino  Destination email.
     * @param string              $detalle  Failure detail.
     * @param array<string,mixed> $extra    Extra safe context.
     * @return void
     */
    public static function registrar_fallo_email($contexto, $destino, $detalle = '', $extra = array()) {
        $context = array_merge(
            array(
                'contexto' => $contexto,
                'destino'  => self::enmascarar_email($destino),
            ),
            $extra
        );

        self::registrar_evento_admin(
            'email_fallido',
            sprintf('Fallo el envio de email en %s.', $contexto),
            array_merge($context, array('detalle' => $detalle)),
            'error'
        );

        tp_log(
            'Fallo el envio de email.',
            array_merge($context, array('detalle' => $detalle)),
            'error'
        );
    }

    /**
     * Returns recent admin-visible operational events.
     *
     * @param int $limit Max events.
     * @return array<int,array<string,mixed>>
     */
    public static function eventos_admin($limit = 8) {
        $eventos = get_option(self::OPTION_ADMIN_EVENTS, array());

        if (!is_array($eventos)) {
            return array();
        }

        return array_slice($eventos, 0, max(1, min(20, absint($limit))));
    }

    /**
     * Sanitizes event context.
     *
     * @param array<string,mixed> $context Raw context.
     * @return array<string,string>
     */
    private static function sanitizar_contexto_evento($context) {
        $safe = array();

        foreach ($context as $key => $value) {
            $key = sanitize_key($key);

            if ('' === $key) {
                continue;
            }

            if (is_scalar($value) || null === $value) {
                $safe[$key] = sanitize_text_field((string) $value);
            }
        }

        return $safe;
    }

    /**
     * Masks an email for persistent operational logs.
     *
     * @param string $email Email.
     * @return string
     */
    private static function enmascarar_email($email) {
        $email = sanitize_email($email);

        if (!$email || false === strpos($email, '@')) {
            return '';
        }

        list($local, $domain) = explode('@', $email, 2);

        return substr($local, 0, 2) . '***@' . $domain;
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
