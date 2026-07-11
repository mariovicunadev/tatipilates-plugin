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
$updater_diagnostico = class_exists('TP_Updater') ? TP_Updater::diagnostico() : array();
$ultimo_backup = class_exists('TP_Backups') ? TP_Backups::ultimo_backup() : null;
$backups_guardados = class_exists('TP_Backups') ? TP_Backups::backups_guardados(5) : array();
$backup_tablas = class_exists('TP_Backups') ? TP_Backups::etiquetas_tablas() : array();
$aviso_backup = class_exists('TP_Backups') ? TP_Backups::aviso_almacenamiento() : null;
$borrar_datos_al_desinstalar = class_exists('TP_Backups') ? TP_Backups::borrar_datos_al_desinstalar() : false;
$eventos_admin = class_exists('TP_Helpers') ? TP_Helpers::eventos_admin(6) : array();
$demo_disponible = class_exists('TP_Test_Data') && TP_Test_Data::is_available();
$credenciales_demo = $demo_disponible ? get_transient('tp_demo_credentials_' . get_current_user_id()) : null;
$aviso_demo_produccion = class_exists('TP_Test_Data') ? TP_Test_Data::hardening_notice() : array();
$backup_max_upload = class_exists('TP_Backups') ? TP_Backups::max_upload_bytes() : 0;
$encryption_status = class_exists('TP_Data_Encryption') ? TP_Data_Encryption::status() : array();

if ($credenciales_demo) {
    delete_transient('tp_demo_credentials_' . get_current_user_id());
}

$tp_formatear_fecha_estado = static function ($valor) {
    if (!$valor) {
        return __('Nunca', 'tatipilates');
    }

    $timestamp = strtotime((string) $valor);

    return $timestamp ? date_i18n('d/m/Y H:i', $timestamp) : (string) $valor;
};
?>

