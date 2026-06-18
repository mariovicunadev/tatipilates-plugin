<?php
/**
 * Admin dashboard.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$hoy          = gmdate('Y-m-d', current_time('timestamp'));
$fecha_base   = isset($_GET['semana']) ? sanitize_text_field(wp_unslash($_GET['semana'])) : $hoy;
$semana       = TP_Reservas::rango_semana($fecha_base);
$anterior     = gmdate('Y-m-d', strtotime($semana['inicio'] . ' -7 days'));
$siguiente    = gmdate('Y-m-d', strtotime($semana['inicio'] . ' +7 days'));
$selector_semana = gmdate('o-\WW', strtotime($semana['inicio']));
$selector_semana_label = sprintf(
    __('Semana %1$d, %2$d', 'tatipilates'),
    (int) gmdate('W', strtotime($semana['inicio'])),
    (int) gmdate('o', strtotime($semana['inicio']))
);
$selector_semana_rango = sprintf(
    __('Del %1$s al %2$s', 'tatipilates'),
    TP_Pagos::formatear_fecha($semana['inicio']),
    TP_Pagos::formatear_fecha($semana['fin'])
);
$mes_actual   = TP_Pagos::normalizar_mes($hoy);
$estado_pagos = TP_Pagos::estado_mensual($mes_actual);
$en_gracia    = TP_Pagos::mes_en_gracia($mes_actual);
$pendientes_pago = array_filter(
    $estado_pagos,
    function ($fila) {
        return 'individual' !== $fila->plan_actual && empty($fila->pago_id);
    }
);
$recuperaciones = TP_Recuperaciones::listar('pendiente');
$recuperaciones_urgentes = array_filter(
    $recuperaciones,
    function ($recuperacion) {
        return TP_Recuperaciones::dias_restantes($recuperacion->fecha_limite) <= 14;
    }
);
$cumpleanos_hoy = TP_Alumnas::cumpleanos_hoy();

$reservas_semana = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT r.*, h.hora_inicio, h.cupo_maximo, u.display_name
        FROM {$wpdb->prefix}tp_reservas r
        INNER JOIN {$wpdb->prefix}tp_horarios h ON h.id = r.horario_id
        INNER JOIN {$wpdb->prefix}tp_alumnas a ON a.id = r.alumna_id
        INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
        WHERE r.fecha BETWEEN %s AND %s
        ORDER BY r.fecha ASC, h.hora_inicio ASC, u.display_name ASC",
        $semana['inicio'],
        $semana['fin']
    )
);

$reservas_por_dia = array();

foreach ($reservas_semana as $reserva) {
    $dia_key     = $reserva->fecha;
    $horario_key = $reserva->fecha . '-' . $reserva->horario_id;

    if (!isset($reservas_por_dia[$dia_key])) {
        $reservas_por_dia[$dia_key] = array(
            'fecha'    => $reserva->fecha,
            'horarios' => array(),
        );
    }

    if (!isset($reservas_por_dia[$dia_key]['horarios'][$horario_key])) {
        $reservas_por_dia[$dia_key]['horarios'][$horario_key] = array(
            'hora_inicio' => $reserva->hora_inicio,
            'cupo_maximo' => (int) $reserva->cupo_maximo,
            'reservas'    => array(),
            'conteo'      => array(
                'activas'       => 0,
                'reservadas'    => 0,
                'asistieron'    => 0,
                'faltas'        => 0,
                'canceladas'    => 0,
                'recuperaciones' => 0,
            ),
        );
    }

    $reservas_por_dia[$dia_key]['horarios'][$horario_key]['reservas'][] = $reserva;

    if (in_array($reserva->estado, array('reservada', 'asistio'), true)) {
        $reservas_por_dia[$dia_key]['horarios'][$horario_key]['conteo']['activas']++;
    }

    if ('reservada' === $reserva->estado) {
        $reservas_por_dia[$dia_key]['horarios'][$horario_key]['conteo']['reservadas']++;
    } elseif ('asistio' === $reserva->estado) {
        $reservas_por_dia[$dia_key]['horarios'][$horario_key]['conteo']['asistieron']++;
    } elseif ('falto' === $reserva->estado) {
        $reservas_por_dia[$dia_key]['horarios'][$horario_key]['conteo']['faltas']++;
    } elseif ('cancelada' === $reserva->estado) {
        $reservas_por_dia[$dia_key]['horarios'][$horario_key]['conteo']['canceladas']++;
    }

    if ('recuperacion' === $reserva->tipo && 'cancelada' !== $reserva->estado) {
        $reservas_por_dia[$dia_key]['horarios'][$horario_key]['conteo']['recuperaciones']++;
    }
}

$actividad = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT r.*, h.hora_inicio, u.display_name
        FROM {$wpdb->prefix}tp_reservas r
        INNER JOIN {$wpdb->prefix}tp_horarios h ON h.id = r.horario_id
        INNER JOIN {$wpdb->prefix}tp_alumnas a ON a.id = r.alumna_id
        INNER JOIN {$wpdb->users} u ON u.ID = a.wp_user_id
        WHERE r.fecha BETWEEN %s AND %s
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT 8",
        $semana['inicio'],
        $semana['fin']
    )
);

$resumen_semana = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT
            SUM(CASE WHEN estado = 'reservada' THEN 1 ELSE 0 END) AS reservadas,
            SUM(CASE WHEN estado = 'asistio' THEN 1 ELSE 0 END) AS asistieron,
            SUM(CASE WHEN estado = 'falto' THEN 1 ELSE 0 END) AS faltas,
            SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) AS canceladas
        FROM {$wpdb->prefix}tp_reservas
        WHERE fecha BETWEEN %s AND %s",
        $semana['inicio'],
        $semana['fin']
    )
);

$recups_semana = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$wpdb->prefix}tp_recuperaciones recuperacion
        INNER JOIN {$wpdb->prefix}tp_reservas origen
            ON origen.id = recuperacion.reserva_origen_id
        WHERE origen.fecha BETWEEN %s AND %s",
        $semana['inicio'],
        $semana['fin']
    )
);

$reservadas      = (int) ($resumen_semana->reservadas ?? 0);
$asistieron      = (int) ($resumen_semana->asistieron ?? 0);
$faltas          = (int) ($resumen_semana->faltas ?? 0);
$canceladas      = (int) ($resumen_semana->canceladas ?? 0);
$estudiantes     = TP_Alumnas::obtener_todas();
$estudiantes_activos = count(
    array_filter(
        $estudiantes,
        function ($estudiante) {
            return (int) $estudiante->activa;
        }
    )
);

$dias_semana = array(
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo',
);

$estado_labels = array(
    'reservada' => 'Reservada',
    'asistio'   => 'Asistió',
    'falto'     => 'Faltó',
    'cancelada' => 'Cancelada',
);

$actividad_labels = array(
    'reservada' => 'Reservó',
    'asistio'   => 'Asistió',
    'falto'     => 'Faltó',
    'cancelada' => 'Canceló',
);

$dashboard_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'resumen';

if (!in_array($dashboard_tab, array('resumen', 'notificaciones'), true)) {
    $dashboard_tab = 'resumen';
}

$notificaciones_admin = class_exists('TP_Notificaciones') ? TP_Notificaciones::listar_admin(array('limit' => 60)) : array();
$notificaciones_pendientes = class_exists('TP_Notificaciones') ? TP_Notificaciones::contar_no_vistas('admin') : 0;
?>

<div class="wrap tp-admin tp-dashboard-page">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Panel de Tatiana', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Tati Pilates', 'tatipilates'); ?></h1>
    </div>

    <?php if ($cumpleanos_hoy) : ?>
        <div class="tp-dashboard-birthday-alert">
            <span class="dashicons dashicons-heart" aria-hidden="true"></span>
            <div>
                <strong><?php echo esc_html__('Cumpleanos hoy', 'tatipilates'); ?></strong>
                <p>
                    <?php
                    echo esc_html(
                        implode(
                            ', ',
                            array_map(
                                function ($cumpleanero) {
                                    return $cumpleanero->display_name;
                                },
                                $cumpleanos_hoy
                            )
                        )
                    );
                    ?>
                </p>
            </div>
            <a class="button" href="<?php echo esc_url(add_query_arg('page', 'tatipilates-alumnas-cumpleanos', admin_url('admin.php'))); ?>"><?php echo esc_html__('Ver cumpleanos', 'tatipilates'); ?></a>
        </div>
    <?php endif; ?>

    <nav class="tp-dashboard-tabs" aria-label="<?php echo esc_attr__('Secciones del dashboard', 'tatipilates'); ?>">
        <a class="<?php echo esc_attr('resumen' === $dashboard_tab ? 'is-active' : ''); ?>" href="<?php echo esc_url(add_query_arg('page', 'tatipilates', admin_url('admin.php'))); ?>">
            <?php echo esc_html__('Resumen', 'tatipilates'); ?>
        </a>
        <a class="<?php echo esc_attr('notificaciones' === $dashboard_tab ? 'is-active' : ''); ?>" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates', 'tab' => 'notificaciones'), admin_url('admin.php'))); ?>">
            <?php echo esc_html__('Notificaciones', 'tatipilates'); ?>
            <?php if ($notificaciones_pendientes) : ?>
                <span><?php echo esc_html($notificaciones_pendientes); ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <?php if ('notificaciones' === $dashboard_tab) : ?>
        <section class="tp-window tp-notifications-admin">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Centro de notificaciones', 'tatipilates'); ?></strong>
            </div>
            <div class="tp-window-body">
                <?php if ($notificaciones_admin) : ?>
                    <div class="tp-notifications-list">
                        <?php foreach ($notificaciones_admin as $notificacion) : ?>
                            <?php
                            $mensaje_whatsapp = trim($notificacion->mensaje);
                            $marcar_url       = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action'          => 'tp_admin_notificacion_vista',
                                        'notificacion_id' => (int) $notificacion->id,
                                    ),
                                    admin_url('admin-post.php')
                                ),
                                'tp_admin_notificacion_vista_' . (int) $notificacion->id
                            );
                            $eliminar_url     = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action'          => 'tp_admin_notificacion_eliminar',
                                        'notificacion_id' => (int) $notificacion->id,
                                    ),
                                    admin_url('admin-post.php')
                                ),
                                'tp_admin_notificacion_eliminar_' . (int) $notificacion->id
                            );
                            ?>
                            <article class="<?php echo esc_attr((int) $notificacion->visto_admin ? 'is-read' : 'is-unread'); ?>" data-tp-admin-notification-card="<?php echo esc_attr((int) $notificacion->id); ?>">
                                <div>
                                    <span class="tp-status <?php echo esc_attr((int) $notificacion->visto_admin ? '' : 'tp-status-active'); ?>" data-tp-admin-notification-state>
                                        <?php echo esc_html((int) $notificacion->visto_admin ? __('Vista', 'tatipilates') : __('Nueva', 'tatipilates')); ?>
                                    </span>
                                    <strong><?php echo esc_html($notificacion->titulo); ?></strong>
                                    <p><?php echo esc_html($notificacion->mensaje); ?></p>
                                    <small>
                                        <?php if (!empty($notificacion->display_name)) : ?>
                                            <?php echo esc_html($notificacion->display_name); ?> ·
                                        <?php endif; ?>
                                        <?php echo esc_html(mysql2date('d/m/Y g:i a', $notificacion->created_at)); ?>
                                    </small>
                                </div>
                                <div class="tp-notification-actions">
                                    <button class="tp-icon-button" type="button" data-tp-copy="<?php echo esc_attr($mensaje_whatsapp); ?>" aria-label="<?php echo esc_attr__('Copiar mensaje', 'tatipilates'); ?>" title="<?php echo esc_attr__('Copiar mensaje', 'tatipilates'); ?>">
                                        <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                                    </button>
                                    <?php if (!empty($notificacion->alumna_id)) : ?>
                                        <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-alumnas', 'ficha' => (int) $notificacion->alumna_id), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Ver alumna', 'tatipilates'); ?>" title="<?php echo esc_attr__('Ver alumna', 'tatipilates'); ?>">
                                            <span class="dashicons dashicons-id" aria-hidden="true"></span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!(int) $notificacion->visto_admin) : ?>
                                        <a class="tp-icon-button" href="<?php echo esc_url($marcar_url); ?>" aria-label="<?php echo esc_attr__('Marcar vista', 'tatipilates'); ?>" title="<?php echo esc_attr__('Marcar vista', 'tatipilates'); ?>" data-tp-admin-notification-mark="<?php echo esc_attr((int) $notificacion->id); ?>">
                                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                        </a>
                                    <?php endif; ?>
                                    <a class="tp-icon-button tp-icon-button-danger" href="<?php echo esc_url($eliminar_url); ?>" aria-label="<?php echo esc_attr__('Eliminar', 'tatipilates'); ?>" title="<?php echo esc_attr__('Eliminar', 'tatipilates'); ?>" data-tp-admin-notification-delete="<?php echo esc_attr((int) $notificacion->id); ?>">
                                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="tp-empty-state"><?php echo esc_html__('Todavia no hay notificaciones.', 'tatipilates'); ?></p>
                <?php endif; ?>
            </div>
        </section>

        <script>
            (function () {
                document.querySelectorAll('[data-tp-copy]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        window.navigator.clipboard.writeText(button.dataset.tpCopy || '').then(function () {
                            var icon = button.querySelector('.dashicons');

                            if (icon) {
                                icon.classList.remove('dashicons-clipboard');
                                icon.classList.add('dashicons-yes');
                            }

                            button.setAttribute('title', '<?php echo esc_js(__('Copiado', 'tatipilates')); ?>');
                            window.setTimeout(function () {
                                if (icon) {
                                    icon.classList.remove('dashicons-yes');
                                    icon.classList.add('dashicons-clipboard');
                                }

                                button.setAttribute('title', '<?php echo esc_js(__('Copiar mensaje', 'tatipilates')); ?>');
                            }, 1600);
                        });
                    });
                });

                document.querySelectorAll('[data-tp-admin-notification-mark], [data-tp-admin-notification-delete]').forEach(function (link) {
                    link.addEventListener('click', function (event) {
                        if (!window.fetch || link.dataset.tpNotificationBusy === '1') {
                            return;
                        }

                        event.preventDefault();

                        var notificationId = link.getAttribute('data-tp-admin-notification-mark') || link.getAttribute('data-tp-admin-notification-delete');
                        var card = link.closest('[data-tp-admin-notification-card]');
                        var isDelete = link.hasAttribute('data-tp-admin-notification-delete');
                        var wasUnread = card ? card.classList.contains('is-unread') : false;

                        if (!notificationId || !card) {
                            window.location.href = link.href;
                            return;
                        }

                        link.dataset.tpNotificationBusy = '1';
                        link.setAttribute('aria-busy', 'true');
                        link.classList.add('is-busy');

                        window.fetch(link.href, {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function (response) {
                            if (!response.ok) {
                                throw new Error('admin-notification-action-failed');
                            }

                            return response.json().catch(function () {
                                return { success: true };
                            });
                        }).then(function (payload) {
                            if (payload && payload.success === false) {
                                throw new Error('admin-notification-action-failed');
                            }

                            if (wasUnread) {
                                decrementAdminNotificationBadge();
                            }

                            if (isDelete) {
                                removeAdminNotificationCard(card);
                                return;
                            }

                            markAdminNotificationCardRead(card, link);
                        }).catch(function () {
                            link.dataset.tpNotificationBusy = '0';
                            link.removeAttribute('aria-busy');
                            link.classList.remove('is-busy');
                        });
                    });
                });

                function markAdminNotificationCardRead(card, link) {
                    var state = card.querySelector('[data-tp-admin-notification-state]');

                    card.classList.remove('is-unread');
                    card.classList.add('is-read');

                    if (state) {
                        state.classList.remove('tp-status-active');
                        state.textContent = '<?php echo esc_js(__('Vista', 'tatipilates')); ?>';
                    }

                    link.remove();
                }

                function removeAdminNotificationCard(card) {
                    card.classList.add('is-removing');

                    window.setTimeout(function () {
                        var list = card.closest('.tp-notifications-list');

                        card.remove();
                        ensureAdminNotificationsEmptyState(list);
                    }, 180);
                }

                function decrementAdminNotificationBadge() {
                    var badge = document.querySelector('.tp-dashboard-tabs a.is-active span');

                    if (!badge) {
                        return;
                    }

                    var count = parseInt(badge.textContent, 10);

                    if (!count || count <= 1) {
                        badge.remove();
                        return;
                    }

                    badge.textContent = String(count - 1);
                }

                function ensureAdminNotificationsEmptyState(list) {
                    if (!list || list.querySelector('[data-tp-admin-notification-card]') || list.querySelector('.tp-empty-state')) {
                        return;
                    }

                    var empty = document.createElement('p');
                    empty.className = 'tp-empty-state';
                    empty.textContent = '<?php echo esc_js(__('Todavia no hay notificaciones.', 'tatipilates')); ?>';
                    list.appendChild(empty);
                }
            }());
        </script>
    <?php else : ?>

    <div class="tp-dashboard-grid">
        <section class="tp-window tp-dashboard-main">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Semana', 'tatipilates'); ?> <?php echo esc_html(TP_Pagos::formatear_fecha($semana['inicio'])); ?> - <?php echo esc_html(TP_Pagos::formatear_fecha($semana['fin'])); ?></strong>
            </div>
            <div class="tp-window-body">
                <div class="tp-dashboard-stats">
                    <div>
                        <span><?php echo esc_html__('Reservadas', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html($reservadas); ?></strong>
                    </div>
                    <div>
                        <span><?php echo esc_html__('Asistieron', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html($asistieron); ?></strong>
                    </div>
                    <div>
                        <span><?php echo esc_html__('Faltas', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html($faltas); ?></strong>
                    </div>
                    <div>
                        <span><?php echo esc_html__('Canceladas', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html($canceladas); ?></strong>
                    </div>
                    <div>
                        <span><?php echo esc_html__('Recuperaciones generadas', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html($recups_semana); ?></strong>
                    </div>
                </div>

                <div class="tp-dashboard-actions">
                    <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-asistencia', 'semana' => $semana['inicio']), admin_url('admin.php'))); ?>"><?php echo esc_html__('Ver asistencia', 'tatipilates'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-agenda', 'semana' => $semana['inicio']), admin_url('admin.php'))); ?>"><?php echo esc_html__('Agenda semanal', 'tatipilates'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg('page', 'tatipilates-reservar', admin_url('admin.php'))); ?>"><?php echo esc_html__('Reservar clase', 'tatipilates'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg('page', 'tatipilates-pagos', admin_url('admin.php'))); ?>"><?php echo esc_html__('Pagos', 'tatipilates'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg('page', 'tatipilates-alumnas', admin_url('admin.php'))); ?>"><?php echo esc_html__('Estudiantes', 'tatipilates'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg('page', 'tatipilates-horarios', admin_url('admin.php'))); ?>"><?php echo esc_html__('Horarios', 'tatipilates'); ?></a>
                </div>

                <div class="tp-dashboard-week-selector">
                    <a class="tp-icon-button" data-tp-dashboard-week-nav href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates', 'semana' => $anterior), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>" title="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                    </a>

                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" data-tp-dashboard-week-form>
                        <input type="hidden" name="page" value="tatipilates">
                        <label class="tp-field">
                            <span><?php echo esc_html__('Seleccionar semana', 'tatipilates'); ?></span>
                            <span class="tp-week-picker-control" data-tp-week-picker>
                                <span class="tp-week-picker-copy" aria-hidden="true">
                                    <strong><?php echo esc_html($selector_semana_label); ?></strong>
                                    <small><?php echo esc_html($selector_semana_rango); ?></small>
                                </span>
                                <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                <input type="week" name="semana" value="<?php echo esc_attr($selector_semana); ?>" aria-label="<?php echo esc_attr($selector_semana_label . '. ' . $selector_semana_rango); ?>" onchange="window.tpDashboardNavigate ? window.tpDashboardNavigate(this.form) : this.form.submit();">
                            </span>
                        </label>
                    </form>

                    <a class="tp-icon-button" data-tp-dashboard-week-nav href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates', 'semana' => $siguiente), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>" title="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </div>

                <div class="tp-section-title tp-section-title-compact">
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    <h2><?php echo esc_html__('Clases de la semana', 'tatipilates'); ?></h2>
                </div>

                <div class="tp-dashboard-week-classes">
                    <?php if ($reservas_por_dia) : ?>
                        <?php foreach ($reservas_por_dia as $dia) : ?>
                            <?php
                            $dia_iso    = (int) gmdate('N', strtotime($dia['fecha']));
                            $dia_nombre = $dias_semana[$dia_iso] ?? TP_Pagos::formatear_fecha($dia['fecha']);
                            ?>
                            <section class="tp-dashboard-day">
                                <header>
                                    <strong><?php echo esc_html($dia_nombre); ?></strong>
                                    <span><?php echo esc_html(TP_Pagos::formatear_fecha($dia['fecha'])); ?></span>
                                </header>

                                <div class="tp-dashboard-class-list">
                                    <?php foreach ($dia['horarios'] as $horario) : ?>
                                        <?php
                                        $conteo = $horario['conteo'];
                                        $resumen = array();

                                        if ($conteo['reservadas']) {
                                            $resumen[] = $conteo['reservadas'] . ' reservadas';
                                        }

                                        if ($conteo['asistieron']) {
                                            $resumen[] = $conteo['asistieron'] . ' asistieron';
                                        }

                                        if ($conteo['faltas']) {
                                            $resumen[] = $conteo['faltas'] . ' faltas';
                                        }

                                        if ($conteo['canceladas']) {
                                            $resumen[] = $conteo['canceladas'] . ' canceladas';
                                        }

                                        if ($conteo['recuperaciones']) {
                                            $resumen[] = $conteo['recuperaciones'] . ' recuperaciones';
                                        }
                                        ?>
                                        <details class="tp-dashboard-class">
                                            <summary>
                                                <span>
                                                    <strong><?php echo esc_html(TP_Horarios::formatear_hora($horario['hora_inicio'])); ?></strong>
                                                    <em><?php echo esc_html($conteo['activas'] . '/' . $horario['cupo_maximo'] . ' cupos'); ?></em>
                                                </span>
                                                <small><?php echo esc_html($resumen ? implode(' · ', $resumen) : 'Sin reservas activas'); ?></small>
                                            </summary>

                                            <div class="tp-dashboard-students">
                                                <?php foreach ($horario['reservas'] as $reserva) : ?>
                                                    <div class="tp-dashboard-student <?php echo esc_attr('cancelada' === $reserva->estado ? 'is-cancelled' : ''); ?>">
                                                        <strong><?php echo esc_html($reserva->display_name); ?></strong>
                                                        <span class="tp-status <?php echo esc_attr('falto' === $reserva->estado || 'cancelada' === $reserva->estado ? 'tp-status-inactive' : ('asistio' === $reserva->estado ? 'tp-status-active' : '')); ?>">
                                                            <?php echo esc_html('recuperacion' === $reserva->tipo ? 'Recuperación' : ($estado_labels[$reserva->estado] ?? $reserva->estado)); ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="tp-empty-state"><?php echo esc_html__('No hay reservas para esta semana.', 'tatipilates'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <aside class="tp-dashboard-side">
            <section class="tp-window">
                <div class="tp-window-bar">
                    <span></span>
                    <span></span>
                    <strong><?php echo esc_html__('Atención', 'tatipilates'); ?></strong>
                </div>
                <div class="tp-window-body">
                    <div class="tp-dashboard-alerts">
                        <a href="<?php echo esc_url(add_query_arg('page', 'tatipilates-pagos', admin_url('admin.php'))); ?>">
                            <strong><?php echo esc_html(count($pendientes_pago)); ?></strong>
                            <span><?php echo esc_html($en_gracia ? 'pagos pendientes en gracia' : 'pagos vencidos'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('page', 'tatipilates-recuperaciones', admin_url('admin.php'))); ?>">
                            <strong><?php echo esc_html(count($recuperaciones)); ?></strong>
                            <span><?php echo esc_html__('recuperaciones pendientes', 'tatipilates'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('page', 'tatipilates-recuperaciones', admin_url('admin.php'))); ?>">
                            <strong><?php echo esc_html(count($recuperaciones_urgentes)); ?></strong>
                            <span><?php echo esc_html__('recuperaciones por vencer', 'tatipilates'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('page', 'tatipilates-alumnas', admin_url('admin.php'))); ?>">
                            <strong><?php echo esc_html($estudiantes_activos); ?></strong>
                            <span><?php echo esc_html__('estudiantes activos', 'tatipilates'); ?></span>
                        </a>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <div class="tp-section-title">
        <span class="dashicons dashicons-clock" aria-hidden="true"></span>
        <h2><?php echo esc_html__('Actividad reciente', 'tatipilates'); ?></h2>
    </div>

    <div class="tp-window">
        <div class="tp-window-body">
            <div class="tp-dashboard-list">
                <?php if ($actividad) : ?>
                    <?php foreach ($actividad as $reserva) : ?>
                        <?php
                        $accion = $actividad_labels[$reserva->estado] ?? 'Actualizó';

                        if ('recuperacion' === $reserva->tipo && 'reservada' === $reserva->estado) {
                            $accion = 'Reservó recuperación';
                        }
                        ?>
                        <article>
                            <div>
                                <strong><?php echo esc_html($accion . ' - ' . $reserva->display_name); ?></strong>
                                <span><?php echo esc_html(TP_Pagos::formatear_fecha($reserva->fecha) . ' - ' . TP_Horarios::formatear_hora($reserva->hora_inicio)); ?></span>
                            </div>
                            <span class="tp-status <?php echo esc_attr('falto' === $reserva->estado || 'cancelada' === $reserva->estado ? 'tp-status-inactive' : ('asistio' === $reserva->estado ? 'tp-status-active' : '')); ?>">
                                <?php echo esc_html('recuperacion' === $reserva->tipo ? 'Recuperación' : ($estado_labels[$reserva->estado] ?? $reserva->estado)); ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="tp-empty-state"><?php echo esc_html__('Todavía no hay actividad esta semana.', 'tatipilates'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($tp_dashboard_partial)) : ?>
<script>
    (function () {
        if (window.tpDashboardNavigate) {
            return;
        }

        var loading = false;
        var partialNonce = '<?php echo esc_js(wp_create_nonce('tp_dashboard_partial')); ?>';

        function targetUrl(target) {
            if (target instanceof HTMLFormElement) {
                var url = new URL(target.action, window.location.href);
                var pageField = target.querySelector('[name="page"]');
                var weekField = target.querySelector('[name="semana"]');

                if (pageField) {
                    url.searchParams.set('page', pageField.value);
                }

                if (weekField) {
                    url.searchParams.set('semana', weekField.value);
                }

                return url.toString();
            }

            return String(target);
        }

        window.tpDashboardNavigate = function (target, addHistory) {
            var url = targetUrl(target);
            var requestUrl = new URL(url);
            var currentPage = document.querySelector('.tp-dashboard-page');

            if (loading || !currentPage) {
                return;
            }

            requestUrl.searchParams.set('tp_dashboard_partial', '1');
            requestUrl.searchParams.set('_wpnonce', partialNonce);

            loading = true;
            currentPage.classList.add('is-week-loading');
            currentPage.setAttribute('aria-busy', 'true');

            window.fetch(requestUrl.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Dashboard request failed.');
                    }

                    return response.text();
                })
                .then(function (html) {
                    var nextDocument = new DOMParser().parseFromString(html, 'text/html');
                    var nextPage = nextDocument.querySelector('.tp-dashboard-page');

                    if (!nextPage) {
                        throw new Error('Dashboard response is incomplete.');
                    }

                    currentPage.replaceWith(document.importNode(nextPage, true));

                    if (false !== addHistory) {
                        window.history.pushState({tpDashboardWeek: true}, '', url);
                    }
                })
                .catch(function () {
                    window.location.assign(url);
                })
                .finally(function () {
                    loading = false;

                    var activePage = document.querySelector('.tp-dashboard-page');

                    if (activePage) {
                        activePage.classList.remove('is-week-loading');
                        activePage.removeAttribute('aria-busy');
                    }
                });
        };

        document.addEventListener('click', function (event) {
            var link = event.target.closest('[data-tp-dashboard-week-nav]');
            var picker = event.target.closest('[data-tp-week-picker]');

            if (picker && !event.defaultPrevented && event.button === 0) {
                var weekInput = picker.querySelector('input[type="week"]');

                if (weekInput && 'function' === typeof weekInput.showPicker) {
                    try {
                        weekInput.showPicker();
                    } catch (error) {
                        weekInput.focus();
                    }
                }
            }

            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            window.tpDashboardNavigate(link.href);
        });

        window.addEventListener('popstate', function () {
            window.tpDashboardNavigate(window.location.href, false);
        });
    }());
</script>
<?php endif; ?>
