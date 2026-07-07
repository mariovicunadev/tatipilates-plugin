<?php
/**
 * WordPress admin integration.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers wp-admin menus and admin actions.
 */
class TP_Admin {

    /**
     * Registers admin hooks.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'registrar_menus'));
        add_action('admin_init', array($this, 'asegurar_schema'));
        add_action('admin_init', array($this, 'render_dashboard_partial'), 20);
        add_action('admin_enqueue_scripts', array($this, 'cargar_assets'));
        add_action('admin_post_tp_guardar_horario', array($this, 'guardar_horario'));
        add_action('admin_post_tp_cambiar_estado_horario', array($this, 'cambiar_estado_horario'));
        add_action('admin_post_tp_eliminar_horario', array($this, 'eliminar_horario'));
        add_action('admin_post_tp_guardar_alumna', array($this, 'guardar_alumna'));
        add_action('admin_post_tp_eliminar_alumna', array($this, 'eliminar_alumna'));
        add_action('admin_post_tp_registrar_pago', array($this, 'registrar_pago'));
        add_action('admin_post_tp_eliminar_pago', array($this, 'eliminar_pago'));
        add_action('admin_post_tp_marcar_asistencia', array($this, 'marcar_asistencia'));
        add_action('admin_post_tp_admin_reservar', array($this, 'reservar_clase'));
        add_action('admin_post_tp_admin_cancelar_reserva', array($this, 'cancelar_reserva_estudiante'));
        add_action('admin_post_tp_admin_reset_estudiante', array($this, 'resetear_estudiante'));
        add_action('admin_post_tp_admin_eliminar_recuperacion', array($this, 'eliminar_recuperacion_estudiante'));
        add_action('admin_post_tp_crear_milestone', array($this, 'crear_milestone'));
        add_action('admin_post_tp_cambiar_milestone', array($this, 'cambiar_milestone'));
        add_action('admin_post_tp_eliminar_milestone', array($this, 'eliminar_milestone'));
        add_action('admin_post_tp_crear_recuperacion', array($this, 'crear_recuperacion'));
        add_action('admin_post_tp_eliminar_recuperacion', array($this, 'eliminar_recuperacion'));
        add_action('admin_post_tp_admin_notificacion_vista', array($this, 'marcar_notificacion_vista'));
        add_action('admin_post_tp_admin_notificacion_eliminar', array($this, 'eliminar_notificacion'));
        add_action('admin_post_tp_guardar_notificaciones_config', array($this, 'guardar_notificaciones_config'));
        add_action('admin_post_tp_guardar_updater_config', array($this, 'guardar_updater_config'));
        add_action('admin_post_tp_seed_test_data', array($this, 'generar_datos_prueba'));
        add_action('admin_post_tp_descartar_demo_notice', array($this, 'descartar_aviso_demo'));
        add_action('admin_post_tp_backup_descargar', array($this, 'descargar_backup'));
        add_action('admin_post_tp_backup_archivo_descargar', array($this, 'descargar_backup_guardado'));
        add_action('admin_post_tp_backup_generar', array($this, 'generar_backup'));
        add_action('admin_post_tp_backup_importar', array($this, 'importar_backup'));
        add_action('admin_post_tp_guardar_uninstall_config', array($this, 'guardar_uninstall_config'));
    }

    /**
     * Keeps database tables updated while developing on an active plugin.
     *
     * @return void
     */
    public function asegurar_schema() {
        if (class_exists('TP_Activator')) {
            TP_Activator::ensure_schema();
        }
    }

    /**
     * Adds the Tati Pilates admin menu.
     *
     * @return void
     */
    public function registrar_menus() {
        $this->agregar_separador_menu(25);
        $this->agregar_separador_menu(35);
        $capability = TP_Roles::CAP_MANAGE_PILATES;

        add_menu_page(
            'Dashboard',
            'Dashboard',
            $capability,
            'tatipilates',
            array($this, 'render_dashboard'),
            'dashicons-chart-bar',
            26
        );

        add_menu_page(
            'Horarios y Cupos',
            'Horarios y Cupos',
            $capability,
            'tatipilates-horarios',
            array($this, 'render_horarios'),
            'dashicons-clock',
            27
        );

        add_menu_page(
            'Estudiantes',
            'Estudiantes',
            $capability,
            'tatipilates-alumnas',
            array($this, 'render_alumnas'),
            'dashicons-groups',
            28
        );

        add_submenu_page(
            'tatipilates-alumnas',
            'Estudiantes registrados',
            'Estudiantes registrados',
            $capability,
            'tatipilates-alumnas',
            array($this, 'render_alumnas')
        );

        add_submenu_page(
            'tatipilates-alumnas',
            'Registrar estudiante',
            'Registrar estudiante',
            $capability,
            'tatipilates-alumnas-registrar',
            array($this, 'render_alumnas_registrar')
        );

        add_submenu_page(
            'tatipilates-alumnas',
            'Cumpleaños del mes',
            'Cumpleaños del mes',
            $capability,
            'tatipilates-alumnas-cumpleanos',
            array($this, 'render_alumnas_cumpleanos')
        );

        add_menu_page(
            'Pagos',
            'Pagos',
            $capability,
            'tatipilates-pagos',
            array($this, 'render_pagos'),
            'dashicons-money-alt',
            29
        );

        add_menu_page(
            'Asistencia',
            'Asistencia',
            $capability,
            'tatipilates-asistencia',
            array($this, 'render_asistencia'),
            'dashicons-yes-alt',
            30
        );

        add_menu_page(
            'Agenda semanal',
            'Agenda semanal',
            $capability,
            'tatipilates-agenda',
            array($this, 'render_agenda'),
            'dashicons-clipboard',
            31
        );

        add_menu_page(
            'Reservar clase',
            'Reservar clase',
            $capability,
            'tatipilates-reservar',
            array($this, 'render_reservar'),
            'dashicons-calendar-alt',
            32
        );

        add_menu_page(
            'Recuperaciones',
            'Recuperaciones',
            $capability,
            'tatipilates-recuperaciones',
            array($this, 'render_recuperaciones'),
            'dashicons-backup',
            33
        );

        add_menu_page(
            'Configuración',
            'Configuración',
            $capability,
            'tatipilates-configuracion',
            array($this, 'render_configuracion'),
            'dashicons-admin-generic',
            34
        );
    }

    /**
     * Adds a visual separator around the Tati Pilates menu block.
     *
     * @param int $position Menu position.
     *
     * @return void
     */
    private function agregar_separador_menu($position) {
        global $menu;

        $index = 'separator-tatipilates-' . absint($position);

        $menu[(string) $position] = array(
            '',
            'read',
            $index,
            '',
            'wp-menu-separator',
        );
    }

    /**
     * Loads admin CSS only on plugin screens.
     *
     * @param string $hook_suffix Current admin screen hook.
     * @return void
     */
    public function cargar_assets($hook_suffix) {
        if (false === strpos($hook_suffix, 'tatipilates')) {
            return;
        }

        $admin_css = TP_PLUGIN_DIR . 'assets/css/admin.css';

        wp_enqueue_style(
            'tatipilates-admin',
            TP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            file_exists($admin_css) ? filemtime($admin_css) : TP_VERSION
        );
    }

    /**
     * Renders the temporary dashboard.
     *
     * @return void
     */
    public function render_dashboard() {
        $this->require_admin();
        include TP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Returns only the dashboard content for fast week navigation.
     *
     * @return void
     */
    public function render_dashboard_partial() {
        if (
            empty($_GET['tp_dashboard_partial']) ||
            empty($_GET['page']) ||
            'tatipilates' !== sanitize_key(wp_unslash($_GET['page']))
        ) {
            return;
        }

        $this->require_admin();
        check_admin_referer('tp_dashboard_partial');

        $tp_dashboard_partial = true;

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));

