<?php
/**
 * Reservation engine.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles reservation validation and creation.
 */
class TP_Reservas {

    /**
     * Weekly class limits by plan.
     *
     * @var array<string,int>
     */
    const CLASES_POR_PLAN = array(
        '2x'         => 2,
        '3x'         => 3,
        '4x'         => 4,
        '5x'         => 5,
        'individual' => 0,
    );

    /**
     * Tries to create a reservation.
     *
     * @param int    $alumna_id  Student profile ID.
     * @param int    $horario_id Schedule ID.
     * @param string $fecha      Class date in Y-m-d.
     * @param string $creada_por          Who creates it: alumna or admin.
     * @param bool   $permitir_extra_admin Whether admin can bypass weekly plan limit.
     * @return array<string,mixed>|WP_Error
     */
    public static function intentar_reserva($alumna_id, $horario_id, $fecha, $creada_por = 'alumna', $permitir_extra_admin = false) {
        global $wpdb;

        $alumna_id  = absint($alumna_id);
        $horario_id = absint($horario_id);
        $fecha      = self::normalizar_fecha($fecha);
        $creada_por = in_array($creada_por, array('alumna', 'admin'), true) ? $creada_por : 'alumna';

        if (!$fecha) {
            return new WP_Error('tp_fecha_invalida', __('Selecciona una fecha valida.', 'tatipilates'));
        }

        $alumna  = TP_Alumnas::obtener($alumna_id);
        $horario = TP_Horarios::obtener($horario_id);

        if (!$alumna || !(int) $alumna->activa) {
            return new WP_Error('tp_alumna_inactiva', __('El estudiante no esta activo.', 'tatipilates'));
        }

        if (!$horario || !(int) $horario->activo) {
            return new WP_Error('tp_horario_no_disponible', __('El horario no esta disponible.', 'tatipilates'));
        }

        if ((int) $horario->dia_semana !== (int) gmdate('N', strtotime($fecha))) {
            return new WP_Error('tp_fecha_no_coincide', __('La fecha seleccionada no coincide con el dia del horario.', 'tatipilates'));
        }

        if ('individual' === $alumna->plan && 'alumna' === $creada_por) {
            return new WP_Error(
                'tp_plan_individual',
                __('Tu plan no permite reservas en linea. Coordina tu clase directamente con Tatiana.', 'tatipilates')
            );
        }

        $wpdb->query('START TRANSACTION');

        $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tp_horarios WHERE id = %d FOR UPDATE",
                $horario_id
            )
        );

        if (!self::hay_cupo_disponible($horario_id, $fecha, (int) $horario->cupo_maximo)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('tp_clase_llena', __('Clase llena.', 'tatipilates'));
        }

        $estado_pago = 'individual' === $alumna->plan && 'admin' === $creada_por
            ? array(
                'puede_reservar' => true,
                'estado'         => 'individual_admin',
                'mensaje'        => '',
            )
            : TP_Pagos::estado_para_reservas($alumna_id, TP_Pagos::normalizar_mes($fecha));

        if (empty($estado_pago['puede_reservar'])) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('tp_pago_no_permite_reserva', $estado_pago['mensaje']);
        }

        if (self::ya_tiene_reserva($alumna_id, $horario_id, $fecha)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('tp_reserva_duplicada', __('Ya tienes esta clase reservada.', 'tatipilates'));
        }

        $contar_para_semana = self::cuenta_para_limite_semanal($alumna, $horario);
        $tipo               = 'normal';
        $recuperacion       = null;

        if ($contar_para_semana && 'individual' !== $alumna->plan) {
            $cupo_plan = self::cupo_plan_semana($alumna_id, $fecha, $alumna->plan);

            if ($cupo_plan['completo']) {
                if ('admin' === $creada_por && $permitir_extra_admin) {
                    $tipo = 'normal';
                } else {
                    $recuperacion = self::obtener_recuperacion_pendiente($alumna_id, $fecha);

                    if (!$recuperacion) {
                        $wpdb->query('ROLLBACK');
                        return new WP_Error('tp_limite_semanal_sin_recuperacion', __('Ya usaste tus clases de esta semana y no tienes recuperaciones.', 'tatipilates'));
                    }

                    $tipo = 'recuperacion';
                }
            }
        }

        $reserva_cancelada = self::obtener_reserva_cancelada($alumna_id, $horario_id, $fecha);

        if ($reserva_cancelada) {
            if ($recuperacion) {
                $actualizada = $wpdb->update(
                    $wpdb->prefix . 'tp_reservas',
                    array(
                        'tipo'            => $tipo,
                        'recuperacion_id' => (int) $recuperacion->id,
                        'estado'          => 'reservada',
                        'creada_por'      => $creada_por,
                    ),
                    array('id' => (int) $reserva_cancelada->id),
                    array('%s', '%d', '%s', '%s'),
                    array('%d')
                );
            } else {
                $actualizada = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}tp_reservas
                        SET tipo = %s, recuperacion_id = NULL, estado = 'reservada', creada_por = %s
                        WHERE id = %d",
                        $tipo,
                        $creada_por,
                        (int) $reserva_cancelada->id
                    )
                );
            }

            if (false === $actualizada) {
                $wpdb->query('ROLLBACK');
                TP_Helpers::log_db_error('TP_Reservas::intentar_reserva');
                return new WP_Error('tp_reserva_no_reactivada', __('No se pudo reactivar la reserva.', 'tatipilates'));
            }

            $reserva_id = (int) $reserva_cancelada->id;
        } else {
            $datos_reserva = array(
                'alumna_id'  => $alumna_id,
                'horario_id' => $horario_id,
                'fecha'      => $fecha,
                'tipo'       => $tipo,
                'estado'     => 'reservada',
                'creada_por' => $creada_por,
            );
            $formatos      = array('%d', '%d', '%s', '%s', '%s', '%s');

            if ($recuperacion) {
                $datos_reserva['recuperacion_id'] = (int) $recuperacion->id;
                $formatos[]                       = '%d';
            }

            $insertado = $wpdb->insert($wpdb->prefix . 'tp_reservas', $datos_reserva, $formatos);

            if (false === $insertado) {
                $wpdb->query('ROLLBACK');
                TP_Helpers::log_db_error('TP_Reservas::intentar_reserva');
                return new WP_Error('tp_reserva_no_creada', __('No se pudo crear la reserva.', 'tatipilates'));
            }

            $reserva_id = (int) $wpdb->insert_id;
        }

        if ($recuperacion) {
            $actualizada = $wpdb->update(
                $wpdb->prefix . 'tp_recuperaciones',
                array('estado' => 'usada'),
                array('id' => (int) $recuperacion->id),
                array('%s'),
                array('%d')
            );

            if (false === $actualizada) {
                $wpdb->query('ROLLBACK');
                TP_Helpers::log_db_error('TP_Reservas::intentar_reserva');
                return new WP_Error('tp_recuperacion_no_usada', __('No se pudo usar la recuperacion.', 'tatipilates'));
            }
        }

        $wpdb->query('COMMIT');

        $mensaje = 'Reserva creada correctamente.';

        if ('gracia' === ($estado_pago['estado'] ?? '')) {
            $mensaje .= ' ' . $estado_pago['mensaje'];
        }

        return array(
            'success'         => true,
            'tipo'            => $tipo,
            'mensaje'         => $mensaje,
            'reserva_id'      => $reserva_id,
            'recuperacion_id' => $recuperacion ? (int) $recuperacion->id : null,
            'pago_estado'     => $estado_pago['estado'] ?? '',
        );
    }

    /**
     * Creates reservations for every occurrence of one schedule inside a month.
     *
     * @param int    $alumna_id  Student profile ID.
     * @param int    $horario_id Schedule ID.
     * @param string $mes        Month date or YYYY-MM.
     * @param string $creada_por Who creates it.
     * @param string $desde      Optional first date to include.
     * @return array<string,mixed>|WP_Error
     */
    public static function reservar_mes($alumna_id, $horario_id, $mes, $creada_por = 'admin', $desde = '') {
        $alumna_id  = absint($alumna_id);
        $horario_id = absint($horario_id);
        $horario    = TP_Horarios::obtener($horario_id);
        $mes        = TP_Pagos::normalizar_mes($mes);
        $desde      = $desde ? self::normalizar_fecha($desde) : '';

        if (!$horario || !(int) $horario->activo) {
            return new WP_Error('tp_horario_no_disponible', __('El horario no esta disponible.', 'tatipilates'));
        }

        $inicio = strtotime($mes);

        if (!$inicio) {
            return new WP_Error('tp_mes_invalido', __('Selecciona un mes valido.', 'tatipilates'));
        }

        $fin          = strtotime(gmdate('Y-m-t', $inicio));
        $dia_horario  = (int) $horario->dia_semana;
        $creadas      = 0;
        $omitidas     = 0;
        $errores      = array();
        $primera_fecha = '';
        $reserva_ids  = array();

        for ($fecha_ts = $inicio; $fecha_ts <= $fin; $fecha_ts = strtotime('+1 day', $fecha_ts)) {
            if ((int) gmdate('N', $fecha_ts) !== $dia_horario) {
                continue;
            }

            $fecha = gmdate('Y-m-d', $fecha_ts);

            if ($desde && $fecha < $desde) {
                continue;
            }

            if (!$primera_fecha) {
                $primera_fecha = $fecha;
            }

            $resultado = self::intentar_reserva($alumna_id, $horario_id, $fecha, $creada_por);

            if (is_wp_error($resultado)) {
                $omitidas++;
                $errores[] = TP_Pagos::formatear_fecha($fecha) . ': ' . $resultado->get_error_message();
                continue;
            }

            if (!empty($resultado['reserva_id'])) {
                $reserva_ids[] = (int) $resultado['reserva_id'];
            }

            $creadas++;
        }

        if (!$primera_fecha) {
            return new WP_Error('tp_mes_sin_horarios', __('No hay fechas para ese horario en el mes seleccionado.', 'tatipilates'));
        }

        if (!$creadas && $errores) {
            return new WP_Error('tp_mes_no_reservado', implode(' ', array_slice($errores, 0, 3)));
        }

        $mensaje = sprintf(
            __('Mes reservado: %1$d clases creadas, %2$d omitidas.', 'tatipilates'),
            $creadas,
            $omitidas
        );

        if ($errores) {
            $mensaje .= ' ' . __('No se reservaron:', 'tatipilates') . ' ' . implode(' | ', array_slice($errores, 0, 3));
        }

        return array(
            'success'       => true,
            'creadas'       => $creadas,
            'omitidas'      => $omitidas,
            'errores'       => $errores,
            'primera_fecha' => $primera_fecha,
            'mensaje'       => $mensaje,
            'reserva_ids'   => $reserva_ids,
        );
    }

    /**
     * Counts active reservations for a class/date.
     *
     * @param int    $horario_id Schedule ID.
     * @param string $fecha      Class date.
     * @return int
     */
    public static function contar_reservas_activas($horario_id, $fecha) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tp_reservas
                WHERE horario_id = %d AND fecha = %s AND estado IN ('reservada', 'asistio')",
                absint($horario_id),
                self::normalizar_fecha($fecha)
            )
        );
    }

    /**
     * Returns remaining spots for a schedule/date.
     *
     * @param int    $horario_id Schedule ID.
     * @param string $fecha      Class date.
     * @return int
     */
    public static function cupos_disponibles($horario_id, $fecha) {
        $horario = TP_Horarios::obtener($horario_id);

        if (!$horario) {
            return 0;
        }

        return max(0, (int) $horario->cupo_maximo - self::contar_reservas_activas($horario_id, $fecha));
    }

    /**
     * Returns ISO week start and end dates.
     *
     * @param string $fecha Date.
     * @return array{inicio:string,fin:string}
     */
    public static function rango_semana($fecha) {
        return TP_Helpers::rango_semana($fecha);
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
     * Builds a standard response.
     *
     * @param bool   $success Success flag.
     * @param string $tipo    Reservation type.
     * @param string $mensaje Message.
     * @return array<string,mixed>
     */
    private static function respuesta($success, $tipo, $mensaje) {
        return array(
            'success' => (bool) $success,
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
        );
    }

    /**
     * Checks class capacity.
     *
     * @param int    $horario_id Schedule ID.
     * @param string $fecha      Class date.
     * @param int    $cupo       Capacity.
     * @return bool
     */
    private static function hay_cupo_disponible($horario_id, $fecha, $cupo) {
        return self::contar_reservas_activas($horario_id, $fecha) < $cupo;
    }

    /**
     * Checks duplicate reservation.
     *
     * @param int    $alumna_id  Student profile ID.
     * @param int    $horario_id Schedule ID.
     * @param string $fecha      Class date.
     * @return bool
     */
    private static function ya_tiene_reserva($alumna_id, $horario_id, $fecha) {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}tp_reservas
                WHERE alumna_id = %d AND horario_id = %d AND fecha = %s AND estado != 'cancelada'
                LIMIT 1",
                absint($alumna_id),
                absint($horario_id),
                self::normalizar_fecha($fecha)
            )
        );
    }

    /**
     * Gets a canceled reservation that can be reused.
     *
     * @param int    $alumna_id  Student profile ID.
     * @param int    $horario_id Schedule ID.
     * @param string $fecha      Class date.
     * @return object|null
     */
    private static function obtener_reserva_cancelada($alumna_id, $horario_id, $fecha) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tp_reservas
                WHERE alumna_id = %d AND horario_id = %d AND fecha = %s AND estado = 'cancelada'
                LIMIT 1",
                absint($alumna_id),
                absint($horario_id),
                self::normalizar_fecha($fecha)
            )
        );
    }

    /**
     * Counts normal weekly reservations.
     *
     * @param int    $alumna_id Student profile ID.
     * @param string $fecha     Date inside the week.
     * @param string $plan      Student plan.
     * @return int
     */
    public static function contar_normales_semana($alumna_id, $fecha, $plan) {
        global $wpdb;

        $rango         = self::rango_semana($fecha);
        $excluir_mat_5 = '5x' === $plan ? "AND NOT (h.modalidad = 'mat')" : '';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tp_reservas r
                INNER JOIN {$wpdb->prefix}tp_horarios h ON h.id = r.horario_id
                WHERE r.alumna_id = %d
                    AND r.tipo = 'normal'
                    AND r.estado IN ('reservada', 'asistio', 'falto')
                    AND r.fecha BETWEEN %s AND %s
                    {$excluir_mat_5}",
                absint($alumna_id),
                $rango['inicio'],
                $rango['fin']
            )
        );
    }

    /**
     * Returns weekly plan usage for a student.
     *
     * @param int    $alumna_id Student profile ID.
     * @param string $fecha     Date inside the week.
     * @param string $plan      Student plan.
     * @return array{limite:int,usadas:int,restantes:int,completo:bool}
     */
    public static function cupo_plan_semana($alumna_id, $fecha, $plan) {
        $limite = TP_Helpers::clases_por_plan($plan);
        $usadas = self::contar_normales_semana($alumna_id, $fecha, $plan);

        return array(
            'limite'    => $limite,
            'usadas'    => $usadas,
            'restantes' => max(0, $limite - $usadas),
            'completo'  => $limite > 0 && $usadas >= $limite,
        );
    }

    /**
     * Checks whether a schedule should count against weekly plan limit.
     *
     * @param object $alumna  Student.
     * @param object $horario Schedule.
     * @return bool
     */
    private static function cuenta_para_limite_semanal($alumna, $horario) {
        if ('mat' === $horario->modalidad && '5x' === $alumna->plan) {
            return false;
        }

        return true;
    }

    /**
     * Gets the next usable recovery credit.
     *
     * @param int    $alumna_id Student profile ID.
     * @param string $fecha     Reservation date.
     * @return object|null
     */
    private static function obtener_recuperacion_pendiente($alumna_id, $fecha) {
        global $wpdb;

        $hoy = gmdate('Y-m-d', current_time('timestamp'));

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tp_recuperaciones
                WHERE alumna_id = %d AND estado = 'pendiente' AND fecha_limite >= %s
                ORDER BY fecha_limite ASC, id ASC
                LIMIT 1",
                absint($alumna_id),
                max($hoy, self::normalizar_fecha($fecha))
            )
        );
    }
}
