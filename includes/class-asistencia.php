<?php
/**
 * Attendance tracking.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles reservation attendance status.
 */
class TP_Asistencia {

    /**
     * Valid attendance statuses managed from admin.
     *
     * @var array<int,string>
     */
    const ESTADOS = array('reservada', 'asistio', 'falto');

    /**
     * Returns reservations grouped by day and schedule for a week.
     *
     * @param string|null $fecha Date inside the week.
     * @return array<string,mixed>
     */
    public static function semana($fecha = null) {
        global $wpdb;

        $fecha = $fecha ? sanitize_text_field(wp_unslash($fecha)) : gmdate('Y-m-d', current_time('timestamp'));
        $rango = TP_Reservas::rango_semana($fecha);

        $reservas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, h.dia_semana, h.hora_inicio, h.cupo_maximo, u.display_name, u.user_email
                FROM {$wpdb->prefix}tp_reservas r
                INNER JOIN {$wpdb->prefix}tp_horarios h ON h.id = r.horario_id
                INNER JOIN {$wpdb->prefix}tp_alumnas a ON a.id = r.alumna_id
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                WHERE r.fecha BETWEEN %s AND %s
                    AND r.estado != 'cancelada'
                ORDER BY r.fecha ASC, h.hora_inicio ASC, u.display_name ASC",
                $rango['inicio'],
                $rango['fin']
            )
        );

        $grupos   = array();
        $horarios = TP_Horarios::obtener_todos(true);

        for ($i = 0; $i < 7; $i++) {
            $fecha_dia = gmdate('Y-m-d', strtotime($rango['inicio'] . " +{$i} days"));
            $dia_iso   = (int) gmdate('N', strtotime($fecha_dia));

            foreach ($horarios as $horario) {
                if ((int) $horario->dia_semana !== $dia_iso) {
                    continue;
                }

                $clave = $horario->id . '|' . $fecha_dia;

                if (!isset($grupos[$fecha_dia])) {
                    $grupos[$fecha_dia] = array();
                }

                $grupos[$fecha_dia][$clave] = array(
                    'horario_id'  => (int) $horario->id,
                    'fecha'       => $fecha_dia,
                    'hora_inicio' => $horario->hora_inicio,
                    'cupo_maximo' => (int) $horario->cupo_maximo,
                    'reservas'    => array(),
                );
            }
        }

        foreach ($reservas as $reserva) {
            $fecha_clase = $reserva->fecha;
            $clave       = $reserva->horario_id . '|' . $reserva->fecha;

            if (!isset($grupos[$fecha_clase])) {
                $grupos[$fecha_clase] = array();
            }

            if (!isset($grupos[$fecha_clase][$clave])) {
                $grupos[$fecha_clase][$clave] = array(
                    'horario_id'  => (int) $reserva->horario_id,
                    'fecha'       => $fecha_clase,
                    'hora_inicio' => $reserva->hora_inicio,
                    'cupo_maximo' => (int) $reserva->cupo_maximo,
                    'reservas'    => array(),
                );
            }

            $grupos[$fecha_clase][$clave]['reservas'][] = $reserva;
        }

        return array(
            'inicio' => $rango['inicio'],
            'fin'    => $rango['fin'],
            'grupos' => $grupos,
        );
    }

    /**
     * Marks reservation attendance and creates recovery when needed.
     *
     * @param int    $reserva_id Reservation ID.
     * @param string $estado     New state.
     * @param string $motivo     Optional absence reason.
     * @return bool|WP_Error
     */
    public static function marcar($reserva_id, $estado, $motivo = '') {
        global $wpdb;

        $reserva_id = absint($reserva_id);
        $estado     = sanitize_text_field(wp_unslash($estado));
        $motivo     = sanitize_text_field(wp_unslash($motivo));

        if (!$reserva_id) {
            return new WP_Error('tp_reserva_invalida', 'Reserva invalida.');
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            return new WP_Error('tp_estado_asistencia_invalido', 'Estado invalido.');
        }

        $reserva = self::obtener_reserva($reserva_id);

        if (!$reserva) {
            return new WP_Error('tp_reserva_no_existe', 'La reserva no existe.');
        }

        $wpdb->query('START TRANSACTION');

        $actualizada = $wpdb->update(
            $wpdb->prefix . 'tp_reservas',
            array('estado' => $estado),
            array('id' => $reserva_id),
            array('%s'),
            array('%d')
        );

        if (false === $actualizada) {
            $wpdb->query('ROLLBACK');
            TP_Helpers::log_db_error('TP_Asistencia::marcar');
            return new WP_Error('tp_asistencia_no_actualizada', 'No se pudo actualizar la asistencia.');
        }

        if ('falto' === $estado && 'reservada' === $reserva->estado) {
            $resultado = self::crear_recuperacion($reserva, $motivo);

            if (is_wp_error($resultado)) {
                $wpdb->query('ROLLBACK');
                return $resultado;
            }
        }

        $wpdb->query('COMMIT');

        return true;
    }

    /**
     * Gets one reservation.
     *
     * @param int $reserva_id Reservation ID.
     * @return object|null
     */
    public static function obtener_reserva($reserva_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tp_reservas WHERE id = %d",
                absint($reserva_id)
            )
        );
    }

    /**
     * Creates a recovery credit if one does not exist for the source reservation.
     *
     * @param object $reserva Source reservation.
     * @param string $motivo  Optional reason.
     * @return int|WP_Error
     */
    private static function crear_recuperacion($reserva, $motivo = '') {
        global $wpdb;

        $existente = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}tp_recuperaciones WHERE reserva_origen_id = %d LIMIT 1",
                (int) $reserva->id
            )
        );

        if ($existente) {
            return $existente;
        }

        $fecha_limite = TP_Helpers::fecha_vencimiento_recuperacion($reserva->fecha);
        $insertado    = $wpdb->insert(
            $wpdb->prefix . 'tp_recuperaciones',
            array(
                'alumna_id'          => (int) $reserva->alumna_id,
                'reserva_origen_id'  => (int) $reserva->id,
                'motivo'             => $motivo,
                'fecha_limite'       => $fecha_limite,
                'estado'             => 'pendiente',
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );

        if (false === $insertado) {
            TP_Helpers::log_db_error('TP_Asistencia::crear_recuperacion');
            return new WP_Error('tp_recuperacion_no_creada', 'No se pudo crear la recuperacion.');
        }

        return (int) $wpdb->insert_id;
    }
}