        include TP_PLUGIN_DIR . 'admin/views/dashboard.php';
        exit;
    }

    /**
     * Renders the schedule admin page.
     *
     * @return void
     */
    public function render_horarios() {
        $this->require_admin();
        include TP_PLUGIN_DIR . 'admin/views/horarios.php';
    }

    /**
     * Renders the students admin page.
     *
     * @return void
     */
    public function render_alumnas() {
        $this->require_admin();
        $tp_alumnas_vista = 'registrados';
        include TP_PLUGIN_DIR . 'admin/views/alumnas.php';
    }

    /**
     * Renders the student registration page.
     *
     * @return void
     */
    public function render_alumnas_registrar() {
        $this->require_admin();
        $tp_alumnas_vista = 'registrar';
        include TP_PLUGIN_DIR . 'admin/views/alumnas.php';
    }

    /**
     * Renders the monthly birthdays page.
     *
     * @return void
     */
    public function render_alumnas_cumpleanos() {
        $this->require_admin();
        $tp_alumnas_vista = 'cumpleanos';
        include TP_PLUGIN_DIR . 'admin/views/alumnas.php';
    }

    /**
     * Renders the payments admin page.
     *
     * @return void
     */
    public function render_pagos() {
        $this->require_admin();
        include TP_PLUGIN_DIR . 'admin/views/pagos.php';
    }

    /**
     * Renders the attendance admin page.
     *
     * @return void
     */
    public function render_asistencia() {
        $this->require_admin();
        $tp_asistencia = $this->preparar_vista_asistencia();
        extract($tp_asistencia, EXTR_SKIP);
        include TP_PLUGIN_DIR . 'admin/views/asistencia.php';
    }

    /**
     * Renders the private weekly schedule share page.
     *
     * @return void
     */
    public function render_agenda() {
        $this->require_admin();
        $tp_agenda = $this->preparar_vista_agenda();
        extract($tp_agenda, EXTR_SKIP);
        include TP_PLUGIN_DIR . 'admin/views/agenda.php';
    }

    /**
     * Renders the manual booking admin page.
     *
     * @return void
     */
    public function render_reservar() {
        $this->require_admin();
        $tp_reservar = $this->preparar_vista_reservar();
        extract($tp_reservar, EXTR_SKIP);
        include TP_PLUGIN_DIR . 'admin/views/reservar.php';
    }

    /**
     * Renders plugin configuration tools.
     *
     * @return void
     */
    public function render_configuracion() {
        $this->require_admin();
        include TP_PLUGIN_DIR . 'admin/views/configuracion.php';
    }

    /**
     * Prepares data for the attendance admin view.
     *
     * @return array<string,mixed>
     */
    private function preparar_vista_asistencia() {
        $fecha     = isset($_GET['semana']) ? sanitize_text_field(wp_unslash($_GET['semana'])) : gmdate('Y-m-d', current_time('timestamp'));
        $semana    = TP_Asistencia::semana($fecha);
        $dias      = TP_Horarios::dias_semana();
        $mensaje   = isset($_GET['tp_mensaje']) ? sanitize_text_field(wp_unslash($_GET['tp_mensaje'])) : '';
        $error     = isset($_GET['tp_error']) ? sanitize_text_field(wp_unslash($_GET['tp_error'])) : '';
        $anterior  = gmdate('Y-m-d', strtotime($semana['inicio'] . ' -7 days'));
        $siguiente = gmdate('Y-m-d', strtotime($semana['inicio'] . ' +7 days'));
        $resumen   = array(
            'reservada'    => 0,
            'asistio'      => 0,
            'falto'        => 0,
            'recuperacion' => 0,
        );

        foreach ($semana['grupos'] as $fecha_dia => $grupos_dia) {
            foreach ($grupos_dia as $indice => $grupo) {
                $ocupadas     = 0;
                $conteo_grupo = array(
                    'reservada' => 0,
                    'asistio'   => 0,
                    'falto'     => 0,
                );

                foreach ($grupo['reservas'] as $reserva) {
                    if (isset($resumen[$reserva->estado])) {
                        $resumen[$reserva->estado]++;
                    }

                    if (isset($conteo_grupo[$reserva->estado])) {
                        $conteo_grupo[$reserva->estado]++;
                    }

                    if (in_array($reserva->estado, array('reservada', 'asistio'), true)) {
                        $ocupadas++;
                    }

                    if ('recuperacion' === $reserva->tipo) {
                        $resumen['recuperacion']++;
                    }

                    $reserva->tp_estado_clase = 'tp-status';
                    $reserva->tp_estado_label = 'Reservada';

                    if ('asistio' === $reserva->estado) {
                        $reserva->tp_estado_clase .= ' tp-status-active';
                        $reserva->tp_estado_label  = 'Asistio';
                    } elseif ('falto' === $reserva->estado) {
                        $reserva->tp_estado_clase .= ' tp-status-inactive';
                        $reserva->tp_estado_label  = 'Falto';
                    }
                }

                $resumen_grupo = array();

                if ($conteo_grupo['reservada']) {
                    $resumen_grupo[] = $conteo_grupo['reservada'] . ' reservadas';
                }

                if ($conteo_grupo['asistio']) {
                    $resumen_grupo[] = $conteo_grupo['asistio'] . ' asistieron';
                }

                if ($conteo_grupo['falto']) {
                    $resumen_grupo[] = $conteo_grupo['falto'] . ' faltas';
                }

                $semana['grupos'][$fecha_dia][$indice]['ocupadas']      = $ocupadas;
                $semana['grupos'][$fecha_dia][$indice]['resumen_grupo'] = $resumen_grupo ? implode(' · ', $resumen_grupo) : 'Sin reservas';
            }
        }

        return compact('fecha', 'semana', 'dias', 'mensaje', 'error', 'anterior', 'siguiente', 'resumen');
    }

    /**
     * Prepares data for the private agenda admin view.
     *
     * @return array<string,mixed>
     */
    private function preparar_vista_agenda() {
        $fecha     = isset($_GET['semana']) ? sanitize_text_field(wp_unslash($_GET['semana'])) : gmdate('Y-m-d', current_time('timestamp'));
        $semana    = TP_Asistencia::semana($fecha);
        $dias      = TP_Horarios::dias_semana();
        $anterior  = gmdate('Y-m-d', strtotime($semana['inicio'] . ' -7 days'));
        $siguiente = gmdate('Y-m-d', strtotime($semana['inicio'] . ' +7 days'));
        $enlace    = add_query_arg(
            array(
                'vista'  => 'agenda',
                'semana' => $semana['inicio'],
            ),
            TP_Roles::portal_url()
        );

        $lineas_whatsapp = array(
            'Agenda Tati Pilates',
            TP_Pagos::formatear_fecha($semana['inicio']) . ' - ' . TP_Pagos::formatear_fecha($semana['fin']),
            '',
        );

        foreach ($semana['grupos'] as $fecha_dia => $grupos_dia) {
            $dia_nombre = $dias[(int) gmdate('N', strtotime($fecha_dia))] ?? TP_Pagos::formatear_fecha($fecha_dia);
            $lineas_whatsapp[] = $dia_nombre . ' ' . TP_Pagos::formatear_fecha($fecha_dia);

            foreach ($grupos_dia as $indice => $grupo) {
                $reservas_visibles = array();
                $ocupadas          = 0;

                foreach ($grupo['reservas'] as $reserva) {
                    if (!in_array($reserva->estado, array('reservada', 'asistio', 'falto'), true)) {
                        continue;
                    }

                    if (in_array($reserva->estado, array('reservada', 'asistio'), true)) {
                        $ocupadas++;
                    }

                    $reserva->tp_estado_clase = 'tp-status';
                    $reserva->tp_estado_label = 'Reservada';

                    if ('asistio' === $reserva->estado) {
                        $reserva->tp_estado_clase .= ' tp-status-active';
                        $reserva->tp_estado_label  = 'Asistio';
                    } elseif ('falto' === $reserva->estado) {
                        $reserva->tp_estado_clase .= ' tp-status-inactive';
                        $reserva->tp_estado_label  = 'Falto';
                    }

                    $reservas_visibles[] = $reserva;
                }

                $libres = max(0, (int) $grupo['cupo_maximo'] - $ocupadas);
                $semana['grupos'][$fecha_dia][$indice]['reservas_visibles'] = $reservas_visibles;
                $semana['grupos'][$fecha_dia][$indice]['ocupadas']          = $ocupadas;
                $semana['grupos'][$fecha_dia][$indice]['libres']            = $libres;

                $lineas_whatsapp[] = TP_Horarios::formatear_hora($grupo['hora_inicio']) . ' - ' . $ocupadas . '/' . (int) $grupo['cupo_maximo'] . ' cupos (' . $libres . ' libres)';

                if ($reservas_visibles) {
                    foreach ($reservas_visibles as $reserva) {
                        $lineas_whatsapp[] = '- ' . TP_Helpers::etiqueta_reserva_agenda($reserva);
                    }
                } else {
                    $lineas_whatsapp[] = '- Sin reservas';
                }
            }

            $lineas_whatsapp[] = '';
        }

        $texto_whatsapp = trim(implode("\n", $lineas_whatsapp));

        return compact('fecha', 'semana', 'dias', 'anterior', 'siguiente', 'enlace', 'texto_whatsapp');
    }

    /**
     * Prepares data for the manual booking admin view.
     *
     * @return array<string,mixed>
     */
    private function preparar_vista_reservar() {
        $fecha       = isset($_GET['fecha']) ? sanitize_text_field(wp_unslash($_GET['fecha'])) : gmdate('Y-m-d', current_time('timestamp'));
        $mes         = isset($_GET['mes']) ? sanitize_text_field(wp_unslash($_GET['mes'])) : gmdate('Y-m', current_time('timestamp'));
        $alumna_id   = isset($_GET['alumna_id']) ? absint($_GET['alumna_id']) : 0;
        $semana_link = isset($_GET['semana']) ? sanitize_text_field(wp_unslash($_GET['semana'])) : TP_Reservas::rango_semana($fecha)['inicio'];
        $mensaje     = isset($_GET['tp_mensaje']) ? sanitize_text_field(wp_unslash($_GET['tp_mensaje'])) : '';
        $error       = isset($_GET['tp_error']) ? sanitize_text_field(wp_unslash($_GET['tp_error'])) : '';
        $planes      = TP_Alumnas::planes();
        $dias        = TP_Horarios::dias_semana();
        $dia_fecha   = (int) gmdate('N', strtotime($fecha));
        $rango_fecha = TP_Reservas::rango_semana($fecha);
        $estudiantes = array();

        foreach (TP_Alumnas::obtener_todas() as $alumna) {
            if ((int) $alumna->activa) {
                $estudiantes[] = $alumna;
            }
        }

        $estudiantes_meta = array();

        foreach ($estudiantes as $estudiante) {
            $estudiantes_meta[(int) $estudiante->id] = array(
                'plan'   => $estudiante->plan,
                'label'  => $planes[$estudiante->plan] ?? $estudiante->plan,
                'limite' => TP_Reservas::CLASES_POR_PLAN[$estudiante->plan] ?? 0,
            );
        }

        $horarios     = TP_Horarios::obtener_todos(true);
        $horarios_dia = array();

        foreach ($horarios as $horario) {
            $horario->tp_visible              = (int) $horario->dia_semana === $dia_fecha;
            $horario->tp_fecha_semana_horario = gmdate('Y-m-d', strtotime($rango_fecha['inicio'] . ' +' . ((int) $horario->dia_semana - 1) . ' days'));
            $horario->tp_cupos_single         = $horario->tp_visible ? TP_Reservas::cupos_disponibles((int) $horario->id, $fecha) : (int) $horario->cupo_maximo;
            $horario->tp_cupos_semana         = TP_Reservas::cupos_disponibles((int) $horario->id, $horario->tp_fecha_semana_horario);
            $horario->tp_dia_nombre           = $dias[(int) $horario->dia_semana] ?? '';

            if ($horario->tp_visible) {
                $horarios_dia[] = $horario;
            }
        }

        return compact('fecha', 'mes', 'alumna_id', 'semana_link', 'mensaje', 'error', 'planes', 'dias', 'dia_fecha', 'rango_fecha', 'estudiantes', 'estudiantes_meta', 'horarios', 'horarios_dia');
    }

    /**
     * Renders the recovery credits admin page.
     *
     * @return void
     */
    public function render_recuperaciones() {
        $this->require_admin();
        $expiradas = TP_Recuperaciones::expirar_vencidas();

        if (false === $expiradas) {
            tp_log(
                'No se pudieron expirar recuperaciones antes de renderizar la vista admin.',
                array('contexto' => 'TP_Admin::render_recuperaciones'),
                'error'
            );
        }

        include TP_PLUGIN_DIR . 'admin/views/recuperaciones.php';
    }

    /**
     * Marks an admin notification as seen.
     *
     * @return void
     */
    public function marcar_notificacion_vista() {
        $notificacion_id = isset($_GET['notificacion_id']) ? absint($_GET['notificacion_id']) : 0;

        check_admin_referer('tp_admin_notificacion_vista_' . $notificacion_id);

        if (!current_user_can(TP_Roles::CAP_MANAGE_PILATES)) {
            if ($this->admin_json_requested()) {
                wp_send_json_error(array('message' => 'No tienes permisos para gestionar notificaciones.'), 403);
            }

            wp_die(esc_html__('No tienes permisos para gestionar notificaciones.', 'tatipilates'));
        }

        $actualizada = false;

        if ($notificacion_id && class_exists('TP_Notificaciones')) {
            $actualizada = TP_Notificaciones::marcar_vista($notificacion_id, 'admin');
        }

        if ($this->admin_json_requested()) {
            if ($actualizada) {
                wp_send_json_success(
                    array(
                        'notificacion_id' => $notificacion_id,
                        'estado'          => 'vista',
                    )
                );
            }

            wp_send_json_error(array('message' => 'No se pudo marcar la notificacion.'), 400);
        }

        wp_safe_redirect(add_query_arg(array('page' => 'tatipilates', 'tab' => 'notificaciones'), admin_url('admin.php')));
        exit;
    }

    /**
     * Soft-deletes an admin notification.
     *
     * @return void
     */
    public function eliminar_notificacion() {
        $notificacion_id = isset($_GET['notificacion_id']) ? absint($_GET['notificacion_id']) : 0;

        check_admin_referer('tp_admin_notificacion_eliminar_' . $notificacion_id);

        if (!current_user_can(TP_Roles::CAP_MANAGE_PILATES)) {
            if ($this->admin_json_requested()) {
                wp_send_json_error(array('message' => 'No tienes permisos para gestionar notificaciones.'), 403);
            }

            wp_die(esc_html__('No tienes permisos para gestionar notificaciones.', 'tatipilates'));
        }

        $eliminada = false;

        if ($notificacion_id && class_exists('TP_Notificaciones')) {
            $eliminada = TP_Notificaciones::eliminar($notificacion_id, 'admin');
        }

        if ($this->admin_json_requested()) {
            if ($eliminada) {
                wp_send_json_success(
                    array(
                        'notificacion_id' => $notificacion_id,
                        'estado'          => 'eliminada',
                    )
                );
            }

            wp_send_json_error(array('message' => 'No se pudo eliminar la notificacion.'), 400);
        }

        wp_safe_redirect(add_query_arg(array('page' => 'tatipilates', 'tab' => 'notificaciones'), admin_url('admin.php')));
        exit;
    }

    /**
     * Detects enhanced admin requests that expect JSON instead of redirects.
     *
     * @return bool
     */
    private function admin_json_requested() {
        $requested_with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUESTED_WITH'])) : '';
        $accept         = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : '';

        return 'XMLHttpRequest' === $requested_with || false !== strpos($accept, 'application/json');
    }

    /**
     * Saves notification reminder settings.
     *
     * @return void
     */
    public function guardar_notificaciones_config() {
        $this->require_admin();
        check_admin_referer('tp_guardar_notificaciones_config');

        $args = array('page' => 'tatipilates-configuracion');

        if (!class_exists('TP_Notificaciones')) {
            $args['tp_error'] = rawurlencode('El modulo de notificaciones no esta disponible.');
        } else {
            TP_Notificaciones::guardar_configuracion($_POST);
            $args['tp_mensaje'] = rawurlencode('Configuracion de notificaciones guardada.');
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Saves private updater settings.
     *
     * @return void
     */
    public function guardar_updater_config() {
        $this->require_admin();
        check_admin_referer('tp_guardar_updater_config');

        $args = array('page' => 'tatipilates-configuracion');

        if (!class_exists('TP_Updater')) {
            $args['tp_error'] = rawurlencode('El modulo de actualizaciones privadas no esta disponible.');
        } else {
            TP_Updater::guardar_configuracion($_POST);
            $args['tp_mensaje'] = rawurlencode('Configuracion de actualizaciones guardada.');
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles schedule create/update.
     *
     * @return void
     */
    public function guardar_horario() {
        $this->require_admin();
        check_admin_referer('tp_guardar_horario');

        $horario_id = isset($_POST['horario_id']) ? absint($_POST['horario_id']) : 0;
        $datos      = array(
            'dia_semana'  => isset($_POST['dia_semana']) ? absint($_POST['dia_semana']) : 0,
            'hora_inicio' => isset($_POST['hora_inicio']) ? sanitize_text_field(wp_unslash($_POST['hora_inicio'])) : '',
            'cupo_maximo' => isset($_POST['cupo_maximo']) ? absint($_POST['cupo_maximo']) : 0,
            'activo'      => isset($_POST['activo']) ? 1 : 0,
        );

        $resultado = $horario_id ? TP_Horarios::actualizar($horario_id, $datos) : TP_Horarios::crear($datos);
        $args      = array('page' => 'tatipilates-horarios');

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = $horario_id ? 'actualizado' : 'creado';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles schedule active/inactive toggles.
     *
     * @return void
     */
    public function cambiar_estado_horario() {
        $this->require_admin();
        check_admin_referer('tp_cambiar_estado_horario');

        $horario_id = isset($_GET['horario_id']) ? absint($_GET['horario_id']) : 0;
        $activo     = isset($_GET['activo']) ? absint($_GET['activo']) : 0;
        $resultado  = TP_Horarios::cambiar_estado($horario_id, (bool) $activo);
        $args       = array('page' => 'tatipilates-horarios');

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = $activo ? 'activado' : 'desactivado';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles schedule deletion.
     *
     * @return void
     */
    public function eliminar_horario() {
        $this->require_admin();
        check_admin_referer('tp_eliminar_horario');

        $horario_id = isset($_GET['horario_id']) ? absint($_GET['horario_id']) : 0;
        $resultado  = TP_Horarios::eliminar($horario_id);
        $args       = array('page' => 'tatipilates-horarios');

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = 'eliminado';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles student create/update.
     *
     * @return void
     */
    public function guardar_alumna() {
        $this->require_admin();
        check_admin_referer('tp_guardar_alumna');

        $alumna_id                  = isset($_POST['alumna_id']) ? absint($_POST['alumna_id']) : 0;
        $campos_medicos             = array('historia_medica', 'alergias', 'motivo_pilates');
        $puede_editar_datos_medicos = current_user_can(TP_Roles::CAP_VIEW_MEDICAL_DATA);

        foreach ($campos_medicos as $campo_medico) {
            if (array_key_exists($campo_medico, $_POST) && !$puede_editar_datos_medicos) {
                wp_die(esc_html__('No tienes permisos para editar los datos medicos.', 'tatipilates'), '', array('response' => 403));
            }
        }

        $datos = array(
            'nombre' => isset($_POST['nombre']) ? sanitize_text_field(wp_unslash($_POST['nombre'])) : '',
            'email'  => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
            'plan'   => isset($_POST['plan']) ? sanitize_text_field(wp_unslash($_POST['plan'])) : '',
            'activa' => isset($_POST['activa']) ? 1 : 0,
            'notas'  => isset($_POST['notas']) ? sanitize_textarea_field(wp_unslash($_POST['notas'])) : '',
            'fecha_nacimiento' => isset($_POST['fecha_nacimiento']) ? sanitize_text_field(wp_unslash($_POST['fecha_nacimiento'])) : '',
            'fecha_inicio_pilates' => isset($_POST['fecha_inicio_pilates']) ? sanitize_text_field(wp_unslash($_POST['fecha_inicio_pilates'])) : '',
        );

        if ($puede_editar_datos_medicos) {
            foreach ($campos_medicos as $campo_medico) {
                $datos[$campo_medico] = isset($_POST[$campo_medico]) ? sanitize_textarea_field(wp_unslash($_POST[$campo_medico])) : '';
            }
        }

        $resultado = $alumna_id ? TP_Alumnas::actualizar($alumna_id, $datos) : TP_Alumnas::crear($datos);
        $args      = array('page' => 'tatipilates-alumnas');

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
            if ($alumna_id) {
                $args['editar'] = $alumna_id;
            }
        } else {
            $args['tp_mensaje'] = $alumna_id ? 'actualizado' : 'creado';

            if (!$alumna_id && is_array($resultado)) {
                set_transient(
                    'tp_credenciales_' . get_current_user_id(),
                    array(
                        'email'     => sanitize_email($resultado['email']),
                        'password'  => sanitize_text_field($resultado['password']),
                        'portal'    => esc_url_raw($resultado['portal']),
                        'mail_sent' => !empty($resultado['mail_sent']),
                        'from_name' => isset($resultado['from_name']) ? sanitize_text_field($resultado['from_name']) : '',
                        'from_email' => isset($resultado['from_email']) ? sanitize_email($resultado['from_email']) : '',
                        'mail_error' => isset($resultado['mail_error']) ? sanitize_text_field($resultado['mail_error']) : '',
                    ),
                    10 * MINUTE_IN_SECONDS
                );
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles student deletion.
     *
     * @return void
     */
    public function eliminar_alumna() {
        $this->require_admin();
        check_admin_referer('tp_eliminar_alumna');

        $alumna_id = isset($_GET['alumna_id']) ? absint($_GET['alumna_id']) : 0;
        $resultado = TP_Alumnas::eliminar($alumna_id);
        $args      = array('page' => 'tatipilates-alumnas');

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = 'eliminado';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles milestone creation.
     *
     * @return void
     */
    public function crear_milestone() {
        $this->require_admin();
        check_admin_referer('tp_crear_milestone');

        $alumna_id = isset($_POST['alumna_id']) ? absint($_POST['alumna_id']) : 0;
        $resultado = TP_Alumnas::crear_milestone(
            array(
                'alumna_id'   => $alumna_id,
                'titulo'      => isset($_POST['titulo']) ? sanitize_text_field(wp_unslash($_POST['titulo'])) : '',
                'descripcion' => isset($_POST['descripcion']) ? sanitize_textarea_field(wp_unslash($_POST['descripcion'])) : '',
                'fecha'       => isset($_POST['fecha']) ? sanitize_text_field(wp_unslash($_POST['fecha'])) : '',
            )
        );
        $args = array(
            'page'  => 'tatipilates-alumnas',
            'ficha' => $alumna_id,
        );

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = rawurlencode('Logro creado correctamente');
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles milestone status changes.
     *
     * @return void
     */
    public function cambiar_milestone() {
        $this->require_admin();
        check_admin_referer('tp_cambiar_milestone');

        $milestone_id = isset($_GET['milestone_id']) ? absint($_GET['milestone_id']) : 0;
        $alumna_id    = isset($_GET['alumna_id']) ? absint($_GET['alumna_id']) : 0;
        $estado       = isset($_GET['estado']) ? sanitize_text_field(wp_unslash($_GET['estado'])) : 'activo';
        $resultado    = TP_Alumnas::cambiar_milestone($milestone_id, $estado);
        $args         = array(
            'page'  => 'tatipilates-alumnas',
            'ficha' => $alumna_id,
        );

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = rawurlencode('Logro actualizado correctamente');
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles milestone deletion.
     *
     * @return void
     */
    public function eliminar_milestone() {
        $this->require_admin();
        check_admin_referer('tp_eliminar_milestone');

        $milestone_id = isset($_GET['milestone_id']) ? absint($_GET['milestone_id']) : 0;
        $alumna_id    = isset($_GET['alumna_id']) ? absint($_GET['alumna_id']) : 0;
        $resultado    = TP_Alumnas::eliminar_milestone($milestone_id);
        $args         = array(
            'page'  => 'tatipilates-alumnas',
            'ficha' => $alumna_id,
        );

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = rawurlencode('Logro eliminado correctamente');
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles monthly payment registration.
     *
     * @return void
     */
    public function registrar_pago() {
        $this->require_admin();
        check_admin_referer('tp_registrar_pago');

        $datos = array(
            'alumna_id'  => isset($_POST['alumna_id']) ? absint($_POST['alumna_id']) : 0,
            'mes'        => isset($_POST['mes']) ? sanitize_text_field(wp_unslash($_POST['mes'])) : '',
            'plan'       => isset($_POST['plan']) ? sanitize_text_field(wp_unslash($_POST['plan'])) : '',
            'fecha_pago' => isset($_POST['fecha_pago']) ? sanitize_text_field(wp_unslash($_POST['fecha_pago'])) : '',
            'notas'      => isset($_POST['notas']) ? sanitize_text_field(wp_unslash($_POST['notas'])) : '',
        );

        $resultado = TP_Pagos::registrar($datos);
        $args      = array(
            'page' => 'tatipilates-pagos',
        );
        $redirect_estudiante = isset($_POST['redirect_estudiante']) ? absint($_POST['redirect_estudiante']) : 0;

        if ($redirect_estudiante) {
            $args['estudiante'] = $redirect_estudiante;
        }

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = 'registrado';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles payment deletion.
     *
     * @return void
     */
    public function eliminar_pago() {
        $this->require_admin();
        check_admin_referer('tp_eliminar_pago');

        $pago_id   = isset($_GET['pago_id']) ? absint($_GET['pago_id']) : 0;
        $resultado = TP_Pagos::eliminar($pago_id);
        $args      = array('page' => 'tatipilates-pagos');
        $estudiante_id = isset($_GET['estudiante']) ? absint($_GET['estudiante']) : 0;

        if ($estudiante_id) {
            $args['estudiante'] = $estudiante_id;
        }

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = 'eliminado';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles attendance status changes.
     *
     * @return void
     */
    public function marcar_asistencia() {
        $this->require_admin();
        check_admin_referer('tp_marcar_asistencia');

        $reserva_id = isset($_POST['reserva_id']) ? absint($_POST['reserva_id']) : 0;
        $estado     = isset($_POST['estado']) ? sanitize_text_field(wp_unslash($_POST['estado'])) : '';
        $motivo     = isset($_POST['motivo']) ? sanitize_text_field(wp_unslash($_POST['motivo'])) : '';
        $semana     = isset($_POST['semana']) ? sanitize_text_field(wp_unslash($_POST['semana'])) : '';
        $redirect_estudiante = isset($_POST['redirect_estudiante']) ? absint($_POST['redirect_estudiante']) : 0;
        $resultado  = TP_Asistencia::marcar($reserva_id, $estado, $motivo);
        $args       = array(
            'page'   => 'tatipilates-asistencia',
            'semana' => $semana,
        );

        if ($redirect_estudiante) {
            $args = array(
                'page'   => 'tatipilates-alumnas',
                'ficha'  => $redirect_estudiante,
                'semana' => TP_Reservas::rango_semana($semana)['inicio'],
            );
        }

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = 'actualizada';

            if ('falto' === $estado && class_exists('TP_Notificaciones')) {
                global $wpdb;

                $reserva = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}tp_reservas WHERE id = %d",
                        $reserva_id
                    )
                );

                TP_Notificaciones::crear_ausencia_reportada($reserva);
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles manual reservation creation from wp-admin.
     *
     * @return void
     */
    public function reservar_clase() {
        $this->require_admin();
        check_admin_referer('tp_admin_reservar');

        $alumna_id  = isset($_POST['alumna_id']) ? absint($_POST['alumna_id']) : 0;
        $horario_ids = isset($_POST['horario_ids']) && is_array($_POST['horario_ids'])
            ? array_values(array_unique(array_map('absint', wp_unslash($_POST['horario_ids']))))
            : array();
        $horario_id = $horario_ids ? (int) $horario_ids[0] : 0;
        $fecha      = isset($_POST['fecha']) ? sanitize_text_field(wp_unslash($_POST['fecha'])) : '';
        $modo       = isset($_POST['modo_reserva']) ? sanitize_text_field(wp_unslash($_POST['modo_reserva'])) : 'puntual';
        $mes        = isset($_POST['mes']) ? sanitize_text_field(wp_unslash($_POST['mes'])) : '';
        $resultado  = null;

        if ('mensual' === $modo) {
            $resultado = $this->reservar_multiples_horarios_mes($alumna_id, $horario_ids, $mes ?: $fecha);
        } elseif ('semanal' === $modo) {
            $resultado = $this->reservar_multiples_horarios_semana($alumna_id, $horario_ids, $fecha);
        } else {
            $resultado = TP_Reservas::intentar_reserva($alumna_id, $horario_id, $fecha, 'admin');
        }

        $args       = array(
            'page'  => 'tatipilates-reservar',
            'fecha' => $fecha,
        );

        if ($alumna_id) {
            $args['alumna_id'] = $alumna_id;
        }

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = rawurlencode($resultado['mensaje'] ?? __('Reserva creada correctamente.', 'tatipilates'));
            $args['semana']     = TP_Reservas::rango_semana($resultado['primera_fecha'] ?? $fecha)['inicio'];

            if (!empty($resultado['reserva_id']) && class_exists('TP_Notificaciones')) {
                TP_Notificaciones::crear_reserva_confirmada((int) $resultado['reserva_id']);
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Creates monthly reservations for one or more fixed schedules.
     *
     * @param int        $alumna_id   Student profile ID.
     * @param array<int> $horario_ids Schedule IDs.
     * @param string     $mes         Month date.
     * @return array<string,mixed>|WP_Error
     */
    private function reservar_multiples_horarios_mes($alumna_id, $horario_ids, $mes) {
        $horario_ids = array_values(array_filter(array_map('absint', $horario_ids)));

        if (!$horario_ids) {
            return new WP_Error('tp_horarios_requeridos', 'Selecciona al menos un horario fijo para reservar el mes.');
        }

        $creadas = 0;
        $omitidas = 0;
        $errores = array();
        $primera_fecha = '';

        foreach ($horario_ids as $horario_id) {
            $resultado = TP_Reservas::reservar_mes($alumna_id, $horario_id, $mes, 'admin');

            if (is_wp_error($resultado)) {
                $errores[] = $resultado->get_error_message();
                continue;
            }

            if (!empty($resultado['reserva_ids']) && class_exists('TP_Notificaciones')) {
                foreach ($resultado['reserva_ids'] as $reserva_id) {
                    TP_Notificaciones::crear_reserva_confirmada((int) $reserva_id);
                }
            }

            $creadas += (int) ($resultado['creadas'] ?? 0);
            $omitidas += (int) ($resultado['omitidas'] ?? 0);

            if (!$primera_fecha && !empty($resultado['primera_fecha'])) {
                $primera_fecha = $resultado['primera_fecha'];
            }
        }

        if (!$creadas && $errores) {
            return new WP_Error('tp_mes_no_reservado', implode(' ', array_slice($errores, 0, 3)));
        }

        $mensaje = sprintf(
            'Mes reservado: %1$d clases creadas, %2$d omitidas en %3$d horario(s).',
            $creadas,
            $omitidas,
            count($horario_ids)
        );

        if ($errores) {
            $mensaje .= ' No se reservaron algunos horarios: ' . implode(' | ', array_slice($errores, 0, 3));
        }

        return array(
            'creadas'       => $creadas,
            'omitidas'      => $omitidas,
            'mensaje'       => $mensaje,
            'primera_fecha' => $primera_fecha ?: TP_Pagos::normalizar_mes($mes),
        );
    }

    /**
     * Creates weekly reservations for selected fixed schedules.
     *
     * @param int        $alumna_id   Student profile ID.
     * @param array<int> $horario_ids Schedule IDs.
     * @param string     $fecha       Date inside the target week.
     * @return array<string,mixed>|WP_Error
     */
    private function reservar_multiples_horarios_semana($alumna_id, $horario_ids, $fecha) {
        $horario_ids = array_values(array_filter(array_map('absint', $horario_ids)));
        $rango       = TP_Reservas::rango_semana($fecha);

        if (!$horario_ids) {
            return new WP_Error('tp_horarios_requeridos', 'Selecciona al menos un horario para reservar la semana.');
        }

        $creadas = 0;
        $omitidas = 0;
        $errores = array();
        $primera_fecha = '';

        foreach ($horario_ids as $horario_id) {
            $horario = TP_Horarios::obtener($horario_id);

            if (!$horario) {
                $omitidas++;
                $errores[] = 'Horario no disponible.';
                continue;
            }

            $fecha_clase = gmdate('Y-m-d', strtotime($rango['inicio'] . ' +' . ((int) $horario->dia_semana - 1) . ' days'));

            if (!$primera_fecha) {
                $primera_fecha = $fecha_clase;
            }

            $resultado = TP_Reservas::intentar_reserva($alumna_id, $horario_id, $fecha_clase, 'admin', true);

            if (is_wp_error($resultado)) {
                $omitidas++;
                $errores[] = TP_Pagos::formatear_fecha($fecha_clase) . ' ' . TP_Horarios::formatear_hora($horario->hora_inicio) . ': ' . $resultado->get_error_message();
                continue;
            }

            if (!empty($resultado['reserva_id']) && class_exists('TP_Notificaciones')) {
                TP_Notificaciones::crear_reserva_confirmada((int) $resultado['reserva_id']);
            }

            $creadas++;
        }

        if (!$creadas && $errores) {
            return new WP_Error('tp_semana_no_reservada', implode(' ', array_slice($errores, 0, 3)));
        }

        $mensaje = sprintf(
            'Semana reservada: %1$d clases creadas, %2$d omitidas.',
            $creadas,
            $omitidas
        );

        if ($errores) {
            $mensaje .= ' No se reservaron: ' . implode(' | ', array_slice($errores, 0, 3));
        }

        return array(
            'creadas'       => $creadas,
            'omitidas'      => $omitidas,
            'mensaje'       => $mensaje,
            'primera_fecha' => $primera_fecha ?: $rango['inicio'],
        );
    }

    /**
     * Cancels one student reservation from the admin profile.
     *
     * @return void
     */
    public function cancelar_reserva_estudiante() {
        global $wpdb;

        $this->require_admin();
        check_admin_referer('tp_admin_cancelar_reserva');

        $reserva_id = isset($_POST['reserva_id']) ? absint($_POST['reserva_id']) : 0;
        $alumna_id  = isset($_POST['alumna_id']) ? absint($_POST['alumna_id']) : 0;
        $semana     = isset($_POST['semana']) ? sanitize_text_field(wp_unslash($_POST['semana'])) : gmdate('Y-m-d', current_time('timestamp'));
        $reserva    = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tp_reservas WHERE id = %d AND alumna_id = %d",
                $reserva_id,
                $alumna_id
            )
        );
        $args       = array(
            'page'   => 'tatipilates-alumnas',
            'ficha'  => $alumna_id,
            'semana' => TP_Reservas::rango_semana($semana)['inicio'],
        );

        if (!$reserva) {
            $args['tp_error'] = rawurlencode('No encontramos esa reserva para este estudiante.');
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }

        $wpdb->query('START TRANSACTION');

        $actualizada = $wpdb->update(
            $wpdb->prefix . 'tp_reservas',
            array('estado' => 'cancelada'),
            array('id' => (int) $reserva->id),
            array('%s'),
            array('%d')
        );

        if (false === $actualizada) {
            $wpdb->query('ROLLBACK');
            $args['tp_error'] = rawurlencode('No se pudo cancelar la reserva.');
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }

        if ('recuperacion' === $reserva->tipo && !empty($reserva->recuperacion_id)) {
            $recuperacion_actualizada = $wpdb->update(
                $wpdb->prefix . 'tp_recuperaciones',
                array('estado' => 'pendiente'),
                array(
                    'id'        => (int) $reserva->recuperacion_id,
                    'alumna_id' => $alumna_id,
                ),
                array('%s'),
                array('%d', '%d')
            );

            if (false === $recuperacion_actualizada) {
                $wpdb->query('ROLLBACK');
                TP_Helpers::log_db_error('TP_Admin::cancelar_reserva_estudiante');
                $args['tp_error'] = rawurlencode('No se pudo devolver la recuperacion.');
                wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
                exit;
            }
        }

        $wpdb->query('COMMIT');
        $args['tp_mensaje'] = rawurlencode('Reserva cancelada');

        if (class_exists('TP_Notificaciones')) {
            TP_Notificaciones::crear_reserva_cancelada($reserva);
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Resets a student's operational reservations and recoveries.
     *
     * @return void
     */
    public function resetear_estudiante() {
        global $wpdb;

        $this->require_admin();
        check_admin_referer('tp_admin_reset_estudiante');

        $alumna_id = isset($_POST['alumna_id']) ? absint($_POST['alumna_id']) : 0;
        $alcance   = isset($_POST['alcance']) ? sanitize_text_field(wp_unslash($_POST['alcance'])) : 'semana';
        $semana    = isset($_POST['semana']) ? sanitize_text_field(wp_unslash($_POST['semana'])) : gmdate('Y-m-d', current_time('timestamp'));
        $alumna    = TP_Alumnas::obtener($alumna_id);
        $rango     = TP_Reservas::rango_semana($semana);
        $args      = array(
            'page'   => 'tatipilates-alumnas',
            'ficha'  => $alumna_id,
            'semana' => $rango['inicio'],
        );

        if (!$alumna) {
            $args['tp_error'] = rawurlencode('El estudiante no existe.');
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }

        $wpdb->query('START TRANSACTION');
        $error = false;

        if ('todo' === $alcance) {
            $semana_actual = TP_Reservas::rango_semana(gmdate('Y-m-d', current_time('timestamp')));
            $args['semana'] = $semana_actual['inicio'];

            $reserva_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}tp_reservas
                    WHERE alumna_id = %d AND fecha >= %s AND estado = 'reservada'",
                    $alumna_id,
                    $semana_actual['inicio']
                )
            );

            if ($reserva_ids) {
                $ids = implode(',', array_map('absint', $reserva_ids));

                $recuperaciones_actualizadas = $wpdb->query(
                    "UPDATE {$wpdb->prefix}tp_recuperaciones
                    SET estado = 'pendiente'
                    WHERE alumna_id = {$alumna_id}
                    AND id IN (
                        SELECT recuperacion_id
                        FROM {$wpdb->prefix}tp_reservas
                        WHERE id IN ({$ids}) AND recuperacion_id IS NOT NULL
                    )"
                );

                if (false === $recuperaciones_actualizadas) {
                    $error = true;
                }

                if (!$error) {
                    $reservas_eliminadas = $wpdb->query("DELETE FROM {$wpdb->prefix}tp_reservas WHERE alumna_id = {$alumna_id} AND id IN ({$ids})");

                    if (false === $reservas_eliminadas) {
                        $error = true;
                    }
                }
            }
        } else {
            $reserva_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}tp_reservas
                    WHERE alumna_id = %d AND fecha BETWEEN %s AND %s",
                    $alumna_id,
                    $rango['inicio'],
                    $rango['fin']
                )
            );

            if ($reserva_ids) {
                $ids = implode(',', array_map('absint', $reserva_ids));
                $recuperaciones_eliminadas = $wpdb->query("DELETE FROM {$wpdb->prefix}tp_recuperaciones WHERE alumna_id = {$alumna_id} AND (reserva_origen_id IN ({$ids}) OR id IN (SELECT recuperacion_id FROM {$wpdb->prefix}tp_reservas WHERE id IN ({$ids}) AND recuperacion_id IS NOT NULL))");

                if (false === $recuperaciones_eliminadas) {
                    $error = true;
                }

                if (!$error) {
                    $reservas_eliminadas = $wpdb->query("DELETE FROM {$wpdb->prefix}tp_reservas WHERE alumna_id = {$alumna_id} AND id IN ({$ids})");

                    if (false === $reservas_eliminadas) {
                        $error = true;
                    }
                }
            }
        }

        if ($error) {
            $wpdb->query('ROLLBACK');
            TP_Helpers::log_db_error('TP_Admin::resetear_estudiante');
            $args['tp_error'] = rawurlencode('No se pudo resetear la ficha.');
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }

        $wpdb->query('COMMIT');
        $args['tp_mensaje'] = rawurlencode('Ficha reseteada');

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Deletes one recovery from the student profile.
     *
     * @return void
     */
    public function eliminar_recuperacion_estudiante() {
        $this->require_admin();
        check_admin_referer('tp_admin_eliminar_recuperacion');

        $recuperacion_id = isset($_POST['recuperacion_id']) ? absint($_POST['recuperacion_id']) : 0;
        $alumna_id       = isset($_POST['alumna_id']) ? absint($_POST['alumna_id']) : 0;
        $semana          = isset($_POST['semana']) ? sanitize_text_field(wp_unslash($_POST['semana'])) : gmdate('Y-m-d', current_time('timestamp'));
        $resultado       = TP_Recuperaciones::eliminar($recuperacion_id);
        $args            = array(
            'page'   => 'tatipilates-alumnas',
            'ficha'  => $alumna_id,
            'semana' => TP_Reservas::rango_semana($semana)['inicio'],
        );

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = rawurlencode('Recuperación eliminada');
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles manual recovery creation.
     *
     * @return void
     */
    public function crear_recuperacion() {
        $this->require_admin();
        check_admin_referer('tp_crear_recuperacion');

        $datos = array(
            'alumna_id'    => isset($_POST['alumna_id']) ? absint($_POST['alumna_id']) : 0,
            'fecha_falta'  => isset($_POST['fecha_falta']) ? sanitize_text_field(wp_unslash($_POST['fecha_falta'])) : '',
            'fecha_limite' => isset($_POST['fecha_limite']) ? sanitize_text_field(wp_unslash($_POST['fecha_limite'])) : '',
            'motivo'       => isset($_POST['motivo']) ? sanitize_text_field(wp_unslash($_POST['motivo'])) : '',
        );

        $resultado = TP_Recuperaciones::crear_manual($datos);
        $args      = array('page' => 'tatipilates-recuperaciones');
        $redirect_estudiante = isset($_POST['redirect_estudiante']) ? absint($_POST['redirect_estudiante']) : 0;
        $redirect_semana     = isset($_POST['semana']) ? sanitize_text_field(wp_unslash($_POST['semana'])) : '';

        if ($redirect_estudiante) {
            $args = array(
                'page'   => 'tatipilates-alumnas',
                'ficha'  => $redirect_estudiante,
                'semana' => TP_Reservas::rango_semana($redirect_semana ?: $datos['fecha_falta'])['inicio'],
            );
        }

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = 'creada';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles recovery deletion.
     *
     * @return void
     */
    public function eliminar_recuperacion() {
        $this->require_admin();
        check_admin_referer('tp_eliminar_recuperacion');

        $recuperacion_id = isset($_GET['recuperacion_id']) ? absint($_GET['recuperacion_id']) : 0;
        $resultado       = TP_Recuperaciones::eliminar($recuperacion_id);
        $args            = array('page' => 'tatipilates-recuperaciones');

        if (is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } else {
            $args['tp_mensaje'] = 'eliminada';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Refreshes local/demo data from the configuration page.
     *
     * @return void
     */
    public function generar_datos_prueba() {
        $this->require_admin();
        check_admin_referer('tp_seed_test_data');

        $args      = array('page' => 'tatipilates-configuracion');

        if (!class_exists('TP_Test_Data') || !TP_Test_Data::is_available()) {
            $args['tp_error'] = rawurlencode('Los datos de prueba no estan habilitados en este entorno.');
        } else {
            $resultado = TP_Test_Data::seed();
        }

        if (isset($resultado) && is_wp_error($resultado)) {
            $args['tp_error'] = rawurlencode($resultado->get_error_message());
        } elseif (isset($resultado)) {
            set_transient(
                'tp_demo_credentials_' . get_current_user_id(),
                array(
                    'portal'                 => esc_url_raw($resultado['portal'] ?? ''),
                    'usuarios_alumnas'       => array_map('sanitize_email', (array) ($resultado['usuarios_alumnas'] ?? array())),
                    'password_alumnas'       => (string) ($resultado['password_alumnas'] ?? ''),
                    'usuario_admin_pilates'  => sanitize_email($resultado['usuario_admin_pilates'] ?? ''),
                    'password_admin_pilates' => (string) ($resultado['password_admin_pilates'] ?? ''),
                ),
                10 * MINUTE_IN_SECONDS
            );
            $args['tp_mensaje'] = rawurlencode(
                sprintf(
                    'Datos de prueba generados: %d alumnas demo.',
                    (int) ($resultado['alumnas_creadas'] ?? 0)
                )
            );
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Dismisses the production demo hardening notice.
     *
     * @return void
     */
    public function descartar_aviso_demo() {
        $this->require_admin();
        check_admin_referer('tp_descartar_demo_notice');

        if (class_exists('TP_Test_Data')) {
            TP_Test_Data::dismiss_hardening_notice();
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'       => 'tatipilates-configuracion',
                    'tp_mensaje' => rawurlencode('Aviso de cuentas demo ocultado.'),
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Downloads a plugin-only backup JSON.
     *
     * @return void
     */
    public function descargar_backup() {
        $this->require_admin();
        check_admin_referer('tp_backup_descargar');

        if (!class_exists('TP_Backups')) {
            wp_die(esc_html__('El modulo de backups no esta disponible.', 'tatipilates'), '', array('response' => 403));
        }

        TP_Backups::descargar();
    }

    /**
     * Downloads one saved backup from private storage.
     *
     * @return void
     */
    public function descargar_backup_guardado() {
        $this->require_admin();
        check_admin_referer('tp_backup_archivo_descargar');

        if (!class_exists('TP_Backups')) {
            wp_die(esc_html__('El modulo de backups no esta disponible.', 'tatipilates'), '', array('response' => 403));
        }

        $nombre = isset($_POST['backup']) ? sanitize_file_name(wp_unslash($_POST['backup'])) : '';
        TP_Backups::descargar_archivo($nombre);
    }

    /**
     * Generates an on-demand backup file.
     *
     * @return void
     */
    public function generar_backup() {
        $this->require_admin();
        check_admin_referer('tp_backup_generar');

        $args = array('page' => 'tatipilates-configuracion');

        if (!class_exists('TP_Backups')) {
            $args['tp_error'] = rawurlencode('El modulo de backups no esta disponible.');
        } else {
            $resultado = TP_Backups::crear_archivo_diario(false);

            if (is_wp_error($resultado)) {
                $args['tp_error'] = rawurlencode($resultado->get_error_message());
            } else {
                $args['tp_mensaje'] = rawurlencode('Backup generado correctamente.');
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Imports a plugin-only backup JSON.
     *
     * @return void
     */
    public function importar_backup() {
        $this->require_admin();
        check_admin_referer('tp_backup_importar');

        $args = array('page' => 'tatipilates-configuracion');

        if (!class_exists('TP_Backups')) {
            $args['tp_error'] = rawurlencode('El modulo de backups no esta disponible.');
        } else {
            $archivo = $this->validar_upload_backup($_FILES['tp_backup_file'] ?? null);

            if (is_wp_error($archivo)) {
                $resultado = $archivo;
            } else {
                $resultado = TP_Backups::importar_archivo($archivo);
            }

            if (is_wp_error($resultado)) {
                $args['tp_error'] = rawurlencode($resultado->get_error_message());
            } else {
                $args['tp_mensaje'] = rawurlencode(
                    sprintf(
                        'Backup importado: %d usuarios revisados y %d filas sincronizadas.',
                        (int) ($resultado['usuarios'] ?? 0),
                        (int) ($resultado['filas'] ?? 0)
                    )
                );
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Validates the HTTP upload before the backup parser reads it.
     *
     * @param mixed $file Uploaded file entry.
     * @return string|WP_Error
     */
    private function validar_upload_backup($file) {
        if (!is_array($file) || empty($file['tmp_name'])) {
            return new WP_Error('tp_backup_upload_missing', 'Selecciona un archivo JSON de backup.');
        }

        $upload_error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if (UPLOAD_ERR_OK !== $upload_error) {
            $messages = array(
                UPLOAD_ERR_INI_SIZE   => 'El backup supera el limite de carga configurado en PHP.',
                UPLOAD_ERR_FORM_SIZE  => 'El backup supera el limite permitido por el formulario.',
                UPLOAD_ERR_PARTIAL    => 'El backup se recibio de forma incompleta.',
                UPLOAD_ERR_NO_FILE    => 'Selecciona un archivo JSON de backup.',
                UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene directorio temporal para recibir el backup.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir temporalmente el backup.',
                UPLOAD_ERR_EXTENSION  => 'Una extension de PHP detuvo la carga del backup.',
            );

            return new WP_Error('tp_backup_upload_error', $messages[$upload_error] ?? 'No se pudo recibir el archivo de backup.');
        }

        $tmp_path = (string) $file['tmp_name'];
        $name     = sanitize_file_name((string) ($file['name'] ?? ''));

        if ('json' !== strtolower((string) pathinfo($name, PATHINFO_EXTENSION))) {
            return new WP_Error('tp_backup_invalid_extension', 'El archivo de backup debe usar la extension .json.');
        }

        if (!is_uploaded_file($tmp_path)) {
            return new WP_Error('tp_backup_invalid_upload', 'El archivo recibido no es un upload HTTP valido.');
        }

        $declared_size = isset($file['size']) ? (int) $file['size'] : -1;
        $actual_size   = filesize($tmp_path);

        if (false === $actual_size || $declared_size < 0 || $declared_size !== $actual_size) {
            return new WP_Error('tp_backup_size_mismatch', 'El tamano recibido no coincide con el archivo temporal.');
        }

        if ($actual_size > TP_Backups::max_upload_bytes()) {
            return new WP_Error(
                'tp_backup_file_too_large',
                sprintf(
                    'El backup pesa %1$s y supera el limite permitido de %2$s.',
                    size_format($actual_size),
                    size_format(TP_Backups::max_upload_bytes())
                )
            );
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? finfo_file($finfo, $tmp_path) : false;

            if ($finfo) {
                finfo_close($finfo);
            }

            $allowed_mimes = apply_filters(
                'tp_backup_allowed_mime_types',
                array('application/json', 'text/plain')
            );

            if (!$mime || !in_array(strtolower((string) $mime), $allowed_mimes, true)) {
                return new WP_Error('tp_backup_invalid_mime', 'El contenido del archivo no tiene un MIME JSON permitido.');
            }
        }

        return $tmp_path;
    }

    /**
     * Saves uninstall data deletion preference.
     *
     * @return void
     */
    public function guardar_uninstall_config() {
        $this->require_admin();
        check_admin_referer('tp_guardar_uninstall_config');

        $borrar = isset($_POST['delete_data_on_uninstall']) && '1' === sanitize_text_field(wp_unslash($_POST['delete_data_on_uninstall']));

        if (class_exists('TP_Backups')) {
            TP_Backups::guardar_borrado_desinstalacion($borrar);
        }

        $mensaje = $borrar
            ? 'Zona peligrosa actualizada: eliminar el plugin borrara los datos.'
            : 'Zona peligrosa actualizada: eliminar el plugin conservara los datos.';

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'       => 'tatipilates-configuracion',
                    'tp_mensaje' => rawurlencode($mensaje),
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Blocks direct access for non-admin users.
     *
     * @return void
     */
    private function require_admin() {
        if (!current_user_can(TP_Roles::CAP_MANAGE_PILATES)) {
            wp_die(esc_html__('No tienes permisos para acceder a esta pagina.', 'tatipilates'));
        }
    }
}