<div class="wrap tp-admin tp-configuracion-page">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Herramientas', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Configuración', 'tatipilates'); ?></h1>
        <p><?php echo esc_html__('Opciones de mantenimiento, backups, actualizaciones y seguridad operativa del plugin.', 'tatipilates'); ?></p>
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

    <?php if ($aviso_backup) : ?>
        <div class="notice notice-error">
            <p><strong><?php echo esc_html__('Backups:', 'tatipilates'); ?></strong> <?php echo esc_html($aviso_backup['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($aviso_demo_produccion['emails']) && is_array($aviso_demo_produccion['emails'])) : ?>
        <div class="notice notice-warning tp-demo-hardening-notice">
            <p>
                <strong><?php echo esc_html__('Cuentas demo desactivadas:', 'tatipilates'); ?></strong>
                <?php echo esc_html(implode(', ', array_map('sanitize_email', $aviso_demo_produccion['emails']))); ?>
            </p>
            <p><?php echo esc_html__('Sus contrasenas fueron rotadas y la cuenta administrativa demo perdio sus privilegios. Revisa estas cuentas y elimina las que no deban conservarse.', 'tatipilates'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('tp_descartar_demo_notice'); ?>
                <input type="hidden" name="action" value="tp_descartar_demo_notice">
                <button type="submit" class="button">
                    <?php echo esc_html__('Ocultar aviso', 'tatipilates'); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($updater_config && 'legacy' === $updater_config['token_source']) : ?>
        <div class="notice notice-warning">
            <p><strong><?php echo esc_html__('Updater privado:', 'tatipilates'); ?></strong> <?php echo esc_html__('Este sitio aun usa el PAT legacy guardado en la base de datos. Define TP_GITHUB_TOKEN en el servidor para que el plugin elimine esa copia automaticamente.', 'tatipilates'); ?></p>
        </div>
    <?php elseif ($updater_config && !empty($updater_config['enabled']) && 'none' === $updater_config['token_source']) : ?>
        <div class="notice notice-error">
            <p><strong><?php echo esc_html__('Updater privado:', 'tatipilates'); ?></strong> <?php echo esc_html__('Esta activado, pero TP_GITHUB_TOKEN no esta configurado. WordPress no podra consultar ni descargar actualizaciones privadas.', 'tatipilates'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($encryption_status && empty($encryption_status['ready'])) : ?>
        <div class="notice notice-warning">
            <p><strong><?php echo esc_html__('Datos medicos:', 'tatipilates'); ?></strong> <?php echo esc_html__('TP_DATA_ENCRYPTION_KEY no esta listo. Los campos medicos no se podran ver ni editar hasta configurarlo en el servidor.', 'tatipilates'); ?></p>
        </div>
    <?php endif; ?>

    <div class="tp-admin-layout tp-config-layout">
        <div class="tp-config-main-stack">
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
                    <strong><?php echo esc_html__('Zona peligrosa', 'tatipilates'); ?></strong>
                </div>

            <div class="tp-window-body">
                <p><?php echo esc_html__('Controla que ocurre si alguien elimina el plugin desde WordPress. La opcion segura es conservar los datos.', 'tatipilates'); ?></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('tp_guardar_uninstall_config'); ?>
                    <input type="hidden" name="action" value="tp_guardar_uninstall_config">

                    <label class="tp-field tp-field-checkbox">
                        <input type="radio" name="delete_data_on_uninstall" value="0" <?php checked($borrar_datos_al_desinstalar, false); ?>>
                        <span><?php echo esc_html__('NO borrar datos al eliminar el plugin', 'tatipilates'); ?></span>
                        <small><?php echo esc_html__('Recomendado para staging y live. Conserva tablas, opciones, roles y backups.', 'tatipilates'); ?></small>
                    </label>

                    <label class="tp-field tp-field-checkbox">
                        <input type="radio" name="delete_data_on_uninstall" value="1" <?php checked($borrar_datos_al_desinstalar, true); ?>>
                        <span><?php echo esc_html__('Borrar todos los datos al eliminar el plugin', 'tatipilates'); ?></span>
                        <small><?php echo esc_html__('Solo para limpiezas controladas. Borra tablas tp_*, opciones, roles y capabilities.', 'tatipilates'); ?></small>
                    </label>

                    <button type="submit" class="button" onclick="return confirm('<?php echo esc_js(__('Estas cambiando una preferencia sensible de desinstalacion. Continuar?', 'tatipilates')); ?>');">
                        <?php echo esc_html__('Guardar zona peligrosa', 'tatipilates'); ?>
                    </button>
                </form>
            </div>
            </div>
        </div>

        <div class="tp-config-side-stack">
            <div class="tp-window tp-admin-side">
                <div class="tp-window-bar">
                    <span></span>
                    <span></span>
                    <strong><?php echo esc_html__('Backups del plugin', 'tatipilates'); ?></strong>
                </div>

            <div class="tp-window-body">
                <p><?php echo esc_html__('Exporta e importa solo la data propia de Tati Pilates: horarios, alumnas, reservas, recuperaciones, pagos, logros, notificaciones y configuracion de recordatorios.', 'tatipilates'); ?></p>
                <p><?php echo esc_html__('La importacion es idempotente: actualiza lo que ya existe por ID e inserta lo que falte, sin duplicar filas.', 'tatipilates'); ?></p>

                <?php if ($backups_guardados) : ?>
                    <p>
                        <strong><?php echo esc_html__('Historial privado:', 'tatipilates'); ?></strong><br>
                        <small><?php echo esc_html__('Ultimos backups guardados fuera del webroot.', 'tatipilates'); ?></small>
                    </p>
                    <div class="tp-backup-history">
                        <?php foreach ($backups_guardados as $backup_guardado) : ?>
                            <details class="tp-backup-history-item" <?php echo $ultimo_backup && $backup_guardado['name'] === $ultimo_backup['name'] ? 'open' : ''; ?>>
                                <summary>
                                    <span>
                                        <strong><?php echo esc_html($backup_guardado['name']); ?></strong>
                                        <small><?php echo esc_html($backup_guardado['date']); ?> · <?php echo esc_html(size_format((int) $backup_guardado['size'])); ?> · <?php echo esc_html($backup_guardado['hash']); ?></small>
                                    </span>
                                </summary>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('tp_backup_archivo_descargar'); ?>
                                    <input type="hidden" name="action" value="tp_backup_archivo_descargar">
                                    <input type="hidden" name="backup" value="<?php echo esc_attr($backup_guardado['name']); ?>">
                                    <button type="submit" class="button">
                                        <?php echo esc_html__('Descargar', 'tatipilates'); ?>
                                    </button>
                                </form>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tp-backup-restore-form">
                                    <?php wp_nonce_field('tp_backup_archivo_restaurar'); ?>
                                    <input type="hidden" name="action" value="tp_backup_archivo_restaurar">
                                    <input type="hidden" name="backup" value="<?php echo esc_attr($backup_guardado['name']); ?>">

                                    <fieldset>
                                        <legend><?php echo esc_html__('Restaurar tablas', 'tatipilates'); ?></legend>
                                        <?php foreach ($backup_tablas as $tabla_key => $tabla_label) : ?>
                                            <label>
                                                <input type="checkbox" name="tablas[]" value="<?php echo esc_attr($tabla_key); ?>">
                                                <span><?php echo esc_html($tabla_label); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                        <small><?php echo esc_html__('Sin seleccion restaura todo. Seleccionar tablas restaura solo esas secciones despues de validar el backup completo.', 'tatipilates'); ?></small>
                                    </fieldset>

                                    <button type="submit" class="button" onclick="return confirm('<?php echo esc_js(__('Vas a restaurar datos desde este backup privado. Continuar?', 'tatipilates')); ?>');">
                                        <?php echo esc_html__('Restaurar', 'tatipilates'); ?>
                                    </button>
                                </form>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p><?php echo esc_html__('Aun no hay backups automaticos guardados.', 'tatipilates'); ?></p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('tp_backup_descargar'); ?>
                    <input type="hidden" name="action" value="tp_backup_descargar">
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Descargar backup JSON', 'tatipilates'); ?>
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;">
                    <?php wp_nonce_field('tp_backup_generar'); ?>
                    <input type="hidden" name="action" value="tp_backup_generar">
                    <button type="submit" class="button">
                        <?php echo esc_html__('Generar backup ahora', 'tatipilates'); ?>
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="margin-top: 16px;">
                    <?php wp_nonce_field('tp_backup_importar'); ?>
                    <input type="hidden" name="action" value="tp_backup_importar">
                    <label class="tp-field">
                        <span><?php echo esc_html__('Importar backup JSON', 'tatipilates'); ?></span>
                        <input type="file" name="tp_backup_file" accept="application/json,.json" required>
                        <small>
                            <?php
                            echo esc_html(
                                sprintf(
                                    __('Formatos soportados: legacy v1 y actual v%1$d. Limite: %2$s.', 'tatipilates'),
                                    TP_Backups::BACKUP_FORMAT_VERSION,
                                    size_format($backup_max_upload)
                                )
                            );
                            ?>
                        </small>
                    </label>
                    <?php if ($backup_tablas) : ?>
                        <fieldset class="tp-backup-restore-form">
                            <legend><?php echo esc_html__('Importacion selectiva', 'tatipilates'); ?></legend>
                            <?php foreach ($backup_tablas as $tabla_key => $tabla_label) : ?>
                                <label>
                                    <input type="checkbox" name="tablas[]" value="<?php echo esc_attr($tabla_key); ?>">
                                    <span><?php echo esc_html($tabla_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                            <small><?php echo esc_html__('Sin seleccion importa todo. Si eliges tablas, las demas se validan pero no se escriben.', 'tatipilates'); ?></small>
                        </fieldset>
                    <?php endif; ?>
                    <button type="submit" class="button" onclick="return confirm('<?php echo esc_js(__('La importacion sincronizara datos del backup con la base actual. Continuar?', 'tatipilates')); ?>');">
                        <?php echo esc_html__('Importar backup', 'tatipilates'); ?>
                    </button>
                </form>
            </div>
        </div>

        <?php if ($eventos_admin) : ?>
        <div class="tp-window tp-admin-side">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Eventos operativos', 'tatipilates'); ?></strong>
            </div>

            <div class="tp-window-body">
                <p><?php echo esc_html__('Ultimos avisos tecnicos relevantes para administracion.', 'tatipilates'); ?></p>
                <ul class="tp-admin-events">
                    <?php foreach ($eventos_admin as $evento) : ?>
                        <li class="tp-admin-event-<?php echo esc_attr($evento['level'] ?? 'info'); ?>">
                            <strong><?php echo esc_html($evento['mensaje'] ?? 'Evento registrado.'); ?></strong>
                            <small><?php echo esc_html($tp_formatear_fecha_estado($evento['created_at'] ?? '')); ?></small>
                            <?php if (!empty($evento['context']) && is_array($evento['context'])) : ?>
                                <code><?php echo esc_html(wp_json_encode($evento['context'])); ?></code>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($demo_disponible) : ?>
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

                <?php if ($credenciales_demo) : ?>
                    <hr>
                    <p><strong><?php echo esc_html__('Credenciales temporales recien generadas', 'tatipilates'); ?></strong></p>
                    <p>
                        <code><?php echo esc_html(implode(', ', (array) $credenciales_demo['usuarios_alumnas'])); ?></code><br>
                        <code><?php echo esc_html($credenciales_demo['password_alumnas']); ?></code>
                    </p>
                    <p>
                        <code><?php echo esc_html($credenciales_demo['usuario_admin_pilates']); ?></code><br>
                        <code><?php echo esc_html($credenciales_demo['password_admin_pilates']); ?></code>
                    </p>
                    <p><a href="<?php echo esc_url($credenciales_demo['portal']); ?>" target="_blank" rel="noreferrer"><?php echo esc_html($credenciales_demo['portal']); ?></a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($encryption_status) : ?>
        <div class="tp-window tp-admin-side">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Datos medicos', 'tatipilates'); ?></strong>
            </div>

            <div class="tp-window-body">
                <p><?php echo esc_html__('Protege historia medica, alergias y motivo de Pilates con una clave del servidor.', 'tatipilates'); ?></p>

                <div class="tp-updater-diagnostics">
                    <div class="tp-updater-diagnostics-head">
                        <strong><?php echo esc_html__('Cifrado', 'tatipilates'); ?></strong>
                        <span class="tp-status <?php echo !empty($encryption_status['ready']) ? 'tp-updater-state-current' : 'tp-updater-state-error'; ?>">
                            <?php echo !empty($encryption_status['ready']) ? esc_html__('Activo', 'tatipilates') : esc_html__('Revisar', 'tatipilates'); ?>
                        </span>
                    </div>

                    <dl>
                        <div>
                            <dt><?php echo esc_html__('Sodium', 'tatipilates'); ?></dt>
                            <dd><?php echo !empty($encryption_status['sodium_available']) ? esc_html__('Disponible', 'tatipilates') : esc_html__('No disponible', 'tatipilates'); ?></dd>
                        </div>
                        <div>
                            <dt><?php echo esc_html__('Clave', 'tatipilates'); ?></dt>
                            <dd>
                                <?php
                                if (!empty($encryption_status['key_valid'])) {
                                    echo esc_html__('Configurada', 'tatipilates');
                                } elseif (!empty($encryption_status['key_defined'])) {
                                    echo esc_html__('Formato invalido', 'tatipilates');
                                } else {
                                    echo esc_html__('No configurada', 'tatipilates');
                                }
                                ?>
                            </dd>
                        </div>
                    </dl>

                    <code class="tp-updater-token-example"><?php echo esc_html(TP_Data_Encryption::setup_hint()); ?></code>
                    <small><?php echo esc_html__('Genera una clave hex de 64 caracteres y agregala en wp-config.php. Guarda una copia segura fuera de WordPress; sin esta clave no se recuperan los datos cifrados.', 'tatipilates'); ?></small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="tp-window tp-admin-side">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Actualizaciones privadas', 'tatipilates'); ?></strong>
            </div>

            <div class="tp-window-body">
                <?php if ($updater_config) : ?>
                    <p><?php echo esc_html__('Permite que WordPress detecte versiones publicadas en GitHub segun el canal configurado para este sitio.', 'tatipilates'); ?></p>

                    <?php if ($updater_diagnostico) : ?>
                        <?php
                        $estado_updater = isset($updater_diagnostico['state']) ? (string) $updater_diagnostico['state'] : 'ok';
                        $estado_label   = array(
                            'ok'       => __('Update disponible', 'tatipilates'),
                            'current'  => __('Al dia', 'tatipilates'),
                            'disabled' => __('Desactivado', 'tatipilates'),
                            'error'    => __('Revisar', 'tatipilates'),
                        );
                        $token_label = array(
                            'server' => __('Servidor', 'tatipilates'),
                            'legacy' => __('Legacy', 'tatipilates'),
                            'none'   => __('No configurado', 'tatipilates'),
                        );
                        ?>
                        <div class="tp-updater-diagnostics">
                            <div class="tp-updater-diagnostics-head">
                                <strong><?php echo esc_html__('Diagnostico', 'tatipilates'); ?></strong>
                                <span class="tp-status tp-updater-state-<?php echo esc_attr($estado_updater); ?>">
                                    <?php echo esc_html($estado_label[$estado_updater] ?? __('Estado', 'tatipilates')); ?>
                                </span>
                            </div>

                            <dl>
                                <div>
                                    <dt><?php echo esc_html__('Instalada', 'tatipilates'); ?></dt>
                                    <dd><?php echo esc_html($updater_diagnostico['installed_version']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php echo esc_html__('Disponible', 'tatipilates'); ?></dt>
                                    <dd><?php echo esc_html($updater_diagnostico['available_version'] ?: __('Sin version nueva', 'tatipilates')); ?></dd>
                                </div>
                                <div>
                                    <dt><?php echo esc_html__('Canal', 'tatipilates'); ?></dt>
                                    <dd><?php echo esc_html($updater_diagnostico['channel']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php echo esc_html__('Token', 'tatipilates'); ?></dt>
                                    <dd><?php echo esc_html($token_label[$updater_diagnostico['token_source']] ?? $updater_diagnostico['token_source']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php echo esc_html__('Ultima comprobacion', 'tatipilates'); ?></dt>
                                    <dd><?php echo esc_html($tp_formatear_fecha_estado($updater_diagnostico['last_checked'])); ?></dd>
                                </div>
                                <div>
                                    <dt><?php echo esc_html__('Ultimo error', 'tatipilates'); ?></dt>
                                    <dd><?php echo esc_html($updater_diagnostico['last_error_message'] ?: __('Sin errores registrados', 'tatipilates')); ?></dd>
                                </div>
                            </dl>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('tp_updater_comprobar'); ?>
                                <input type="hidden" name="action" value="tp_updater_comprobar">
                                <button type="submit" class="button">
                                    <?php echo esc_html__('Comprobar ahora', 'tatipilates'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

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

                        <div class="tp-field">
                            <span><?php echo esc_html__('GitHub token privado', 'tatipilates'); ?></span>
                            <?php if ('server' === $updater_config['token_source']) : ?>
                                <strong><?php echo esc_html__('Configurado por servidor', 'tatipilates'); ?></strong>
                                <small><?php echo esc_html__('El PAT se obtiene de TP_GITHUB_TOKEN y no se guarda en WordPress.', 'tatipilates'); ?></small>
                            <?php elseif ('legacy' === $updater_config['token_source']) : ?>
                                <strong><?php echo esc_html__('Usando configuracion legacy', 'tatipilates'); ?></strong>
                                <small><?php echo esc_html__('El updater sigue operativo temporalmente, pero el PAT permanece en la base de datos hasta configurar el servidor.', 'tatipilates'); ?></small>
                            <?php else : ?>
                                <strong><?php echo esc_html__('No configurado', 'tatipilates'); ?></strong>
                                <small><?php echo esc_html__('El updater privado no puede autenticarse con GitHub.', 'tatipilates'); ?></small>
                            <?php endif; ?>
                            <code class="tp-updater-token-example"><?php echo esc_html("define('TP_GITHUB_TOKEN', 'github_pat_REEMPLAZAR');"); ?></code>
                            <small><?php echo esc_html__('Agrega la constante en wp-config.php antes de la linea que detiene la edicion, o define una variable de entorno con el mismo nombre. Usa Contents: read-only solo para este repositorio.', 'tatipilates'); ?></small>
                        </div>

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
</div>
