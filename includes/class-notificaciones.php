<?php
/**
 * Internal notifications for admin and students.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stores and retrieves operational notifications.
 */
class TP_Notificaciones {

    /**
     * Option name for reminder settings.
     */
    const OPCION_CONFIG = 'tp_notificaciones_config';

    /**
     * Returns notification reminder configuration.
     *
     * @return array<string,mixed>
     */
    public static function configuracion() {
        $guardada = get_option(self::OPCION_CONFIG, array());
        $guardada = is_array($guardada) ? $guardada : array();

        return wp_parse_args($guardada, self::configuracion_default());
    }

    /**
     * Stores notification reminder configuration.
     *
     * @param array<string,mixed> $datos Raw settings.
     * @return bool
     */
    public static function guardar_configuracion($datos) {
        $defaults = self::configuracion_default();
        $config = array(
            'clase_proxima_activa'            => !empty($datos['clase_proxima_activa']) ? 1 : 0,
            'recuperacion_por_vencer_activa' => !empty($datos['recuperacion_por_vencer_activa']) ? 1 : 0,
            'recuperacion_dias'              => isset($datos['recuperacion_dias']) ? max(1, min(30, absint($datos['recuperacion_dias']))) : $defaults['recuperacion_dias'],
            'pago_pendiente_activa'          => !empty($datos['pago_pendiente_activa']) ? 1 : 0,
            'pago_vencido_activa'            => !empty($datos['pago_vencido_activa']) ? 1 : 0,
        );

        foreach (self::campos_texto_configuracion() as $campo) {
            $texto = isset($datos[$campo]) ? sanitize_textarea_field(wp_unslash($datos[$campo])) : '';
            $config[$campo] = $texto ? $texto : $defaults[$campo];
        }

        return update_option(self::OPCION_CONFIG, $config);
    }

