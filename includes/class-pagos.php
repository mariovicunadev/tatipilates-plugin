<?php
/**
 * Payment tracking.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles simplified monthly payment confirmations.
 */
class TP_Pagos {

    /**
     * Grace period in days after the month starts.
     */
    const DIAS_GRACIA = 15;

    /**
     * Returns the first day of a month.
     *
     * @param string|null $fecha Date string or null for current month.
     * @return string
     */
    public static function normalizar_mes($fecha = null) {
        $timestamp = $fecha ? strtotime($fecha) : current_time('timestamp');

        if (!$timestamp) {
            $timestamp = current_time('timestamp');
        }

        return gmdate('Y-m-01', $timestamp);
    }

    /**
     * Returns current month payment status for all active students.
     *
     * @param string|null $mes Month date.
     * @return array<int,object>
     */
    public static function estado_mensual($mes = null) {
        global $wpdb;

        $tabla_alumnas = $wpdb->prefix . 'tp_alumnas';
        $tabla_pagos   = $wpdb->prefix . 'tp_pagos';
        $mes           = self::normalizar_mes($mes);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id AS alumna_id, a.plan AS plan_actual, a.activa, u.display_name, u.user_email,
                    p.id AS pago_id, p.plan AS plan_pagado, p.fecha_pago, p.notas
                FROM {$tabla_alumnas} a
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                LEFT JOIN {$tabla_pagos} p ON p.alumna_id = a.id AND p.mes = %s
                WHERE a.activa = 1
                ORDER BY u.display_name ASC",
                $mes
            )
        );
    }

    /**
     * Returns whether the selected month is still inside the grace period.
     *
     * @param string|null $mes Month date.
     * @return bool
     */
    public static function mes_en_gracia($mes = null) {
        $mes    = self::normalizar_mes($mes);
        $hoy    = gmdate('Y-m-d', current_time('timestamp'));
        $limite = gmdate('Y-m-d', strtotime($mes . ' +' . (self::DIAS_GRACIA - 1) . ' days'));

        return $hoy <= $limite;
    }

    /**
     * Returns the grace period deadline for a month.
     *
     * @param string|null $mes Month date.
     * @return string
     */
    public static function fecha_limite_gracia($mes = null) {
        $mes = self::normalizar_mes($mes);

        return gmdate('Y-m-d', strtotime($mes . ' +' . (self::DIAS_GRACIA - 1) . ' days'));
    }

    /**
     * Checks payment access status for reservation rules.
     *
     * @param int         $alumna_id Student profile ID.
     * @param string|null $mes       Month date.
     * @return array<string,mixed>
     */
    public static function estado_para_reservas($alumna_id, $mes = null) {
        global $wpdb;

        $alumna = TP_Alumnas::obtener($alumna_id);
        $mes    = self::normalizar_mes($mes);
        $mes_actual = self::normalizar_mes();

        if (!$alumna) {
            return array(
                'puede_reservar' => false,
                'estado'         => 'sin_estudiante',
                'mensaje'        => 'El estudiante no existe.',
            );
        }

        if ('individual' === $alumna->plan) {
            return array(
                'puede_reservar' => false,
                'estado'         => 'individual',
                'mensaje'        => 'Tu plan no permite reservas en línea. Coordina tu clase directamente con Tatiana.',
            );
        }

        $pago_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}tp_pagos WHERE alumna_id = %d AND mes = %s",
                absint($alumna_id),
                $mes
            )
        );

        if ($pago_id) {
            return array(
                'puede_reservar' => true,
                'estado'         => 'pagado',
                'mensaje'        => '',
            );
        }

        $pago_mes_actual = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}tp_pagos WHERE alumna_id = %d AND mes = %s",
                absint($alumna_id),
                $mes_actual
            )
        );

        if ($mes > $mes_actual && $pago_mes_actual) {
            $ultimo_mes_pagado = self::ultimo_mes_pagado($alumna_id);
            $cubierto_hasta   = $ultimo_mes_pagado ? gmdate('Y-m-t', strtotime($ultimo_mes_pagado)) : gmdate('Y-m-t', strtotime($mes_actual));

            return array(
                'puede_reservar' => true,
                'estado'         => 'gracia',
                'contexto'       => 'futuro',
                'mensaje'        => 'Estas viendo ' . self::formatear_mes($mes) . '. Ese mes aun no esta registrado como pagado; tu pago esta listo hasta el ' . self::formatear_fecha($cubierto_hasta) . '.',
            );
        }

        if (self::mes_en_gracia($mes)) {
            return array(
                'puede_reservar' => true,
                'estado'         => 'gracia',
                'mensaje'        => 'Tu pago del mes esta pendiente. Puedes reservar durante el periodo de gracia, hasta el ' . self::formatear_fecha(self::fecha_limite_gracia($mes)) . '.',
            );
        }

        return array(
            'puede_reservar' => false,
            'estado'         => 'vencido',
            'mensaje'        => 'Tu pago del mes no esta confirmado.',
        );
    }

    /**
     * Returns the latest paid month for one student.
     *
     * @param int $alumna_id Student profile ID.
     * @return string|null
     */
    public static function ultimo_mes_pagado($alumna_id) {
        global $wpdb;

        $alumna_id = absint($alumna_id);

        if (!$alumna_id) {
            return null;
        }

        $mes = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(mes) FROM {$wpdb->prefix}tp_pagos WHERE alumna_id = %d",
                $alumna_id
            )
        );

        return $mes ? (string) $mes : null;
    }

    /**
     * Returns recent payment history.
     *
     * @param int $limite Number of rows.
     * @return array<int,object>
     */
    public static function historial($limite = 20) {
        global $wpdb;

        $tabla_pagos   = $wpdb->prefix . 'tp_pagos';
        $tabla_alumnas = $wpdb->prefix . 'tp_alumnas';
        $limite        = max(1, min(100, absint($limite)));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, u.display_name, u.user_email
                FROM {$tabla_pagos} p
                INNER JOIN {$tabla_alumnas} a ON a.id = p.alumna_id
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                ORDER BY p.mes DESC, p.fecha_pago DESC, p.id DESC
                LIMIT %d",
                $limite
            )
        );
    }

    /**
     * Returns recent payment history for one student.
     *
     * @param int $alumna_id Student profile ID.
     * @param int $limite    Number of rows.
     * @return array<int,object>
     */
    public static function historial_estudiante($alumna_id, $limite = 12) {
        global $wpdb;

        $tabla_pagos = $wpdb->prefix . 'tp_pagos';
        $alumna_id   = absint($alumna_id);
        $limite      = max(1, min(100, absint($limite)));

        if (!$alumna_id) {
            return array();
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$tabla_pagos}
                WHERE alumna_id = %d
                ORDER BY mes DESC, fecha_pago DESC, id DESC
                LIMIT %d",
                $alumna_id,
                $limite
            )
        );
    }

    /**
     * Returns a payment by student and month.
     *
     * @param int    $alumna_id Student profile ID.
     * @param string $mes       Month date.
     * @return object|null
     */
    public static function obtener_por_estudiante_mes($alumna_id, $mes) {
        global $wpdb;

        $alumna_id = absint($alumna_id);
        $mes       = self::normalizar_mes($mes);

        if (!$alumna_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tp_pagos WHERE alumna_id = %d AND mes = %s",
                $alumna_id,
                $mes
            )
        );
    }

    /**
     * Creates or updates a monthly payment confirmation.
     *
     * @param array<string,mixed> $datos Raw payment data.
     * @return int|bool|WP_Error
     */
    public static function registrar($datos) {
        global $wpdb;

        $validado = self::validar_datos($datos);

        if (is_wp_error($validado)) {
            return $validado;
        }

        $tabla_pagos = $wpdb->prefix . 'tp_pagos';
        $existente   = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$tabla_pagos} WHERE alumna_id = %d AND mes = %s",
                $validado['alumna_id'],
                $validado['mes']
            )
        );

        if ($existente) {
            $actualizado = $wpdb->update(
                $tabla_pagos,
                array(
                    'plan'       => $validado['plan'],
                    'fecha_pago' => $validado['fecha_pago'],
                    'notas'      => $validado['notas'],
                ),
                array('id' => $existente),
                array('%s', '%s', '%s'),
                array('%d')
            );

            if (false === $actualizado) {
                TP_Helpers::log_db_error('TP_Pagos::registrar');
                return new WP_Error('tp_pago_no_actualizado', 'No se pudo actualizar el pago.');
            }

            return true;
        }

        $insertado = $wpdb->insert(
            $tabla_pagos,
            $validado,
            array('%d', '%s', '%s', '%s', '%s')
        );

        if (false === $insertado) {
            TP_Helpers::log_db_error('TP_Pagos::registrar');
            return new WP_Error('tp_pago_no_creado', 'No se pudo registrar el pago.');
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Deletes a payment confirmation.
     *
     * @param int $pago_id Payment ID.
     * @return bool|WP_Error
     */
    public static function eliminar($pago_id) {
        global $wpdb;

        $pago_id = absint($pago_id);

        if (!$pago_id) {
            return new WP_Error('tp_pago_invalido', 'Pago invalido.');
        }

        $eliminado = $wpdb->delete(
            $wpdb->prefix . 'tp_pagos',
            array('id' => $pago_id),
            array('%d')
        );

        if (false === $eliminado) {
            TP_Helpers::log_db_error('TP_Pagos::eliminar');
            return new WP_Error('tp_pago_no_eliminado', 'No se pudo eliminar el pago.');
        }

        return true;
    }

    /**
     * Formats a MySQL date as d/m/Y.
     *
     * @param string $fecha MySQL date.
     * @return string
     */
    public static function formatear_fecha($fecha) {
        $timestamp = strtotime($fecha);

        if (!$timestamp) {
            return '';
        }

        return date_i18n('d/m/Y', $timestamp);
    }

    /**
     * Formats a month label.
     *
     * @param string $mes Month date.
     * @return string
     */
    public static function formatear_mes($mes) {
        $timestamp = strtotime($mes);

        if (!$timestamp) {
            return '';
        }

        $meses = array(
            1  => 'enero',
            2  => 'febrero',
            3  => 'marzo',
            4  => 'abril',
            5  => 'mayo',
            6  => 'junio',
            7  => 'julio',
            8  => 'agosto',
            9  => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        );

        $numero_mes = (int) gmdate('n', $timestamp);
        $anio       = gmdate('Y', $timestamp);

        return ($meses[$numero_mes] ?? '') . ' ' . $anio;
    }

    /**
     * Validates payment data.
     *
     * @param array<string,mixed> $datos Raw data.
     * @return array<string,mixed>|WP_Error
     */
    private static function validar_datos($datos) {
        $alumna_id  = isset($datos['alumna_id']) ? absint($datos['alumna_id']) : 0;
        $mes        = isset($datos['mes']) ? sanitize_text_field(wp_unslash($datos['mes'])) : '';
        $plan       = isset($datos['plan']) ? sanitize_text_field(wp_unslash($datos['plan'])) : '';
        $fecha_pago = isset($datos['fecha_pago']) ? sanitize_text_field(wp_unslash($datos['fecha_pago'])) : '';
        $notas      = isset($datos['notas']) ? sanitize_text_field(wp_unslash($datos['notas'])) : '';

        if (!$alumna_id || !TP_Alumnas::obtener($alumna_id)) {
            return new WP_Error('tp_pago_estudiante_invalido', 'Selecciona un estudiante valido.');
        }

        if (!array_key_exists($plan, TP_Alumnas::planes())) {
            return new WP_Error('tp_pago_plan_invalido', 'Selecciona un plan valido.');
        }

        if (!$mes || !strtotime($mes)) {
            return new WP_Error('tp_pago_mes_invalido', 'Selecciona un mes valido.');
        }

        if (!$fecha_pago || !strtotime($fecha_pago)) {
            return new WP_Error('tp_pago_fecha_invalida', 'Selecciona una fecha de pago valida.');
        }

        return array(
            'alumna_id'  => $alumna_id,
            'mes'        => self::normalizar_mes($mes),
            'plan'       => $plan,
            'fecha_pago' => gmdate('Y-m-d', strtotime($fecha_pago)),
            'notas'      => $notas,
        );
    }
}
