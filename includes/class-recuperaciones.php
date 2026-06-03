<?php
/**
 * Recovery credits.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles pending, used and expired recovery credits.
 */
class TP_Recuperaciones {

    /**
     * Lists recovery credits.
     *
     * @param string $estado Filter by status.
     * @param int    $limite Maximum rows to return.
     * @return array<int,object>
     */
    public static function listar($estado = 'pendiente', $limite = 100) {
        global $wpdb;

        $estado = sanitize_text_field(wp_unslash($estado));
        $limite = max(1, min(500, absint($limite)));
        $where  = '';
        $params = array();

        if ($estado && 'todas' !== $estado) {
            $where    = 'WHERE rec.estado = %s';
            $params[] = $estado;
        }

        $sql = "SELECT rec.*, u.display_name, u.user_email, r.fecha AS fecha_falta, h.hora_inicio
            FROM {$wpdb->prefix}tp_recuperaciones rec
            INNER JOIN {$wpdb->prefix}tp_alumnas a ON a.id = rec.alumna_id
            INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
            LEFT JOIN {$wpdb->prefix}tp_reservas r ON r.id = rec.reserva_origen_id
            LEFT JOIN {$wpdb->prefix}tp_horarios h ON h.id = r.horario_id
            {$where}
            ORDER BY rec.estado ASC, rec.fecha_limite ASC, rec.id ASC
            LIMIT %d";

        $params[] = $limite;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Creates a manual recovery credit.
     *
     * @param array<string,mixed> $datos Raw data.
     * @return int|WP_Error
     */
    public static function crear_manual($datos) {
        global $wpdb;

        $alumna_id    = isset($datos['alumna_id']) ? absint($datos['alumna_id']) : 0;
        $fecha_falta  = isset($datos['fecha_falta']) ? sanitize_text_field(wp_unslash($datos['fecha_falta'])) : '';
        $fecha_limite = isset($datos['fecha_limite']) ? sanitize_text_field(wp_unslash($datos['fecha_limite'])) : '';
        $motivo       = isset($datos['motivo']) ? sanitize_text_field(wp_unslash($datos['motivo'])) : '';
        $alumna       = TP_Alumnas::obtener($alumna_id);

        if (!$alumna) {
            return new WP_Error('tp_recuperacion_estudiante_invalido', 'Selecciona un estudiante valido.');
        }

        $fecha_falta = self::normalizar_fecha($fecha_falta);

        if (!$fecha_falta) {
            return new WP_Error('tp_recuperacion_fecha_invalida', 'Selecciona una fecha valida.');
        }

        $fecha_limite = $fecha_limite ? self::normalizar_fecha($fecha_limite) : TP_Helpers::fecha_vencimiento_recuperacion($fecha_falta);

        if (!$fecha_limite) {
            return new WP_Error('tp_recuperacion_limite_invalida', 'Selecciona una fecha limite valida.');
        }

        $horario = self::horario_para_fecha($fecha_falta);

        if (!$horario) {
            return new WP_Error('tp_recuperacion_sin_horario', 'No hay horarios activos para esa fecha.');
        }

        $wpdb->query('START TRANSACTION');

        $reserva_insertada = $wpdb->insert(
            $wpdb->prefix . 'tp_reservas',
            array(
                'alumna_id'  => $alumna_id,
                'horario_id' => (int) $horario->id,
                'fecha'      => $fecha_falta,
                'tipo'       => 'normal',
                'estado'     => 'falto',
                'creada_por' => 'admin',
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );

        if (false === $reserva_insertada) {
            $wpdb->query('ROLLBACK');
            TP_Helpers::log_db_error('TP_Recuperaciones::crear_manual');
            return new WP_Error('tp_recuperacion_reserva_error', 'No se pudo crear la reserva origen.');
        }

        $reserva_id = (int) $wpdb->insert_id;
        $insertado  = $wpdb->insert(
            $wpdb->prefix . 'tp_recuperaciones',
            array(
                'alumna_id'          => $alumna_id,
                'reserva_origen_id'  => $reserva_id,
                'motivo'             => $motivo,
                'fecha_limite'       => $fecha_limite,
                'estado'             => 'pendiente',
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );

        if (false === $insertado) {
            $wpdb->query('ROLLBACK');
            TP_Helpers::log_db_error('TP_Recuperaciones::crear_manual');
            return new WP_Error('tp_recuperacion_no_creada', 'No se pudo crear la recuperacion.');
        }

        $recuperacion_id = (int) $wpdb->insert_id;
        $wpdb->query('COMMIT');

        return $recuperacion_id;
    }

    /**
     * Expires pending recovery credits past due.
     *
     * @return int|false
     */
    public static function expirar_vencidas() {
        global $wpdb;

        $resultado = $wpdb->query(
            "UPDATE {$wpdb->prefix}tp_recuperaciones
            SET estado = 'expirada'
            WHERE estado = 'pendiente' AND fecha_limite < CURDATE()"
        );

        if (false === $resultado) {
            tp_log(
                'No se pudieron expirar recuperaciones vencidas.',
                array('contexto' => 'TP_Recuperaciones::expirar_vencidas'),
                'error'
            );
        }

        return $resultado;
    }

    /**
     * Deletes a recovery credit when not used.
     *
     * @param int $recuperacion_id Recovery ID.
     * @return bool|WP_Error
     */
    public static function eliminar($recuperacion_id) {
        global $wpdb;

        $recuperacion_id = absint($recuperacion_id);
        $recuperacion    = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tp_recuperaciones WHERE id = %d", $recuperacion_id)
        );

        if (!$recuperacion) {
            return new WP_Error('tp_recuperacion_no_existe', 'La recuperacion no existe.');
        }

        if ('usada' === $recuperacion->estado) {
            return new WP_Error('tp_recuperacion_usada', 'No se puede eliminar una recuperacion usada.');
        }

        $eliminado = $wpdb->delete(
            $wpdb->prefix . 'tp_recuperaciones',
            array('id' => $recuperacion_id),
            array('%d')
        );

        if (false === $eliminado) {
            TP_Helpers::log_db_error('TP_Recuperaciones::eliminar');
            return new WP_Error('tp_recuperacion_no_eliminada', 'No se pudo eliminar la recuperacion.');
        }

        return true;
    }

    /**
     * Returns days remaining until due date.
     *
     * @param string $fecha_limite Due date.
     * @return int
     */
    public static function dias_restantes($fecha_limite) {
        $hoy    = strtotime(gmdate('Y-m-d', current_time('timestamp')));
        $limite = strtotime($fecha_limite);

        return (int) floor(($limite - $hoy) / DAY_IN_SECONDS);
    }

    /**
     * Normalizes a date string.
     *
     * @param string $fecha Date.
     * @return string
     */
    private static function normalizar_fecha($fecha) {
        return TP_Helpers::normalizar_fecha($fecha);
    }

    /**
     * Finds an active schedule matching a date's weekday.
     *
     * @param string $fecha Date.
     * @return object|null
     */
    private static function horario_para_fecha($fecha) {
        global $wpdb;

        $dia_semana = (int) gmdate('N', strtotime($fecha));

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tp_horarios
                WHERE dia_semana = %d AND activo = 1
                ORDER BY hora_inicio ASC
                LIMIT 1",
                $dia_semana
            )
        );
    }
}
