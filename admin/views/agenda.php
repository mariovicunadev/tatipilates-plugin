<?php
/**
 * Private weekly agenda view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap tp-admin">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Agenda privada', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Agenda semanal', 'tatipilates'); ?></h1>
        <p><?php echo esc_html__('Revisa el estado actual de la semana, copia el enlace del portal y genera un resumen listo para pegar en WhatsApp. El enlace pide acceso de estudiante.', 'tatipilates'); ?></p>
    </div>

    <div class="tp-admin-layout tp-agenda-layout">
        <section class="tp-window">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Semana', 'tatipilates'); ?> <?php echo esc_html(TP_Pagos::formatear_fecha($semana['inicio'])); ?> - <?php echo esc_html(TP_Pagos::formatear_fecha($semana['fin'])); ?></strong>
            </div>

            <div class="tp-window-body">
                <div class="tp-attendance-week-selector tp-week-range-nav tp-agenda-nav">
                    <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-agenda', 'semana' => $anterior), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>" title="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                    </a>

                    <div class="tp-week-range">
                        <span><?php echo esc_html__('Semana seleccionada', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html(TP_Pagos::formatear_fecha($semana['inicio'])); ?> - <?php echo esc_html(TP_Pagos::formatear_fecha($semana['fin'])); ?></strong>
                    </div>

                    <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-agenda'), admin_url('admin.php'))); ?>"><?php echo esc_html__('Semana actual', 'tatipilates'); ?></a>

                    <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-agenda', 'semana' => $siguiente), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>" title="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </div>

                <div class="tp-agenda-days">
                    <?php foreach ($semana['grupos'] as $fecha_dia => $grupos_dia) : ?>
                        <section class="tp-agenda-day">
                            <header>
                                <span><?php echo esc_html($dias[(int) gmdate('N', strtotime($fecha_dia))] ?? ''); ?></span>
                                <strong><?php echo esc_html(TP_Pagos::formatear_fecha($fecha_dia)); ?></strong>
                            </header>

                            <div class="tp-agenda-slots">
	                                <?php foreach ($grupos_dia as $grupo) : ?>
	                                    <article class="tp-agenda-slot">
	                                        <div class="tp-agenda-slot-head">
	                                            <strong><?php echo esc_html(TP_Horarios::formatear_hora($grupo['hora_inicio'])); ?></strong>
	                                            <span><?php echo esc_html($grupo['ocupadas'] . '/' . (int) $grupo['cupo_maximo']); ?> <?php echo esc_html__('cupos', 'tatipilates'); ?> &middot; <?php echo esc_html($grupo['libres']); ?> <?php echo esc_html__('libres', 'tatipilates'); ?></span>
	                                        </div>
	
	                                        <?php if ($grupo['reservas_visibles']) : ?>
	                                            <div class="tp-agenda-students">
	                                                <?php foreach ($grupo['reservas_visibles'] as $reserva) : ?>
	                                                    <div class="tp-agenda-student">
	                                                        <strong><?php echo esc_html($reserva->display_name); ?></strong>
	                                                        <span>
	                                                            <?php if ('recuperacion' === $reserva->tipo) : ?>
	                                                                <em class="tp-status tp-status-warning"><?php echo esc_html__('Recuperacion', 'tatipilates'); ?></em>
	                                                            <?php endif; ?>
	                                                            <em class="<?php echo esc_attr($reserva->tp_estado_clase); ?>"><?php echo esc_html($reserva->tp_estado_label); ?></em>
	                                                        </span>
	                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else : ?>
                                            <p class="tp-empty-slot"><?php echo esc_html__('Sin reservas.', 'tatipilates'); ?></p>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <?php if (empty($semana['grupos'])) : ?>
                        <p class="tp-empty-state"><?php echo esc_html__('No hay horarios activos para esta semana.', 'tatipilates'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <aside class="tp-window">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Compartir', 'tatipilates'); ?></strong>
            </div>
            <div class="tp-window-body tp-agenda-share">
                <label class="tp-field">
                    <span><?php echo esc_html__('Enlace privado', 'tatipilates'); ?></span>
                    <input id="tp-agenda-link" type="text" readonly value="<?php echo esc_attr($enlace); ?>">
                </label>
                <button type="button" class="button" data-copy-target="tp-agenda-link"><?php echo esc_html__('Copiar enlace', 'tatipilates'); ?></button>

                <label class="tp-field">
                    <span><?php echo esc_html__('Texto para WhatsApp', 'tatipilates'); ?></span>
                    <textarea id="tp-agenda-text" rows="18" readonly><?php echo esc_textarea($texto_whatsapp); ?></textarea>
                </label>
                <button type="button" class="button button-primary" data-copy-target="tp-agenda-text"><?php echo esc_html__('Copiar texto', 'tatipilates'); ?></button>
                <p><?php echo esc_html__('Este resumen se genera con el estado actual de la agenda. Si cambia una reserva, vuelve a copiarlo.', 'tatipilates'); ?></p>
            </div>
        </aside>
    </div>
</div>

<script>
document.addEventListener('click', function(event) {
    var button = event.target.closest('[data-copy-target]');

    if (!button) {
        return;
    }

    var target = document.getElementById(button.getAttribute('data-copy-target'));

    if (!target) {
        return;
    }

    target.select();
    target.setSelectionRange(0, target.value.length);
    document.execCommand('copy');

    button.dataset.originalText = button.dataset.originalText || button.textContent;
    button.textContent = 'Copiado';

    window.setTimeout(function() {
        button.textContent = button.dataset.originalText;
    }, 1600);
});
</script>
