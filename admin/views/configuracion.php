<?php
/**
 * Plugin configuration view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

$mensaje = isset($_GET['tp_mensaje']) ? sanitize_text_field(wp_unslash($_GET['tp_mensaje'])) : '';
$error   = isset($_GET['tp_error']) ? sanitize_text_field(wp_unslash($_GET['tp_error'])) : '';
$notificaciones_config = class_exists('TP_Notificaciones') ? TP_Notificaciones::configuracion() : array();
$updater_config = class_exists('TP_Updater') ? TP_Updater::configuracion() : array();
?>

<div class="wrap tp-admin tp-configuracion-page">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Herramientas', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Configuración', 'tatipilates'); ?></h1>
        <p><?php echo esc_html__('Opciones de mantenimiento para probar y administrar el sistema en local.', 'tatipilates'); ?></p>
    </div>

    <?php if ($mensaje) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(rawurldecode($mensaje)); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html(rawurldecode($error)); ?></p>
        </div>
    <?php endif; ?>

    <div class="tp-admin-layout">
        <div class="tp-window tp-admin-main">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Recordatorios', 'tatipilates'); ?></strong>
            </div>

            <div class="tp-window-body">
                <?php if ($notificaciones_config) : ?>
                    <p><?php echo esc_html__('Controla que avisos internos se generan automaticamente y que texto vera la alumna.', 'tatipilates'); ?></p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tp-reminders-form">
                        <?php wp_nonce_field('tp_guardar_notificaciones_config'); ?>
                        <input type="hidden" name="action" value="tp_guardar_notificaciones_config">

                        <div class="tp-reminders-grid">
                            <label class="tp-field tp-field-checkbox">
                                <input type="checkbox" name="clase_proxima_activa" value="1" <?php checked((int) $notificaciones_config['clase_proxima_activa'], 1); ?>>
                                <span><?php echo esc_html__('Clase proxima', 'tatipilates'); ?></span>
                                <small><?php echo esc_html__('Aviso diario para reservas de manana.', 'tatipilates'); ?></small>
                            </label>

                            <label class="tp-field tp-field-checkbox">
                                <input type="checkbox" name="recuperacion_por_vencer_activa" value="1" <?php checked((int) $notificaciones_config['recuperacion_por_vencer_activa'], 1); ?>>
                                <span><?php echo esc_html__('Recuperacion por vencer', 'tatipilates'); ?></span>
                                <small><?php echo esc_html__('Aviso cuando una recuperacion esta cerca de expirar.', 'tatipilates'); ?></small>
                            </label>

                            <label class="tp-field tp-field-checkbox">
                                <input type="checkbox" name="pago_pendiente_activa" value="1" <?php checked((int) $notificaciones_config['pago_pendiente_activa'], 1); ?>>
                                <span><?php echo esc_html__('Pago pendiente', 'tatipilates'); ?></span>
                                <small><?php echo esc_html__('Aviso durante el periodo de gracia del mes.', 'tatipilates'); ?></small>
                            </label>

                            <label class="tp-field tp-field-checkbox">
                                <input type="checkbox" name="pago_vencido_activa" value="1" <?php checked((int) $notificaciones_config['pago_vencido_activa'], 1); ?>>
                                <span><?php echo esc_html__('Pago vencido', 'tatipilates'); ?></span>
                                <small><?php echo esc_html__('Aviso cuando ya paso el periodo de gracia.', 'tatipilates'); ?></small>
                            </label>
                        </div>

                        <label class="tp-field tp-reminder-days">
                            <span><?php echo esc_html__('Dias antes de vencer recuperacion', 'tatipilates'); ?></span>
                            <input type="number" name="recuperacion_dias" min="1" max="30" value="<?php echo esc_attr((int) $notificaciones_config['recuperacion_dias']); ?>">
                        </label>

                        <div class="tp-reminder-texts">
                            <label class="tp-field">
                                <span><?php echo esc_html__('Texto clase proxima', 'tatipilates'); ?></span>
                                <textarea name="texto_clase_proxima" rows="2"><?php echo esc_textarea($notificaciones_config['texto_clase_proxima']); ?></textarea>
                                <small>{fecha}, {hora}</small>
                            </label>

                            <label class="tp-field">
                                <span><?php echo esc_html__('Texto recuperacion por vencer', 'tatipilates'); ?></span>
                                <textarea name="texto_recuperacion" rows="2"><?php echo esc_textarea($notificaciones_config['texto_recuperacion']); ?></textarea>
                                <small>{fecha_limite}, {dias}</small>
                            </label>

                            <label class="tp-field">
                                <span><?php echo esc_html__('Texto recuperacion vence hoy', 'tatipilates'); ?></span>
                                <textarea name="texto_recuperacion_hoy" rows="2"><?php echo esc_textarea($notificaciones_config['texto_recuperacion_hoy']); ?></textarea>
                                <small>{fecha_limite}, {dias}</small>
                            </label>

                            <label class="tp-field">
                                <span><?php echo esc_html__('Texto pago pendiente', 'tatipilates'); ?></span>
                                <textarea name="texto_pago_pendiente" rows="2"><?php echo esc_textarea($notificaciones_config['texto_pago_pendiente']); ?></textarea>
                                <small>{mes}, {fecha_limite}</small>
                            </label>

                            <label class="tp-field">
                                <span><?php echo esc_html__('Texto pago vencido', 'tatipilates'); ?></span>
                                <textarea name="texto_pago_vencido" rows="2"><?php echo esc_textarea($notificaciones_config['texto_pago_vencido']); ?></textarea>
                                <small>{mes}, {fecha_limite}</small>
                            </label>
                        </div>

                        <div class="tp-form-actions">
                            <?php submit_button(__('Guardar recordatorios', 'tatipilates'), 'primary', 'submit', false); ?>
                        </div>
                    </form>
                <?php else : ?>
                    <p><?php echo esc_html__('El modulo de notificaciones no esta disponible.', 'tatipilates'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="tp-window tp-admin-side">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Datos de prueba', 'tatipilates'); ?></strong>
            </div>

            <div class="tp-window-body">
                <p><?php echo esc_html__('Genera 16 alumnas demo con planes, pagos, reservas, recuperaciones, logros y un usuario Admin Pilates.', 'tatipilates'); ?></p>
                <p><?php echo esc_html__('La acción es idempotente: actualiza esos usuarios demo y rehace sus datos relacionados sin duplicarlos.', 'tatipilates'); ?></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('tp_seed_test_data'); ?>
                    <input type="hidden" name="action" value="tp_seed_test_data">
                    <button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js(__('Esto regenerara los datos demo de las alumnas de prueba. Continuar?', 'tatipilates')); ?>');">
                        <?php echo esc_html__('Generar datos de prueba', 'tatipilates'); ?>
                    </button>
                </form>

                <hr>
                <p><strong><?php echo esc_html__('Desactivar plugin:', 'tatipilates'); ?></strong> <?php echo esc_html__('conserva las tablas y los datos.', 'tatipilates'); ?></p>
                <p><strong><?php echo esc_html__('Eliminar/desinstalar plugin:', 'tatipilates'); ?></strong> <?php echo esc_html__('ejecuta uninstall.php y borra las tablas tp_*, opciones, roles y capabilities del plugin.', 'tatipilates'); ?></p>
                <hr>
                <p><?php echo esc_html__('Credenciales demo:', 'tatipilates'); ?></p>
                <p><code>*.demo@tatipilates.test</code><br><code>Pilates2026!</code></p>
                <p><code>admin.pilates.demo@tatipilates.test</code><br><code>AdminPilates2026!</code></p>
            </div>
        </div>

        <div class="tp-window tp-admin-side">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Actualizaciones privadas', 'tatipilates'); ?></strong>
            </div>

            <div class="tp-window-body">
                <?php if ($updater_config) : ?>
                    <p><?php echo esc_html__('Permite que WordPress detecte versiones publicadas en GitHub segun el canal configurado para este sitio.', 'tatipilates'); ?></p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tp-updater-form">
                        <?php wp_nonce_field('tp_guardar_updater_config'); ?>
                        <input type="hidden" name="action" value="tp_guardar_updater_config">

                        <label class="tp-field tp-field-checkbox">
                            <input type="checkbox" name="enabled" value="1" <?php checked((int) $updater_config['enabled'], 1); ?>>
                            <span><?php echo esc_html__('Activar updater privado', 'tatipilates'); ?></span>
                            <small><?php echo esc_html__('Si esta desactivado, WordPress no mostrara updates privados.', 'tatipilates'); ?></small>
                        </label>

                        <label class="tp-field">
                            <span><?php echo esc_html__('Canal de updates', 'tatipilates'); ?></span>
                            <select name="channel">
                                <option value="stable" <?php selected($updater_config['channel'], 'stable'); ?>><?php echo esc_html__('Stable / live', 'tatipilates'); ?></option>
                                <option value="staging" <?php selected($updater_config['channel'], 'staging'); ?>><?php echo esc_html__('Staging / pruebas', 'tatipilates'); ?></option>
                            </select>
                            <small><?php echo esc_html__('Usa staging en el sitio de pruebas y stable en el sitio live.', 'tatipilates'); ?></small>
                        </label>

                        <label class="tp-field">
                            <span><?php echo esc_html__('GitHub token privado', 'tatipilates'); ?></span>
                            <input type="password" name="token" value="" autocomplete="new-password" placeholder="<?php echo esc_attr(!empty($updater_config['token']) ? __('Token guardado. Dejalo vacio para conservarlo.', 'tatipilates') : __('Pega un token fine-grained de GitHub.', 'tatipilates')); ?>">
                            <small><?php echo esc_html__('Permiso minimo recomendado: Contents read-only solo para este repositorio.', 'tatipilates'); ?></small>
                        </label>

                        <?php if (!empty($updater_config['token'])) : ?>
                            <label class="tp-field tp-field-checkbox">
                                <input type="checkbox" name="clear_token" value="1">
                                <span><?php echo esc_html__('Borrar token guardado', 'tatipilates'); ?></span>
                                <small><?php echo esc_html__('Usalo si queres desconectar este sitio de GitHub.', 'tatipilates'); ?></small>
                            </label>
                        <?php endif; ?>

                        <div class="tp-form-actions">
                            <?php submit_button(__('Guardar updater', 'tatipilates'), 'primary', 'submit', false); ?>
                        </div>
                    </form>
                <?php else : ?>
                    <p><?php echo esc_html__('El modulo de actualizaciones privadas no esta disponible.', 'tatipilates'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
