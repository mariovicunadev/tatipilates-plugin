<?php
/**
 * Public student portal.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders and handles the Mi Pilates student portal.
 */
class TP_Portal {

    /**
     * Registers public hooks.
     */
    public function __construct() {
        add_action('init', array($this, 'asegurar_pagina_portal'));
        add_shortcode('tatipilates_portal', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'cargar_assets'));
        add_filter('body_class', array($this, 'agregar_body_class_portal'));
        add_action('init', array($this, 'servir_pwa_endpoints'));
        add_action('template_redirect', array($this, 'evitar_cache_portal'));
        add_action('wp_head', array($this, 'imprimir_pwa_head'), 99);
        add_action('wp_footer', array($this, 'imprimir_registro_service_worker'));
        add_filter('auth_cookie_expiration', array($this, 'duracion_cookie_portal'), 10, 3);
        add_action('admin_post_nopriv_tp_portal_login', array($this, 'login'));
        add_action('admin_post_nopriv_tp_portal_lostpassword', array($this, 'lostpassword'));
        add_action('admin_post_nopriv_tp_portal_resetpassword', array($this, 'resetpassword'));
        add_action('admin_post_tp_portal_resetpassword', array($this, 'resetpassword'));
        add_action('login_form_rp', array($this, 'redirigir_reset_wordpress'));
        add_action('login_form_resetpass', array($this, 'redirigir_reset_wordpress'));
        add_action('admin_post_tp_portal_reservar', array($this, 'reservar'));
        add_action('admin_post_tp_portal_reservar_mes', array($this, 'reservar_mes'));
        add_action('admin_post_tp_portal_cancelar_reserva', array($this, 'cancelar_reserva'));
        add_action('admin_post_tp_portal_reportar_ausencia', array($this, 'reportar_ausencia'));
        add_action('admin_post_tp_portal_notificacion_vista', array($this, 'marcar_notificacion_vista'));
        add_action('admin_post_tp_portal_notificacion_eliminar', array($this, 'eliminar_notificacion'));
    }

    /**
     * Ensures the portal page exists even on installs activated before this class existed.
     *
     * @return void
     */
    public function asegurar_pagina_portal() {
        if (get_page_by_path('mi-pilates')) {
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
     * Loads frontend CSS only for the portal page.
     *
     * @return void
     */
    public function cargar_assets() {
        if (!is_page('mi-pilates')) {
            return;
        }

        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'tatipilates-portal',
            TP_PLUGIN_URL . 'assets/css/portal.css',
            array('dashicons'),
            filemtime(TP_PLUGIN_DIR . 'assets/css/portal.css')
        );

        wp_enqueue_script(
            'tatipilates-portal',
            TP_PLUGIN_URL . 'assets/js/portal.js',
            array(),
            filemtime(TP_PLUGIN_DIR . 'assets/js/portal.js'),
            true
        );

        wp_localize_script(
            'tatipilates-portal',
            'TPPortalPWA',
            array(
                'serviceWorkerUrl' => '/?tp_portal_sw=1',
                'scope'            => trailingslashit(wp_parse_url(TP_Roles::portal_url(), PHP_URL_PATH) ?: '/mi-pilates/'),
                'adminPostUrl'     => admin_url('admin-post.php'),
            )
        );
    }

    /**
     * Adds a body class so the portal can hide the public site chrome.
     *
     * @param array<int,string> $classes Body classes.
     * @return array<int,string>
     */
    public function agregar_body_class_portal($classes) {
        if (is_page('mi-pilates')) {
            $classes[] = 'tp-mi-pilates-page';

            if ($this->usuario_actual_tiene_panel_activo()) {
                $classes[] = 'tp-mi-pilates-authenticated';
            } else {
                $classes[] = 'tp-mi-pilates-login';
            }
        }

        return $classes;
    }

    /**
     * Serves lightweight PWA endpoints without adding files to the WordPress root.
     *
     * @return void
     */
    public function servir_pwa_endpoints() {
        if (isset($_GET['tp_portal_manifest'])) {
            $this->servir_manifest();
        }

        if (isset($_GET['tp_portal_sw'])) {
            $this->servir_service_worker();
        }

        if (isset($_GET['tp_portal_offline'])) {
            $this->servir_offline();
        }
    }

    /**
     * Prevents optimization plugins from caching private portal responses.
     *
     * @return void
     */
    public function evitar_cache_portal() {
        if (!is_page('mi-pilates')) {
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }

        nocache_headers();
    }

    /**
     * Prints PWA metadata for the student portal only.
     *
     * @return void
     */
    public function imprimir_pwa_head() {
        if (!is_page('mi-pilates')) {
            return;
        }

        $manifest_url   = '/?tp_portal_manifest=1';
        $icon_url       = wp_make_link_relative(TP_PLUGIN_URL . 'assets/icons/mi-pilates-apple-touch-icon.png');
        $icon_192_url   = wp_make_link_relative(TP_PLUGIN_URL . 'assets/icons/mi-pilates-icon-192.png');
        $maskable_url   = wp_make_link_relative(TP_PLUGIN_URL . 'assets/icons/mi-pilates-maskable-512.png');
        $tile_image_url = wp_make_link_relative(TP_PLUGIN_URL . 'assets/icons/mi-pilates-tile-270.png');
        ?>
        <link rel="manifest" href="<?php echo esc_url($manifest_url); ?>">
        <meta name="theme-color" content="#8eae8c">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr__('Mi Pilates', 'tatipilates'); ?>">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="icon" href="<?php echo esc_url($icon_192_url); ?>" sizes="192x192" type="image/png">
        <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url($icon_url); ?>">
        <link rel="apple-touch-icon-precomposed" sizes="180x180" href="<?php echo esc_url($icon_url); ?>">
        <meta name="msapplication-TileImage" content="<?php echo esc_url($tile_image_url); ?>">
        <meta name="msapplication-TileColor" content="#f5f0ea">
        <meta name="mask-icon" content="<?php echo esc_url($maskable_url); ?>" color="#8eae8c">
        <?php
    }

    /**
     * Registers the service worker from the portal page.
     *
     * @return void
     */
    public function imprimir_registro_service_worker() {
        if (!is_page('mi-pilates')) {
            return;
        }
        ?>
        <div class="tp-install-app" data-tp-install-shell hidden>
            <button class="tp-install-app-button" type="button" data-tp-install-app>
                <?php echo esc_html__('Instalar app', 'tatipilates'); ?>
            </button>
        </div>
        <div class="tp-install-modal" id="tp-install-instructions" hidden aria-hidden="true">
            <div class="tp-install-dialog" role="dialog" aria-modal="true" aria-labelledby="tp-install-title">
                <button class="tp-install-close" type="button" data-tp-install-close aria-label="<?php echo esc_attr__('Cerrar instrucciones', 'tatipilates'); ?>">&times;</button>
                <p class="tp-kicker"><?php echo esc_html__('Mi Pilates', 'tatipilates'); ?></p>
                <h2 id="tp-install-title"><?php echo esc_html__('Instalar en iPhone', 'tatipilates'); ?></h2>
                <ol>
                    <li><?php echo esc_html__('Toca el boton Compartir de Safari.', 'tatipilates'); ?></li>
                    <li><?php echo esc_html__('Elige Agregar a pantalla de inicio.', 'tatipilates'); ?></li>
                    <li><?php echo esc_html__('Toca Agregar para abrir Mi Pilates como app.', 'tatipilates'); ?></li>
                </ol>
                <button class="tp-button tp-button-soft" type="button" data-tp-install-dismiss><?php echo esc_html__('No mostrar de nuevo', 'tatipilates'); ?></button>
            </div>
        </div>
        <?php if (isset($_GET['tp_pwa_debug']) && current_user_can('manage_options')) : ?>
            <script>
                (function () {
                    var panel = document.createElement('pre');
                    panel.id = 'tp-pwa-debug';
                    panel.style.cssText = 'position:fixed;right:12px;bottom:12px;z-index:999999;max-width:min(440px,calc(100vw - 24px));max-height:45vh;overflow:auto;margin:0;padding:12px;border:1px solid #8eae8c;border-radius:10px;background:#fffdf9;color:#202033;font:12px/1.4 monospace;white-space:pre-wrap;box-shadow:0 12px 28px rgba(0,0,0,.18);';
                    panel.textContent = 'Revisando PWA...';
                    document.addEventListener('DOMContentLoaded', function () {
                        document.body.appendChild(panel);
                    });

                    window.addEventListener('load', function () {
                        var result = {
                            href: window.location.href,
                            isSecureContext: window.isSecureContext,
                            hasServiceWorker: 'serviceWorker' in navigator,
                            hasCaches: 'caches' in window,
                            displayModeStandalone: window.matchMedia('(display-mode: standalone)').matches,
                            manifest: document.querySelector('link[rel="manifest"]') ? document.querySelector('link[rel="manifest"]').href : null,
                            themeColor: document.querySelector('meta[name="theme-color"]') ? document.querySelector('meta[name="theme-color"]').content : null,
                            registration: null,
                            error: null
                        };

                        if (!result.hasServiceWorker) {
                            panel.textContent = JSON.stringify(result, null, 2);
                            return;
                        }

                        navigator.serviceWorker.getRegistration(window.TPPortalPWA.scope).then(function (registration) {
                            result.controller = !!navigator.serviceWorker.controller;
                            result.registration = registration ? {
                                scope: registration.scope,
                                active: !!registration.active,
                                installing: !!registration.installing,
                                waiting: !!registration.waiting
                            } : null;
                            panel.textContent = JSON.stringify(result, null, 2);
                        }).catch(function (error) {
                            result.error = error && error.message ? error.message : String(error);
                            panel.textContent = JSON.stringify(result, null, 2);
                        });
                    });
                }());
            </script>
        <?php endif; ?>
        <?php
    }

    /**
     * Extends remembered student sessions so Mi Pilates behaves like a mobile app.
     *
     * @param int  $expiration Current expiration length in seconds.
     * @param int  $user_id    WordPress user ID.
     * @param bool $remember   Whether the login requested a persistent cookie.
     * @return int
     */
    public function duracion_cookie_portal($expiration, $user_id, $remember) {
        if (!$remember || !$user_id) {
            return $expiration;
        }

        $user = get_user_by('id', (int) $user_id);

        if (!$user || !TP_Roles::usuario_es_alumna($user)) {
            return $expiration;
        }

        return 90 * DAY_IN_SECONDS;
    }

    /**
     * Checks if the current visitor has a valid portal view that should hide site chrome.
     *
     * @return bool
     */
    private function usuario_actual_tiene_panel_activo() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (isset($_GET['tp_reset']) && '1' === sanitize_text_field(wp_unslash($_GET['tp_reset']))) {
            return false;
        }

        if (!TP_Roles::usuario_actual_es_alumna()) {
            return current_user_can('read');
        }

        $estudiante = self::estudiante_actual();

        return $estudiante && (int) $estudiante->activa;
    }

    /**
     * Renders the portal shortcode.
     *
     * @return string
     */
    public function render_shortcode() {
        $stylesheet = $this->stylesheet_tag();

        if (isset($_GET['tp_reset']) && '1' === sanitize_text_field(wp_unslash($_GET['tp_reset']))) {
            return $stylesheet . $this->render_login();
        }

        if (!is_user_logged_in()) {
            return $stylesheet . $this->render_login();
        }

        if (!TP_Roles::usuario_actual_es_alumna()) {
            if (!current_user_can(TP_Roles::CAP_MANAGE_PILATES)) {
                wp_die(
                    esc_html__('No tienes permisos para ver esta agenda.', 'tatipilates'),
                    esc_html__('Acceso no autorizado', 'tatipilates'),
                    array('response' => 403)
                );
            }

            return $this->render_agenda_admin($stylesheet);
        }

        $estudiante = self::estudiante_actual();

        if (!$estudiante || !(int) $estudiante->activa) {
            return $stylesheet . '<div class="tp-portal"><p class="tp-portal-alert">Tu perfil no esta activo. Contacta a Tatiana.</p></div>';
        }

        $fecha_hoy        = gmdate('Y-m-d', current_time('timestamp'));
        $fecha_portal     = 7 === (int) gmdate('N', strtotime($fecha_hoy)) ? gmdate('Y-m-d', strtotime($fecha_hoy . ' +1 day')) : $fecha_hoy;
        $semana_actual    = TP_Reservas::rango_semana($fecha_portal);
        $fecha_base       = isset($_GET['semana']) ? sanitize_text_field(wp_unslash($_GET['semana'])) : $fecha_portal;
        $semana           = TP_Reservas::rango_semana($fecha_base);
        $vista            = isset($_GET['vista']) ? sanitize_key(wp_unslash($_GET['vista'])) : 'reservas';

        if (!in_array($vista, array('reservas', 'agenda', 'notificaciones'), true)) {
            $vista = 'reservas';
        }

        $semanas_disponibles = self::semanas_disponibles($semana_actual['inicio'], $semana['inicio']);
        $modo_historial      = $semana['fin'] < $semana_actual['inicio'];
        $estado_pago         = TP_Pagos::estado_para_reservas((int) $estudiante->id, $semana['inicio']);
        $planes              = TP_Alumnas::planes();
        $agenda              = self::agenda_semana((int) $estudiante->id, $semana['inicio']);
        $agenda_publica      = TP_Asistencia::semana($semana['inicio']);
        $mis_reservas        = self::reservas_estudiante_semana((int) $estudiante->id, $semana['inicio']);
        $recuperaciones      = self::recuperaciones_pendientes((int) $estudiante->id);
        $logros              = TP_Alumnas::milestones((int) $estudiante->id);
        $mensaje             = isset($_GET['tp_mensaje']) ? sanitize_text_field(wp_unslash($_GET['tp_mensaje'])) : '';
        $error               = isset($_GET['tp_error']) ? sanitize_text_field(wp_unslash($_GET['tp_error'])) : '';
        $error_code          = isset($_GET['tp_error_code']) ? sanitize_key(wp_unslash($_GET['tp_error_code'])) : '';
        $plan_individual     = 'individual' === $estudiante->plan;
        $tp_portal           = self::preparar_vista_portal(
            $estudiante,
            $estado_pago,
            $mis_reservas,
            $recuperaciones,
            $semanas_disponibles,
            $semana,
            $vista,
            $modo_historial,
            $agenda,
            $agenda_publica
        );
        extract($tp_portal, EXTR_OVERWRITE);

        ob_start();
        include TP_PLUGIN_DIR . 'public/views/portal.php';
        return $stylesheet . ob_get_clean();
    }

    /**
     * Prepares derived values for the student portal view.
     *
     * @param object             $estudiante           Student profile.
     * @param array<string,mixed> $estado_pago          Payment state.
     * @param array<int,object>  $mis_reservas         Student reservations.
     * @param array<int,object>  $recuperaciones       Recovery credits.
     * @param array<int,array>   $semanas_disponibles  Week picker options.
     * @param array<string,mixed> $semana               Current week.
     * @param string             $vista                Current view.
     * @param bool               $modo_historial       Whether week is historical.
     * @param array<int,array>   $agenda               Student agenda.
     * @param array<string,mixed> $agenda_publica       Public agenda.
     * @return array<string,mixed>
     */
    private static function preparar_vista_portal($estudiante, $estado_pago, $mis_reservas, $recuperaciones, $semanas_disponibles, $semana, $vista, $modo_historial, $agenda, $agenda_publica) {
        $hoy          = gmdate('Y-m-d', current_time('timestamp'));
        $puede_pagar  = !empty($estado_pago['puede_reservar']);
        $mensaje_tipo = isset($_GET['tp_mensaje_tipo']) ? sanitize_key(wp_unslash($_GET['tp_mensaje_tipo'])) : 'success';
        $estado_label = array(
            'pagado'     => 'Pago al dia',
            'gracia'     => 'Pago pendiente',
            'vencido'    => 'Pago vencido',
            'individual' => 'Clase individual',
        );

        if ('futuro' === ($estado_pago['contexto'] ?? '')) {
            $estado_label['gracia'] = 'Mes por pagar';
        }

        $reservas_semana_total             = 0;
        $ausencias_semana_total            = 0;
        $recuperaciones_semana_total       = 0;
        $reservas_reportables              = array();
        $recuperaciones_disponibles_total  = count($recuperaciones);
        $recuperaciones_semana_capacidad   = 0;
        $recuperaciones_progreso           = 0;
        $cupo_plan_semana                  = TP_Reservas::cupo_plan_semana((int) $estudiante->id, $semana['inicio'], $estudiante->plan);
        $limite_semana                     = $cupo_plan_semana['limite'];
        $portal_base_url                   = 'agenda' === $vista ? add_query_arg('vista', 'agenda', TP_Roles::portal_url()) : TP_Roles::portal_url();
        $semana_anterior_url               = add_query_arg('semana', TP_Helpers::mover_semanas($semana['inicio'], -1), $portal_base_url);
        $semana_siguiente_url              = add_query_arg('semana', TP_Helpers::mover_semanas($semana['inicio'], 1), $portal_base_url);
        $agenda_url                        = add_query_arg(
            array(
                'vista'  => 'agenda',
                'semana' => $semana['inicio'],
            ),
            TP_Roles::portal_url()
        );
        $reservas_url                      = add_query_arg('semana', $semana['inicio'], TP_Roles::portal_url());
        $notificaciones_url                = add_query_arg('vista', 'notificaciones', TP_Roles::portal_url());
        $logout_url                        = wp_logout_url(TP_Roles::portal_url());
        $logo_url                          = content_url('uploads/2026/04/1775228635121-2048x1587.png');
        $notificaciones                    = class_exists('TP_Notificaciones') ? TP_Notificaciones::listar_alumna((int) $estudiante->id, array('limit' => 50)) : array();
        $notificaciones_recientes          = class_exists('TP_Notificaciones') ? TP_Notificaciones::listar_alumna((int) $estudiante->id, array('limit' => 5, 'pendientes' => true)) : array();
        $notificaciones_no_vistas          = class_exists('TP_Notificaciones') ? TP_Notificaciones::contar_no_vistas('alumna', (int) $estudiante->id) : 0;
        $semana['label_rango']             = self::formatear_rango_corto($semana['inicio'], $semana['fin']);
        $ocultar_dias_pasados_reservas     = 'reservas' === $vista && !$modo_historial && $semana['inicio'] <= $hoy && $semana['fin'] >= $hoy;

        foreach ($mis_reservas as $reserva) {
            if ('normal' === $reserva->tipo && 'falto' === $reserva->estado) {
                $ausencias_semana_total++;
            }

            if ('recuperacion' === $reserva->tipo && in_array($reserva->estado, array('reservada', 'asistio'), true)) {
                $recuperaciones_semana_total++;
            }

            if ('reservada' === $reserva->estado && $reserva->fecha >= $hoy) {
                $reservas_reportables[] = $reserva;
            }
        }

        $reservas_semana_total             = $cupo_plan_semana['usadas'];
        $recuperaciones_semana_capacidad   = $recuperaciones_disponibles_total + $recuperaciones_semana_total;
        $recuperaciones_progreso           = $recuperaciones_semana_capacidad ? min(100, round(($recuperaciones_semana_total / $recuperaciones_semana_capacidad) * 100)) : 0;
        $progreso                          = $limite_semana ? min(100, round(($reservas_semana_total / $limite_semana) * 100)) : 100;

        foreach ($semanas_disponibles as $indice => $semana_opcion) {
            $semanas_disponibles[$indice]['seleccionada'] = $semana_opcion['inicio'] === $semana['inicio'];
            $semanas_disponibles[$indice]['label_rango']  = self::formatear_rango_corto($semana_opcion['inicio'], $semana_opcion['fin']);
        }

        foreach ($agenda as $indice_dia => $dia) {
            $horarios_visibles = array();

            if ($ocultar_dias_pasados_reservas && $dia['fecha'] < $hoy) {
                $agenda[$indice_dia]['horarios_visibles'] = array();
                $agenda[$indice_dia]['mostrar']           = false;
                continue;
            }

            foreach ($dia['horarios'] as $slot) {
                if ($modo_historial && empty($slot['reserva'])) {
                    continue;
                }

                $slot['fecha_pasada'] = $dia['fecha'] < $hoy;
                $slot['lleno']        = (int) $slot['cupos'] <= 0;
                $slot['clases']       = self::clases_slot_portal($slot['reserva']);
                $horarios_visibles[]  = $slot;
            }

            $agenda[$indice_dia]['horarios_visibles'] = $horarios_visibles;
            $agenda[$indice_dia]['mostrar']           = !$modo_historial || !empty($horarios_visibles);
        }

        foreach ($agenda_publica['grupos'] as $fecha_dia => $grupos_dia) {
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

                    $reservas_visibles[] = $reserva;
                }

                $agenda_publica['grupos'][$fecha_dia][$indice]['reservas_visibles'] = $reservas_visibles;
                $agenda_publica['grupos'][$fecha_dia][$indice]['ocupadas']          = $ocupadas;
                $agenda_publica['grupos'][$fecha_dia][$indice]['libres']            = max(0, (int) $grupo['cupo_maximo'] - $ocupadas);
            }
        }

        return compact(
            'hoy',
            'puede_pagar',
            'mensaje_tipo',
            'estado_label',
            'reservas_semana_total',
            'ausencias_semana_total',
            'recuperaciones_semana_total',
            'recuperaciones_disponibles_total',
            'recuperaciones_semana_capacidad',
            'recuperaciones_progreso',
            'limite_semana',
            'progreso',
            'portal_base_url',
            'semana_anterior_url',
            'semana_siguiente_url',
            'agenda_url',
            'reservas_url',
            'notificaciones_url',
            'logout_url',
            'logo_url',
            'notificaciones',
            'notificaciones_recientes',
            'notificaciones_no_vistas',
            'semanas_disponibles',
            'semana',
            'agenda',
            'agenda_publica',
            'reservas_reportables'
        );
    }

    /**
     * Formats a short week range for portal UI.
     *
     * @param string $inicio Start date.
     * @param string $fin    End date.
     * @return string
     */
    private static function formatear_rango_corto($inicio, $fin) {
        $meses_cortos = array(
            1  => 'ene',
            2  => 'feb',
            3  => 'mar',
            4  => 'abr',
            5  => 'may',
            6  => 'jun',
            7  => 'jul',
            8  => 'ago',
            9  => 'sep',
            10 => 'oct',
            11 => 'nov',
            12 => 'dic',
        );
        $inicio_ts = strtotime($inicio);
        $fin_ts    = strtotime($fin);
        $mes_ini   = $meses_cortos[(int) gmdate('n', $inicio_ts)] ?? '';
        $mes_fin   = $meses_cortos[(int) gmdate('n', $fin_ts)] ?? '';

        if ($mes_ini === $mes_fin) {
            return gmdate('d', $inicio_ts) . ' - ' . gmdate('d', $fin_ts) . ' ' . $mes_fin;
        }

        return gmdate('d', $inicio_ts) . ' ' . $mes_ini . ' - ' . gmdate('d', $fin_ts) . ' ' . $mes_fin;
    }

    /**
     * Builds CSS classes for a booking slot.
     *
     * @param object|null $reserva Reservation object.
     * @return string
     */
    private static function clases_slot_portal($reserva) {
        $clases = array();

        if ($reserva) {
            $clases[] = 'is-reserved';

            if ('falto' === $reserva->estado) {
                $clases[] = 'is-missed';
            }

            if ('recuperacion' === $reserva->tipo) {
                $clases[] = 'is-recovery';
            }
        }

        return implode(' ', $clases);
    }
	
	    /**
	     * Renders the private weekly agenda for WordPress admins.
     *
     * @param string $stylesheet Inline fallback stylesheet.
     * @return string
     */
    private function render_agenda_admin($stylesheet) {
        $fecha_hoy   = gmdate('Y-m-d', current_time('timestamp'));
        $fecha_base  = isset($_GET['semana']) ? sanitize_text_field(wp_unslash($_GET['semana'])) : $fecha_hoy;
        $semana      = TP_Reservas::rango_semana($fecha_base);
        $agenda      = TP_Asistencia::semana($semana['inicio']);
        $anterior    = gmdate('Y-m-d', strtotime($semana['inicio'] . ' -7 days'));
        $siguiente   = gmdate('Y-m-d', strtotime($semana['inicio'] . ' +7 days'));
        $agenda_base = add_query_arg('vista', 'agenda', TP_Roles::portal_url());
        $logout_url  = wp_logout_url(TP_Roles::portal_url());
        $logo_url    = content_url('uploads/2026/04/1775228635121-2048x1587.png');

        ob_start();
        ?>
        <div class="tp-portal tp-portal-admin-agenda">
            <header class="tp-portal-topbar">
                <a class="tp-portal-brand" href="<?php echo esc_url($agenda_base); ?>">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr__('Tati Pilates', 'tatipilates'); ?>">
                    <span>
                        <small><?php echo esc_html(current_user_can(TP_Roles::CAP_MANAGE_PILATES) ? __('Admin Pilates', 'tatipilates') : __('Mi Pilates', 'tatipilates')); ?></small>
                        <strong><?php echo esc_html__('Tati Pilates', 'tatipilates'); ?></strong>
                    </span>
                </a>

                <nav class="tp-portal-menu" aria-label="<?php echo esc_attr__('Menu de administracion Pilates', 'tatipilates'); ?>">
                    <a class="is-active" href="<?php echo esc_url(add_query_arg('semana', $semana['inicio'], $agenda_base)); ?>"><?php echo esc_html__('Agenda', 'tatipilates'); ?></a>
                    <?php if (current_user_can(TP_Roles::CAP_MANAGE_PILATES)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=tatipilates')); ?>"><?php echo esc_html__('Admin WP', 'tatipilates'); ?></a>
                    <?php endif; ?>
                    <a class="tp-portal-logout" href="<?php echo esc_url($logout_url); ?>"><?php echo esc_html__('Salir', 'tatipilates'); ?></a>
                </nav>
            </header>

            <section class="tp-portal-hero">
                <p class="tp-kicker"><?php echo esc_html__('Agenda privada', 'tatipilates'); ?></p>
                <h1><?php echo esc_html__('Agenda Semanal', 'tatipilates'); ?></h1>
                <p><?php echo esc_html__('Vista de administracion para revisar cupos, reservas y estudiantes de la semana.', 'tatipilates'); ?></p>
            </section>

            <main class="tp-portal-main">
                <div class="tp-section-title">
                    <span aria-hidden="true"><?php echo esc_html__('Agenda', 'tatipilates'); ?></span>
                    <h2><?php echo esc_html(TP_Pagos::formatear_fecha($semana['inicio']) . ' - ' . TP_Pagos::formatear_fecha($semana['fin'])); ?></h2>
                </div>

                <div class="tp-window">
                    <div class="tp-window-bar">
                        <span></span>
                        <span></span>
                        <strong><?php echo esc_html__('Semana', 'tatipilates'); ?> <?php echo esc_html(TP_Pagos::formatear_fecha($semana['inicio'])); ?> - <?php echo esc_html(TP_Pagos::formatear_fecha($semana['fin'])); ?></strong>
                    </div>

                    <div class="tp-window-body">
                        <div class="tp-week-control">
                            <div class="tp-week-control-head">
                                <a class="tp-week-arrow" href="<?php echo esc_url(add_query_arg('semana', $anterior, $agenda_base)); ?>" aria-label="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>">&lsaquo;</a>
                                <div>
                                    <span><?php echo esc_html__('Semana seleccionada', 'tatipilates'); ?></span>
                                    <strong><?php echo esc_html(TP_Pagos::formatear_fecha($semana['inicio']) . ' - ' . TP_Pagos::formatear_fecha($semana['fin'])); ?></strong>
                                </div>
                                <a class="tp-week-arrow" href="<?php echo esc_url(add_query_arg('semana', $siguiente, $agenda_base)); ?>" aria-label="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>">&rsaquo;</a>
                            </div>
                        </div>

                        <div class="tp-public-agenda-days">
                            <?php foreach ($agenda['grupos'] as $fecha_dia => $grupos_dia) : ?>
                                <section class="tp-public-agenda-day">
                                    <header>
                                        <span><?php echo esc_html(TP_Horarios::dias_semana()[(int) gmdate('N', strtotime($fecha_dia))] ?? ''); ?></span>
                                        <strong><?php echo esc_html(TP_Pagos::formatear_fecha($fecha_dia)); ?></strong>
                                    </header>

                                    <div class="tp-public-agenda-slots">
                                        <?php foreach ($grupos_dia as $grupo) : ?>
                                            <?php
                                            $reservas_visibles = array_values(
                                                array_filter(
                                                    $grupo['reservas'],
                                                    function ($reserva) {
                                                        return in_array($reserva->estado, array('reservada', 'asistio', 'falto'), true);
                                                    }
                                                )
                                            );
                                            $ocupadas = count(
                                                array_filter(
                                                    $reservas_visibles,
                                                    function ($reserva) {
                                                        return in_array($reserva->estado, array('reservada', 'asistio'), true);
                                                    }
                                                )
                                            );
                                            $libres = max(0, (int) $grupo['cupo_maximo'] - $ocupadas);
                                            ?>
                                            <article class="tp-public-agenda-slot">
                                                <div class="tp-public-agenda-slot-head">
                                                    <strong><?php echo esc_html(TP_Horarios::formatear_hora($grupo['hora_inicio'])); ?></strong>
                                                    <span><?php echo esc_html($ocupadas . '/' . (int) $grupo['cupo_maximo']); ?> <?php echo esc_html__('cupos', 'tatipilates'); ?> &middot; <?php echo esc_html($libres); ?> <?php echo esc_html__('libres', 'tatipilates'); ?></span>
                                                </div>

                                                <?php if ($reservas_visibles) : ?>
                                                    <div class="tp-public-agenda-students">
                                                        <?php foreach ($reservas_visibles as $reserva) : ?>
                                                            <div class="tp-public-agenda-student">
                                                                <strong><?php echo esc_html($reserva->display_name); ?></strong>
                                                                <span>
                                                                    <?php if ('recuperacion' === $reserva->tipo) : ?>
                                                                        <em class="tp-pill tp-pill-recovery"><?php echo esc_html__('Recuperacion', 'tatipilates'); ?></em>
                                                                    <?php endif; ?>
                                                                    <?php if ('asistio' === $reserva->estado) : ?>
                                                                        <em class="tp-pill tp-pill-active"><?php echo esc_html__('Asistio', 'tatipilates'); ?></em>
                                                                    <?php elseif ('falto' === $reserva->estado) : ?>
                                                                        <em class="tp-pill tp-pill-danger"><?php echo esc_html__('Falto', 'tatipilates'); ?></em>
                                                                    <?php else : ?>
                                                                        <em class="tp-pill"><?php echo esc_html__('Reservada', 'tatipilates'); ?></em>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else : ?>
                                                    <p class="tp-empty-state"><?php echo esc_html__('Sin reservas todavia.', 'tatipilates'); ?></p>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <?php

        return $stylesheet . ob_get_clean();
    }

    /**
     * Handles reservation requests from the portal.
     *
     * @return void
     */
    public function reservar() {
        $estudiante = $this->require_student();
        check_admin_referer('tp_portal_reservar');

        $horario_id = isset($_POST['horario_id']) ? absint($_POST['horario_id']) : 0;
        $fecha      = isset($_POST['fecha']) ? sanitize_text_field(wp_unslash($_POST['fecha'])) : '';
        $resultado  = TP_Reservas::intentar_reserva((int) $estudiante->id, $horario_id, $fecha, 'alumna');
        $args       = array('semana' => TP_Reservas::rango_semana($fecha)['inicio']);

        if (is_wp_error($resultado)) {
            $args['tp_error']      = rawurlencode($resultado->get_error_message());
            $args['tp_error_code'] = $resultado->get_error_code();
        } else {
            $args['tp_mensaje'] = rawurlencode($resultado['mensaje'] ?? 'Reserva creada correctamente.');

            if (!empty($resultado['reserva_id']) && class_exists('TP_Notificaciones')) {
                TP_Notificaciones::crear_reserva_confirmada((int) $resultado['reserva_id']);
            }
        }

        wp_safe_redirect(add_query_arg($args, TP_Roles::portal_url()));
        exit;
    }

    /**
     * Handles monthly fixed-slot reservation requests from the portal.
     *
     * @return void
     */
    public function reservar_mes() {
        $estudiante = $this->require_student();
        check_admin_referer('tp_portal_reservar_mes');

        $horario_id = isset($_POST['horario_id']) ? absint($_POST['horario_id']) : 0;
        $fecha      = isset($_POST['fecha']) ? sanitize_text_field(wp_unslash($_POST['fecha'])) : '';
        $mes        = TP_Pagos::normalizar_mes($fecha);
        $resultado  = TP_Reservas::reservar_mes((int) $estudiante->id, $horario_id, $mes, 'alumna', $fecha);
        $args       = array('semana' => TP_Reservas::rango_semana($fecha)['inicio']);

        if (is_wp_error($resultado)) {
            $args['tp_error']      = rawurlencode($resultado->get_error_message());
            $args['tp_error_code'] = $resultado->get_error_code();
        } else {
            $args['tp_mensaje'] = rawurlencode($resultado['mensaje'] ?? 'Mes reservado correctamente.');
            if (!empty($resultado['omitidas'])) {
                $args['tp_mensaje_tipo'] = 'warning';
            }

            if (!empty($resultado['reserva_ids']) && class_exists('TP_Notificaciones')) {
                foreach ($resultado['reserva_ids'] as $reserva_id) {
                    TP_Notificaciones::crear_reserva_confirmada((int) $reserva_id);
                }
            }
        }

        wp_safe_redirect(add_query_arg($args, TP_Roles::portal_url()));
        exit;
    }

    /**
     * Handles frontend login without sending students to wp-login.php.
     *
     * @return void
     */
    public function login() {
        check_admin_referer('tp_portal_login');

        $redirect = isset($_POST['redirect_to'])
            ? wp_validate_redirect(esc_url_raw(wp_unslash($_POST['redirect_to'])), TP_Roles::portal_url())
            : TP_Roles::portal_url();
        $login    = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
        $password = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
        $remember = !empty($_POST['rememberme']);
        $rate_key = 'tp_login_' . md5(wp_get_session_token() . '|' . $login);
        $intentos = (int) get_transient($rate_key);

        if ('' === $login || '' === $password) {
            wp_safe_redirect(add_query_arg('tp_error', rawurlencode('Ingresa tu correo y contraseña.'), $redirect));
            exit;
        }

        if ($intentos >= 5) {
            wp_safe_redirect(add_query_arg('tp_error', rawurlencode('Demasiados intentos. Intenta de nuevo en unos minutos.'), $redirect));
            exit;
        }

        $user = wp_signon(
            array(
                'user_login'    => $login,
                'user_password' => $password,
                'remember'      => $remember,
            ),
            is_ssl()
        );

        if (is_wp_error($user)) {
            set_transient($rate_key, $intentos + 1, 10 * MINUTE_IN_SECONDS);
            wp_safe_redirect(add_query_arg('tp_error', rawurlencode('Correo o contraseña incorrectos.'), $redirect));
            exit;
        }

        $es_estudiante = TP_Roles::usuario_es_alumna($user);

        if (!$es_estudiante && !user_can($user, TP_Roles::CAP_MANAGE_PILATES)) {
            wp_logout();
            wp_safe_redirect(add_query_arg('tp_error', rawurlencode('Correo o contraseña incorrectos.'), $redirect));
            exit;
        }

        delete_transient($rate_key);

        wp_set_current_user((int) $user->ID);

        if (!$es_estudiante) {
            $redirect = add_query_arg('vista', 'agenda', TP_Roles::portal_url());
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Sends a student password reset email from the portal screen.
     *
     * @return void
     */
    public function lostpassword() {
        check_admin_referer('tp_portal_lostpassword');

        $redirect   = add_query_arg('tp_recuperar', '1', TP_Roles::portal_url());
        $user_login = isset($_POST['user_login']) ? sanitize_text_field(wp_unslash($_POST['user_login'])) : '';
        $reset_message = 'Si el correo esta registrado, te enviaremos un enlace para recuperar tu acceso.';

        if ('' === $user_login) {
            wp_safe_redirect(add_query_arg('tp_mensaje', rawurlencode($reset_message), $redirect));
            exit;
        }

        $user = is_email($user_login) ? get_user_by('email', $user_login) : get_user_by('login', $user_login);

        if (!$user || !user_can($user, 'read')) {
            wp_safe_redirect(add_query_arg('tp_mensaje', rawurlencode($reset_message), $redirect));
            exit;
        }

        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            tp_log(
                'No se pudo crear la clave de recuperacion de contraseña.',
                array(
                    'contexto' => 'TP_Portal::lostpassword',
                    'user_id'  => (int) $user->ID,
                    'codigo'   => $key->get_error_code(),
                ),
                'error'
            );
            wp_safe_redirect(add_query_arg('tp_mensaje', rawurlencode($reset_message), $redirect));
            exit;
        }

        $reset_url = add_query_arg(
            array(
                'tp_reset' => '1',
                'key'      => $key,
                'login'    => $user->user_login,
            ),
            TP_Roles::portal_url()
        );

        $subject = 'Recupera tu acceso a Mi Pilates';
        $message = "Hola {$user->display_name},\n\n";
        $message .= "Recibimos una solicitud para recuperar tu acceso a Mi Pilates.\n\n";
        $message .= "Crea una nueva contraseña aquí:\n{$reset_url}\n\n";
        $message .= "Si no solicitaste este cambio, puedes ignorar este correo.\n";

        $sent = wp_mail(
            $user->user_email,
            $subject,
            $message,
            array('Content-Type: text/plain; charset=UTF-8')
        );

        if (!$sent) {
            tp_log(
                'WordPress no pudo enviar el correo de recuperacion de contraseña.',
                array(
                    'contexto' => 'TP_Portal::lostpassword',
                    'user_id'  => (int) $user->ID,
                ),
                'error'
            );
            wp_safe_redirect(add_query_arg('tp_mensaje', rawurlencode($reset_message), $redirect));
            exit;
        }

        wp_safe_redirect(add_query_arg('tp_mensaje', rawurlencode($reset_message), $redirect));
        exit;
    }

    /**
     * Sends native WordPress reset links back into the Mi Pilates portal.
     *
     * @return void
     */
    public function redirigir_reset_wordpress() {
        $key   = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $login = isset($_GET['login']) ? sanitize_text_field(wp_unslash($_GET['login'])) : '';

        if ('' === $key || '' === $login) {
            return;
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'tp_reset' => '1',
                    'key'      => $key,
                    'login'    => $login,
                ),
                TP_Roles::portal_url()
            )
        );
        exit;
    }

    /**
     * Saves a new password from the portal reset screen.
     *
     * @return void
     */
    public function resetpassword() {
        check_admin_referer('tp_portal_resetpassword');

        $key       = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
        $login     = isset($_POST['login']) ? sanitize_text_field(wp_unslash($_POST['login'])) : '';
        $password  = isset($_POST['pass1']) ? (string) wp_unslash($_POST['pass1']) : '';
        $confirmar = isset($_POST['pass2']) ? (string) wp_unslash($_POST['pass2']) : '';
        $reset_url = add_query_arg(
            array(
                'tp_reset' => '1',
                'key'      => $key,
                'login'    => $login,
            ),
            TP_Roles::portal_url()
        );

        if ('' === $key || '' === $login) {
            wp_safe_redirect(add_query_arg('tp_error', rawurlencode('El enlace de recuperacion no es valido.'), TP_Roles::portal_url()));
            exit;
        }

        if (strlen($password) < 8) {
            wp_safe_redirect(add_query_arg('tp_error', rawurlencode('Usa una contraseña de al menos 8 caracteres.'), $reset_url));
            exit;
        }

        if ($password !== $confirmar) {
            wp_safe_redirect(add_query_arg('tp_error', rawurlencode('Las contraseñas no coinciden.'), $reset_url));
            exit;
        }

        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user) || !user_can($user, 'read')) {
            wp_safe_redirect(add_query_arg('tp_error', rawurlencode('El enlace expiro o ya fue utilizado. Solicita uno nuevo.'), add_query_arg('tp_recuperar', '1', TP_Roles::portal_url())));
            exit;
        }

        reset_password($user, $password);

        wp_safe_redirect(add_query_arg('tp_mensaje', rawurlencode('Tu contraseña fue actualizada. Ya puedes entrar.'), TP_Roles::portal_url()));
        exit;
    }

    /**
     * Handles absence reports from the portal.
     *
     * @return void
     */
    public function reportar_ausencia() {
        $estudiante = $this->require_student();
        check_admin_referer('tp_portal_reportar_ausencia');

        $reserva_id = isset($_POST['reserva_id']) ? absint($_POST['reserva_id']) : 0;
        $motivo     = isset($_POST['motivo']) ? sanitize_text_field(wp_unslash($_POST['motivo'])) : '';
        $reserva    = self::reserva_estudiante($reserva_id, (int) $estudiante->id);
        $args       = array();

        if ($reserva) {
            $args['semana'] = TP_Reservas::rango_semana($reserva->fecha)['inicio'];
        }

        if (!$reserva) {
            $args['tp_error'] = rawurlencode('No encontramos esa reserva.');
        } elseif ('reservada' !== $reserva->estado) {
            $args['tp_error'] = rawurlencode('Solo puedes reportar ausencia en clases reservadas.');
        } elseif ($reserva->fecha < gmdate('Y-m-d', current_time('timestamp'))) {
            $args['tp_error'] = rawurlencode('Esta clase ya paso.');
        } else {
            $resultado = TP_Asistencia::marcar($reserva_id, 'falto', $motivo);

            if (is_wp_error($resultado)) {
                $args['tp_error'] = rawurlencode($resultado->get_error_message());
            } else {
                $args['tp_mensaje'] = rawurlencode('Ausencia reportada. Tu recuperacion queda pendiente por 3 meses.');

                if (class_exists('TP_Notificaciones')) {
                    TP_Notificaciones::crear_ausencia_reportada($reserva);
                }
            }
        }

        wp_safe_redirect(add_query_arg($args, TP_Roles::portal_url()));
        exit;
    }

    /**
     * Cancels a future reservation without creating a recovery credit.
     *
     * @return void
     */
    public function cancelar_reserva() {
        global $wpdb;

        $estudiante = $this->require_student();
        check_admin_referer('tp_portal_cancelar_reserva');

        $reserva_id = isset($_POST['reserva_id']) ? absint($_POST['reserva_id']) : 0;
        $reserva    = self::reserva_estudiante($reserva_id, (int) $estudiante->id);
        $args       = array();

        if ($reserva) {
            $args['semana'] = TP_Reservas::rango_semana($reserva->fecha)['inicio'];
        }

        if (!$reserva) {
            $args['tp_error'] = rawurlencode('No encontramos esa reserva.');
        } elseif ('reservada' !== $reserva->estado) {
            $args['tp_error'] = rawurlencode('Solo puedes cancelar clases reservadas.');
        } elseif ($reserva->fecha < gmdate('Y-m-d', current_time('timestamp'))) {
            $args['tp_error'] = rawurlencode('Esta clase ya paso.');
        } else {
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
            } else {
                if ('recuperacion' === $reserva->tipo && !empty($reserva->recuperacion_id)) {
                    $recuperacion = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}tp_recuperaciones WHERE id = %d AND alumna_id = %d",
                            (int) $reserva->recuperacion_id,
                            (int) $estudiante->id
                        )
                    );

                    if ($recuperacion && 'usada' === $recuperacion->estado && $recuperacion->fecha_limite >= gmdate('Y-m-d', current_time('timestamp'))) {
                        $recuperacion_actualizada = $wpdb->update(
                            $wpdb->prefix . 'tp_recuperaciones',
                            array('estado' => 'pendiente'),
                            array('id' => (int) $recuperacion->id),
                            array('%s'),
                            array('%d')
                        );

                        if (false === $recuperacion_actualizada) {
                            $wpdb->query('ROLLBACK');
                            $args['tp_error'] = rawurlencode('No se pudo cancelar la reserva y devolver la recuperacion.');
                            wp_safe_redirect(add_query_arg($args, TP_Roles::portal_url()));
                            exit;
                        }
                    }
                }

                $wpdb->query('COMMIT');
                $args['tp_mensaje'] = rawurlencode('Reserva cancelada. El cupo queda libre y no se crea recuperacion.');

                if (class_exists('TP_Notificaciones')) {
                    TP_Notificaciones::crear_reserva_cancelada($reserva);
                }
            }
        }

        wp_safe_redirect(add_query_arg($args, TP_Roles::portal_url()));
        exit;
    }

    /**
     * Marks one student notification as seen.
     *
     * @return void
     */
    public function marcar_notificacion_vista() {
        $estudiante = $this->require_student();
        $notificacion_id = isset($_POST['notificacion_id']) ? absint($_POST['notificacion_id']) : 0;

        check_admin_referer('tp_portal_notificacion_vista_' . $notificacion_id);

        $actualizada = false;

        if ($notificacion_id && class_exists('TP_Notificaciones')) {
            $actualizada = TP_Notificaciones::marcar_vista($notificacion_id, 'alumna', (int) $estudiante->id);
        }

        if ($this->portal_json_requested()) {
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

        wp_safe_redirect(add_query_arg('vista', 'notificaciones', TP_Roles::portal_url()));
        exit;
    }

    /**
     * Soft-deletes one student notification.
     *
     * @return void
     */
    public function eliminar_notificacion() {
        $estudiante = $this->require_student();
        $notificacion_id = isset($_POST['notificacion_id']) ? absint($_POST['notificacion_id']) : 0;

        check_admin_referer('tp_portal_notificacion_eliminar_' . $notificacion_id);

        $eliminada = false;

        if ($notificacion_id && class_exists('TP_Notificaciones')) {
            $eliminada = TP_Notificaciones::eliminar($notificacion_id, 'alumna', (int) $estudiante->id);
        }

        if ($this->portal_json_requested()) {
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

        wp_safe_redirect(add_query_arg('vista', 'notificaciones', TP_Roles::portal_url()));
        exit;
    }

    /**
     * Detects enhanced portal requests that expect JSON instead of redirects.
     *
     * @return bool
     */
    private function portal_json_requested() {
        $requested_with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUESTED_WITH'])) : '';
        $accept         = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : '';

        return 'XMLHttpRequest' === $requested_with || false !== strpos($accept, 'application/json');
    }

    /**
     * Gets the current logged-in student profile.
     *
     * @return object|null
     */
    public static function estudiante_actual() {
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            return null;
        }

        return TP_Alumnas::obtener_por_wp_user_id($user_id);
    }

    /**
     * Builds a week agenda with capacity and student reservation status.
     *
     * @param int    $alumna_id Student ID.
     * @param string $fecha     Date inside the week.
     * @return array<int,array<string,mixed>>
     */
    public static function agenda_semana($alumna_id, $fecha) {
        global $wpdb;

        $rango    = TP_Reservas::rango_semana($fecha);
        $dias     = TP_Horarios::dias_semana();
        $horarios = TP_Horarios::obtener_todos(true);
        $agenda   = array();

        foreach ($dias as $dia_numero => $dia_nombre) {
            $fecha_dia = gmdate('Y-m-d', strtotime($rango['inicio'] . ' +' . ((int) $dia_numero - 1) . ' days'));

            $agenda[$dia_numero] = array(
                'nombre'   => $dia_nombre,
                'fecha'    => $fecha_dia,
                'horarios' => array(),
            );
        }

        $reservas_activas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT horario_id, fecha, COUNT(*) AS total
                FROM {$wpdb->prefix}tp_reservas
                WHERE fecha BETWEEN %s AND %s AND estado IN ('reservada', 'asistio')
                GROUP BY horario_id, fecha",
                $rango['inicio'],
                $rango['fin']
            )
        );
        $reservas_activas_por_slot = array();

        foreach ($reservas_activas as $reserva_activa) {
            $clave                              = (int) $reserva_activa->horario_id . '|' . $reserva_activa->fecha;
            $reservas_activas_por_slot[$clave] = (int) $reserva_activa->total;
        }

        $reservas_alumna = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$wpdb->prefix}tp_reservas
                WHERE alumna_id = %d AND fecha BETWEEN %s AND %s AND estado != 'cancelada'",
                absint($alumna_id),
                $rango['inicio'],
                $rango['fin']
            )
        );
        $reservas_alumna_por_slot = array();

        foreach ($reservas_alumna as $reserva_alumna) {
            $clave                             = (int) $reserva_alumna->horario_id . '|' . $reserva_alumna->fecha;
            $reservas_alumna_por_slot[$clave] = $reserva_alumna;
        }

        foreach ($horarios as $horario) {
            $dia_numero = (int) $horario->dia_semana;

            if (!isset($agenda[$dia_numero])) {
                continue;
            }

            $fecha_dia      = $agenda[$dia_numero]['fecha'];
            $clave          = (int) $horario->id . '|' . $fecha_dia;
            $ocupadas       = $reservas_activas_por_slot[$clave] ?? 0;
            $cupos          = max(0, (int) $horario->cupo_maximo - $ocupadas);
            $reserva_alumna = $reservas_alumna_por_slot[$clave] ?? null;

            $agenda[$dia_numero]['horarios'][] = array(
                'horario' => $horario,
                'cupos'   => $cupos,
                'reserva' => $reserva_alumna,
            );
        }

        return $agenda;
    }

    /**
     * Lists student reservations for a week.
     *
     * @param int    $alumna_id Student ID.
     * @param string $fecha     Date inside the week.
     * @return array<int,object>
     */
    public static function reservas_estudiante_semana($alumna_id, $fecha) {
        global $wpdb;

        $rango = TP_Reservas::rango_semana($fecha);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, h.hora_inicio, h.dia_semana
                FROM {$wpdb->prefix}tp_reservas r
                INNER JOIN {$wpdb->prefix}tp_horarios h ON h.id = r.horario_id
                WHERE r.alumna_id = %d AND r.fecha BETWEEN %s AND %s AND r.estado != 'cancelada'
                ORDER BY r.fecha ASC, h.hora_inicio ASC",
                absint($alumna_id),
                $rango['inicio'],
                $rango['fin']
            )
        );
    }

    /**
     * Lists pending recovery credits for a student.
     *
     * @param int $alumna_id Student ID.
     * @return array<int,object>
     */
    public static function recuperaciones_pendientes($alumna_id) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$wpdb->prefix}tp_recuperaciones
                WHERE alumna_id = %d AND estado = 'pendiente' AND fecha_limite >= CURDATE()
                ORDER BY fecha_limite ASC, id ASC",
                absint($alumna_id)
            )
        );
    }

    /**
     * Builds the week selector shown in the portal.
     *
     * @param string $inicio_actual     Current week start.
     * @param string $inicio_seleccion  Selected week start.
     * @return array<int,array<string,string>>
     */
    public static function semanas_disponibles($inicio_actual, $inicio_seleccion) {
        $semanas = array();
        $inicio_lista = $inicio_seleccion < $inicio_actual ? $inicio_seleccion : $inicio_actual;

        foreach (range(0, 5) as $offset) {
            $inicio = TP_Helpers::mover_semanas($inicio_lista, $offset);
            $fin    = TP_Helpers::rango_semana($inicio)['fin'];
            $offset_actual = (int) round((strtotime($inicio) - strtotime($inicio_actual)) / WEEK_IN_SECONDS);
            $label = 'Esta semana';

            if ($offset_actual < 0) {
                $label = -1 === $offset_actual ? 'Semana pasada' : 'Hace ' . absint($offset_actual) . ' semanas';
            } elseif ($offset_actual > 0) {
                $label = 1 === $offset_actual ? 'Proxima' : 'En ' . $offset_actual . ' semanas';
            }

            $semanas[$inicio] = array(
                'inicio' => $inicio,
                'fin'    => $fin,
                'label'  => $label,
            );
        }

        if (!isset($semanas[$inicio_seleccion])) {
            $semanas[$inicio_seleccion] = array(
                'inicio' => $inicio_seleccion,
                'fin'    => TP_Helpers::rango_semana($inicio_seleccion)['fin'],
                'label'  => 'Seleccionada',
            );
        }

        ksort($semanas);

        return array_values($semanas);
    }

    /**
     * Gets one student reservation.
     *
     * @param int $reserva_id Reservation ID.
     * @param int $alumna_id  Student ID.
     * @return object|null
     */
    private static function reserva_estudiante($reserva_id, $alumna_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tp_reservas WHERE id = %d AND alumna_id = %d",
                absint($reserva_id),
                absint($alumna_id)
            )
        );
    }

    /**
     * Requires a logged-in student and returns its profile.
     *
     * @return object
     */
    private function require_student() {
        if (!is_user_logged_in() || !TP_Roles::usuario_actual_es_alumna()) {
            wp_safe_redirect(add_query_arg('tp_error', rawurlencode('Ingresa para continuar.'), TP_Roles::portal_url()));
            exit;
        }

        $estudiante = self::estudiante_actual();

        if (!$estudiante) {
            tp_log(
                'Usuario con rol de alumna sin perfil asociado.',
                array(
                    'contexto' => 'TP_Portal::require_student',
                    'user_id'  => get_current_user_id(),
                ),
                'warning'
            );
            wp_die(
                esc_html__('No encontramos tu perfil de estudiante.', 'tatipilates'),
                esc_html__('Perfil no encontrado', 'tatipilates'),
                array('response' => 403)
            );
        }

        return $estudiante;
    }

    /**
     * Renders the login state.
     *
     * @return string
     */
    private function render_login() {
        $recuperar = isset($_GET['tp_recuperar']) && '1' === sanitize_text_field(wp_unslash($_GET['tp_recuperar']));
        $resetear  = isset($_GET['tp_reset']) && '1' === sanitize_text_field(wp_unslash($_GET['tp_reset']));
        $reset_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $reset_login = isset($_GET['login']) ? sanitize_text_field(wp_unslash($_GET['login'])) : '';
        $reset_user = null;

        if ($resetear && '' !== $reset_key && '' !== $reset_login) {
            $reset_user = check_password_reset_key($reset_key, $reset_login);
        }

        if ($resetear) {
            $recuperar = false;
        }

        $titulo = 'Accede a tus clases';
        $texto  = 'Reserva tu semana, revisa tus recuperaciones y reporta ausencias desde tu panel.';
        $card   = 'Entrar al portal';

        if ($recuperar) {
            $titulo = 'Recupera tu acceso';
            $texto  = 'Te enviaremos un enlace para crear una contraseña nueva.';
            $card   = 'Nuevo acceso';
        } elseif ($resetear) {
            $titulo = 'Crea tu nueva contraseña';
            $texto  = 'Elige una contraseña nueva para volver a entrar a Mi Pilates.';
            $card   = 'Nueva contraseña';
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $redirect_to = $request_uri ? home_url($request_uri) : TP_Roles::portal_url();

        ob_start();
        ?>
        <div class="tp-portal tp-portal-login">
            <section class="tp-login-shell">
                <div class="tp-login-copy">
                    <p class="tp-kicker">Mi Pilates</p>
                    <h1><?php echo esc_html($titulo); ?></h1>
                    <p><?php echo esc_html($texto); ?></p>

                    <div class="tp-login-highlights" aria-label="Beneficios del portal">
                        <span>Reservas semanales</span>
                        <span>Recuperaciones activas</span>
                        <span>Ausencias en un toque</span>
                    </div>
                </div>

                <div class="tp-window tp-login-card">
                    <div class="tp-window-bar">
                        <span></span>
                        <span></span>
                        <strong><?php echo esc_html($card); ?></strong>
                    </div>
                    <div class="tp-window-body">
                        <?php if (isset($_GET['tp_error'])) : ?>
                            <div class="tp-portal-message tp-portal-message-error">
                                <?php echo esc_html(rawurldecode(sanitize_text_field(wp_unslash($_GET['tp_error'])))); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['tp_mensaje'])) : ?>
                            <div class="tp-portal-message tp-portal-message-success">
                                <?php echo esc_html(rawurldecode(sanitize_text_field(wp_unslash($_GET['tp_mensaje'])))); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($resetear) : ?>
                            <?php if (!$reset_user || is_wp_error($reset_user) || !user_can($reset_user, 'read')) : ?>
                                <div class="tp-portal-message tp-portal-message-error">
                                    <?php echo esc_html('Este enlace expiro o ya fue utilizado. Solicita uno nuevo.'); ?>
                                </div>
                                <a class="tp-button tp-button-primary" href="<?php echo esc_url(add_query_arg('tp_recuperar', '1', TP_Roles::portal_url())); ?>">Solicitar nuevo enlace</a>
                            <?php else : ?>
                                <form class="tp-login-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                                    <?php wp_nonce_field('tp_portal_resetpassword'); ?>
                                    <input type="hidden" name="action" value="tp_portal_resetpassword">
                                    <input type="hidden" name="key" value="<?php echo esc_attr($reset_key); ?>">
                                    <input type="hidden" name="login" value="<?php echo esc_attr($reset_login); ?>">
                                    <label for="tp_new_password">
                                        <span>Nueva contraseña</span>
                                        <span class="tp-password-field">
                                            <input type="password" name="pass1" id="tp_new_password" autocomplete="new-password" minlength="8" required>
                                            <button class="tp-password-toggle" type="button" aria-label="Mostrar contraseña" aria-pressed="false" data-show-label="Mostrar contraseña" data-hide-label="Ocultar contraseña">
                                                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                            </button>
                                        </span>
                                    </label>
                                    <label for="tp_new_password_confirm">
                                        <span>Confirmar contraseña</span>
                                        <span class="tp-password-field">
                                            <input type="password" name="pass2" id="tp_new_password_confirm" autocomplete="new-password" minlength="8" required>
                                            <button class="tp-password-toggle" type="button" aria-label="Mostrar contraseña" aria-pressed="false" data-show-label="Mostrar contraseña" data-hide-label="Ocultar contraseña">
                                                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                            </button>
                                        </span>
                                    </label>
                                    <button class="tp-button tp-button-primary" type="submit">Guardar contraseña</button>
                                </form>
                            <?php endif; ?>
                            <a class="tp-muted-link" href="<?php echo esc_url(TP_Roles::portal_url()); ?>">Volver al ingreso</a>
                        <?php elseif ($recuperar) : ?>
                            <form class="tp-login-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                                <?php wp_nonce_field('tp_portal_lostpassword'); ?>
                                <input type="hidden" name="action" value="tp_portal_lostpassword">
                                <label for="tp_recover_email">
                                    <span>Correo</span>
                                    <input type="email" name="user_login" id="tp_recover_email" autocomplete="email" required>
                                </label>
                                <button class="tp-button tp-button-primary" type="submit">Enviar enlace</button>
                            </form>
                            <a class="tp-muted-link" href="<?php echo esc_url(TP_Roles::portal_url()); ?>">Volver al ingreso</a>
                        <?php else : ?>
                            <form class="tp-login-form" name="loginform" id="loginform" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                                <?php wp_nonce_field('tp_portal_login'); ?>
                                <input type="hidden" name="action" value="tp_portal_login">
                                <label for="user_login">
                                    <span>Correo</span>
                                    <input type="email" name="log" id="user_login" autocomplete="username" required>
                                </label>
                                <label for="user_pass">
                                    <span>Contraseña</span>
                                    <span class="tp-password-field">
                                        <input type="password" name="pwd" id="user_pass" autocomplete="current-password" required>
                                        <button class="tp-password-toggle" type="button" aria-label="Mostrar contraseña" aria-pressed="false" data-show-label="Mostrar contraseña" data-hide-label="Ocultar contraseña">
                                            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                        </button>
                                    </span>
                                </label>
                                <label class="login-remember">
                                    <input name="rememberme" type="checkbox" id="rememberme" value="forever" checked>
                                    <span>Mantener sesión en este teléfono</span>
                                </label>
                                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>">
                                <input type="hidden" name="testcookie" value="1">
                                <button class="tp-button tp-button-primary" type="submit">Entrar</button>
                            </form>
                            <a class="tp-muted-link" href="<?php echo esc_url(add_query_arg('tp_recuperar', '1', TP_Roles::portal_url())); ?>">Olvidé mi contraseña</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <script>
                (function () {
                    document.querySelectorAll('.tp-password-toggle').forEach(function (toggle) {
                        toggle.addEventListener('click', function () {
                            var field = toggle.closest('.tp-password-field');
                            var input = field ? field.querySelector('input') : null;
                            var icon = toggle.querySelector('.dashicons');

                            if (!input || !icon) {
                                return;
                            }

                            var visible = input.type === 'text';
                            input.type = visible ? 'password' : 'text';
                            toggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
                            toggle.setAttribute('aria-label', visible ? toggle.dataset.showLabel : toggle.dataset.hideLabel);
                            icon.className = visible ? 'dashicons dashicons-visibility' : 'dashicons dashicons-hidden';
                        });
                    });
                }());
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Outputs the web app manifest for Mi Pilates.
     *
     * @return void
     */
    private function servir_manifest() {
        $portal_path = trailingslashit(wp_parse_url(TP_Roles::portal_url(), PHP_URL_PATH) ?: '/mi-pilates/');
        $icons_url   = wp_make_link_relative(TP_PLUGIN_URL . 'assets/icons/');
        $manifest    = array(
            'name'             => 'Mi Pilates',
            'short_name'       => 'Mi Pilates',
            'description'      => 'Portal para reservar clases, revisar agenda y gestionar recuperaciones.',
            'id'               => $portal_path,
            'start_url'        => $portal_path,
            'scope'            => $portal_path,
            'display'          => 'standalone',
            'display_override' => array('standalone', 'minimal-ui', 'browser'),
            'orientation'      => 'portrait',
            'background_color' => '#f5f0ea',
            'theme_color'      => '#8eae8c',
            'icons'            => array(
                array(
                    'src'     => $icons_url . 'mi-pilates-icon-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ),
                array(
                    'src'     => $icons_url . 'mi-pilates-icon-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ),
                array(
                    'src'     => $icons_url . 'mi-pilates-maskable-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ),
            ),
        );

        status_header(200);
        header('Content-Type: application/manifest+json; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Outputs a conservative service worker for static assets and offline fallback.
     *
     * @return void
     */
    private function servir_service_worker() {
        $offline_url = '/?tp_portal_offline=1';
        $asset_urls  = array(
            $offline_url,
            wp_make_link_relative(TP_PLUGIN_URL . 'assets/css/portal.css'),
            wp_make_link_relative(TP_PLUGIN_URL . 'assets/js/portal.js'),
            wp_make_link_relative(TP_PLUGIN_URL . 'assets/icons/mi-pilates-apple-touch-icon.png'),
            wp_make_link_relative(TP_PLUGIN_URL . 'assets/icons/mi-pilates-icon-192.png'),
            wp_make_link_relative(TP_PLUGIN_URL . 'assets/icons/mi-pilates-icon-512.png'),
            wp_make_link_relative(TP_PLUGIN_URL . 'assets/icons/mi-pilates-maskable-512.png'),
        );
        $config      = array(
            'cacheName'  => 'mi-pilates-static-' . TP_VERSION,
            'offlineUrl' => $offline_url,
            'assetUrls'  => $asset_urls,
        );

        status_header(200);
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Service-Worker-Allowed: /');
        ?>
const TP_PORTAL_PWA = <?php echo wp_json_encode($config, JSON_UNESCAPED_SLASHES); ?>;

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(TP_PORTAL_PWA.cacheName).then(function (cache) {
            return cache.addAll(TP_PORTAL_PWA.assetUrls);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key !== TP_PORTAL_PWA.cacheName && key.indexOf('mi-pilates-') === 0) {
                    return caches.delete(key);
                }

                return Promise.resolve();
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }

    var url = new URL(event.request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (
        url.pathname.indexOf('/wp-admin/') === 0 ||
        url.pathname.indexOf('/wp-login.php') === 0 ||
        url.pathname.indexOf('/wp-json/') === 0 ||
        url.pathname.indexOf('/wp-admin/admin-post.php') === 0
    ) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(function () {
                return caches.match(TP_PORTAL_PWA.offlineUrl);
            })
        );
        return;
    }

    if (url.pathname.indexOf('/wp-content/plugins/tatipilates/assets/') !== -1) {
        event.respondWith(
            caches.match(event.request).then(function (cached) {
                return cached || fetch(event.request).then(function (response) {
                    var copy = response.clone();
                    caches.open(TP_PORTAL_PWA.cacheName).then(function (cache) {
                        cache.put(event.request, copy);
                    });
                    return response;
                });
            })
        );
    }
});
        <?php
        exit;
    }

    /**
     * Outputs the offline fallback shell.
     *
     * @return void
     */
    private function servir_offline() {
        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#8eae8c">
    <title>Mi Pilates</title>
    <style>
        body {
            display: grid;
            min-height: 100vh;
            place-items: center;
            margin: 0;
            padding: 24px;
            background: #f5f0ea;
            color: #202033;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            max-width: 420px;
            border-top: 4px solid #8eae8c;
        }

        h1 {
            margin: 14px 0 8px;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 40px;
            font-weight: 400;
            line-height: 1.05;
        }

        p {
            margin: 0;
            color: #6f6472;
            font-size: 16px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <main>
        <p>Mi Pilates</p>
        <h1>Sin conexion</h1>
        <p>No pudimos actualizar tu portal. Revisa tu internet y vuelve a abrir Mi Pilates para ver cupos, reservas y pagos al dia.</p>
    </main>
</body>
</html>
        <?php
        exit;
    }

    /**
     * Returns a stylesheet tag for shortcode contexts where optimization plugins skip late enqueues.
     *
     * @return string
     */
    private function stylesheet_tag() {
        $path = TP_PLUGIN_DIR . 'assets/css/portal.css';

        if (!file_exists($path)) {
            return '';
        }

        $css = file_get_contents($path);

        if (false === $css) {
            return '';
        }

        return '<style id="tatipilates-portal-shortcode-css">' . $css . '</style>';
    }
}