    /**
     * Creates a notification.
     *
     * @param array<string,mixed> $datos Notification data.
     * @return int|WP_Error
     */
    public static function crear($datos) {
        global $wpdb;

        $alumna_id = isset($datos['alumna_id']) ? absint($datos['alumna_id']) : 0;
        $tipo      = isset($datos['tipo']) ? sanitize_key($datos['tipo']) : 'general';
        $referencia = isset($datos['referencia']) ? sanitize_text_field($datos['referencia']) : '';
        $titulo    = isset($datos['titulo']) ? sanitize_text_field($datos['titulo']) : '';
        $mensaje   = isset($datos['mensaje']) ? wp_kses_post($datos['mensaje']) : '';

        if (!$titulo || !$mensaje) {
            return new WP_Error('tp_notificacion_invalida', __('La notificacion necesita titulo y mensaje.', 'tatipilates'));
        }

        if ($referencia && self::existe_referencia($tipo, $referencia)) {
            return null;
        }

        $insertado = $wpdb->insert(
            self::tabla(),
            array(
                'alumna_id'         => $alumna_id ?: null,
                'tipo'              => $tipo,
                'referencia'        => $referencia ?: null,
                'titulo'            => $titulo,
                'mensaje'           => $mensaje,
                'url_accion'        => isset($datos['url_accion']) ? esc_url_raw($datos['url_accion']) : '',
                'canal'             => isset($datos['canal']) ? sanitize_key($datos['canal']) : 'interno',
                'visible_admin'     => isset($datos['visible_admin']) ? (int) (bool) $datos['visible_admin'] : 1,
                'visible_alumna'    => isset($datos['visible_alumna']) ? (int) (bool) $datos['visible_alumna'] : 1,
                'visto_admin'       => 0,
                'visto_alumna'      => 0,
                'eliminado_admin'   => 0,
                'eliminado_alumna'  => 0,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d')
        );

        if (false === $insertado) {
            TP_Helpers::log_db_error('TP_Notificaciones::crear');
            return new WP_Error('tp_notificacion_no_creada', __('No se pudo crear la notificacion.', 'tatipilates'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Lists admin notifications.
     *
     * @param array<string,mixed> $args Query arguments.
     * @return array<int,object>
     */
    public static function listar_admin($args = array()) {
        global $wpdb;

        $limit = isset($args['limit']) ? max(1, min(100, absint($args['limit']))) : 30;
        $solo_pendientes = !empty($args['pendientes']);

        $where = 'n.visible_admin = 1 AND n.eliminado_admin = 0';

        if ($solo_pendientes) {
            $where .= ' AND n.visto_admin = 0';
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT n.*, u.display_name, u.user_email
                FROM " . self::tabla() . " n
                LEFT JOIN {$wpdb->prefix}tp_alumnas a ON a.id = n.alumna_id
                LEFT JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                WHERE {$where}
                ORDER BY n.created_at DESC, n.id DESC
                LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Lists student notifications.
     *
     * @param int                 $alumna_id Student profile ID.
     * @param array<string,mixed> $args Query arguments.
     * @return array<int,object>
     */
    public static function listar_alumna($alumna_id, $args = array()) {
        global $wpdb;

        $limit = isset($args['limit']) ? max(1, min(100, absint($args['limit']))) : 30;
        $solo_pendientes = !empty($args['pendientes']);
        $where = 'alumna_id = %d AND visible_alumna = 1 AND eliminado_alumna = 0';

        if ($solo_pendientes) {
            $where .= ' AND visto_alumna = 0';
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM " . self::tabla() . "
                WHERE {$where}
                ORDER BY created_at DESC, id DESC
                LIMIT %d",
                absint($alumna_id),
                $limit
            )
        );
    }

    /**
     * Counts unread notifications.
     *
     * @param string $contexto admin|alumna.
     * @param int    $alumna_id Student profile ID when context is alumna.
     * @return int
     */
    public static function contar_no_vistas($contexto = 'admin', $alumna_id = 0) {
        global $wpdb;

        if ('alumna' === $contexto) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM " . self::tabla() . "
                    WHERE alumna_id = %d
                        AND visible_alumna = 1
                        AND eliminado_alumna = 0
                        AND visto_alumna = 0",
                    absint($alumna_id)
                )
            );
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM " . self::tabla() . "
            WHERE visible_admin = 1
                AND eliminado_admin = 0
                AND visto_admin = 0"
        );
    }

    /**
     * Marks a notification as seen for the given context.
     *
     * @param int    $notificacion_id Notification ID.
     * @param string $contexto admin|alumna.
     * @param int    $alumna_id Student profile ID for ownership checks.
     * @return bool
     */
    public static function marcar_vista($notificacion_id, $contexto = 'admin', $alumna_id = 0) {
        return self::actualizar_estado($notificacion_id, $contexto, $alumna_id, 'visto');
    }

    /**
     * Soft-deletes a notification for the given context.
     *
     * @param int    $notificacion_id Notification ID.
     * @param string $contexto admin|alumna.
     * @param int    $alumna_id Student profile ID for ownership checks.
     * @return bool
     */
    public static function eliminar($notificacion_id, $contexto = 'admin', $alumna_id = 0) {
        return self::actualizar_estado($notificacion_id, $contexto, $alumna_id, 'eliminar');
    }

    /**
     * Creates a reservation confirmation notification.
     *
     * @param int $reserva_id Reservation ID.
     * @return void
     */
    public static function crear_reserva_confirmada($reserva_id) {
        $reserva = self::obtener_reserva($reserva_id);

        if (!$reserva) {
            return;
        }

        $detalle = TP_Helpers::detalle_reserva($reserva);

        self::crear(
            array(
                'alumna_id'      => (int) $reserva->alumna_id,
                'tipo'           => 'reserva_confirmada',
                'referencia'     => 'reserva:' . (int) $reserva->id,
                'titulo'         => __('Reserva confirmada', 'tatipilates'),
                'mensaje'        => sprintf(__('Tu clase del %1$s a las %2$s quedo reservada.', 'tatipilates'), $detalle['fecha'], $detalle['hora']),
                'url_accion'     => add_query_arg('semana', TP_Reservas::rango_semana($reserva->fecha)['inicio'], TP_Roles::portal_url()),
                'visible_admin'  => 1,
                'visible_alumna' => 1,
            )
        );
    }

    /**
     * Creates a cancellation notification.
     *
     * @param object $reserva Reservation row before cancellation.
     * @return void
     */
    public static function crear_reserva_cancelada($reserva) {
        if (!$reserva || empty($reserva->alumna_id)) {
            return;
        }

        $detalle = TP_Helpers::detalle_reserva($reserva);

        self::crear(
            array(
                'alumna_id'      => (int) $reserva->alumna_id,
                'tipo'           => 'reserva_cancelada',
                'referencia'     => 'reserva:' . (int) $reserva->id,
                'titulo'         => __('Reserva cancelada', 'tatipilates'),
                'mensaje'        => sprintf(__('Se cancelo tu reserva del %1$s a las %2$s.', 'tatipilates'), $detalle['fecha'], $detalle['hora']),
                'url_accion'     => add_query_arg('semana', TP_Reservas::rango_semana($reserva->fecha)['inicio'], TP_Roles::portal_url()),
                'visible_admin'  => 1,
                'visible_alumna' => 1,
            )
        );
    }

    /**
     * Creates an absence/recovery notification.
     *
     * @param object $reserva Reservation row.
     * @return void
     */
    public static function crear_ausencia_reportada($reserva) {
        if (!$reserva || empty($reserva->alumna_id)) {
            return;
        }

        $detalle = TP_Helpers::detalle_reserva($reserva);

        self::crear(
            array(
                'alumna_id'      => (int) $reserva->alumna_id,
                'tipo'           => 'ausencia_reportada',
                'referencia'     => 'reserva:' . (int) $reserva->id,
                'titulo'         => __('Ausencia reportada', 'tatipilates'),
                'mensaje'        => sprintf(__('Reportaste ausencia para el %1$s a las %2$s. Tu recuperacion queda pendiente por 3 meses.', 'tatipilates'), $detalle['fecha'], $detalle['hora']),
                'url_accion'     => TP_Roles::portal_url(),
                'visible_admin'  => 1,
                'visible_alumna' => 1,
            )
        );
    }

    /**
     * Creates reminders for classes scheduled for tomorrow.
     *
     * @return int Number of created reminders.
     */
    public static function generar_recordatorios_clase_proxima() {
        global $wpdb;

        $config = self::configuracion();

        if (empty($config['clase_proxima_activa'])) {
            return 0;
        }

        $manana = gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' +1 day'));
        $reservas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, h.hora_inicio
                FROM {$wpdb->prefix}tp_reservas r
                INNER JOIN {$wpdb->prefix}tp_horarios h ON h.id = r.horario_id
                WHERE r.fecha = %s
                    AND r.estado = 'reservada'
                ORDER BY h.hora_inicio ASC, r.id ASC",
                $manana
            )
        );
        $creadas = 0;

