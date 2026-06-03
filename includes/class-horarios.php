<?php
/**
 * Fixed schedule CRUD.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles fixed class schedules.
 */
class TP_Horarios {

    /**
     * Weekday labels using ISO weekday numbers.
     *
     * @return array<int,string>
     */
    public static function dias_semana() {
        return array(
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        );
    }

    /**
     * Returns all schedules.
     *
     * @param bool $solo_activos Whether to return only active schedules.
     * @return array<int,object>
     */
    public static function obtener_todos($solo_activos = false) {
        global $wpdb;

        $cache_key = $solo_activos ? 'tp_horarios_activos' : 'tp_horarios_todos';
        $cached    = wp_cache_get($cache_key, 'tatipilates');

        if (false !== $cached) {
            return $cached;
        }

        $tabla      = $wpdb->prefix . 'tp_horarios';
        $where      = $solo_activos ? 'WHERE activo = 1' : '';
        $resultados = $wpdb->get_results(
            "SELECT * FROM {$tabla} {$where} ORDER BY dia_semana ASC, hora_inicio ASC"
        );

        wp_cache_set($cache_key, $resultados, 'tatipilates', 10 * MINUTE_IN_SECONDS);

        return $resultados;
    }

    /**
     * Returns one schedule by ID.
     *
     * @param int $horario_id Schedule ID.
     * @return object|null
     */
    public static function obtener($horario_id) {
        global $wpdb;

        $tabla      = $wpdb->prefix . 'tp_horarios';
        $horario_id = absint($horario_id);

        if (!$horario_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tabla} WHERE id = %d", $horario_id)
        );
    }

    /**
     * Creates a schedule.
     *
     * @param array<string,mixed> $datos Schedule data.
     * @return int|WP_Error
     */
    public static function crear($datos) {
        global $wpdb;

        $validado = self::validar_datos($datos);

        if (is_wp_error($validado)) {
            return $validado;
        }

        $duplicado = self::existe_horario($validado['dia_semana'], $validado['hora_inicio']);

        if ($duplicado) {
            return new WP_Error('tp_horario_duplicado', 'Ya existe un horario configurado para ese dia y hora.');
        }

        $insertado = $wpdb->insert(
            $wpdb->prefix . 'tp_horarios',
            $validado,
            array('%d', '%s', '%s', '%d', '%d')
        );

        if (false === $insertado) {
            TP_Helpers::log_db_error('TP_Horarios::crear');
            return new WP_Error('tp_horario_no_creado', 'No se pudo crear el horario.');
        }

        self::limpiar_cache();

        return (int) $wpdb->insert_id;
    }

    /**
     * Updates a schedule.
     *
     * @param int                 $horario_id Schedule ID.
     * @param array<string,mixed> $datos      Schedule data.
     * @return bool|WP_Error
     */
    public static function actualizar($horario_id, $datos) {
        global $wpdb;

        $horario_id = absint($horario_id);
        $validado   = self::validar_datos($datos);

        if (!$horario_id) {
            return new WP_Error('tp_horario_invalido', 'Horario invalido.');
        }

        if (is_wp_error($validado)) {
            return $validado;
        }

        $duplicado = self::existe_horario($validado['dia_semana'], $validado['hora_inicio'], $horario_id);

        if ($duplicado) {
            return new WP_Error('tp_horario_duplicado', 'Ya existe un horario configurado para ese dia y hora.');
        }

        $actualizado = $wpdb->update(
            $wpdb->prefix . 'tp_horarios',
            $validado,
            array('id' => $horario_id),
            array('%d', '%s', '%s', '%d', '%d'),
            array('%d')
        );

        if (false === $actualizado) {
            TP_Helpers::log_db_error('TP_Horarios::actualizar');
            return new WP_Error('tp_horario_no_actualizado', 'No se pudo actualizar el horario.');
        }

        self::limpiar_cache();

        return true;
    }

    /**
     * Activates or deactivates a schedule without deleting it.
     *
     * @param int  $horario_id Schedule ID.
     * @param bool $activo     Active flag.
     * @return bool|WP_Error
     */
    public static function cambiar_estado($horario_id, $activo) {
        global $wpdb;

        $horario_id = absint($horario_id);

        if (!$horario_id) {
            return new WP_Error('tp_horario_invalido', 'Horario invalido.');
        }

        $wpdb->query('START TRANSACTION');

        $actualizado = $wpdb->update(
            $wpdb->prefix . 'tp_horarios',
            array('activo' => $activo ? 1 : 0),
            array('id' => $horario_id),
            array('%d'),
            array('%d')
        );

        if (false === $actualizado) {
            $wpdb->query('ROLLBACK');
            TP_Helpers::log_db_error('TP_Horarios::cambiar_estado');
            return new WP_Error('tp_horario_estado_error', 'No se pudo cambiar el estado del horario.');
        }

        if (!$activo) {
            $canceladas = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}tp_reservas
                    SET estado = 'cancelada'
                    WHERE horario_id = %d AND fecha >= %s AND estado = 'reservada'",
                    $horario_id,
                    gmdate('Y-m-d', current_time('timestamp'))
                )
            );

            if (false === $canceladas) {
                $wpdb->query('ROLLBACK');
                TP_Helpers::log_db_error('TP_Horarios::cambiar_estado');
                return new WP_Error('tp_reservas_horario_no_canceladas', 'No se pudieron cancelar las reservas futuras del horario.');
            }
        }

        $wpdb->query('COMMIT');

        self::limpiar_cache();

        return true;
    }

    /**
     * Deletes a schedule when it is not linked to reservations.
     *
     * @param int $horario_id Schedule ID.
     * @return bool|WP_Error
     */
    public static function eliminar($horario_id) {
        global $wpdb;

        $horario_id = absint($horario_id);

        if (!$horario_id) {
            return new WP_Error('tp_horario_invalido', 'Horario invalido.');
        }

        $tabla_reservas = $wpdb->prefix . 'tp_reservas';
        $reservas       = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$tabla_reservas} WHERE horario_id = %d", $horario_id)
        );

        if ($reservas > 0) {
            return new WP_Error('tp_horario_con_reservas', 'Este horario ya tiene reservas asociadas. Desactivalo en vez de eliminarlo.');
        }

        $eliminado = $wpdb->delete(
            $wpdb->prefix . 'tp_horarios',
            array('id' => $horario_id),
            array('%d')
        );

        if (false === $eliminado) {
            TP_Helpers::log_db_error('TP_Horarios::eliminar');
            return new WP_Error('tp_horario_no_eliminado', 'No se pudo eliminar el horario.');
        }

        self::limpiar_cache();

        return true;
    }

    /**
     * Formats a MySQL time value as 12-hour UI time.
     *
     * @param string $hora Time in H:i:s.
     * @return string
     */
    public static function formatear_hora($hora) {
        $timestamp = strtotime($hora);

        if (!$timestamp) {
            return '';
        }

        return strtolower(date_i18n('g:i a', $timestamp));
    }

    /**
     * Sanitizes and validates schedule input.
     *
     * @param array<string,mixed> $datos Raw data.
     * @return array<string,mixed>|WP_Error
     */
    private static function validar_datos($datos) {
        $dia_semana  = isset($datos['dia_semana']) ? absint($datos['dia_semana']) : 0;
        $hora_inicio = isset($datos['hora_inicio']) ? sanitize_text_field(wp_unslash($datos['hora_inicio'])) : '';
        $cupo_maximo = isset($datos['cupo_maximo']) ? absint($datos['cupo_maximo']) : 0;
        $activo      = isset($datos['activo']) ? absint($datos['activo']) : 0;

        if (!array_key_exists($dia_semana, self::dias_semana())) {
            return new WP_Error('tp_dia_invalido', 'Selecciona un dia valido.');
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora_inicio)) {
            return new WP_Error('tp_hora_invalida', 'Ingresa una hora valida.');
        }

        if ($cupo_maximo < 1 || $cupo_maximo > 30) {
            return new WP_Error('tp_cupo_invalido', 'El cupo debe estar entre 1 y 30.');
        }

        return array(
            'dia_semana'  => $dia_semana,
            'hora_inicio' => $hora_inicio . ':00',
            'modalidad'   => 'reformer',
            'cupo_maximo' => $cupo_maximo,
            'activo'      => $activo ? 1 : 0,
        );
    }

    /**
     * Checks whether a schedule already exists for the same weekday and time.
     *
     * @param int    $dia_semana Weekday number.
     * @param string $hora_inicio Time in H:i:s.
     * @param int    $excluir_id Schedule ID to ignore during edits.
     * @return bool
     */
    private static function existe_horario($dia_semana, $hora_inicio, $excluir_id = 0) {
        global $wpdb;

        $tabla      = $wpdb->prefix . 'tp_horarios';
        $excluir_id = absint($excluir_id);

        if ($excluir_id) {
            $existente = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$tabla} WHERE dia_semana = %d AND hora_inicio = %s AND id <> %d LIMIT 1",
                    absint($dia_semana),
                    $hora_inicio,
                    $excluir_id
                )
            );
        } else {
            $existente = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$tabla} WHERE dia_semana = %d AND hora_inicio = %s LIMIT 1",
                    absint($dia_semana),
                    $hora_inicio
                )
            );
        }

        return (bool) $existente;
    }

    /**
     * Clears cached schedule lists.
     *
     * @return void
     */
    private static function limpiar_cache() {
        wp_cache_delete('tp_horarios_activos', 'tatipilates');
        wp_cache_delete('tp_horarios_todos', 'tatipilates');
    }
}
