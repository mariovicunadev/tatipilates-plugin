<?php
/**
 * Local/demo test data generator.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seeds repeatable demo data for local testing.
 */
class TP_Test_Data {

    const PASSWORD_ALUMNAS = 'Pilates2026!';
    const PASSWORD_ADMIN   = 'AdminPilates2026!';

    /**
     * Creates or refreshes demo users and Pilates data.
     *
     * @return array<string,mixed>|WP_Error
     */
    public static function seed() {
        global $wpdb;

        if (class_exists('TP_Roles')) {
            TP_Roles::registrar_roles();
        }

        $tables_ready = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'tp_alumnas')));

        if (!$tables_ready) {
            if (!class_exists('TP_Activator')) {
                return new WP_Error('tp_seed_sin_tablas', 'No existen las tablas del plugin.');
            }

            TP_Activator::activate();
        }

        $hoy          = gmdate('Y-m-d', current_time('timestamp'));
        $lunes_actual = gmdate('Y-m-d', strtotime($hoy . ' monday this week'));
        $mes_actual   = gmdate('Y-m-01', strtotime($hoy));
        $mes_anterior = gmdate('Y-m-01', strtotime($hoy . ' -1 month'));
        $horarios     = self::crear_horarios();
        $alumnas      = self::crear_alumnas();

        foreach ($alumnas as $alumna_id) {
            self::limpiar_alumna($alumna_id);
        }

        self::ensure_user('admin.pilates.demo@tatipilates.test', 'Admin Pilates Demo', TP_Roles::ROLE_ADMIN_PILATES, self::PASSWORD_ADMIN);

        self::crear_pagos($alumnas, $mes_actual, $mes_anterior);
        self::crear_reservas($alumnas, $horarios, $lunes_actual, $hoy);
        self::crear_milestones($alumnas, $hoy);

        return array(
            'portal'                 => home_url('/mi-pilates/'),
            'password_alumnas'       => self::PASSWORD_ALUMNAS,
            'password_admin_pilates' => self::PASSWORD_ADMIN,
            'usuarios_alumnas'       => self::emails_alumnas(),
            'usuario_admin_pilates'  => 'admin.pilates.demo@tatipilates.test',
            'alumnas_creadas'        => count($alumnas),
        );
    }

    /**
     * Returns demo student definitions.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function alumnas_demo() {
        return array(
            'ana' => array('ana.demo@tatipilates.test', 'Ana Demo 2x', '2x', 1, array('fecha_nacimiento' => '1991-05-29')),
            'bel' => array('belen.demo@tatipilates.test', 'Belen Demo 3x', '3x', 1, array('alergias' => 'Polen')),
            'car' => array('carla.demo@tatipilates.test', 'Carla Demo 5x + Mat', '5x', 1, array()),
            'dia' => array('diana.demo@tatipilates.test', 'Diana Demo Individual', 'individual', 1, array()),
            'ele' => array('elena.demo@tatipilates.test', 'Elena Demo Pago Vencido', '3x', 1, array()),
            'fab' => array('fabiola.demo@tatipilates.test', 'Fabiola Demo Inactiva', '2x', 0, array()),
            'gab' => array('gabriela.demo@tatipilates.test', 'Gabriela Demo 4x', '4x', 1, array('fecha_nacimiento' => '1988-06-03')),
            'hel' => array('helena.demo@tatipilates.test', 'Helena Demo 2x Recuperacion', '2x', 1, array()),
            'ine' => array('ines.demo@tatipilates.test', 'Ines Demo 3x Libre', '3x', 1, array()),
            'jul' => array('julia.demo@tatipilates.test', 'Julia Demo 5x Completo', '5x', 1, array()),
            'kar' => array('karina.demo@tatipilates.test', 'Karina Demo 4x Parcial', '4x', 1, array('alergias' => 'Lactosa')),
            'luc' => array('lucia.demo@tatipilates.test', 'Lucia Demo 2x Sin Reservas', '2x', 1, array()),
            'mar' => array('marina.demo@tatipilates.test', 'Marina Demo Cumple Hoy', '3x', 1, array('fecha_nacimiento' => '1992-' . gmdate('m-d', current_time('timestamp')))),
            'nat' => array('natalia.demo@tatipilates.test', 'Natalia Demo Gracia', '4x', 1, array()),
            'oli' => array('olivia.demo@tatipilates.test', 'Olivia Demo Inactiva', '5x', 0, array()),
            'pau' => array('paula.demo@tatipilates.test', 'Paula Demo Individual Admin', 'individual', 1, array()),
        );
    }

    /**
     * Returns demo student emails.
     *
     * @return array<int,string>
     */
    private static function emails_alumnas() {
        return array_values(
            array_map(
                function ($definicion) {
                    return $definicion[0];
                },
                self::alumnas_demo()
            )
        );
    }

    /**
     * Creates fixed demo schedules.
     *
     * @return array<string,int>
     */
    private static function crear_horarios() {
        return array(
            'lu_0730' => self::ensure_horario(1, '07:30:00', 'reformer', 5, 1),
            'lu_0830' => self::ensure_horario(1, '08:30:00', 'reformer', 5, 1),
            'ma_0930' => self::ensure_horario(2, '09:30:00', 'reformer', 5, 1),
            'ma_1700' => self::ensure_horario(2, '17:00:00', 'reformer', 5, 1),
            'mi_1700' => self::ensure_horario(3, '17:00:00', 'reformer', 5, 1),
            'ju_1800' => self::ensure_horario(4, '18:00:00', 'reformer', 5, 1),
            'vi_0730' => self::ensure_horario(5, '07:30:00', 'reformer', 5, 1),
            'vi_1800' => self::ensure_horario(5, '18:00:00', 'reformer', 5, 1),
            'sa_0830' => self::ensure_horario(6, '08:30:00', 'mat', 8, 1),
        );
    }

    /**
     * Creates demo student profiles.
     *
     * @return array<string,int>
     */
    private static function crear_alumnas() {
        $alumnas = array();

        foreach (self::alumnas_demo() as $key => $definicion) {
            $alumnas[$key] = self::ensure_alumna($definicion[0], $definicion[1], $definicion[2], $definicion[3], $definicion[4]);
        }

        return $alumnas;
    }

    /**
     * Creates demo monthly payments.
     *
     * @param array<string,int> $alumnas Student IDs.
     * @param string            $mes_actual Current month.
     * @param string            $mes_anterior Previous month.
     * @return void
     */
    private static function crear_pagos($alumnas, $mes_actual, $mes_anterior) {
        foreach (self::alumnas_demo() as $key => $definicion) {
            if ('ele' === $key) {
                self::insert_pago($alumnas[$key], $mes_anterior, $definicion[2], 'Demo: sin pago del mes actual.');
                continue;
            }

            self::insert_pago($alumnas[$key], $mes_actual, $definicion[2], 'Demo: pago actual al dia.');
        }
    }

    /**
     * Creates reservations and recoveries.
     *
     * @param array<string,int> $alumnas Student IDs.
     * @param array<string,int> $horarios Schedule IDs.
     * @param string            $lunes_actual Current week Monday.
     * @param string            $hoy Current date.
     * @return void
     */
    private static function crear_reservas($alumnas, $horarios, $lunes_actual, $hoy) {
        $mapa = array(
            'ana' => array('lu_0730' => 1, 'mi_1700' => 3),
            'bel' => array('ma_0930' => 2, 'ju_1800' => 4, 'vi_0730' => 5),
            'car' => array('lu_0730' => 1, 'ma_0930' => 2, 'mi_1700' => 3, 'ju_1800' => 4, 'sa_0830' => 6),
            'dia' => array('vi_0730' => 5),
            'ele' => array('ma_0930' => 2),
            'gab' => array('lu_0830' => 1, 'ma_1700' => 2, 'ju_1800' => 4, 'vi_1800' => 5),
            'hel' => array('mi_1700' => 3),
            'jul' => array('lu_0730' => 1, 'ma_0930' => 2, 'mi_1700' => 3, 'ju_1800' => 4, 'vi_0730' => 5),
            'kar' => array('ma_1700' => 2, 'ju_1800' => 4),
            'nat' => array('lu_0830' => 1, 'mi_1700' => 3),
            'pau' => array('vi_1800' => 5),
        );

        foreach ($mapa as $key => $reservas) {
            foreach ($reservas as $horario_key => $dia_semana) {
                self::insert_reserva($alumnas[$key], $horarios[$horario_key], self::fecha_semana($lunes_actual, $dia_semana));
            }
        }

        self::insert_reserva($alumnas['ana'], $horarios['lu_0830'], self::fecha_semana(gmdate('Y-m-d', strtotime($lunes_actual . ' +1 week')), 1));
        self::insert_reserva($alumnas['fab'], $horarios['lu_0830'], self::fecha_semana(gmdate('Y-m-d', strtotime($lunes_actual . ' +1 week')), 1), 'cancelada');
        self::insert_reserva($alumnas['oli'], $horarios['vi_1800'], self::fecha_semana(gmdate('Y-m-d', strtotime($lunes_actual . ' +1 week')), 5), 'cancelada');

        $origen_bel = self::insert_reserva($alumnas['bel'], $horarios['lu_0730'], gmdate('Y-m-d', strtotime($lunes_actual . ' -3 weeks')), 'falto');
        self::insert_recuperacion($alumnas['bel'], $origen_bel, 'Ausencia avisada demo', gmdate('Y-m-d', strtotime($hoy . ' +2 months')), 'pendiente');

        $origen_hel = self::insert_reserva($alumnas['hel'], $horarios['ma_0930'], gmdate('Y-m-d', strtotime($lunes_actual . ' -2 weeks')), 'falto');
        self::insert_recuperacion($alumnas['hel'], $origen_hel, 'Viaje demo', gmdate('Y-m-d', strtotime($hoy . ' +1 month')), 'pendiente');

        $origen_nat = self::insert_reserva($alumnas['nat'], $horarios['ju_1800'], gmdate('Y-m-d', strtotime($lunes_actual . ' -5 weeks')), 'falto');
        self::insert_recuperacion($alumnas['nat'], $origen_nat, 'Recuperacion vencida demo', gmdate('Y-m-d', strtotime($hoy . ' -1 week')), 'expirada');
    }

    /**
     * Creates milestones.
     *
     * @param array<string,int> $alumnas Student IDs.
     * @param string            $hoy Current date.
     * @return void
     */
    private static function crear_milestones($alumnas, $hoy) {
        self::insert_milestone($alumnas['ana'], 'Respiracion controlada', 'Mantener respiracion estable en serie de reformer.', $hoy, 'activo');
        self::insert_milestone($alumnas['bel'], 'Plancha lograda', 'Completo la serie sin pausas.', gmdate('Y-m-d', strtotime($hoy . ' -1 week')), 'logrado');
        self::insert_milestone($alumnas['car'], 'Mayor movilidad', 'Mejor rango de movimiento en cadera.', $hoy, 'activo');
        self::insert_milestone($alumnas['gab'], 'Primer mes constante', 'Asistio cuatro semanas seguidas.', gmdate('Y-m-d', strtotime($hoy . ' -3 days')), 'logrado');
        self::insert_milestone($alumnas['mar'], 'Cumpleanos destacado', 'Caso demo para cumpleaños del mes.', $hoy, 'activo');
    }

    /**
     * Returns a date inside a given week.
     *
     * @param string $lunes Week Monday.
     * @param int    $dia_semana ISO weekday.
     * @return string
     */
    private static function fecha_semana($lunes, $dia_semana) {
        return gmdate('Y-m-d', strtotime($lunes . ' +' . ((int) $dia_semana - 1) . ' days'));
    }

    /**
     * Creates or updates a WordPress user.
     *
     * @param string $email Email.
     * @param string $nombre Display name.
     * @param string $role Role.
     * @param string $password Password.
     * @return int
     */
    private static function ensure_user($email, $nombre, $role, $password) {
        $user = get_user_by('email', $email);

        if ($user) {
            $user_id = (int) $user->ID;
            wp_update_user(
                array(
                    'ID'           => $user_id,
                    'display_name' => $nombre,
                    'first_name'   => $nombre,
                )
            );
        } else {
            $user_id = wp_insert_user(
                array(
                    'user_login'   => $email,
                    'user_email'   => $email,
                    'user_pass'    => $password,
                    'display_name' => $nombre,
                    'first_name'   => $nombre,
                    'role'         => $role,
                )
            );
        }

        if (is_wp_error($user_id)) {
            return 0;
        }

        $wp_user = new WP_User((int) $user_id);
        $wp_user->set_role($role);

        return (int) $user_id;
    }

    /**
     * Creates or updates a schedule.
     *
     * @return int
     */
    private static function ensure_horario($dia_semana, $hora_inicio, $modalidad = 'reformer', $cupo_maximo = 5, $activo = 1) {
        global $wpdb;

        $table = $wpdb->prefix . 'tp_horarios';
        $id    = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE dia_semana = %d AND hora_inicio = %s LIMIT 1",
                $dia_semana,
                $hora_inicio
            )
        );

        if ($id) {
            $wpdb->update(
                $table,
                array(
                    'modalidad'   => $modalidad,
                    'cupo_maximo' => $cupo_maximo,
                    'activo'      => $activo,
                ),
                array('id' => $id),
                array('%s', '%d', '%d'),
                array('%d')
            );

            return $id;
        }

        $wpdb->insert(
            $table,
            array(
                'dia_semana'  => $dia_semana,
                'hora_inicio' => $hora_inicio,
                'modalidad'   => $modalidad,
                'cupo_maximo' => $cupo_maximo,
                'activo'      => $activo,
            ),
            array('%d', '%s', '%s', '%d', '%d')
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Creates or updates a student profile.
     *
     * @return int
     */
    private static function ensure_alumna($email, $nombre, $plan, $activa, $extras = array()) {
        global $wpdb;

        $user_id = self::ensure_user($email, $nombre, TP_Roles::ROLE_ALUMNA, self::PASSWORD_ALUMNAS);
        $table   = $wpdb->prefix . 'tp_alumnas';
        $alumna  = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE wp_user_id = %d", $user_id));
        $data    = array_merge(
            array(
                'wp_user_id'             => $user_id,
                'plan'                   => $plan,
                'activa'                 => $activa,
                'notas'                  => 'Dato demo para pruebas locales.',
                'historia_medica'        => 'Sin lesiones relevantes declaradas.',
                'alergias'               => 'Ninguna',
                'motivo_pilates'         => 'Mejorar fuerza y movilidad.',
                'fecha_nacimiento'       => '1990-05-15',
                'fecha_inicio_pilates'   => gmdate('Y-m-d', strtotime('-6 months')),
            ),
            $extras
        );

        if ($alumna) {
            $wpdb->update(
                $table,
                $data,
                array('id' => (int) $alumna->id),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            return (int) $alumna->id;
        }

        $wpdb->insert(
            $table,
            $data,
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Clears related demo records for one student.
     *
     * @param int $alumna_id Student ID.
     * @return void
     */
    private static function limpiar_alumna($alumna_id) {
        global $wpdb;

        foreach (array('tp_milestones', 'tp_pagos', 'tp_recuperaciones', 'tp_reservas') as $table) {
            $wpdb->delete(
                $wpdb->prefix . $table,
                array('alumna_id' => (int) $alumna_id),
                array('%d')
            );
        }
    }

    /**
     * Inserts or replaces one payment.
     */
    private static function insert_pago($alumna_id, $mes, $plan, $nota) {
        global $wpdb;

        $wpdb->replace(
            $wpdb->prefix . 'tp_pagos',
            array(
                'alumna_id'  => (int) $alumna_id,
                'mes'        => $mes,
                'plan'       => $plan,
                'fecha_pago' => gmdate('Y-m-d', strtotime($mes . ' +2 days')),
                'notas'      => $nota,
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Inserts or replaces one reservation.
     */
    private static function insert_reserva($alumna_id, $horario_id, $fecha, $estado = 'reservada', $tipo = 'normal', $recuperacion_id = null, $creada_por = 'admin') {
        global $wpdb;

        $data = array(
            'alumna_id'  => (int) $alumna_id,
            'horario_id' => (int) $horario_id,
            'fecha'      => $fecha,
            'tipo'       => $tipo,
            'estado'     => $estado,
            'creada_por' => $creada_por,
        );
        $format = array('%d', '%d', '%s', '%s', '%s', '%s');

        if ($recuperacion_id) {
            $data['recuperacion_id'] = (int) $recuperacion_id;
            $format[]                = '%d';
        }

        $wpdb->replace($wpdb->prefix . 'tp_reservas', $data, $format);

        return (int) $wpdb->insert_id;
    }

    /**
     * Inserts or replaces one recovery credit.
     */
    private static function insert_recuperacion($alumna_id, $reserva_origen_id, $motivo, $fecha_limite, $estado = 'pendiente') {
        global $wpdb;

        $wpdb->replace(
            $wpdb->prefix . 'tp_recuperaciones',
            array(
                'alumna_id'         => (int) $alumna_id,
                'reserva_origen_id' => (int) $reserva_origen_id,
                'motivo'            => $motivo,
                'fecha_limite'      => $fecha_limite,
                'estado'            => $estado,
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Inserts one milestone.
     */
    private static function insert_milestone($alumna_id, $titulo, $descripcion, $fecha, $estado = 'activo') {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'tp_milestones',
            array(
                'alumna_id'   => (int) $alumna_id,
                'titulo'      => $titulo,
                'descripcion' => $descripcion,
                'fecha'       => $fecha,
                'estado'      => $estado,
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
}