        foreach ($reservas as $reserva) {
            $creada = self::crear_clase_proxima($reserva);

            if (!is_wp_error($creada) && $creada) {
                $creadas++;
            }
        }

        return $creadas;
    }

    /**
     * Creates reminders for recovery credits that are about to expire.
     *
     * @return int Number of created reminders.
     */
    public static function generar_recordatorios_recuperacion_por_vencer() {
        global $wpdb;

        $config = self::configuracion();

        if (empty($config['recuperacion_por_vencer_activa'])) {
            return 0;
        }

        $hoy = gmdate('Y-m-d', current_time('timestamp'));
        $limite = gmdate('Y-m-d', strtotime($hoy . ' +' . absint($config['recuperacion_dias']) . ' days'));
        $recuperaciones = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$wpdb->prefix}tp_recuperaciones
                WHERE estado = 'pendiente'
                    AND fecha_limite BETWEEN %s AND %s
                ORDER BY fecha_limite ASC, id ASC",
                $hoy,
                $limite
            )
        );
        $creadas = 0;

        foreach ($recuperaciones as $recuperacion) {
            $creada = self::crear_recuperacion_por_vencer($recuperacion);

            if (!is_wp_error($creada) && $creada) {
                $creadas++;
            }
        }

        return $creadas;
    }

    /**
     * Creates reminders for current month payments that are not confirmed yet.
     *
     * @return int Number of created reminders.
     */
    public static function generar_recordatorios_pago_mensual() {
        global $wpdb;

        $mes = TP_Pagos::normalizar_mes();
        $estado = TP_Pagos::mes_en_gracia($mes) ? 'pendiente' : 'vencido';
        $config = self::configuracion();

        if ('pendiente' === $estado && empty($config['pago_pendiente_activa'])) {
            return 0;
        }

        if ('vencido' === $estado && empty($config['pago_vencido_activa'])) {
            return 0;
        }

        $alumnas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id AS alumna_id, a.plan, u.display_name, u.user_email
                FROM {$wpdb->prefix}tp_alumnas a
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                LEFT JOIN {$wpdb->prefix}tp_pagos p ON p.alumna_id = a.id AND p.mes = %s
                WHERE a.activa = 1
                    AND a.plan <> 'individual'
                    AND p.id IS NULL
                ORDER BY u.display_name ASC, a.id ASC",
                $mes
            )
        );
        $creadas = 0;

        foreach ($alumnas as $alumna) {
            $creada = self::crear_pago_mensual($alumna, $mes, $estado);

            if (!is_wp_error($creada) && $creada) {
                $creadas++;
            }
        }

        return $creadas;
    }

    /**
     * Creates one class reminder.
     *
     * @param object $reserva Reservation row.
     * @return int|WP_Error|null
     */
    private static function crear_clase_proxima($reserva) {
        if (!$reserva || empty($reserva->id) || empty($reserva->alumna_id)) {
            return null;
        }

        $detalle = TP_Helpers::detalle_reserva($reserva);
        $mensaje = self::texto_configurado(
            'texto_clase_proxima',
            array(
                'fecha' => $detalle['fecha'],
                'hora'  => $detalle['hora'],
            )
        );

        return self::crear(
            array(
                'alumna_id'      => (int) $reserva->alumna_id,
                'tipo'           => 'clase_proxima',
                'referencia'     => 'reserva:' . (int) $reserva->id,
                'titulo'         => __('Clase proxima', 'tatipilates'),
                'mensaje'        => $mensaje,
                'url_accion'     => add_query_arg('semana', TP_Reservas::rango_semana($reserva->fecha)['inicio'], TP_Roles::portal_url()),
                'visible_admin'  => 1,
                'visible_alumna' => 1,
            )
        );
    }

    /**
     * Creates one recovery expiration reminder.
     *
     * @param object $recuperacion Recovery row.
     * @return int|WP_Error|null
     */
    private static function crear_recuperacion_por_vencer($recuperacion) {
        if (!$recuperacion || empty($recuperacion->id) || empty($recuperacion->alumna_id)) {
            return null;
        }

        $dias = TP_Recuperaciones::dias_restantes($recuperacion->fecha_limite);
        $fecha_limite = TP_Pagos::formatear_fecha($recuperacion->fecha_limite);
        $mensaje = $dias <= 0
            ? self::texto_configurado(
                'texto_recuperacion_hoy',
                array(
                    'fecha_limite' => $fecha_limite,
                    'dias'         => $dias,
                )
            )
            : self::texto_configurado(
                'texto_recuperacion',
                array(
                    'fecha_limite' => $fecha_limite,
                    'dias'         => $dias,
                )
            );

        return self::crear(
            array(
                'alumna_id'      => (int) $recuperacion->alumna_id,
                'tipo'           => 'recuperacion_por_vencer',
                'referencia'     => 'recuperacion:' . (int) $recuperacion->id,
                'titulo'         => __('Recuperacion por vencer', 'tatipilates'),
                'mensaje'        => $mensaje,
                'url_accion'     => TP_Roles::portal_url(),
                'visible_admin'  => 1,
                'visible_alumna' => 1,
            )
        );
    }

    /**
     * Creates one monthly payment reminder.
     *
     * @param object $alumna Student row.
     * @param string $mes    Month date.
     * @param string $estado pendiente|vencido.
     * @return int|WP_Error|null
     */
    private static function crear_pago_mensual($alumna, $mes, $estado) {
        if (!$alumna || empty($alumna->alumna_id)) {
            return null;
        }

        $alumna_id = (int) $alumna->alumna_id;
        $mes_label = TP_Pagos::formatear_mes($mes);
        $tipo = 'vencido' === $estado ? 'pago_vencido' : 'pago_pendiente';
        $titulo = 'vencido' === $estado ? __('Pago vencido', 'tatipilates') : __('Pago pendiente', 'tatipilates');
        $fecha_limite = TP_Pagos::formatear_fecha(TP_Pagos::fecha_limite_gracia($mes));

        if ('vencido' === $estado) {
            $mensaje = self::texto_configurado(
                'texto_pago_vencido',
                array(
                    'mes'          => $mes_label,
                    'fecha_limite' => $fecha_limite,
                )
            );
        } else {
            $mensaje = self::texto_configurado(
                'texto_pago_pendiente',
                array(
                    'mes'          => $mes_label,
                    'fecha_limite' => $fecha_limite,
                )
            );
        }

        return self::crear(
            array(
                'alumna_id'      => $alumna_id,
                'tipo'           => $tipo,
                'referencia'     => 'pago:' . $alumna_id . ':' . $mes,
                'titulo'         => $titulo,
                'mensaje'        => $mensaje,
                'url_accion'     => TP_Roles::portal_url(),
                'visible_admin'  => 1,
                'visible_alumna' => 1,
            )
        );
    }

    /**
     * Updates notification state.
     *
     * @param int    $notificacion_id Notification ID.
     * @param string $contexto admin|alumna.
     * @param int    $alumna_id Student profile ID for ownership checks.
     * @param string $accion visto|eliminar.
     * @return bool
     */
    private static function actualizar_estado($notificacion_id, $contexto, $alumna_id, $accion) {
        global $wpdb;

        $notificacion_id = absint($notificacion_id);

        if (!$notificacion_id) {
            return false;
        }

        $datos = array();
        $formatos = array();
        $where = array('id' => $notificacion_id);
        $where_formatos = array('%d');

        if ('alumna' === $contexto) {
            $where['alumna_id'] = absint($alumna_id);
            $where_formatos[] = '%d';

            if ('visto' === $accion) {
                $datos = array(
                    'visto_alumna'    => 1,
                    'visto_alumna_at' => current_time('mysql'),
                );
                $formatos = array('%d', '%s');
            } else {
                $datos = array('eliminado_alumna' => 1);
                $formatos = array('%d');
            }
        } else {
            if ('visto' === $accion) {
                $datos = array(
                    'visto_admin'    => 1,
                    'visto_admin_at' => current_time('mysql'),
                );
                $formatos = array('%d', '%s');
            } else {
                $datos = array('eliminado_admin' => 1);
                $formatos = array('%d');
            }
        }

        $actualizado = $wpdb->update(self::tabla(), $datos, $where, $formatos, $where_formatos);

        if (false === $actualizado) {
            TP_Helpers::log_db_error('TP_Notificaciones::actualizar_estado');
            return false;
        }

        return true;
    }

    /**
     * Gets a reservation with schedule data.
     *
     * @param int $reserva_id Reservation ID.
     * @return object|null
     */
    private static function obtener_reserva($reserva_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, h.hora_inicio
                FROM {$wpdb->prefix}tp_reservas r
                INNER JOIN {$wpdb->prefix}tp_horarios h ON h.id = r.horario_id
                WHERE r.id = %d",
                absint($reserva_id)
            )
        );
    }

    /**
     * Checks whether a notification with a logical reference already exists.
     *
     * @param string $tipo Notification type.
     * @param string $referencia Logical reference.
     * @return bool
     */
    private static function existe_referencia($tipo, $referencia) {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                FROM " . self::tabla() . "
                WHERE tipo = %s AND referencia = %s
                LIMIT 1",
                sanitize_key($tipo),
                sanitize_text_field($referencia)
            )
        );
    }

    /**
     * Returns default notification reminder settings.
     *
     * @return array<string,mixed>
     */
    private static function configuracion_default() {
        return array(
            'clase_proxima_activa'            => 1,
            'recuperacion_por_vencer_activa' => 1,
            'recuperacion_dias'              => 7,
            'pago_pendiente_activa'          => 1,
            'pago_vencido_activa'            => 1,
            'texto_clase_proxima'            => __('Te recordamos tu clase de manana, {fecha} a las {hora}.', 'tatipilates'),
            'texto_recuperacion'             => __('Tienes una recuperacion que vence el {fecha_limite}. Te quedan {dias} dias para usarla.', 'tatipilates'),
            'texto_recuperacion_hoy'         => __('Tu recuperacion vence hoy, {fecha_limite}. Puedes reservarla desde Mi Pilates antes de que expire.', 'tatipilates'),
            'texto_pago_pendiente'           => __('Tu pago de {mes} esta pendiente. Puedes reservar durante el periodo de gracia, hasta el {fecha_limite}.', 'tatipilates'),
            'texto_pago_vencido'             => __('Tu pago de {mes} no esta confirmado. Por ahora no podras reservar nuevas clases hasta que Tatiana lo registre.', 'tatipilates'),
        );
    }

    /**
     * Returns editable text setting keys.
     *
     * @return array<int,string>
     */
    private static function campos_texto_configuracion() {
        return array(
            'texto_clase_proxima',
            'texto_recuperacion',
            'texto_recuperacion_hoy',
            'texto_pago_pendiente',
            'texto_pago_vencido',
        );
    }

    /**
     * Applies variables to a configured text template.
     *
     * @param string              $campo     Setting key.
     * @param array<string,mixed> $variables Template variables.
     * @return string
     */
    private static function texto_configurado($campo, $variables) {
        $config = self::configuracion();
        $texto = isset($config[$campo]) ? (string) $config[$campo] : '';

        if (!$texto) {
            $defaults = self::configuracion_default();
            $texto = isset($defaults[$campo]) ? (string) $defaults[$campo] : '';
        }

        foreach ($variables as $clave => $valor) {
            $texto = str_replace('{' . $clave . '}', (string) $valor, $texto);
        }

        return $texto;
    }

    /**
     * Returns the notification table name.
     *
     * @return string
     */
    private static function tabla() {
        global $wpdb;

        return $wpdb->prefix . 'tp_notificaciones';
    }
}
