<?php
/**
 * Manual booking admin view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap tp-admin">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Reserva manual', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Reservar clase', 'tatipilates'); ?></h1>
        <p><?php echo esc_html__('Agenda una clase para una estudiante desde wp-admin. Esto tambien permite cargar clases individuales coordinadas por Tatiana.', 'tatipilates'); ?></p>
    </div>

    <?php if ($mensaje) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(rawurldecode($mensaje)); ?></p>
            <p>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-asistencia', 'semana' => $semana_link), admin_url('admin.php'))); ?>">
                    <?php echo esc_html__('Ver en asistencia', 'tatipilates'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($error) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html(rawurldecode($error)); ?></p>
        </div>
    <?php endif; ?>

    <div class="tp-admin-layout">
        <main class="tp-admin-main">
            <div class="tp-window">
                <div class="tp-window-bar">
                    <span></span>
                    <span></span>
                    <strong><?php echo esc_html__('Nueva reserva', 'tatipilates'); ?></strong>
                </div>

                <div class="tp-window-body">
                    <form class="tp-side-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('tp_admin_reservar'); ?>
                        <input type="hidden" name="action" value="tp_admin_reservar">

                        <fieldset class="tp-field tp-mode-toggle">
                            <span><?php echo esc_html__('Tipo de reserva', 'tatipilates'); ?></span>
                            <div class="tp-segmented-control">
                                <label>
                                    <input type="radio" name="modo_reserva" value="puntual" checked data-tp-booking-mode>
                                    <span><?php echo esc_html__('Una clase', 'tatipilates'); ?></span>
                                </label>
                                <label>
                                    <input type="radio" name="modo_reserva" value="semanal" data-tp-booking-mode>
                                    <span><?php echo esc_html__('Semana completa', 'tatipilates'); ?></span>
                                </label>
                                <label>
                                    <input type="radio" name="modo_reserva" value="mensual" data-tp-booking-mode>
                                    <span><?php echo esc_html__('Mes completo', 'tatipilates'); ?></span>
                                </label>
                            </div>
                        </fieldset>

                        <label class="tp-field">
                            <span><?php echo esc_html__('Estudiante', 'tatipilates'); ?></span>
                            <select name="alumna_id" required data-tp-student-select>
                                <option value=""><?php echo esc_html__('Seleccionar estudiante', 'tatipilates'); ?></option>
                                <?php foreach ($estudiantes as $estudiante) : ?>
                                    <option value="<?php echo esc_attr((int) $estudiante->id); ?>" <?php selected($alumna_id, (int) $estudiante->id); ?>>
                                        <?php echo esc_html($estudiante->display_name . ' - ' . ($planes[$estudiante->plan] ?? $estudiante->plan)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="tp-field" data-tp-single-field>
                            <span data-tp-date-title data-single-title="<?php echo esc_attr__('Fecha', 'tatipilates'); ?>" data-week-title="<?php echo esc_attr__('Semana', 'tatipilates'); ?>"><?php echo esc_html__('Fecha', 'tatipilates'); ?></span>
                            <input type="date" name="fecha" value="<?php echo esc_attr($fecha); ?>" required data-tp-manual-date>
                            <small class="is-hidden" data-tp-week-field-help><?php echo esc_html__('Se usara la semana completa de esta fecha.', 'tatipilates'); ?> <?php echo esc_html(TP_Pagos::formatear_fecha($rango_fecha['inicio'])); ?> - <?php echo esc_html(TP_Pagos::formatear_fecha($rango_fecha['fin'])); ?></small>
                        </label>

                        <label class="tp-field is-hidden" data-tp-month-field>
                            <span><?php echo esc_html__('Mes', 'tatipilates'); ?></span>
                            <input type="month" name="mes" value="<?php echo esc_attr(substr($mes, 0, 7)); ?>" data-tp-manual-month>
                            <small><?php echo esc_html__('Puedes elegir uno o varios horarios fijos. El sistema intentara reservar cada fecha de esos horarios dentro del mes.', 'tatipilates'); ?></small>
                        </label>

                        <fieldset class="tp-field tp-manual-slots">
                            <div class="tp-selection-status is-hidden" data-tp-week-status>
                                <div>
                                    <strong data-tp-week-status-title><?php echo esc_html__('Selecciona clases para esta semana', 'tatipilates'); ?></strong>
                                    <span data-tp-week-status-copy><?php echo esc_html__('El contador avanza mientras eliges horarios.', 'tatipilates'); ?></span>
                                </div>
                                <div class="tp-selection-meter">
                                    <span data-tp-selected-count>0</span>/<span data-tp-plan-limit>0</span>
                                </div>
                            </div>

                            <span data-tp-slot-title data-single-title="<?php echo esc_attr__('Horario', 'tatipilates'); ?>" data-week-title="<?php echo esc_attr__('Horarios de la semana', 'tatipilates'); ?>" data-month-title="<?php echo esc_attr__('Horarios fijos del mes', 'tatipilates'); ?>"><?php echo esc_html__('Horario', 'tatipilates'); ?></span>
                            <div class="tp-slot-pill-grid" data-tp-slot-list>
	                                <?php foreach ($horarios as $horario) : ?>
	                                    <label class="tp-slot-pill <?php echo esc_attr($horario->tp_visible ? '' : 'is-hidden'); ?>" data-tp-day="<?php echo esc_attr((int) $horario->dia_semana); ?>" data-tp-week-date="<?php echo esc_attr($horario->tp_fecha_semana_horario); ?>">
	                                        <input type="checkbox" name="horario_ids[]" value="<?php echo esc_attr((int) $horario->id); ?>" <?php disabled(!$horario->tp_visible); ?>>
	                                        <span class="tp-slot-day"><?php echo esc_html($horario->tp_dia_nombre); ?></span>
	                                        <strong><?php echo esc_html(TP_Horarios::formatear_hora($horario->hora_inicio)); ?></strong>
	                                        <span class="tp-slot-cupos-single"><?php echo esc_html(sprintf(__('%1$d de %2$d cupos libres', 'tatipilates'), $horario->tp_cupos_single, (int) $horario->cupo_maximo)); ?></span>
	                                        <span class="tp-slot-cupos-week"><?php echo esc_html(sprintf(__('%1$s - %2$d de %3$d cupos libres', 'tatipilates'), TP_Pagos::formatear_fecha($horario->tp_fecha_semana_horario), $horario->tp_cupos_semana, (int) $horario->cupo_maximo)); ?></span>
	                                        <span class="tp-slot-cupos-month"><?php echo esc_html(sprintf(__('Se repite todos los %1$s - %2$d cupos por clase', 'tatipilates'), strtolower($horario->tp_dia_nombre), (int) $horario->cupo_maximo)); ?></span>
	                                    </label>
	                                <?php endforeach; ?>
                            </div>
                            <p class="tp-empty-day <?php echo esc_attr($horarios_dia ? 'is-hidden' : ''); ?>" data-tp-no-slots>
                                <?php echo esc_html__('No hay horarios activos para esta fecha.', 'tatipilates'); ?>
                            </p>
                            <small data-tp-slot-help-single><?php echo esc_html__('Elige un solo horario del dia seleccionado.', 'tatipilates'); ?></small>
                            <small class="is-hidden" data-tp-slot-help-week><?php echo esc_html__('Elige varias clases para reservar la semana en una sola accion.', 'tatipilates'); ?></small>
                            <small class="is-hidden" data-tp-slot-help-month><?php echo esc_html__('Selecciona los horarios fijos que quieras reservar para ese mes. Puedes marcar varios.', 'tatipilates'); ?></small>
                        </fieldset>

                        <div class="tp-form-actions">
                            <?php submit_button(__('Reservar clase', 'tatipilates'), 'primary', 'submit', false, array('data-tp-submit' => '1')); ?>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <aside class="tp-admin-side">
            <div class="tp-window">
                <div class="tp-window-bar">
                    <span></span>
                    <span></span>
                    <strong><?php echo esc_html__('Notas', 'tatipilates'); ?></strong>
                </div>

                <div class="tp-window-body">
                    <p class="tp-side-help"><?php echo esc_html__('Las clases individuales se reservan solamente desde esta pantalla. El portal de alumnas seguira bloqueando ese plan.', 'tatipilates'); ?></p>
                    <p class="tp-side-help"><?php echo esc_html__('Para planes semanales normales se mantienen las validaciones de cupo, pago, limite semanal y recuperaciones.', 'tatipilates'); ?></p>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const students = <?php echo wp_json_encode($estudiantes_meta); ?>;
    const dateInput = document.querySelector('[data-tp-manual-date]');
    const monthInput = document.querySelector('[data-tp-manual-month]');
    const studentInput = document.querySelector('[data-tp-student-select]');
    const slotList = document.querySelector('[data-tp-slot-list]');
    const emptyState = document.querySelector('[data-tp-no-slots]');
    const modeInputs = document.querySelectorAll('[data-tp-booking-mode]');
    const singleField = document.querySelector('[data-tp-single-field]');
    const monthField = document.querySelector('[data-tp-month-field]');
    const dateTitle = document.querySelector('[data-tp-date-title]');
    const weekFieldHelp = document.querySelector('[data-tp-week-field-help]');
    const slotTitle = document.querySelector('[data-tp-slot-title]');
    const singleHelp = document.querySelector('[data-tp-slot-help-single]');
    const weekHelp = document.querySelector('[data-tp-slot-help-week]');
    const monthHelp = document.querySelector('[data-tp-slot-help-month]');
    const weekStatus = document.querySelector('[data-tp-week-status]');
    const weekStatusTitle = document.querySelector('[data-tp-week-status-title]');
    const weekStatusCopy = document.querySelector('[data-tp-week-status-copy]');
    const selectedCount = document.querySelector('[data-tp-selected-count]');
    const planLimit = document.querySelector('[data-tp-plan-limit]');
    const submitButton = document.querySelector('[data-tp-submit]');

    if (!dateInput || !slotList || !emptyState) {
        return;
    }

    const currentMode = function () {
        const selected = document.querySelector('[data-tp-booking-mode]:checked');
        return selected ? selected.value : 'puntual';
    };

    const selectedStudent = function () {
        return studentInput && studentInput.value && students[studentInput.value] ? students[studentInput.value] : null;
    };

    const clearSelections = function () {
        slotList.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
            input.checked = false;
        });
    };

    const updateWeekStatus = function () {
        const mode = currentMode();
        const isWeekly = mode === 'semanal';
        const selected = slotList.querySelectorAll('input[type="checkbox"]:checked').length;
        const student = selectedStudent();
        const limit = student ? Number(student.limite || 0) : 0;

        if (weekStatus) {
            weekStatus.classList.toggle('is-hidden', !isWeekly);
            weekStatus.classList.toggle('is-over', isWeekly && limit > 0 && selected > limit);
        }

        if (selectedCount) {
            selectedCount.textContent = selected;
        }

        if (planLimit) {
            planLimit.textContent = limit || '∞';
        }

        if (weekStatusTitle) {
            weekStatusTitle.textContent = student ? student.label : 'Selecciona un estudiante';
        }

        if (weekStatusCopy) {
            if (!student) {
                weekStatusCopy.textContent = 'Elige estudiante para ver su limite semanal.';
            } else if (student.plan === 'individual') {
                weekStatusCopy.textContent = 'Plan individual: puedes seleccionar las clases coordinadas manualmente.';
            } else if (selected > limit) {
                weekStatusCopy.textContent = 'Seleccionaste mas clases que el plan semanal. Como es una reserva administrativa, se permitira agendarlas como clases extra.';
            } else {
                weekStatusCopy.textContent = 'Puedes reservar hasta ' + limit + ' clase' + (limit === 1 ? '' : 's') + ' de su plan semanal.';
            }
        }
    };

    const updateSlots = function () {
        const mode = currentMode();
        const isMonthly = mode === 'mensual';
        const isWeekly = mode === 'semanal';
        const selectedDate = new Date(dateInput.value + 'T12:00:00');
        const jsDay = selectedDate.getDay();
        const isoDay = jsDay === 0 ? 7 : jsDay;
        let visibleCount = 0;

        slotList.classList.toggle('is-monthly', isMonthly);
        slotList.classList.toggle('is-weekly', isWeekly);
        if (slotTitle) {
            slotTitle.textContent = isMonthly ? slotTitle.dataset.monthTitle : (isWeekly ? slotTitle.dataset.weekTitle : slotTitle.dataset.singleTitle);
        }
        if (dateTitle) {
            dateTitle.textContent = isWeekly ? dateTitle.dataset.weekTitle : dateTitle.dataset.singleTitle;
        }
        singleField.classList.toggle('is-hidden', isMonthly);
        weekFieldHelp.classList.toggle('is-hidden', !isWeekly);
        monthField.classList.toggle('is-hidden', !isMonthly);
        singleHelp.classList.toggle('is-hidden', isMonthly || isWeekly);
        weekHelp.classList.toggle('is-hidden', !isWeekly);
        monthHelp.classList.toggle('is-hidden', !isMonthly);
        dateInput.required = !isMonthly;
        monthInput.required = isMonthly;
        submitButton.value = isMonthly ? 'Reservar mes' : (isWeekly ? 'Reservar semana' : 'Reservar clase');

        slotList.querySelectorAll('[data-tp-day]').forEach(function (slot) {
            const input = slot.querySelector('input[type="checkbox"]');
            const isVisible = isMonthly || isWeekly || Number(slot.dataset.tpDay) === isoDay;

            slot.classList.toggle('is-hidden', !isVisible);
            input.disabled = !isVisible;

            if (!isVisible && input.checked) {
                input.checked = false;
            }

            slot.classList.toggle('is-selected', input.checked);

            if (isVisible) {
                visibleCount++;
            }
        });

        emptyState.classList.toggle('is-hidden', visibleCount > 0);
        updateWeekStatus();
    };

    dateInput.addEventListener('change', function () {
        if (currentMode() === 'mensual') {
            updateSlots();
            return;
        }

        const url = new URL(window.location.href);

        url.searchParams.set('page', 'tatipilates-reservar');
        url.searchParams.set('fecha', dateInput.value);

        if (studentInput && studentInput.value) {
            url.searchParams.set('alumna_id', studentInput.value);
        }

        window.location.href = url.toString();
    });

    modeInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            clearSelections();
            updateSlots();
        });
    });

    slotList.addEventListener('change', function (event) {
        if (currentMode() === 'puntual' && event.target.matches('input[type="checkbox"]') && event.target.checked) {
            slotList.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                if (input !== event.target) {
                    input.checked = false;
                }
            });
        }

        updateSlots();
    });

    if (studentInput) {
        studentInput.addEventListener('change', updateWeekStatus);
    }

    updateSlots();
});
</script>
