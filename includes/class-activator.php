<?php
/**
 * Plugin activation and deactivation tasks.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prepares the plugin database, role and scheduled tasks.
 */
class TP_Activator {

    /**
     * Runs when the plugin is activated from wp-admin.
     *
     * @return void
     */
    public static function activate() {
        self::crear_tablas();
        self::registrar_rol();
        self::crear_pagina_portal();
        self::insertar_horarios_iniciales();
        self::programar_cron();

        flush_rewrite_rules();
    }

    /**
     * Ensures database schema is current for already-active local installs.
     *
     * @return void
     */
    public static function ensure_schema() {
        $schema_version = '2026-06-02-notification-reminders';

        if (get_option('tp_schema_version') === $schema_version) {
            return;
        }

        self::crear_tablas();
        update_option('tp_schema_version', $schema_version);
    }

    /**
     * Runs when the plugin is deactivated from wp-admin.
     *
     * @return void
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('tp_cron_diario');
    }

    /**
     * Creates or updates the custom plugin tables.
     *
     * @return void
     */
    private static function crear_tablas() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_options        = 'ENGINE=InnoDB ' . $wpdb->get_charset_collate();
        $tabla_horarios       = $wpdb->prefix . 'tp_horarios';
        $tabla_alumnas        = $wpdb->prefix . 'tp_alumnas';
        $tabla_reservas       = $wpdb->prefix . 'tp_reservas';
        $tabla_recuperaciones = $wpdb->prefix . 'tp_recuperaciones';
        $tabla_pagos          = $wpdb->prefix . 'tp_pagos';
        $tabla_milestones     = $wpdb->prefix . 'tp_milestones';
        $tabla_notificaciones = $wpdb->prefix . 'tp_notificaciones';
        $tabla_users          = $wpdb->users;

