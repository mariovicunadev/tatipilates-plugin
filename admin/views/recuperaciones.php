<?php
/**
 * Recovery credits admin view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

$estudiantes      = TP_Alumnas::obtener_todas();
$recuperaciones   = TP_Recuperaciones::listar('pendiente');
$historial         = TP_Recuperaciones::listar('todas', 12);
$mensaje           = isset($_GET['tp_mensaje']) ? sanitize_text_field(wp_unslash($_GET['tp_mensaje'])) : '';
$error             = isset($_GET['tp_error']) ? sanitize_text_field(wp_unslash($_GET['tp_error'])) : '';
$fecha_hoy         = gmdate('Y-m-d', current_time('timestamp'));
$fecha_limite_base = gmdate('Y-m-d', strtotime($fecha_hoy . ' +3 months'));
?>

<div class="wrap tp-admin">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Clases pendientes', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Recuperaciones', 'tatipilates'); ?></h1>
        <p><?php echo esc_html__('Controla las clases que un estudiante puede recuperar despues de una falta. Cada recuperacion vence a los 3 meses.', 'tatipilates'); ?></p>
    </div>

    <?php if ($mensaje) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Recuperacion', 'tatipilates'); ?> <?php echo esc_html($mensaje); ?> <?php echo esc_html__('correctamente.', 'tatipilates'); ?></p>
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
                    <strong><?php echo esc_html__('Recuperaciones pendientes', 'tatipilates'); ?></strong>
                </div>

                <div class="tp-window-body">
                    <div class="tp-recovery-list">
                        <?php if ($recuperaciones) : ?>
                            <?php foreach ($recuperaciones as $recuperacion) : ?>
                                <?php
                                $dias_restantes = TP_Recuperaciones::dias_restantes($recuperacion->fecha_limite);
                                $alerta_clase   = $dias_restantes < 0 ? 'tp-recovery-danger' : ($dias_restantes <= 14 ? 'tp-recovery-warning' : '');
                                $eliminar_url   = wp_nonce_url(
                                    add_query_arg(
                                        array(
                                            'action'          => 'tp_eliminar_recuperacion',
                                            'recuperacion_id' => (int) $recuperacion->id,
                                        ),
                                        admin_url('admin-post.php')
                                    ),
                                    'tp_eliminar_recuperacion'
                                );
                                ?>
                                <article class="tp-recovery-card <?php echo esc_attr($alerta_clase); ?>">
                                    <div class="tp-recovery-person">
                                        <span class="tp-avatar"><?php echo esc_html(strtoupper(substr($recuperacion->display_name, 0, 1))); ?></span>
                                        <div>
                                            <strong><?php echo esc_html($recuperacion->display_name); ?></strong>
                                            <a class="tp-muted-link" href="mailto:<?php echo esc_attr($recuperacion->user_email); ?>"><?php echo esc_html($recuperacion->user_email); ?></a>
                                        </div>
                                    </div>

                                    <div class="tp-recovery-meta">
                                        <span>
                                            <small><?php echo esc_html__('Falta', 'tatipilates'); ?></small>
                                            <?php echo esc_html($recuperacion->fecha_falta ? TP_Pagos::formatear_fecha($recuperacion->fecha_falta) : '-'); ?>
                                        </span>
                                        <span>
                                            <small><?php echo esc_html__('Vence', 'tatipilates'); ?></small>
                                            <?php echo esc_html(TP_Pagos::formatear_fecha($recuperacion->fecha_limite)); ?>
                                        </span>
                                        <span>
                                            <small><?php echo esc_html__('Estado', 'tatipilates'); ?></small>
                                            <?php if ($dias_restantes < 0) : ?>
                                                <mark class="tp-status tp-status-inactive"><?php echo esc_html__('Vencida', 'tatipilates'); ?></mark>
                                            <?php elseif ($dias_restantes <= 14) : ?>
                                                <mark class="tp-status tp-status-warning"><?php echo esc_html($dias_restantes); ?> <?php echo esc_html__('dias', 'tatipilates'); ?></mark>
                                            <?php else : ?>
                                                <mark class="tp-status tp-status-active"><?php echo esc_html($dias_restantes); ?> <?php echo esc_html__('dias', 'tatipilates'); ?></mark>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <?php if ($recuperacion->motivo) : ?>
                                        <p class="tp-recovery-reason"><?php echo esc_html($recuperacion->motivo); ?></p>
                                    <?php endif; ?>

                                    <div class="tp-recovery-actions">
                                    <a class="tp-icon-button tp-icon-button-danger" href="<?php echo esc_url($eliminar_url); ?>" onclick="return confirm('<?php echo esc_js(__('Seguro que quieres eliminar esta recuperacion?', 'tatipilates')); ?>');" aria-label="<?php echo esc_attr__('Eliminar recuperacion', 'tatipilates'); ?>" title="<?php echo esc_attr__('Eliminar recuperacion', 'tatipilates'); ?>">
                                            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="tp-empty-state"><?php echo esc_html__('No hay recuperaciones pendientes.', 'tatipilates'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tp-section-title">
                <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                <h2><?php echo esc_html__('Historial', 'tatipilates'); ?></h2>
            </div>

            <div class="tp-window">
                <div class="tp-window-body">
                    <table class="widefat striped tp-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Estudiante', 'tatipilates'); ?></th>
                                <th><?php echo esc_html__('Falta', 'tatipilates'); ?></th>
                                <th><?php echo esc_html__('Vence', 'tatipilates'); ?></th>
                                <th><?php echo esc_html__('Estado', 'tatipilates'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($historial) : ?>
                                <?php foreach ($historial as $recuperacion) : ?>
                                    <tr>
                                        <td><?php echo esc_html($recuperacion->display_name); ?></td>
                                        <td><?php echo esc_html($recuperacion->fecha_falta ? TP_Pagos::formatear_fecha($recuperacion->fecha_falta) : '-'); ?></td>
                                        <td><?php echo esc_html(TP_Pagos::formatear_fecha($recuperacion->fecha_limite)); ?></td>
                                        <td><?php echo esc_html(ucfirst($recuperacion->estado)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4"><?php echo esc_html__('Todavia no hay recuperaciones registradas.', 'tatipilates'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <aside class="tp-admin-side">
            <div class="tp-window">
                <div class="tp-window-bar">
                    <span></span>
                    <span></span>
                    <strong><?php echo esc_html__('Crear recuperacion', 'tatipilates'); ?></strong>
                </div>

                <div class="tp-window-body">
                    <p class="tp-side-help"><?php echo esc_html__('Usa esto para corregir una falta cargada fuera del sistema o darle una recuperacion manual.', 'tatipilates'); ?></p>

                    <form class="tp-side-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('tp_crear_recuperacion'); ?>
                        <input type="hidden" name="action" value="tp_crear_recuperacion">

                        <label class="tp-field">
                            <span><?php echo esc_html__('Estudiante', 'tatipilates'); ?></span>
                            <select name="alumna_id" required>
                                <option value=""><?php echo esc_html__('Seleccionar', 'tatipilates'); ?></option>
                                <?php foreach ($estudiantes as $estudiante) : ?>
                                    <option value="<?php echo esc_attr((int) $estudiante->id); ?>"><?php echo esc_html($estudiante->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="tp-field">
                            <span><?php echo esc_html__('Fecha de falta', 'tatipilates'); ?></span>
                            <input type="date" name="fecha_falta" value="<?php echo esc_attr($fecha_hoy); ?>" required>
                        </label>

                        <label class="tp-field">
                            <span><?php echo esc_html__('Fecha limite', 'tatipilates'); ?></span>
                            <input type="date" name="fecha_limite" value="<?php echo esc_attr($fecha_limite_base); ?>" required>
                        </label>

                        <label class="tp-field">
                            <span><?php echo esc_html__('Motivo', 'tatipilates'); ?></span>
                            <textarea name="motivo" rows="3" placeholder="<?php echo esc_attr__('Opcional', 'tatipilates'); ?>"></textarea>
                        </label>

                        <div class="tp-form-actions">
                            <?php submit_button(__('Crear recuperacion', 'tatipilates'), 'primary', 'submit', false); ?>
                        </div>
                    </form>
                </div>
            </div>
        </aside>
    </div>
</div>
