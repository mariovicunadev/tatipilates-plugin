<?php
/**
 * Student CRUD.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles student profiles linked to WordPress users.
 */
class TP_Alumnas {

    /**
     * Available plan labels.
     *
     * @return array<string,string>
     */
    public static function planes() {
        return array(
            '2x'         => '2 clases por semana',
            '3x'         => '3 clases por semana',
            '4x'         => '4 clases por semana',
            '5x'         => '5 clases por semana',
            'individual' => 'Individual',
        );
    }

    /**
     * Lists students with useful admin counters.
     *
     * @return array<int,object>
     */
    public static function obtener_todas() {
        global $wpdb;

        $tabla_alumnas        = $wpdb->prefix . 'tp_alumnas';
        $tabla_pagos          = $wpdb->prefix . 'tp_pagos';
        $tabla_recuperaciones = $wpdb->prefix . 'tp_recuperaciones';
        $mes_actual           = gmdate('Y-m-01', current_time('timestamp'));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.wp_user_id, a.plan, a.activa,
                    u.display_name, u.user_email,
                    p.fecha_pago AS pago_mes_actual,
                    COUNT(r.id) AS recuperaciones_pendientes
                FROM {$tabla_alumnas} a
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                LEFT JOIN {$tabla_pagos} p ON p.alumna_id = a.id AND p.mes = %s
                LEFT JOIN {$tabla_recuperaciones} r ON r.alumna_id = a.id AND r.estado = 'pendiente' AND r.fecha_limite >= CURDATE()
                GROUP BY a.id, u.display_name, u.user_email, p.fecha_pago
                ORDER BY a.activa DESC, u.display_name ASC",
                $mes_actual
            )
        );
    }

    /**
     * Gets a student by profile ID.
     *
     * @param int $alumna_id Student profile ID.
     * @return object|null
     */
    public static function obtener($alumna_id) {
        global $wpdb;

        $tabla_alumnas = $wpdb->prefix . 'tp_alumnas';
        $alumna_id     = absint($alumna_id);

        if (!$alumna_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.id, a.wp_user_id, a.plan, a.activa, a.notas,
                    a.fecha_nacimiento, a.fecha_inicio_pilates, a.created_at,
                    u.display_name, u.user_email
                FROM {$tabla_alumnas} a
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                WHERE a.id = %d",
                $alumna_id
            )
        );
    }

    /**
     * Gets a complete student profile when the current user may read medical data.
     *
     * @param int $alumna_id Student profile ID.
     * @return object|null|WP_Error
     */
    public static function obtener_con_datos_medicos($alumna_id) {
        global $wpdb;

        if (!current_user_can(TP_Roles::CAP_VIEW_MEDICAL_DATA)) {
            return new WP_Error('tp_datos_medicos_prohibidos', 'No tienes permisos para ver los datos medicos.');
        }

        if (!TP_Data_Encryption::is_ready()) {
            return new WP_Error('tp_encryption_unavailable', 'Configura TP_DATA_ENCRYPTION_KEY para ver los datos medicos.');
        }

        $alumna_id = absint($alumna_id);

        if (!$alumna_id) {
            return null;
        }

        $alumna = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.id, a.wp_user_id, a.plan, a.activa, a.notas,
                    a.historia_medica, a.alergias, a.motivo_pilates,
                    a.fecha_nacimiento, a.fecha_inicio_pilates, a.created_at,
                    u.display_name, u.user_email
                FROM {$wpdb->prefix}tp_alumnas a
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                WHERE a.id = %d",
                $alumna_id
            )
        );

        return $alumna ? self::descifrar_datos_medicos($alumna) : null;
    }

    /**
     * Gets a student by linked WordPress user ID.
     *
     * @param int $user_id WordPress user ID.
     * @return object|null
     */
    public static function obtener_por_wp_user_id($user_id) {
        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.id, a.wp_user_id, a.plan, a.activa,
                    u.display_name, u.user_email
                FROM {$wpdb->prefix}tp_alumnas a
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                WHERE a.wp_user_id = %d",
                $user_id
            )
        );
    }

    /**
     * Creates a WordPress user and the linked student profile.
     *
     * @param array<string,mixed> $datos Raw student data.
     * @return array<string,mixed>|WP_Error
     */
    public static function crear($datos) {
        global $wpdb;

        $acceso_medico = self::validar_acceso_datos_medicos($datos);

        if (is_wp_error($acceso_medico)) {
            return $acceso_medico;
        }

        $validado = self::validar_datos_creacion($datos);

        if (is_wp_error($validado)) {
            return $validado;
        }

        $validado = self::cifrar_datos_medicos($validado);

        if (is_wp_error($validado)) {
            return $validado;
        }

        if (email_exists($validado['email'])) {
            return new WP_Error('tp_email_existente', 'Ya existe un usuario con ese correo.');
        }

        $password = wp_generate_password(10, false);
        $user_id  = wp_insert_user(
            array(
                'user_login'   => $validado['email'],
                'user_email'   => $validado['email'],
                'user_pass'    => $password,
                'display_name' => $validado['nombre'],
                'first_name'   => $validado['nombre'],
                'role'         => TP_Roles::ROLE_ALUMNA,
            )
        );

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $insertado = $wpdb->insert(
            $wpdb->prefix . 'tp_alumnas',
            array(
                'wp_user_id' => (int) $user_id,
                'plan'       => $validado['plan'],
                'activa'     => 1,
                'notas'      => $validado['notas'],
                'historia_medica' => $validado['historia_medica'] ?? '',
                'alergias' => $validado['alergias'] ?? '',
                'motivo_pilates' => $validado['motivo_pilates'] ?? '',
                'fecha_nacimiento' => $validado['fecha_nacimiento'],
                'fecha_inicio_pilates' => $validado['fecha_inicio_pilates'],
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (false === $insertado) {
            TP_Helpers::log_db_error('TP_Alumnas::crear');
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user((int) $user_id);

            return new WP_Error('tp_alumna_no_creada', 'No se pudo crear el perfil del estudiante.');
        }

        $mail_result = self::enviar_credenciales($validado['email'], $validado['nombre'], $password);

        return array(
            'id'        => (int) $wpdb->insert_id,
            'email'     => $validado['email'],
            'password'  => $password,
            'portal'    => TP_Roles::portal_url(),
            'mail_sent' => $mail_result['sent'],
            'from_name' => $mail_result['from_name'],
            'from_email' => $mail_result['from_email'],
            'mail_error' => $mail_result['error'],
        );
    }

    /**
     * Updates a student profile and WordPress display data.
     *
     * @param int                 $alumna_id Student profile ID.
     * @param array<string,mixed> $datos     Raw student data.
     * @return bool|WP_Error
     */
    public static function actualizar($alumna_id, $datos) {
        global $wpdb;

        $alumna_id     = absint($alumna_id);
        $alumna        = self::obtener($alumna_id);
        $acceso_medico = self::validar_acceso_datos_medicos($datos);
        $validado      = self::validar_datos_edicion($datos);

        if (!$alumna) {
            return new WP_Error('tp_alumna_no_existe', 'El estudiante no existe.');
        }

        if (is_wp_error($acceso_medico)) {
            return $acceso_medico;
        }

        if (is_wp_error($validado)) {
            return $validado;
        }

        $validado = self::cifrar_datos_medicos($validado);

        if (is_wp_error($validado)) {
            return $validado;
        }

        $user_update = wp_update_user(
            array(
                'ID'           => (int) $alumna->wp_user_id,
                'display_name' => $validado['nombre'],
                'first_name'   => $validado['nombre'],
            )
        );

        if (is_wp_error($user_update)) {
            return $user_update;
        }

        $wpdb->query('START TRANSACTION');

        $datos_actualizar = array(
            'plan'                  => $validado['plan'],
            'activa'                => $validado['activa'],
            'notas'                 => $validado['notas'],
            'fecha_nacimiento'      => $validado['fecha_nacimiento'],
            'fecha_inicio_pilates'  => $validado['fecha_inicio_pilates'],
        );
        $formatos_actualizar = array('%s', '%d', '%s', '%s', '%s');

        foreach (self::campos_medicos() as $campo) {
            if (array_key_exists($campo, $validado)) {
                $datos_actualizar[$campo] = $validado[$campo];
                $formatos_actualizar[]    = '%s';
            }
        }

        $actualizado = $wpdb->update(
            $wpdb->prefix . 'tp_alumnas',
            $datos_actualizar,
            array('id' => $alumna_id),
            $formatos_actualizar,
            array('%d')
        );

        if (false === $actualizado) {
            $wpdb->query('ROLLBACK');
            TP_Helpers::log_db_error('TP_Alumnas::actualizar');
            return new WP_Error('tp_alumna_no_actualizada', 'No se pudo actualizar el estudiante.');
        }

        if ((int) $alumna->activa && 0 === (int) $validado['activa']) {
            $resultado = self::cancelar_reservas_futuras($alumna_id);

            if (is_wp_error($resultado)) {
                $wpdb->query('ROLLBACK');
                return $resultado;
            }
        }

        $wpdb->query('COMMIT');

        return true;
    }

    /**
     * Cancels future active reservations when a student is deactivated.
     *
     * @param int $alumna_id Student profile ID.
     * @return bool|WP_Error
     */
    private static function cancelar_reservas_futuras($alumna_id) {
        global $wpdb;

        $hoy = gmdate('Y-m-d', current_time('timestamp'));

        $recuperaciones = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}tp_recuperaciones rec
                INNER JOIN {$wpdb->prefix}tp_reservas r ON r.recuperacion_id = rec.id
                SET rec.estado = 'pendiente'
                WHERE r.alumna_id = %d
                    AND r.fecha >= %s
                    AND r.estado = 'reservada'
                    AND r.tipo = 'recuperacion'
                    AND rec.estado = 'usada'",
                $alumna_id,
                $hoy
            )
        );

        if (false === $recuperaciones) {
            TP_Helpers::log_db_error('TP_Alumnas::cancelar_reservas_futuras');
            return new WP_Error('tp_recuperaciones_no_devueltas', 'No se pudieron devolver las recuperaciones futuras.');
        }

        $reservas = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}tp_reservas
                SET estado = 'cancelada'
                WHERE alumna_id = %d AND fecha >= %s AND estado = 'reservada'",
                $alumna_id,
                $hoy
            )
        );

        if (false === $reservas) {
            TP_Helpers::log_db_error('TP_Alumnas::cancelar_reservas_futuras');
            return new WP_Error('tp_reservas_no_canceladas', 'No se pudieron cancelar las reservas futuras.');
        }

        return true;
    }

    /**
     * Returns student progress counters.
     *
     * @param int $alumna_id Student profile ID.
     * @return array<string,int|null>
     */
    public static function estadisticas($alumna_id) {
        global $wpdb;

        $alumna_id = absint($alumna_id);
        $alumna    = self::obtener($alumna_id);

        if (!$alumna) {
            return array(
                'dias_desde_inicio' => null,
                'clases_asistidas'  => 0,
                'reservas_totales'  => 0,
                'faltas'            => 0,
            );
        }

        $resumen = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN estado = 'asistio' THEN 1 ELSE 0 END) AS clases_asistidas,
                    SUM(CASE WHEN estado IN ('reservada','asistio','falto') THEN 1 ELSE 0 END) AS reservas_totales,
                    SUM(CASE WHEN estado = 'falto' THEN 1 ELSE 0 END) AS faltas
                FROM {$wpdb->prefix}tp_reservas
                WHERE alumna_id = %d",
                $alumna_id
            )
        );

        $dias_desde_inicio = null;

        if (!empty($alumna->fecha_inicio_pilates)) {
            $inicio = strtotime($alumna->fecha_inicio_pilates);

            if ($inicio) {
                $hoy = strtotime(gmdate('Y-m-d', current_time('timestamp')));
                $dias_desde_inicio = max(0, (int) floor(($hoy - $inicio) / DAY_IN_SECONDS));
            }
        }

        return array(
            'dias_desde_inicio' => $dias_desde_inicio,
            'clases_asistidas'  => (int) ($resumen->clases_asistidas ?? 0),
            'reservas_totales'  => (int) ($resumen->reservas_totales ?? 0),
            'faltas'            => (int) ($resumen->faltas ?? 0),
        );
    }

    /**
     * Lists birthdays for a month.
     *
     * @param int|null $mes Month number.
     * @return array<int,object>
     */
    public static function cumpleanos_mes($mes = null) {
        global $wpdb;

        $mes = $mes ? absint($mes) : (int) gmdate('n', current_time('timestamp'));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.fecha_nacimiento, u.display_name, u.user_email
                FROM {$wpdb->prefix}tp_alumnas a
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                WHERE a.activa = 1
                    AND a.fecha_nacimiento IS NOT NULL
                    AND MONTH(a.fecha_nacimiento) = %d
                ORDER BY DAY(a.fecha_nacimiento) ASC, u.display_name ASC",
                $mes
            )
        );
    }

    /**
     * Lists active students whose birthday is today.
     *
     * @return array<int,object>
     */
    public static function cumpleanos_hoy() {
        global $wpdb;

        $hoy = current_time('timestamp');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.fecha_nacimiento, u.display_name, u.user_email
                FROM {$wpdb->prefix}tp_alumnas a
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                WHERE a.activa = 1
                    AND a.fecha_nacimiento IS NOT NULL
                    AND MONTH(a.fecha_nacimiento) = %d
                    AND DAY(a.fecha_nacimiento) = %d
                ORDER BY u.display_name ASC",
                (int) gmdate('n', $hoy),
                (int) gmdate('j', $hoy)
            )
        );
    }

    /**
     * Lists milestones for one student.
     *
     * @param int $alumna_id Student profile ID.
     * @return array<int,object>
     */
    public static function milestones($alumna_id) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$wpdb->prefix}tp_milestones
                WHERE alumna_id = %d
                ORDER BY estado ASC, fecha DESC, id DESC",
                absint($alumna_id)
            )
        );
    }

    /**
     * Lists recent achievements across active students.
     *
     * @param int $limite Number of rows.
     * @return array<int,object>
     */
    public static function logros_recientes($limite = 12) {
        global $wpdb;

        $limite = max(1, min(50, absint($limite)));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, u.display_name, u.user_email
                FROM {$wpdb->prefix}tp_milestones m
                INNER JOIN {$wpdb->prefix}tp_alumnas a ON a.id = m.alumna_id
                INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
                WHERE a.activa = 1
                ORDER BY m.estado ASC, m.fecha DESC, m.id DESC
                LIMIT %d",
                $limite
            )
        );
    }

    /**
     * Creates a student milestone.
     *
     * @param array<string,mixed> $datos Raw milestone data.
     * @return int|WP_Error
     */
    public static function crear_milestone($datos) {
        global $wpdb;

        $alumna_id    = isset($datos['alumna_id']) ? absint($datos['alumna_id']) : 0;
        $titulo       = isset($datos['titulo']) ? sanitize_text_field(wp_unslash($datos['titulo'])) : '';
        $descripcion  = isset($datos['descripcion']) ? sanitize_textarea_field(wp_unslash($datos['descripcion'])) : '';
        $fecha        = isset($datos['fecha']) ? self::normalizar_fecha($datos['fecha']) : '';

        if (!$alumna_id || !self::obtener($alumna_id)) {
            return new WP_Error('tp_milestone_estudiante_invalido', 'Selecciona un estudiante valido.');
        }

        if ('' === $titulo) {
            return new WP_Error('tp_milestone_titulo_requerido', 'Ingresa el logro o meta.');
        }

        if ('' === $fecha) {
            return new WP_Error('tp_milestone_fecha_invalida', 'Selecciona una fecha valida.');
        }

        $insertado = $wpdb->insert(
            $wpdb->prefix . 'tp_milestones',
            array(
                'alumna_id'   => $alumna_id,
                'titulo'      => $titulo,
                'descripcion' => $descripcion,
                'fecha'       => $fecha,
                'estado'      => 'activo',
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if (false === $insertado) {
            TP_Helpers::log_db_error('TP_Alumnas::crear_milestone');
            return new WP_Error('tp_milestone_no_creado', 'No se pudo crear el logro.');
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Changes milestone status.
     *
     * @param int    $milestone_id Milestone ID.
     * @param string $estado       New status.
     * @return bool|WP_Error
     */
    public static function cambiar_milestone($milestone_id, $estado) {
        global $wpdb;

        $milestone_id = absint($milestone_id);
        $estado       = sanitize_text_field(wp_unslash($estado));

        if (!in_array($estado, array('activo', 'logrado'), true)) {
            return new WP_Error('tp_milestone_estado_invalido', 'Estado de logro invalido.');
        }

        $actualizado = $wpdb->update(
            $wpdb->prefix . 'tp_milestones',
            array('estado' => $estado),
            array('id' => $milestone_id),
            array('%s'),
            array('%d')
        );

        if (false === $actualizado) {
            TP_Helpers::log_db_error('TP_Alumnas::cambiar_milestone');
            return new WP_Error('tp_milestone_no_actualizado', 'No se pudo actualizar el logro.');
        }

        return true;
    }

    /**
     * Deletes a milestone.
     *
     * @param int $milestone_id Milestone ID.
     * @return bool|WP_Error
     */
    public static function eliminar_milestone($milestone_id) {
        global $wpdb;

        $eliminado = $wpdb->delete(
            $wpdb->prefix . 'tp_milestones',
            array('id' => absint($milestone_id)),
            array('%d')
        );

        if (false === $eliminado) {
            TP_Helpers::log_db_error('TP_Alumnas::eliminar_milestone');
            return new WP_Error('tp_milestone_no_eliminado', 'No se pudo eliminar el logro.');
        }

        return true;
    }

    /**
     * Deletes a student profile and its WordPress user when it has no history.
     *
     * @param int $alumna_id Student profile ID.
     * @return bool|WP_Error
     */
    public static function eliminar($alumna_id) {
        global $wpdb;

        $alumna_id = absint($alumna_id);
        $alumna    = self::obtener($alumna_id);

        if (!$alumna) {
            return new WP_Error('tp_alumna_no_existe', 'El estudiante no existe.');
        }

        $tablas_historial = array(
            $wpdb->prefix . 'tp_reservas',
            $wpdb->prefix . 'tp_recuperaciones',
            $wpdb->prefix . 'tp_pagos',
            $wpdb->prefix . 'tp_milestones',
        );

        foreach ($tablas_historial as $tabla) {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$tabla} WHERE alumna_id = %d", $alumna_id)
            );

            if ($total > 0) {
                return new WP_Error('tp_estudiante_con_historial', 'Este estudiante ya tiene historial. Marcalo como inactivo en vez de eliminarlo.');
            }
        }

        $wpdb->query('START TRANSACTION');

        $perfil_eliminado = $wpdb->delete(
            $wpdb->prefix . 'tp_alumnas',
            array('id' => $alumna_id),
            array('%d')
        );

        if (false === $perfil_eliminado) {
            $wpdb->query('ROLLBACK');
            TP_Helpers::log_db_error('TP_Alumnas::eliminar');
            return new WP_Error('tp_estudiante_no_eliminado', 'No se pudo eliminar el perfil del estudiante.');
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $usuario_eliminado = wp_delete_user((int) $alumna->wp_user_id);

        if (!$usuario_eliminado) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('tp_usuario_no_eliminado', 'No se pudo eliminar el usuario de WordPress.');
        }

        $wpdb->query('COMMIT');

        return true;
    }

    /**
     * Sends portal credentials to a new student.
     *
     * @param string $email    Student email.
     * @param string $nombre   Student name.
     * @param string $password Temporary password.
     * @return array<string,mixed>
     */
    private static function enviar_credenciales($email, $nombre, $password) {
        $asunto = 'Tu acceso a Tati Pilates';
        $portal = TP_Roles::portal_url();
        $cuerpo = "Hola {$nombre},\n\n";
        $cuerpo .= "Tatiana creo tu acceso al portal Mi Pilates.\n\n";
        $cuerpo .= "Portal: {$portal}\n";
        $cuerpo .= "Usuario: {$email}\n";
        $cuerpo .= "Contrasena temporal: {$password}\n\n";
        $cuerpo .= "Puedes cambiar la contrasena desde Mi Cuenta cuando entres al portal.\n";

        $mail_error = '';
        $from_email = sanitize_email(get_option('admin_email'));
        $from_name  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        if (!is_email($from_email)) {
            $from_email = sanitize_email('wordpress@tatipilates.com');
        }

        if ('' === trim($from_name)) {
            $from_name = 'Tati Pilates';
        }

        $headers    = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: Tati Pilates <' . $from_email . '>',
        );

        $capturar_error = function ($wp_error) use (&$mail_error) {
            if ($wp_error instanceof WP_Error) {
                $mail_error = $wp_error->get_error_message();
            }
        };

        add_action('wp_mail_failed', $capturar_error);
        $sent = wp_mail($email, $asunto, $cuerpo, $headers);
        remove_action('wp_mail_failed', $capturar_error);

        return array(
            'sent'       => (bool) $sent,
            'from_name'  => $from_name,
            'from_email' => $from_email,
            'error'      => $mail_error,
        );
    }

    /**
     * Validates create input.
     *
     * @param array<string,mixed> $datos Raw data.
     * @return array<string,mixed>|WP_Error
     */
    private static function validar_datos_creacion($datos) {
        $validado = self::validar_datos_edicion($datos);

        if (is_wp_error($validado)) {
            return $validado;
        }

        $email = isset($datos['email']) ? sanitize_email(wp_unslash($datos['email'])) : '';

        if (!is_email($email)) {
            return new WP_Error('tp_email_invalido', 'Ingresa un correo valido.');
        }

        $validado['email'] = $email;

        return $validado;
    }

    /**
     * Validates editable profile input.
     *
     * @param array<string,mixed> $datos Raw data.
     * @return array<string,mixed>|WP_Error
     */
    private static function validar_datos_edicion($datos) {
        $nombre = isset($datos['nombre']) ? sanitize_text_field(wp_unslash($datos['nombre'])) : '';
        $plan   = isset($datos['plan']) ? sanitize_text_field(wp_unslash($datos['plan'])) : '';
        $activa = isset($datos['activa']) ? absint($datos['activa']) : 0;
        $notas  = isset($datos['notas']) ? sanitize_textarea_field(wp_unslash($datos['notas'])) : '';
        $fecha_nacimiento = isset($datos['fecha_nacimiento']) ? self::normalizar_fecha($datos['fecha_nacimiento']) : null;
        $fecha_inicio_pilates = isset($datos['fecha_inicio_pilates']) ? self::normalizar_fecha($datos['fecha_inicio_pilates']) : null;

        if ('' === $nombre) {
            return new WP_Error('tp_nombre_requerido', 'Ingresa el nombre del estudiante.');
        }

        if (!array_key_exists($plan, self::planes())) {
            return new WP_Error('tp_plan_invalido', 'Selecciona un plan valido.');
        }

        $validado = array(
            'nombre' => $nombre,
            'plan'   => $plan,
            'activa' => $activa ? 1 : 0,
            'notas'  => $notas,
            'fecha_nacimiento' => $fecha_nacimiento,
            'fecha_inicio_pilates' => $fecha_inicio_pilates,
        );

        foreach (self::campos_medicos() as $campo) {
            if (array_key_exists($campo, $datos)) {
                $validado[$campo] = sanitize_textarea_field(wp_unslash($datos[$campo]));
            }
        }

        return $validado;
    }

    /**
     * Prepares one imported student row so medical fields are encrypted at rest.
     *
     * @param array<string,mixed> $fila Validated backup row.
     * @return array<string,mixed>|WP_Error
     */
    public static function preparar_fila_backup($fila) {
        foreach (self::campos_medicos() as $campo) {
            if (!array_key_exists($campo, $fila) || null === $fila[$campo] || '' === $fila[$campo]) {
                continue;
            }

            if (TP_Data_Encryption::is_encrypted($fila[$campo])) {
                $verificado = TP_Data_Encryption::decrypt($fila[$campo]);

                if (is_wp_error($verificado)) {
                    return new WP_Error(
                        'tp_backup_medical_key_mismatch',
                        'El backup contiene datos medicos cifrados que no se pueden leer con la clave actual.'
                    );
                }

                continue;
            }
        }

        return self::cifrar_datos_medicos($fila);
    }

    /**
     * Migrates plaintext medical fields to the encrypted envelope.
     *
     * @param int $limite Maximum rows to inspect in one run.
     * @return array<string,int>|WP_Error
     */
    public static function migrar_datos_medicos_cifrados($limite = 500) {
        global $wpdb;

        if (!TP_Data_Encryption::is_ready()) {
            return new WP_Error('tp_encryption_unavailable', 'Configura TP_DATA_ENCRYPTION_KEY para migrar datos medicos.');
        }

        $limite = max(1, min(1000, absint($limite)));
        $filas  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, historia_medica, alergias, motivo_pilates
                FROM {$wpdb->prefix}tp_alumnas
                WHERE (historia_medica IS NOT NULL AND historia_medica != '' AND historia_medica NOT LIKE %s)
                    OR (alergias IS NOT NULL AND alergias != '' AND alergias NOT LIKE %s)
                    OR (motivo_pilates IS NOT NULL AND motivo_pilates != '' AND motivo_pilates NOT LIKE %s)
                ORDER BY id ASC
                LIMIT %d",
                TP_Data_Encryption::PREFIX . '%',
                TP_Data_Encryption::PREFIX . '%',
                TP_Data_Encryption::PREFIX . '%',
                $limite
            ),
            ARRAY_A
        );

        if (!$filas) {
            update_option('tp_medical_encryption_migrated', TP_VERSION, false);

            return array(
                'inspeccionadas' => 0,
                'migradas'       => 0,
            );
        }

        $migradas = 0;

        foreach ($filas as $fila) {
            $datos_actualizar = array();
            $formatos         = array();

            foreach (self::campos_medicos() as $campo) {
                $valor = isset($fila[$campo]) ? (string) $fila[$campo] : '';

                if ('' === $valor || TP_Data_Encryption::is_encrypted($valor)) {
                    continue;
                }

                $cifrado = TP_Data_Encryption::encrypt($valor);

                if (is_wp_error($cifrado)) {
                    return $cifrado;
                }

                $datos_actualizar[$campo] = $cifrado;
                $formatos[]               = '%s';
            }

            if (!$datos_actualizar) {
                continue;
            }

            $actualizado = $wpdb->update(
                $wpdb->prefix . 'tp_alumnas',
                $datos_actualizar,
                array('id' => absint($fila['id'])),
                $formatos,
                array('%d')
            );

            if (false === $actualizado) {
                TP_Helpers::log_db_error('TP_Alumnas::migrar_datos_medicos_cifrados');

                return new WP_Error('tp_medical_migration_failed', 'No se pudieron cifrar los datos medicos existentes.');
            }

            $migradas++;
        }

        return array(
            'inspeccionadas' => count($filas),
            'migradas'       => $migradas,
        );
    }

    /**
     * Encrypts medical fields present in one data array.
     *
     * @param array<string,mixed> $datos Profile data.
     * @return array<string,mixed>|WP_Error
     */
    private static function cifrar_datos_medicos($datos) {
        foreach (self::campos_medicos() as $campo) {
            if (!array_key_exists($campo, $datos)) {
                continue;
            }

            $valor = null === $datos[$campo] ? '' : (string) $datos[$campo];

            if ('' === $valor) {
                $datos[$campo] = '';
                continue;
            }

            $cifrado = TP_Data_Encryption::encrypt($valor);

            if (is_wp_error($cifrado)) {
                return $cifrado;
            }

            $datos[$campo] = $cifrado;
        }

        return $datos;
    }

    /**
     * Decrypts medical fields on one loaded profile object.
     *
     * @param object $alumna Student row.
     * @return object|WP_Error
     */
    private static function descifrar_datos_medicos($alumna) {
        foreach (self::campos_medicos() as $campo) {
            $descifrado = TP_Data_Encryption::decrypt($alumna->{$campo} ?? '');

            if (is_wp_error($descifrado)) {
                return $descifrado;
            }

            $alumna->{$campo} = $descifrado;
        }

        return $alumna;
    }

    /**
     * Rejects attempts to write medical fields without the dedicated capability.
     *
     * @param array<string,mixed> $datos Raw profile data.
     * @return true|WP_Error
     */
    private static function validar_acceso_datos_medicos($datos) {
        $puede_editar = current_user_can(TP_Roles::CAP_VIEW_MEDICAL_DATA);

        foreach (self::campos_medicos() as $campo) {
            if (array_key_exists($campo, $datos) && !$puede_editar) {
                return new WP_Error('tp_datos_medicos_prohibidos', 'No tienes permisos para editar los datos medicos.');
            }
        }

        return true;
    }

    /**
     * Returns the medical column allowlist.
     *
     * @return array<int,string>
     */
    private static function campos_medicos() {
        return array('historia_medica', 'alergias', 'motivo_pilates');
    }

    /**
     * Normalizes optional date input.
     *
     * @param string $fecha Raw date.
     * @return string|null
     */
    private static function normalizar_fecha($fecha) {
        return TP_Helpers::normalizar_fecha($fecha, null);
    }
}
