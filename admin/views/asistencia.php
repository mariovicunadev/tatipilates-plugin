<?php
/**
 * Attendance admin view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap tp-admin">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Control semanal', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Asistencia', 'tatipilates'); ?></h1>
        <p><?php echo esc_html__('Marca asistencia y confirma faltas. Las faltas generan una recuperación con vencimiento de 3 meses.', 'tatipilates'); ?></p>
    </div>

    <?php if ($mensaje) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Asistencia', 'tatipilates'); ?> <?php echo esc_html($mensaje); ?> <?php echo esc_html__('correctamente.', 'tatipilates'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html(rawurldecode($error)); ?></p>
        </div>
    <?php endif; ?>

    <div class="tp-window">
        <div class="tp-window-bar">
            <span></span>
            <span></span>
            <strong><?php echo esc_html__('Semana', 'tatipilates'); ?> <?php echo esc_html(TP_Pagos::formatear_fecha($semana['inicio'])); ?> - <?php echo esc_html(TP_Pagos::formatear_fecha($semana['fin'])); ?></strong>
        </div>

        <div class="tp-window-body">
            <div class="tp-attendance-summary">
                <div>
                    <span><?php echo esc_html__('Reservadas', 'tatipilates'); ?></span>
                    <strong><?php echo esc_html($resumen['reservada']); ?></strong>
                </div>
                <div>
                    <span><?php echo esc_html__('Asistieron', 'tatipilates'); ?></span>
                    <strong><?php echo esc_html($resumen['asistio']); ?></strong>
                </div>
                <div>
                    <span><?php echo esc_html__('Faltas', 'tatipilates'); ?></span>
                    <strong><?php echo esc_html($resumen['falto']); ?></strong>
                </div>
                <div>
                    <span><?php echo esc_html__('Recuperaciones', 'tatipilates'); ?></span>
                    <strong><?php echo esc_html($resumen['recuperacion']); ?></strong>
                </div>
            </div>

            <div class="tp-attendance-week-selector tp-week-range-nav">
                <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-asistencia', 'semana' => $anterior), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>" title="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>">
                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                </a>

                <div class="tp-week-range">
                    <span><?php echo esc_html__('Semana seleccionada', 'tatipilates'); ?></span>
                    <strong><?php echo esc_html(TP_Pagos::formatear_fecha($semana['inicio'])); ?> - <?php echo esc_html(TP_Pagos::formatear_fecha($semana['fin'])); ?></strong>
                </div>

                <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-asistencia'), admin_url('admin.php'))); ?>"><?php echo esc_html__('Semana actual', 'tatipilates'); ?></a>

                <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-asistencia', 'semana' => $siguiente), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>" title="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>">
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                </a>
            </div>

            <div class="tp-attendance-days">
                <?php foreach ($semana['grupos'] as $fecha_dia => $grupos_dia) : ?>
                    <section class="tp-attendance-day">
                        <header>
                            <span><?php echo esc_html($dias[(int) gmdate('N', strtotime($fecha_dia))] ?? ''); ?></span>
                            <strong><?php echo esc_html(TP_Pagos::formatear_fecha($fecha_dia)); ?></strong>
                        </header>

                        <div class="tp-attendance-classes">
	                            <?php foreach ($grupos_dia as $grupo) : ?>
	                                <details class="tp-attendance-class tp-attendance-class-compact">
	                                    <summary class="tp-attendance-class-head">
	                                        <span class="tp-attendance-time">
	                                            <strong><?php echo esc_html(TP_Horarios::formatear_hora($grupo['hora_inicio'])); ?></strong>
	                                            <em><?php echo esc_html($grupo['ocupadas'] . '/' . $grupo['cupo_maximo']); ?></em>
	                                        </span>
	                                        <small><?php echo esc_html($grupo['resumen_grupo']); ?></small>
	                                    </summary>

                                    <div class="tp-attendance-list">
                                        <?php if ($grupo['reservas']) : ?>
	                                            <?php foreach ($grupo['reservas'] as $reserva) : ?>
	                                                <div class="tp-attendance-student">
	                                                    <div>
	                                                        <strong><?php echo esc_html($reserva->display_name); ?></strong>
	                                                        <?php if ('recuperacion' === $reserva->tipo) : ?>
	                                                            <span class="tp-status tp-status-warning"><?php echo esc_html__('Recuperación', 'tatipilates'); ?></span>
	                                                        <?php endif; ?>
	                                                        <span class="<?php echo esc_attr($reserva->tp_estado_clase); ?>"><?php echo esc_html($reserva->tp_estado_label); ?></span>
	                                                    </div>

                                                    <div class="tp-attendance-actions">
                                                        <?php foreach (array('asistio' => 'Asistió', 'falto' => 'Faltó', 'reservada' => 'Reservada') as $estado => $label) : ?>
                                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                                <?php wp_nonce_field('tp_marcar_asistencia'); ?>
                                                                <input type="hidden" name="action" value="tp_marcar_asistencia">
                                                                <input type="hidden" name="reserva_id" value="<?php echo esc_attr((int) $reserva->id); ?>">
                                                                <input type="hidden" name="estado" value="<?php echo esc_attr($estado); ?>">
                                                                <input type="hidden" name="semana" value="<?php echo esc_attr($semana['inicio']); ?>">
                                                                <?php submit_button($label, 'asistio' === $estado ? 'primary small' : 'secondary small', 'submit', false); ?>
                                                            </form>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <p class="tp-empty-slot"><?php echo esc_html__('Sin reservas todavía.', 'tatipilates'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <?php if (empty($semana['grupos'])) : ?>
                    <p class="tp-empty-state"><?php echo esc_html__('No hay horarios activos para esta semana.', 'tatipilates'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