        $sql_horarios = "CREATE TABLE {$tabla_horarios} (
            id int unsigned NOT NULL AUTO_INCREMENT,
            dia_semana tinyint NOT NULL COMMENT '1=Lunes, 2=Martes, ..., 6=Sabado',
            hora_inicio time NOT NULL,
            modalidad enum('reformer','mat') NOT NULL DEFAULT 'reformer',
            cupo_maximo tinyint unsigned NOT NULL DEFAULT 5,
            activo tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY dia_hora (dia_semana, hora_inicio),
            KEY activo (activo),
            KEY activo_dia_hora (activo, dia_semana, hora_inicio)
        ) {$table_options};";

        $sql_alumnas = "CREATE TABLE {$tabla_alumnas} (
            id int unsigned NOT NULL AUTO_INCREMENT,
            wp_user_id bigint unsigned NOT NULL,
            plan enum('2x','3x','4x','5x','individual') NOT NULL,
            activa tinyint(1) NOT NULL DEFAULT 1,
            notas text NULL,
            historia_medica text NULL,
            alergias text NULL,
            motivo_pilates text NULL,
            fecha_nacimiento date NULL,
            fecha_inicio_pilates date NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY wp_user_id (wp_user_id),
            KEY plan (plan),
            KEY activa (activa),
            KEY fecha_nacimiento (fecha_nacimiento),
            KEY fecha_inicio_pilates (fecha_inicio_pilates),
            CONSTRAINT fk_{$wpdb->prefix}tp_alumnas_user FOREIGN KEY (wp_user_id) REFERENCES {$tabla_users}(ID) ON DELETE CASCADE
        ) {$table_options};";

        $sql_reservas = "CREATE TABLE {$tabla_reservas} (
            id int unsigned NOT NULL AUTO_INCREMENT,
            alumna_id int unsigned NOT NULL,
            horario_id int unsigned NOT NULL,
            fecha date NOT NULL COMMENT 'Fecha exacta de la clase',
            tipo enum('normal','recuperacion') NOT NULL DEFAULT 'normal',
            recuperacion_id int unsigned NULL COMMENT 'FK a tp_recuperaciones si tipo=recuperacion',
            estado enum('reservada','asistio','falto','cancelada') NOT NULL DEFAULT 'reservada',
            creada_por enum('alumna','admin') NOT NULL DEFAULT 'alumna',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_reserva (alumna_id, horario_id, fecha),
            KEY horario_fecha (horario_id, fecha),
            KEY alumna_fecha (alumna_id, fecha),
            KEY alumna_estado_fecha (alumna_id, estado, fecha),
            KEY fecha_estado_horario (fecha, estado, horario_id),
            KEY fecha_created (fecha, created_at, id),
            KEY estado (estado),
            CONSTRAINT fk_{$wpdb->prefix}tp_reservas_alumna FOREIGN KEY (alumna_id) REFERENCES {$tabla_alumnas}(id) ON DELETE CASCADE,
            CONSTRAINT fk_{$wpdb->prefix}tp_reservas_horario FOREIGN KEY (horario_id) REFERENCES {$tabla_horarios}(id)
        ) {$table_options};";

        $sql_recuperaciones = "CREATE TABLE {$tabla_recuperaciones} (
            id int unsigned NOT NULL AUTO_INCREMENT,
            alumna_id int unsigned NOT NULL,
            reserva_origen_id int unsigned NOT NULL COMMENT 'La reserva donde falto',
            motivo varchar(255) NULL,
            fecha_limite date NOT NULL COMMENT 'Vence 3 meses despues de la falta',
            estado enum('pendiente','usada','expirada') NOT NULL DEFAULT 'pendiente',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY alumna_estado_limite (alumna_id, estado, fecha_limite),
            UNIQUE KEY unique_origen (reserva_origen_id),
            KEY estado_limite (estado, fecha_limite),
            CONSTRAINT fk_{$wpdb->prefix}tp_recuperaciones_alumna FOREIGN KEY (alumna_id) REFERENCES {$tabla_alumnas}(id) ON DELETE CASCADE,
            CONSTRAINT fk_{$wpdb->prefix}tp_recuperaciones_reserva FOREIGN KEY (reserva_origen_id) REFERENCES {$tabla_reservas}(id)
        ) {$table_options};";

        $sql_pagos = "CREATE TABLE {$tabla_pagos} (
            id int unsigned NOT NULL AUTO_INCREMENT,
            alumna_id int unsigned NOT NULL,
            mes date NOT NULL COMMENT 'Primer dia del mes, ej: 2025-05-01',
            plan enum('2x','3x','4x','5x','individual') NOT NULL,
            fecha_pago date NOT NULL COMMENT 'Fecha en que Tatiana confirma el pago',
            notas varchar(255) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_pago_mes (alumna_id, mes),
            KEY mes (mes),
            KEY mes_fecha_id (mes, fecha_pago, id),
            CONSTRAINT fk_{$wpdb->prefix}tp_pagos_alumna FOREIGN KEY (alumna_id) REFERENCES {$tabla_alumnas}(id) ON DELETE CASCADE
        ) {$table_options};";

        $sql_milestones = "CREATE TABLE {$tabla_milestones} (
            id int unsigned NOT NULL AUTO_INCREMENT,
            alumna_id int unsigned NOT NULL,
            titulo varchar(120) NOT NULL,
            descripcion text NULL,
            fecha date NOT NULL,
            estado enum('activo','logrado') NOT NULL DEFAULT 'activo',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY alumna_fecha (alumna_id, fecha),
            KEY alumna_estado_fecha (alumna_id, estado, fecha),
            KEY estado_fecha_id (estado, fecha, id),
            KEY estado (estado),
            CONSTRAINT fk_{$wpdb->prefix}tp_milestones_alumna FOREIGN KEY (alumna_id) REFERENCES {$tabla_alumnas}(id) ON DELETE CASCADE
        ) {$table_options};";

        $sql_notificaciones = "CREATE TABLE {$tabla_notificaciones} (
            id int unsigned NOT NULL AUTO_INCREMENT,
            alumna_id int unsigned NULL,
            tipo varchar(60) NOT NULL,
            referencia varchar(120) NULL,
            titulo varchar(140) NOT NULL,
            mensaje text NOT NULL,
            url_accion varchar(255) NULL,
            canal varchar(30) NOT NULL DEFAULT 'interno',
            visible_admin tinyint(1) NOT NULL DEFAULT 1,
            visible_alumna tinyint(1) NOT NULL DEFAULT 1,
            visto_admin tinyint(1) NOT NULL DEFAULT 0,
            visto_alumna tinyint(1) NOT NULL DEFAULT 0,
            eliminado_admin tinyint(1) NOT NULL DEFAULT 0,
            eliminado_alumna tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            visto_admin_at datetime NULL,
            visto_alumna_at datetime NULL,
            PRIMARY KEY  (id),
            KEY alumna_created (alumna_id, created_at),
            UNIQUE KEY unique_referencia (tipo, referencia),
            KEY admin_estado_fecha (visible_admin, eliminado_admin, visto_admin, created_at),
            KEY alumna_estado_fecha (alumna_id, visible_alumna, eliminado_alumna, visto_alumna, created_at),
            KEY tipo_fecha (tipo, created_at),
            CONSTRAINT fk_{$wpdb->prefix}tp_notificaciones_alumna FOREIGN KEY (alumna_id) REFERENCES {$tabla_alumnas}(id) ON DELETE CASCADE
        ) {$table_options};";

        dbDelta($sql_horarios);
        dbDelta($sql_alumnas);
        dbDelta($sql_reservas);
        dbDelta($sql_recuperaciones);
        dbDelta($sql_pagos);
        dbDelta($sql_milestones);
        dbDelta($sql_notificaciones);

        $tablas_requeridas = array(
            $tabla_horarios,
            $tabla_alumnas,
            $tabla_reservas,
            $tabla_recuperaciones,
            $tabla_pagos,
            $tabla_milestones,
            $tabla_notificaciones,
        );

        foreach ($tablas_requeridas as $tabla) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tabla)) !== $tabla) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                deactivate_plugins(plugin_basename(TP_PLUGIN_FILE));
                wp_die(
                    esc_html(sprintf(__('No se pudo crear la tabla requerida: %s', 'tatipilates'), $tabla)),
                    esc_html__('Error de activación de Tati Pilates', 'tatipilates')
                );
            }
        }

        self::migrar_indices();

        update_option('tp_db_version', TP_VERSION);
    }

    /**
     * Adds indexes that dbDelta may not create reliably on existing installs.
     *
     * @return void
     */
    private static function migrar_indices() {
        global $wpdb;

        $indices = array(
            $wpdb->prefix . 'tp_horarios' => array(
                'activo_dia_hora' => 'ADD KEY activo_dia_hora (activo, dia_semana, hora_inicio)',
            ),
            $wpdb->prefix . 'tp_reservas' => array(
                'alumna_estado_fecha' => 'ADD KEY alumna_estado_fecha (alumna_id, estado, fecha)',
                'fecha_estado_horario' => 'ADD KEY fecha_estado_horario (fecha, estado, horario_id)',
                'fecha_created' => 'ADD KEY fecha_created (fecha, created_at, id)',
            ),
            $wpdb->prefix . 'tp_recuperaciones' => array(
                'unique_origen' => 'ADD UNIQUE KEY unique_origen (reserva_origen_id)',
            ),
            $wpdb->prefix . 'tp_pagos' => array(
                'mes_fecha_id' => 'ADD KEY mes_fecha_id (mes, fecha_pago, id)',
            ),
            $wpdb->prefix . 'tp_milestones' => array(
                'alumna_estado_fecha' => 'ADD KEY alumna_estado_fecha (alumna_id, estado, fecha)',
                'estado_fecha_id' => 'ADD KEY estado_fecha_id (estado, fecha, id)',
            ),
            $wpdb->prefix . 'tp_notificaciones' => array(
                'unique_referencia' => 'ADD UNIQUE KEY unique_referencia (tipo, referencia)',
                'admin_estado_fecha' => 'ADD KEY admin_estado_fecha (visible_admin, eliminado_admin, visto_admin, created_at)',
                'alumna_estado_fecha' => 'ADD KEY alumna_estado_fecha (alumna_id, visible_alumna, eliminado_alumna, visto_alumna, created_at)',
                'tipo_fecha' => 'ADD KEY tipo_fecha (tipo, created_at)',
            ),
        );
        $errores_previos = $wpdb->suppress_errors(true);

        foreach ($indices as $tabla => $indices_tabla) {
            foreach ($indices_tabla as $nombre_indice => $alter) {
                $indice = $wpdb->get_var(
                    $wpdb->prepare(
                        "SHOW INDEX FROM {$tabla} WHERE Key_name = %s",
                        $nombre_indice
                    )
                );

                if ($indice) {
                    continue;
                }

                $resultado = $wpdb->query("ALTER TABLE {$tabla} {$alter}");

                if (false === $resultado) {
                    TP_Helpers::log_db_error('TP_Activator::migrar_indices');
                }
            }
        }

        $wpdb->suppress_errors($errores_previos);
    }

    /**
     * Registers custom plugin roles.
     *
     * @return void
     */
    private static function registrar_rol() {
        if (class_exists('TP_Roles')) {
            TP_Roles::registrar_roles();
        }
    }

    /**
     * Creates the public student portal page.
     *
     * @return void
     */
    private static function crear_pagina_portal() {
        $pagina = get_page_by_path('mi-pilates');

        if ($pagina) {
            update_option('tp_portal_page_id', (int) $pagina->ID);
            return;
        }

        $page_id = wp_insert_post(
            array(
                'post_title'   => 'Mi Pilates',
                'post_name'    => 'mi-pilates',
                'post_content' => '[tatipilates_portal]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            )
        );

        if (!is_wp_error($page_id) && $page_id) {
            update_option('tp_portal_page_id', (int) $page_id);
        }
    }

    /**
     * Seeds Tatiana's initial class schedule when the table is empty.
     *
     * @return void
     */
    private static function insertar_horarios_iniciales() {
        global $wpdb;

        $tabla_horarios = $wpdb->prefix . 'tp_horarios';
        $total          = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_horarios}");

        if ($total > 0) {
            return;
        }

        $horarios = array();
        $horas    = array('07:30:00', '08:30:00', '09:30:00', '17:00:00', '18:00:00');

        foreach (range(1, 5) as $dia_semana) {
            foreach ($horas as $hora_inicio) {
                $horarios[] = array(
                    'dia_semana'  => $dia_semana,
                    'hora_inicio' => $hora_inicio,
                    'modalidad'   => 'reformer',
                    'cupo_maximo' => 5,
                    'activo'      => 1,
                );
            }
        }

        $horarios[] = array(
            'dia_semana'  => 6,
            'hora_inicio' => '08:30:00',
            'modalidad'   => 'mat',
            'cupo_maximo' => 5,
            'activo'      => 1,
        );

        foreach ($horarios as $horario) {
            $insertado = $wpdb->insert(
                $tabla_horarios,
                $horario,
                array('%d', '%s', '%s', '%d', '%d')
            );

            if (false === $insertado) {
                TP_Helpers::log_db_error('TP_Activator::insertar_horarios_iniciales');
            }
        }
    }

    /**
     * Schedules the daily recovery expiration event.
     *
     * @return void
     */
    private static function programar_cron() {
        if (!wp_next_scheduled('tp_cron_diario')) {
            wp_schedule_event(time(), 'daily', 'tp_cron_diario');
        }
    }
}
