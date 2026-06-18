<?php
/**
 * Student portal view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="tp-portal">
    <header class="tp-portal-topbar">
        <a class="tp-portal-brand" href="<?php echo esc_url($reservas_url); ?>">
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr__('Tati Pilates', 'tatipilates'); ?>">
            <span>
                <small><?php echo esc_html__('Mi Pilates', 'tatipilates'); ?></small>
                <strong><?php echo esc_html($estudiante->display_name); ?></strong>
            </span>
        </a>

        <nav class="tp-portal-menu" aria-label="<?php echo esc_attr__('Menu del portal', 'tatipilates'); ?>">
            <a class="<?php echo esc_attr('reservas' === $vista ? 'is-active' : ''); ?>" href="<?php echo esc_url($reservas_url); ?>"><?php echo esc_html__('Reservar', 'tatipilates'); ?></a>
            <a class="<?php echo esc_attr('agenda' === $vista ? 'is-active' : ''); ?>" href="<?php echo esc_url($agenda_url); ?>"><?php echo esc_html__('Agenda', 'tatipilates'); ?></a>
            <details class="tp-notification-menu">
                <summary class="<?php echo esc_attr('notificaciones' === $vista ? 'is-active' : ''); ?>" aria-label="<?php echo esc_attr__('Notificaciones', 'tatipilates'); ?>">
                    <span class="dashicons dashicons-bell" aria-hidden="true"></span>
                    <?php if ($notificaciones_no_vistas) : ?>
                        <em><?php echo esc_html($notificaciones_no_vistas); ?></em>
                    <?php endif; ?>
                </summary>
                <div class="tp-notification-dropdown">
                    <?php if ($notificaciones_recientes) : ?>
                        <?php foreach ($notificaciones_recientes as $notificacion_dropdown) : ?>
                            <div class="tp-notification-dropdown-item is-unread" data-tp-notification-item="<?php echo esc_attr((int) $notificacion_dropdown->id); ?>">
                                <a href="<?php echo esc_url($notificaciones_url); ?>">
                                    <strong><?php echo esc_html($notificacion_dropdown->titulo); ?></strong>
                                    <span><?php echo esc_html(wp_trim_words($notificacion_dropdown->mensaje, 12)); ?></span>
                                </a>
                                <button type="button" class="tp-notification-dismiss" aria-label="<?php echo esc_attr__('Marcar como vista', 'tatipilates'); ?>" title="<?php echo esc_attr__('Marcar como vista', 'tatipilates'); ?>" data-tp-notification-dismiss="<?php echo esc_attr((int) $notificacion_dropdown->id); ?>" data-tp-notification-nonce="<?php echo esc_attr(wp_create_nonce('tp_portal_notificacion_vista_' . (int) $notificacion_dropdown->id)); ?>">
                                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="tp-notification-empty"><?php echo esc_html__('Sin notificaciones.', 'tatipilates'); ?></p>
                    <?php endif; ?>
                    <a class="tp-notification-view-all" href="<?php echo esc_url($notificaciones_url); ?>"><?php echo esc_html__('Ver todas', 'tatipilates'); ?></a>
                </div>
            </details>
            <a class="tp-portal-logout" href="<?php echo esc_url($logout_url); ?>"><?php echo esc_html__('Salir', 'tatipilates'); ?></a>
        </nav>
    </header>

    <section class="tp-portal-hero">
        <p class="tp-kicker"><?php echo esc_html__('Mi Pilates', 'tatipilates'); ?></p>
        <h1><?php echo esc_html($estudiante->display_name); ?></h1>
        <p><?php echo esc_html__('Reserva tus clases, revisa tu semana y reporta ausencias para activar recuperaciones.', 'tatipilates'); ?></p>
    </section>

    <?php if ($mensaje) : ?>
        <div class="tp-portal-message <?php echo esc_attr('warning' === $mensaje_tipo ? 'tp-portal-message-warning' : 'tp-portal-message-success'); ?>"><?php echo esc_html(rawurldecode($mensaje)); ?></div>
    <?php endif; ?>

    <?php if ($error) : ?>
        <div class="tp-portal-message tp-portal-message-error <?php echo esc_attr('tp_plan_individual' === $error_code ? 'is-final' : ''); ?>"><?php echo esc_html(rawurldecode($error)); ?></div>
    <?php endif; ?>

    <?php if ($plan_individual && 'tp_plan_individual' !== $error_code) : ?>
        <div class="tp-portal-message tp-portal-message-warning"><?php echo esc_html__('Tu plan no permite reservas en línea. Coordina tu clase directamente con Tatiana.', 'tatipilates'); ?></div>
    <?php endif; ?>

    <?php if (!empty($estado_pago['mensaje'])) : ?>
        <div class="tp-portal-message tp-portal-message-warning"><?php echo esc_html($estado_pago['mensaje']); ?></div>
    <?php endif; ?>

    <div class="tp-portal-shell">
        <main class="tp-portal-main">
            <?php if ('notificaciones' === $vista) : ?>
            <div class="tp-section-title">
                <h2><?php echo esc_html__('Notificaciones', 'tatipilates'); ?></h2>
            </div>

            <div class="tp-window">
                <div class="tp-window-bar">
                    <span></span>
                    <span></span>
                    <strong><?php echo esc_html__('Avisos de Mi Pilates', 'tatipilates'); ?></strong>
                </div>

                <div class="tp-window-body">
                    <div class="tp-portal-notifications">
                        <?php if ($notificaciones) : ?>
                            <?php foreach ($notificaciones as $notificacion) : ?>
                                <article class="<?php echo esc_attr((int) $notificacion->visto_alumna ? 'is-read' : 'is-unread'); ?>" data-tp-portal-notification-card="<?php echo esc_attr((int) $notificacion->id); ?>">
                                    <div>
                                        <span class="tp-pill <?php echo esc_attr((int) $notificacion->visto_alumna ? '' : 'tp-pill-active'); ?>" data-tp-portal-notification-state>
                                            <?php echo esc_html((int) $notificacion->visto_alumna ? __('Vista', 'tatipilates') : __('Nueva', 'tatipilates')); ?>
                                        </span>
                                        <strong><?php echo esc_html($notificacion->titulo); ?></strong>
                                        <p><?php echo esc_html($notificacion->mensaje); ?></p>
                                        <small><?php echo esc_html(mysql2date('d/m/Y g:i a', $notificacion->created_at)); ?></small>
                                    </div>
                                    <div class="tp-portal-notification-actions">
                                        <?php if (!(int) $notificacion->visto_alumna) : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-tp-portal-notification-mark>
                                                <?php wp_nonce_field('tp_portal_notificacion_vista_' . (int) $notificacion->id); ?>
                                                <input type="hidden" name="action" value="tp_portal_notificacion_vista">
                                                <input type="hidden" name="notificacion_id" value="<?php echo esc_attr((int) $notificacion->id); ?>">
                                                <button class="tp-button tp-portal-action-icon" type="submit" aria-label="<?php echo esc_attr__('Marcar como vista', 'tatipilates'); ?>" title="<?php echo esc_attr__('Marcar como vista', 'tatipilates'); ?>">
                                                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-tp-portal-notification-delete>
                                            <?php wp_nonce_field('tp_portal_notificacion_eliminar_' . (int) $notificacion->id); ?>
                                            <input type="hidden" name="action" value="tp_portal_notificacion_eliminar">
                                            <input type="hidden" name="notificacion_id" value="<?php echo esc_attr((int) $notificacion->id); ?>">
                                            <button class="tp-button tp-portal-action-icon tp-portal-action-icon-danger" type="submit" aria-label="<?php echo esc_attr__('Eliminar notificacion', 'tatipilates'); ?>" title="<?php echo esc_attr__('Eliminar notificacion', 'tatipilates'); ?>">
                                                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                            </button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="tp-empty-state"><?php echo esc_html__('No tienes notificaciones por ahora.', 'tatipilates'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php elseif ('agenda' === $vista) : ?>
            <div class="tp-section-title">
                <h2><?php echo esc_html__('Agenda Semanal', 'tatipilates'); ?></h2>
            </div>

            <div class="tp-window">
                <div class="tp-window-bar">
                    <span></span>
                    <span></span>
	                    <strong><?php echo esc_html($semana['label_rango']); ?></strong>
                </div>

                <div class="tp-window-body">
                    <div class="tp-week-control">
                        <div class="tp-week-control-head">
                            <?php if ($semana_anterior_url) : ?>
                                <a class="tp-week-arrow" href="<?php echo esc_url($semana_anterior_url); ?>" aria-label="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>">&lsaquo;</a>
                            <?php else : ?>
                                <span class="tp-week-arrow is-disabled" aria-hidden="true">&lsaquo;</span>
                            <?php endif; ?>

                            <div>
                                <span><?php echo esc_html__('Semana seleccionada', 'tatipilates'); ?></span>
	                                <strong><?php echo esc_html($semana['label_rango']); ?></strong>
                            </div>

                            <?php if ($semana_siguiente_url) : ?>
                                <a class="tp-week-arrow" href="<?php echo esc_url($semana_siguiente_url); ?>" aria-label="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>">&rsaquo;</a>
                            <?php else : ?>
                                <span class="tp-week-arrow is-disabled" aria-hidden="true">&rsaquo;</span>
                            <?php endif; ?>
                        </div>

                        <nav class="tp-week-picker" aria-label="<?php echo esc_attr__('Semanas disponibles', 'tatipilates'); ?>">
                            <?php foreach ($semanas_disponibles as $semana_opcion) : ?>
	                                <a class="tp-week-chip <?php echo esc_attr($semana_opcion['seleccionada'] ? 'is-selected' : ''); ?>" href="<?php echo esc_url(add_query_arg('semana', $semana_opcion['inicio'], $portal_base_url)); ?>" <?php echo $semana_opcion['seleccionada'] ? 'aria-current="date"' : ''; ?>>
	                                    <span><?php echo esc_html($semana_opcion['label']); ?></span>
	                                    <strong><?php echo esc_html($semana_opcion['label_rango']); ?></strong>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>

                    <div class="tp-public-agenda-days">
                        <?php foreach ($agenda_publica['grupos'] as $fecha_dia => $grupos_dia) : ?>
                            <section class="tp-public-agenda-day">
                                <header>
                                    <span><?php echo esc_html(TP_Horarios::dias_semana()[(int) gmdate('N', strtotime($fecha_dia))] ?? ''); ?></span>
                                    <strong><?php echo esc_html(TP_Pagos::formatear_fecha($fecha_dia)); ?></strong>
                                </header>

                                <div class="tp-public-agenda-slots">
                                    <?php foreach ($grupos_dia as $grupo) : ?>
	                                        <article class="tp-public-agenda-slot">
	                                            <div class="tp-public-agenda-slot-head">
	                                                <strong><?php echo esc_html(TP_Horarios::formatear_hora($grupo['hora_inicio'])); ?></strong>
	                                                <span><?php echo esc_html($grupo['ocupadas'] . '/' . (int) $grupo['cupo_maximo']); ?> <?php echo esc_html__('cupos', 'tatipilates'); ?> &middot; <?php echo esc_html($grupo['libres']); ?> <?php echo esc_html__('libres', 'tatipilates'); ?></span>
	                                            </div>
	
	                                            <?php if ($grupo['reservas_visibles']) : ?>
	                                                <div class="tp-public-agenda-students">
	                                                    <?php foreach ($grupo['reservas_visibles'] as $reserva) : ?>
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
                                                                <?php endif; ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else : ?>
                                                <p class="tp-empty-state"><?php echo esc_html__('Sin reservas.', 'tatipilates'); ?></p>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else : ?>
            <div class="tp-section-title">
                <h2><?php echo esc_html($modo_historial ? __('Historial de la semana', 'tatipilates') : __('Elegir clases de la semana', 'tatipilates')); ?></h2>
            </div>

            <div class="tp-window">
                <div class="tp-window-bar">
                    <span></span>
                    <span></span>
	                    <strong><?php echo esc_html($semana['label_rango']); ?></strong>
                </div>

                <div class="tp-window-body">
                    <div class="tp-week-control">
                        <div class="tp-week-control-head">
                            <?php if ($semana_anterior_url) : ?>
                                <a class="tp-week-arrow" href="<?php echo esc_url($semana_anterior_url); ?>" aria-label="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>">&lsaquo;</a>
                            <?php else : ?>
                                <span class="tp-week-arrow is-disabled" aria-hidden="true">&lsaquo;</span>
                            <?php endif; ?>

                            <div>
                                <span><?php echo esc_html__('Semana seleccionada', 'tatipilates'); ?></span>
	                                <strong><?php echo esc_html($semana['label_rango']); ?></strong>
                            </div>

                            <?php if ($semana_siguiente_url) : ?>
                                <a class="tp-week-arrow" href="<?php echo esc_url($semana_siguiente_url); ?>" aria-label="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>">&rsaquo;</a>
                            <?php else : ?>
                                <span class="tp-week-arrow is-disabled" aria-hidden="true">&rsaquo;</span>
                            <?php endif; ?>
                        </div>

                        <nav class="tp-week-picker" aria-label="<?php echo esc_attr__('Semanas disponibles', 'tatipilates'); ?>">
                            <?php foreach ($semanas_disponibles as $semana_opcion) : ?>
	                                <a class="tp-week-chip <?php echo esc_attr($semana_opcion['seleccionada'] ? 'is-selected' : ''); ?>" href="<?php echo esc_url(add_query_arg('semana', $semana_opcion['inicio'], $portal_base_url)); ?>" <?php echo $semana_opcion['seleccionada'] ? 'aria-current="date"' : ''; ?>>
	                                    <span><?php echo esc_html($semana_opcion['label']); ?></span>
	                                    <strong><?php echo esc_html($semana_opcion['label_rango']); ?></strong>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>

                    <?php if ($modo_historial) : ?>
                        <div class="tp-history-note">
                            <strong><?php echo esc_html__('Modo historial', 'tatipilates'); ?></strong>
                            <span><?php echo esc_html__('Esta semana ya termino. Aqui puedes revisar tus clases, asistencias, faltas y recuperaciones registradas.', 'tatipilates'); ?></span>
                        </div>
                    <?php endif; ?>

	                    <div class="tp-booking-days">
	                        <?php foreach ($agenda as $dia) : ?>
	                            <?php if (empty($dia['mostrar'])) : ?>
	                                <?php continue; ?>
	                            <?php endif; ?>
                            <section class="tp-booking-day">
                                <header>
                                    <span><?php echo esc_html($dia['nombre']); ?></span>
                                    <strong><?php echo esc_html(TP_Pagos::formatear_fecha($dia['fecha'])); ?></strong>
                                </header>

                                <div class="tp-booking-slots">
	                                    <?php if ($dia['horarios_visibles']) : ?>
	                                        <?php foreach ($dia['horarios_visibles'] as $slot) : ?>
	                                            <?php
	                                            $horario      = $slot['horario'];
	                                            $reserva      = $slot['reserva'];
	                                            $cupos        = (int) $slot['cupos'];
	                                            ?>
	                                            <article class="tp-booking-slot <?php echo esc_attr($slot['clases']); ?>">
                                                <div>
                                                    <strong><?php echo esc_html(TP_Horarios::formatear_hora($horario->hora_inicio)); ?></strong>
                                                    <span><?php echo esc_html($cupos . ' cupos libres'); ?></span>
                                                </div>

                                                <?php if ($reserva) : ?>
                                                    <div class="tp-slot-reserved-actions">
                                                        <?php if ('falto' === $reserva->estado) : ?>
                                                            <span class="tp-pill tp-pill-danger"><?php echo esc_html__('Falto', 'tatipilates'); ?></span>
                                                        <?php elseif ('recuperacion' === $reserva->tipo) : ?>
                                                            <span class="tp-pill tp-pill-recovery"><?php echo esc_html__('Recuperacion', 'tatipilates'); ?></span>
                                                        <?php else : ?>
                                                            <span class="tp-pill tp-pill-active"><?php echo esc_html(ucfirst($reserva->estado)); ?></span>
                                                        <?php endif; ?>

                                                        <?php if ('reservada' === $reserva->estado && $dia['fecha'] >= $hoy) : ?>
                                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                                <?php wp_nonce_field('tp_portal_cancelar_reserva'); ?>
                                                                <input type="hidden" name="action" value="tp_portal_cancelar_reserva">
                                                                <input type="hidden" name="reserva_id" value="<?php echo esc_attr((int) $reserva->id); ?>">
                                                                <button class="tp-button tp-button-ghost" type="submit" onclick="return confirm('<?php echo esc_js(__('Cancelar esta reserva? No se creara recuperacion.', 'tatipilates')); ?>');"><?php echo esc_html__('Cancelar', 'tatipilates'); ?></button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
	                                                <?php elseif ($modo_historial || $slot['fecha_pasada']) : ?>
                                                    <span class="tp-pill"><?php echo esc_html($modo_historial ? __('Sin registro', 'tatipilates') : __('Pasada', 'tatipilates')); ?></span>
                                                <?php elseif ($plan_individual) : ?>
                                                    <span class="tp-pill tp-pill-warning"><?php echo esc_html__('Coordinar con Tatiana', 'tatipilates'); ?></span>
                                                <?php elseif (!$puede_pagar) : ?>
                                                    <span class="tp-pill tp-pill-warning"><?php echo esc_html__('Pago pendiente', 'tatipilates'); ?></span>
	                                                <?php elseif ($slot['lleno']) : ?>
                                                    <span class="tp-pill"><?php echo esc_html__('Lleno', 'tatipilates'); ?></span>
                                                <?php else : ?>
                                                    <div class="tp-slot-booking-actions">
                                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                            <?php wp_nonce_field('tp_portal_reservar'); ?>
                                                            <input type="hidden" name="action" value="tp_portal_reservar">
                                                            <input type="hidden" name="horario_id" value="<?php echo esc_attr((int) $horario->id); ?>">
                                                            <input type="hidden" name="fecha" value="<?php echo esc_attr($dia['fecha']); ?>">
                                                            <button class="tp-button tp-button-soft" type="submit"><?php echo esc_html__('Reservar', 'tatipilates'); ?></button>
                                                        </form>
                                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                            <?php wp_nonce_field('tp_portal_reservar_mes'); ?>
                                                            <input type="hidden" name="action" value="tp_portal_reservar_mes">
                                                            <input type="hidden" name="horario_id" value="<?php echo esc_attr((int) $horario->id); ?>">
                                                            <input type="hidden" name="fecha" value="<?php echo esc_attr($dia['fecha']); ?>">
                                                            <button class="tp-button tp-button-month" type="submit" onclick="return confirm('<?php echo esc_js(__('Reservar este horario fijo por el resto del mes?', 'tatipilates')); ?>');"><?php echo esc_html__('Reservar mes', 'tatipilates'); ?></button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <p class="tp-empty-state"><?php echo esc_html__('Sin horarios este dia.', 'tatipilates'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>

        <aside class="tp-portal-side">
            <div class="tp-window">
                <div class="tp-window-bar">
                    <span></span>
                    <span></span>
                    <strong><?php echo esc_html__('Mi panel', 'tatipilates'); ?></strong>
                </div>

                <div class="tp-window-body">
                    <div class="tp-profile-pills">
                        <span class="tp-pill tp-pill-active"><?php echo esc_html($planes[$estudiante->plan] ?? $estudiante->plan); ?></span>
                        <span class="tp-pill <?php echo esc_attr('vencido' === ($estado_pago['estado'] ?? '') ? 'tp-pill-danger' : ('gracia' === ($estado_pago['estado'] ?? '') ? 'tp-pill-warning' : 'tp-pill-active')); ?>">
                            <?php echo esc_html($estado_label[$estado_pago['estado'] ?? ''] ?? 'Pago'); ?>
                        </span>
                        <span class="tp-pill"><?php echo esc_html($recuperaciones_disponibles_total); ?> <?php echo esc_html__('recup. disponibles', 'tatipilates'); ?></span>
                    </div>

                    <div class="tp-week-total">
                        <span><?php echo esc_html__('Esta semana', 'tatipilates'); ?></span>
                        <?php if ('individual' === $estudiante->plan) : ?>
                            <strong><?php echo esc_html($reservas_semana_total); ?> <?php echo esc_html__('clases usadas', 'tatipilates'); ?></strong>
                            <p><?php echo esc_html__('Tu plan se maneja por clases individuales.', 'tatipilates'); ?></p>
                        <?php else : ?>
                            <strong><?php echo esc_html($reservas_semana_total); ?> <?php echo esc_html__('de', 'tatipilates'); ?> <?php echo esc_html($limite_semana); ?> <?php echo esc_html__('clases del plan', 'tatipilates'); ?></strong>
                            <div class="tp-progress" aria-hidden="true">
                                <i style="width: <?php echo esc_attr($progreso); ?>%;"></i>
                            </div>
                            <p>
                                <?php if ($reservas_semana_total >= $limite_semana) : ?>
                                    <?php echo esc_html__('Completaste tus reservas de esta semana.', 'tatipilates'); ?>
                                <?php else : ?>
                                    <?php echo esc_html__('Te quedan', 'tatipilates'); ?> <?php echo esc_html($limite_semana - $reservas_semana_total); ?> <?php echo esc_html__('clases por reservar.', 'tatipilates'); ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($ausencias_semana_total) : ?>
                                <p class="tp-week-total-extra"><?php echo esc_html($ausencias_semana_total); ?> <?php echo esc_html__('ausencia convertida en recuperacion.', 'tatipilates'); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($recuperaciones_semana_capacidad) : ?>
                        <div class="tp-week-total tp-week-total-recovery">
                            <span><?php echo esc_html__('Recuperaciones', 'tatipilates'); ?></span>
                            <strong><?php echo esc_html($recuperaciones_semana_total); ?> <?php echo esc_html__('de', 'tatipilates'); ?> <?php echo esc_html($recuperaciones_semana_capacidad); ?> <?php echo esc_html($recuperaciones_semana_capacidad === 1 ? __('recuperacion usada', 'tatipilates') : __('recuperaciones usadas', 'tatipilates')); ?></strong>
                            <div class="tp-progress tp-progress-recovery" aria-hidden="true">
                                <i style="width: <?php echo esc_attr($recuperaciones_progreso); ?>%;"></i>
                            </div>
                            <p>
                                <?php if ($recuperaciones_disponibles_total) : ?>
                                    <?php echo esc_html($recuperaciones_disponibles_total); ?> <?php echo esc_html($recuperaciones_disponibles_total === 1 ? __('recuperacion disponible para usar.', 'tatipilates') : __('recuperaciones disponibles para usar.', 'tatipilates')); ?>
                                <?php else : ?>
                                    <?php echo esc_html__('Ya usaste tus recuperaciones disponibles.', 'tatipilates'); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="tp-dashboard-shortcuts">
                        <?php if ('agenda' === $vista) : ?>
                            <a class="tp-button tp-button-soft" href="<?php echo esc_url($reservas_url); ?>"><?php echo esc_html__('Reservar clases', 'tatipilates'); ?></a>
                        <?php else : ?>
                            <a class="tp-button tp-button-soft" href="<?php echo esc_url($agenda_url); ?>"><?php echo esc_html__('Agenda Semanal', 'tatipilates'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($logros)) : ?>
                <div class="tp-section-title">
                    <h2><?php echo esc_html__('Mis logros', 'tatipilates'); ?></h2>
                </div>

                <div class="tp-window">
                    <div class="tp-window-body">
                        <div class="tp-portal-achievements">
                            <?php foreach ($logros as $logro) : ?>
                                <?php $logro_completado = 'logrado' === $logro->estado; ?>
                                <article class="tp-portal-achievement">
                                    <div>
                                        <strong><?php echo esc_html($logro->titulo); ?></strong>
                                        <span><?php echo esc_html(TP_Pagos::formatear_fecha($logro->fecha)); ?></span>
                                    </div>
                                    <span class="tp-pill <?php echo esc_attr($logro_completado ? 'tp-pill-active' : 'tp-pill-warning'); ?>">
                                        <?php echo esc_html($logro_completado ? __('Logrado', 'tatipilates') : __('En progreso', 'tatipilates')); ?>
                                    </span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($reservas_reportables) : ?>
                <div class="tp-section-title">
                    <h2><?php echo esc_html__('Reportar ausencia', 'tatipilates'); ?></h2>
                </div>

                <div class="tp-window">
                    <div class="tp-window-body">
                        <form class="tp-absence-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('tp_portal_reportar_ausencia'); ?>
                            <input type="hidden" name="action" value="tp_portal_reportar_ausencia">

                            <label>
                                <span><?php echo esc_html__('Clase', 'tatipilates'); ?></span>
                                <select name="reserva_id" required>
                                    <?php foreach ($reservas_reportables as $reserva) : ?>
                                        <option value="<?php echo esc_attr((int) $reserva->id); ?>">
                                            <?php echo esc_html(TP_Pagos::formatear_fecha($reserva->fecha) . ' - ' . TP_Horarios::formatear_hora($reserva->hora_inicio)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span><?php echo esc_html__('Motivo opcional', 'tatipilates'); ?></span>
                                <textarea name="motivo" rows="3" placeholder="<?php echo esc_attr__('Ej: cita medica', 'tatipilates'); ?>"></textarea>
                            </label>

                            <button class="tp-button tp-button-primary" type="submit"><?php echo esc_html__('Confirmar ausencia', 'tatipilates'); ?></button>
                            <p><?php echo esc_html__('Se creara una recuperacion valida por 3 meses.', 'tatipilates'); ?></p>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>
