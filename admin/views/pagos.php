<?php
/**
 * Payments admin view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

$planes                = TP_Alumnas::planes();
$mes                   = TP_Pagos::normalizar_mes();
$estado                = TP_Pagos::estado_mensual($mes);
$estudiantes           = TP_Alumnas::obtener_todas();
$selected_id           = isset($_GET['estudiante']) ? absint($_GET['estudiante']) : 0;
$selected              = $selected_id ? TP_Alumnas::obtener($selected_id) : null;
$pago_actual           = $selected ? TP_Pagos::obtener_por_estudiante_mes($selected->id, $mes) : null;
$historial_estudiante  = $selected ? TP_Pagos::historial_estudiante($selected->id, 12) : array();
$historial_general     = TP_Pagos::historial(8);
$mensaje               = isset($_GET['tp_mensaje']) ? sanitize_text_field(wp_unslash($_GET['tp_mensaje'])) : '';
$error                 = isset($_GET['tp_error']) ? sanitize_text_field(wp_unslash($_GET['tp_error'])) : '';
$en_gracia             = TP_Pagos::mes_en_gracia($mes);
$fecha_limite_gracia   = TP_Pagos::fecha_limite_gracia($mes);
?>

<div class="wrap tp-admin tp-pagos-page">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Seguimiento de pagos', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Pagos', 'tatipilates'); ?></h1>
        <p><?php echo esc_html__('Confirma el pago mensual de cada estudiante. Sin montos ni moneda: solo importa si puede reservar clases.', 'tatipilates'); ?></p>
    </div>

    <?php if ($mensaje) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Pago', 'tatipilates'); ?> <?php echo esc_html($mensaje); ?> <?php echo esc_html__('correctamente.', 'tatipilates'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html(rawurldecode($error)); ?></p>
        </div>
    <?php endif; ?>

    <div class="tp-window tp-payments-panel">
        <div class="tp-window-bar">
            <span></span>
            <span></span>
            <strong><?php echo esc_html__('Registrar pago', 'tatipilates'); ?></strong>
        </div>

        <div class="tp-window-body">
            <form class="tp-student-selector" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="tatipilates-pagos">
                <label class="tp-field">
                    <span><?php echo esc_html__('Estudiante', 'tatipilates'); ?></span>
                    <select name="estudiante" required>
                        <option value=""><?php echo esc_html__('Seleccionar estudiante', 'tatipilates'); ?></option>
                        <?php foreach ($estudiantes as $estudiante) : ?>
                            <option value="<?php echo esc_attr((int) $estudiante->id); ?>" <?php selected($selected_id, (int) $estudiante->id); ?>>
                                <?php echo esc_html($estudiante->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php submit_button(__('Ver pagos', 'tatipilates'), 'secondary', 'submit', false); ?>
            </form>

            <?php if ($selected) : ?>
                <div class="tp-payment-detail">
                    <div class="tp-payment-summary">
                        <div>
                            <span><?php echo esc_html__('Estudiante', 'tatipilates'); ?></span>
                            <strong><?php echo esc_html($selected->display_name); ?></strong>
                            <a class="tp-muted-link" href="mailto:<?php echo esc_attr($selected->user_email); ?>"><?php echo esc_html($selected->user_email); ?></a>
                        </div>
                        <div>
                            <span><?php echo esc_html__('Plan actual', 'tatipilates'); ?></span>
                            <strong><?php echo esc_html($planes[$selected->plan] ?? $selected->plan); ?></strong>
                        </div>
                        <div>
                            <span><?php echo esc_html(TP_Pagos::formatear_mes($mes)); ?></span>
                            <?php if ('individual' === $selected->plan) : ?>
                                <strong><?php echo esc_html__('Individual', 'tatipilates'); ?></strong>
                            <?php elseif ($pago_actual) : ?>
                                <strong><?php echo esc_html__('Pagado', 'tatipilates'); ?></strong>
                            <?php elseif ($en_gracia) : ?>
                                <strong><?php echo esc_html__('En gracia', 'tatipilates'); ?></strong>
                            <?php else : ?>
                                <strong><?php echo esc_html__('Vencido', 'tatipilates'); ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ('individual' !== $selected->plan && !$pago_actual) : ?>
                        <div class="tp-payment-note">
                            <span class="dashicons dashicons-info" aria-hidden="true"></span>
                            <p>
                                <strong><?php echo esc_html($selected->display_name); ?></strong>
                                <?php echo esc_html__('no tiene pago confirmado para', 'tatipilates'); ?>
                                <strong><?php echo esc_html(TP_Pagos::formatear_mes($mes)); ?></strong>.
                                <?php if ($en_gracia) : ?>
                                    <?php echo esc_html__('Puede reservar durante el periodo de gracia hasta el', 'tatipilates'); ?>
                                    <strong><?php echo esc_html(TP_Pagos::formatear_fecha($fecha_limite_gracia)); ?></strong>.
                                <?php else : ?>
                                    <?php echo esc_html__('Su periodo de gracia termino el', 'tatipilates'); ?>
                                    <strong><?php echo esc_html(TP_Pagos::formatear_fecha($fecha_limite_gracia)); ?></strong><?php echo esc_html__(', asi que no podra reservar hasta confirmar pago.', 'tatipilates'); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <form class="tp-selected-payment-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('tp_registrar_pago'); ?>
                        <input type="hidden" name="action" value="tp_registrar_pago">
                        <input type="hidden" name="alumna_id" value="<?php echo esc_attr((int) $selected->id); ?>">
                        <input type="hidden" name="plan" value="<?php echo esc_attr($selected->plan); ?>">
                        <input type="hidden" name="redirect_estudiante" value="<?php echo esc_attr((int) $selected->id); ?>">

                        <label class="tp-field">
                            <span><?php echo esc_html__('Mes a confirmar', 'tatipilates'); ?></span>
                            <input type="month" name="mes" value="<?php echo esc_attr(substr($mes, 0, 7)); ?>" required>
                        </label>
                        <label class="tp-field">
                            <span><?php echo esc_html__('Fecha de pago', 'tatipilates'); ?></span>
                            <input type="date" name="fecha_pago" value="<?php echo esc_attr($pago_actual ? $pago_actual->fecha_pago : gmdate('Y-m-d', current_time('timestamp'))); ?>" required>
                        </label>
                        <label class="tp-field tp-field-full">
                            <span><?php echo esc_html__('Notas', 'tatipilates'); ?></span>
                            <input type="text" name="notas" value="<?php echo esc_attr($pago_actual ? $pago_actual->notas : ''); ?>" placeholder="<?php echo esc_attr__('Opcional', 'tatipilates'); ?>">
                        </label>
                        <div class="tp-form-actions">
                            <?php submit_button($pago_actual ? __('Actualizar pago', 'tatipilates') : __('Registrar pago', 'tatipilates'), 'primary', 'submit', false); ?>
                        </div>
                    </form>

                    <h3><?php echo esc_html__('Historial del estudiante', 'tatipilates'); ?></h3>
                    <table class="widefat striped tp-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Mes', 'tatipilates'); ?></th>
                                <th><?php echo esc_html__('Plan', 'tatipilates'); ?></th>
                                <th><?php echo esc_html__('Fecha de pago', 'tatipilates'); ?></th>
                                <th><?php echo esc_html__('Notas', 'tatipilates'); ?></th>
                                <th><?php echo esc_html__('Acciones', 'tatipilates'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($historial_estudiante) : ?>
                                <?php foreach ($historial_estudiante as $pago) : ?>
                                    <?php
                                    $eliminar_url = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action'     => 'tp_eliminar_pago',
                                                'pago_id'    => (int) $pago->id,
                                                'estudiante' => (int) $selected->id,
                                            ),
                                            admin_url('admin-post.php')
                                        ),
                                        'tp_eliminar_pago'
                                    );
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html(TP_Pagos::formatear_mes($pago->mes)); ?></td>
                                        <td><?php echo esc_html($planes[$pago->plan] ?? $pago->plan); ?></td>
                                        <td><?php echo esc_html(TP_Pagos::formatear_fecha($pago->fecha_pago)); ?></td>
                                        <td><?php echo esc_html($pago->notas ? $pago->notas : '-'); ?></td>
                                        <td class="tp-actions">
                                            <a class="tp-icon-button tp-icon-button-danger" href="<?php echo esc_url($eliminar_url); ?>" onclick="return confirm('<?php echo esc_js(__('Seguro que quieres eliminar este pago?', 'tatipilates')); ?>');" aria-label="<?php echo esc_attr__('Eliminar pago', 'tatipilates'); ?>" title="<?php echo esc_attr__('Eliminar pago', 'tatipilates'); ?>">
                                                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5"><?php echo esc_html__('Este estudiante todavia no tiene pagos registrados.', 'tatipilates'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tp-section-title">
        <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
        <h2><?php echo esc_html__('Resumen mensual', 'tatipilates'); ?></h2>
    </div>

    <div class="tp-window">
        <div class="tp-window-body">
            <div class="tp-payment-summary-grid">
                <?php if ($estado) : ?>
                    <?php foreach ($estado as $fila) : ?>
                        <a class="tp-payment-person" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-pagos', 'estudiante' => (int) $fila->alumna_id), admin_url('admin.php'))); ?>">
                            <strong><?php echo esc_html($fila->display_name); ?></strong>
                            <?php if ('individual' === $fila->plan_actual) : ?>
                                <span class="tp-status"><?php echo esc_html__('Individual', 'tatipilates'); ?></span>
                            <?php elseif ($fila->pago_id) : ?>
                                <span class="tp-status tp-status-active"><?php echo esc_html__('Pagado', 'tatipilates'); ?></span>
                            <?php elseif ($en_gracia) : ?>
                                <span class="tp-status tp-status-warning"><?php echo esc_html__('En gracia', 'tatipilates'); ?></span>
                            <?php else : ?>
                                <span class="tp-status tp-status-inactive"><?php echo esc_html__('Vencido', 'tatipilates'); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="tp-empty-state"><?php echo esc_html__('Todavia no hay estudiantes activos.', 'tatipilates'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tp-section-title">
        <span class="dashicons dashicons-clock" aria-hidden="true"></span>
        <h2><?php echo esc_html__('Actividad reciente', 'tatipilates'); ?></h2>
    </div>

    <div class="tp-window">
        <div class="tp-window-body">
            <table class="widefat striped tp-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Estudiante', 'tatipilates'); ?></th>
                        <th><?php echo esc_html__('Mes', 'tatipilates'); ?></th>
                        <th><?php echo esc_html__('Plan', 'tatipilates'); ?></th>
                        <th><?php echo esc_html__('Fecha de pago', 'tatipilates'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($historial_general) : ?>
                        <?php foreach ($historial_general as $pago) : ?>
                            <tr>
                                <td><?php echo esc_html($pago->display_name); ?></td>
                                <td><?php echo esc_html(TP_Pagos::formatear_mes($pago->mes)); ?></td>
                                <td><?php echo esc_html($planes[$pago->plan] ?? $pago->plan); ?></td>
                                <td><?php echo esc_html(TP_Pagos::formatear_fecha($pago->fecha_pago)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php echo esc_html__('Todavia no hay pagos registrados.', 'tatipilates'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
