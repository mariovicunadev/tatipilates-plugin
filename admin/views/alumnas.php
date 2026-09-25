<?php
/**
 * Students admin view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

$planes   = TP_Alumnas::planes();
$alumnas  = TP_Alumnas::obtener_todas();
$puede_ver_datos_medicos_cap = current_user_can(TP_Roles::CAP_VIEW_MEDICAL_DATA);
$cifrado_datos_medicos_listo = class_exists('TP_Data_Encryption') && TP_Data_Encryption::is_ready();
$puede_ver_datos_medicos = $puede_ver_datos_medicos_cap && $cifrado_datos_medicos_listo;
$edit_id  = isset($_GET['editar']) ? absint($_GET['editar']) : 0;
$editando = $edit_id
    ? ($puede_ver_datos_medicos ? TP_Alumnas::obtener_con_datos_medicos($edit_id) : TP_Alumnas::obtener($edit_id))
    : null;
$ficha_id = isset($_GET['ficha']) ? absint($_GET['ficha']) : 0;
$ficha    = $ficha_id
    ? ($puede_ver_datos_medicos ? TP_Alumnas::obtener_con_datos_medicos($ficha_id) : TP_Alumnas::obtener($ficha_id))
    : null;
$mensaje  = isset($_GET['tp_mensaje']) ? sanitize_text_field(wp_unslash($_GET['tp_mensaje'])) : '';
$error    = isset($_GET['tp_error']) ? sanitize_text_field(wp_unslash($_GET['tp_error'])) : '';
$credenciales = get_transient('tp_credenciales_' . get_current_user_id());
$tp_alumnas_vista = isset($tp_alumnas_vista) ? $tp_alumnas_vista : 'registrados';

$editando = is_wp_error($editando) ? null : $editando;
$ficha    = is_wp_error($ficha) ? null : $ficha;

if ($editando) {
    $tp_alumnas_vista = 'registrar';
}

if ($ficha) {
    $tp_alumnas_vista = 'registrados';
}

$titulo_pagina = 'Estudiantes';
$kicker_pagina = 'Gestion de estudiantes';
$descripcion_pagina = 'Revisa perfiles, historial y estado de cada estudiante en un solo lugar.';

if ('registrar' === $tp_alumnas_vista) {
    $titulo_pagina = $editando ? 'Editar estudiante' : 'Registrar estudiante';
    $descripcion_pagina = 'Crea accesos, registra datos personales y configura el plan de cada estudiante.';
} elseif ('cumpleanos' === $tp_alumnas_vista) {
    $titulo_pagina = 'Cumpleanos del mes';
    $descripcion_pagina = 'Una vista simple para celebrar y cuidar la comunidad de Tati Pilates.';
}

if ($credenciales) {
    delete_transient('tp_credenciales_' . get_current_user_id());
}

$semana_ficha = isset($_GET['semana']) ? sanitize_text_field(wp_unslash($_GET['semana'])) : gmdate('Y-m-d', current_time('timestamp'));
$rango_ficha  = TP_Reservas::rango_semana($semana_ficha);
$anterior_ficha = gmdate('Y-m-d', strtotime($rango_ficha['inicio'] . ' -7 days'));
$siguiente_ficha = gmdate('Y-m-d', strtotime($rango_ficha['inicio'] . ' +7 days'));
$reservas_ficha = array();
$recuperaciones_ficha = array();
$pagos_ficha = array();
$milestones_ficha = array();
$estadisticas_ficha = array();
$cumpleanos_mes = TP_Alumnas::cumpleanos_mes();
$cumpleanos_hoy = TP_Alumnas::cumpleanos_hoy();
$logros_recientes = TP_Alumnas::logros_recientes();
$resumen_ficha = array(
    'reservada'    => 0,
    'asistio'      => 0,
    'falto'        => 0,
    'cancelada'    => 0,
    'recuperacion' => 0,
);

if ($ficha) {
    global $wpdb;

    $estadisticas_ficha = TP_Alumnas::estadisticas((int) $ficha->id);
    $milestones_ficha   = TP_Alumnas::milestones((int) $ficha->id);

    $reservas_ficha = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT r.*, h.hora_inicio, h.cupo_maximo
            FROM {$wpdb->prefix}tp_reservas r
            INNER JOIN {$wpdb->prefix}tp_horarios h ON h.id = r.horario_id
            WHERE r.alumna_id = %d AND r.fecha BETWEEN %s AND %s
            ORDER BY r.fecha ASC, h.hora_inicio ASC, r.id ASC",
            (int) $ficha->id,
            $rango_ficha['inicio'],
            $rango_ficha['fin']
        )
    );

    foreach ($reservas_ficha as $reserva_ficha) {
        if (isset($resumen_ficha[$reserva_ficha->estado])) {
            $resumen_ficha[$reserva_ficha->estado]++;
        }

        if ('recuperacion' === $reserva_ficha->tipo && 'cancelada' !== $reserva_ficha->estado) {
            $resumen_ficha['recuperacion']++;
        }
    }

    $recuperaciones_ficha = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT rec.*, r.fecha AS fecha_falta
            FROM {$wpdb->prefix}tp_recuperaciones rec
            LEFT JOIN {$wpdb->prefix}tp_reservas r ON r.id = rec.reserva_origen_id
            WHERE rec.alumna_id = %d
            ORDER BY rec.estado ASC, rec.fecha_limite ASC, rec.id DESC
            LIMIT 20",
            (int) $ficha->id
        )
    );

    $pagos_ficha = TP_Pagos::historial_estudiante((int) $ficha->id, 6);
}
?>

<div class="wrap tp-admin tp-alumnas-page">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html($kicker_pagina); ?></p>
        <h1><?php echo esc_html($titulo_pagina); ?></h1>
        <p><?php echo esc_html__('Crea accesos, ajusta planes y mantén el estado de cada estudiante en un solo lugar.', 'tatipilates'); ?></p>
    </div>

    <?php if ($mensaje) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $mensaje_decodificado = rawurldecode($mensaje);
                echo esc_html(
                    in_array($mensaje_decodificado, array('creado', 'actualizado', 'eliminado'), true)
                        ? 'Estudiante ' . $mensaje_decodificado . ' correctamente.'
                        : $mensaje_decodificado . '.'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($error) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html(rawurldecode($error)); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($puede_ver_datos_medicos_cap && !$cifrado_datos_medicos_listo) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html__('Datos medicos protegidos:', 'tatipilates'); ?></strong>
                <?php echo esc_html__('configura TP_DATA_ENCRYPTION_KEY en el servidor para ver o editar historia medica, alergias y motivo de Pilates.', 'tatipilates'); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($credenciales) : ?>
        <div class="tp-access-card">
            <div class="tp-access-card-icon">
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
            </div>
            <div>
                <h2><?php echo esc_html__('Acceso temporal creado', 'tatipilates'); ?></h2>
                <p>
                    <?php if (!empty($credenciales['mail_sent'])) : ?>
                        <?php echo esc_html__('WordPress reporto que el correo fue enviado. Si no aparece, revisa spam.', 'tatipilates'); ?>
                    <?php else : ?>
                        <?php echo esc_html__('WordPress no pudo confirmar el envio del correo. Usa estas credenciales para probar el acceso.', 'tatipilates'); ?>
                    <?php endif; ?>
                </p>

                <?php if (!empty($credenciales['mail_error'])) : ?>
                    <p class="tp-access-error">
                        <?php echo esc_html__('Error reportado:', 'tatipilates'); ?> <?php echo esc_html($credenciales['mail_error']); ?>
                    </p>
                <?php endif; ?>

                <dl class="tp-access-list">
                    <div>
                        <dt><?php echo esc_html__('Portal', 'tatipilates'); ?></dt>
                        <dd><a href="<?php echo esc_url($credenciales['portal']); ?>" target="_blank" rel="noreferrer"><?php echo esc_html($credenciales['portal']); ?></a></dd>
                    </div>
                    <div>
                        <dt><?php echo esc_html__('Usuario', 'tatipilates'); ?></dt>
                        <dd><code><?php echo esc_html($credenciales['email']); ?></code></dd>
                    </div>
                    <div>
                        <dt><?php echo esc_html__('Contrasena temporal', 'tatipilates'); ?></dt>
                        <dd><code><?php echo esc_html($credenciales['password']); ?></code></dd>
                    </div>
                    <?php if (!empty($credenciales['from_name']) || !empty($credenciales['from_email'])) : ?>
                        <div>
                            <dt><?php echo esc_html__('Remitente', 'tatipilates'); ?></dt>
                            <dd>
                                <code>
                                    <?php
                                    echo esc_html(
                                        trim(
                                            ($credenciales['from_name'] ?? '') . ' <' . ($credenciales['from_email'] ?? '') . '>'
                                        )
                                    );
                                    ?>
                                </code>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    <?php endif; ?>

    <?php if ('registrados' === $tp_alumnas_vista) : ?>
    <?php if ($ficha) : ?>
        <div class="tp-window tp-student-profile">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html__('Ficha del estudiante', 'tatipilates'); ?> - <?php echo esc_html($ficha->display_name); ?></strong>
            </div>

            <div class="tp-window-body">
                <div class="tp-profile-head">
                    <div>
                        <p class="tp-kicker"><?php echo esc_html__('Control administrativo', 'tatipilates'); ?></p>
                        <h2><?php echo esc_html($ficha->display_name); ?></h2>
                        <p><?php echo esc_html($ficha->user_email); ?> · <?php echo esc_html($planes[$ficha->plan] ?? $ficha->plan); ?></p>
                    </div>
                    <div class="tp-profile-actions">
                        <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-reservar', 'alumna_id' => (int) $ficha->id, 'fecha' => $rango_ficha['inicio']), admin_url('admin.php'))); ?>"><?php echo esc_html__('Reservar clase', 'tatipilates'); ?></a>
                        <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-pagos', 'estudiante' => (int) $ficha->id), admin_url('admin.php'))); ?>"><?php echo esc_html__('Ver pagos', 'tatipilates'); ?></a>
                        <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-alumnas-registrar', 'editar' => (int) $ficha->id), admin_url('admin.php'))); ?>"><?php echo esc_html__('Editar datos', 'tatipilates'); ?></a>
                    </div>
                </div>

                <div class="tp-profile-stats">
                    <div>
                        <span><?php echo esc_html__('Dias en Pilates', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html(null === $estadisticas_ficha['dias_desde_inicio'] ? __('Sin fecha', 'tatipilates') : (string) $estadisticas_ficha['dias_desde_inicio']); ?></strong>
                    </div>
                    <div>
                        <span><?php echo esc_html__('Clases asistidas', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html((string) $estadisticas_ficha['clases_asistidas']); ?></strong>
                    </div>
                    <div>
                        <span><?php echo esc_html__('Reservas historicas', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html((string) $estadisticas_ficha['reservas_totales']); ?></strong>
                    </div>
                    <div>
                        <span><?php echo esc_html__('Faltas registradas', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html((string) $estadisticas_ficha['faltas']); ?></strong>
                    </div>
                </div>

                <div class="tp-profile-info-grid">
                    <section>
                        <span><?php echo esc_html__('Nacimiento', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html($ficha->fecha_nacimiento ? TP_Pagos::formatear_fecha($ficha->fecha_nacimiento) : 'Sin registrar'); ?></strong>
                    </section>
                    <section>
                        <span><?php echo esc_html__('Inicio', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html($ficha->fecha_inicio_pilates ? TP_Pagos::formatear_fecha($ficha->fecha_inicio_pilates) : 'Sin registrar'); ?></strong>
                    </section>
                    <?php if ($puede_ver_datos_medicos) : ?>
                        <section>
                            <span><?php echo esc_html__('Historia medica', 'tatipilates'); ?></span>
                            <p><?php echo esc_html($ficha->historia_medica ?: 'Sin notas medicas.'); ?></p>
                        </section>
                        <section>
                            <span><?php echo esc_html__('Alergias', 'tatipilates'); ?></span>
                            <p><?php echo esc_html($ficha->alergias ?: 'Sin alergias registradas.'); ?></p>
                        </section>
                        <section class="tp-profile-info-wide">
                            <span><?php echo esc_html__('Motivo para asistir a Pilates', 'tatipilates'); ?></span>
                            <p><?php echo esc_html($ficha->motivo_pilates ?: 'Sin motivo registrado.'); ?></p>
                        </section>
                    <?php endif; ?>
                </div>

                <div class="tp-attendance-week-selector tp-week-range-nav">
                    <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-alumnas', 'ficha' => (int) $ficha->id, 'semana' => $anterior_ficha), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>" title="<?php echo esc_attr__('Semana anterior', 'tatipilates'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                    </a>
                    <div class="tp-week-range">
                        <span><?php echo esc_html__('Semana seleccionada', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html(TP_Pagos::formatear_fecha($rango_ficha['inicio'])); ?> - <?php echo esc_html(TP_Pagos::formatear_fecha($rango_ficha['fin'])); ?></strong>
                    </div>
                    <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-alumnas', 'ficha' => (int) $ficha->id), admin_url('admin.php'))); ?>"><?php echo esc_html__('Semana actual', 'tatipilates'); ?></a>
                    <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-alumnas', 'ficha' => (int) $ficha->id, 'semana' => $siguiente_ficha), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>" title="<?php echo esc_attr__('Semana siguiente', 'tatipilates'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </div>

                <div class="tp-attendance-summary">
                    <div><span><?php echo esc_html__('Reservadas', 'tatipilates'); ?></span><strong><?php echo esc_html($resumen_ficha['reservada']); ?></strong></div>
                    <div><span><?php echo esc_html__('Asistieron', 'tatipilates'); ?></span><strong><?php echo esc_html($resumen_ficha['asistio']); ?></strong></div>
                    <div><span><?php echo esc_html__('Faltas', 'tatipilates'); ?></span><strong><?php echo esc_html($resumen_ficha['falto']); ?></strong></div>
                    <div><span><?php echo esc_html__('Recuperaciones', 'tatipilates'); ?></span><strong><?php echo esc_html($resumen_ficha['recuperacion']); ?></strong></div>
                </div>

                <div class="tp-profile-grid">
                    <section class="tp-profile-panel">
                        <h3><?php echo esc_html__('Clases de la semana', 'tatipilates'); ?></h3>
                        <div class="tp-profile-list">
                            <?php if ($reservas_ficha) : ?>
                                <?php foreach ($reservas_ficha as $reserva) : ?>
                                    <?php
                                    $estado_label = array(
                                        'reservada' => 'Reservada',
                                        'asistio'   => 'Asistió',
                                        'falto'     => 'Faltó',
                                        'cancelada' => 'Cancelada',
                                    );
                                    ?>
                                    <article class="tp-profile-row">
                                        <div>
                                            <strong><?php echo esc_html(TP_Pagos::formatear_fecha($reserva->fecha) . ' - ' . TP_Horarios::formatear_hora($reserva->hora_inicio)); ?></strong>
                                            <span><?php echo esc_html('recuperacion' === $reserva->tipo ? 'Recuperación' : 'Clase del plan'); ?></span>
                                        </div>
                                        <span class="tp-status <?php echo esc_attr('falto' === $reserva->estado || 'cancelada' === $reserva->estado ? 'tp-status-inactive' : ('asistio' === $reserva->estado ? 'tp-status-active' : '')); ?>"><?php echo esc_html($estado_label[$reserva->estado] ?? $reserva->estado); ?></span>

                                        <div class="tp-profile-row-actions">
                                            <?php if ('cancelada' !== $reserva->estado) : ?>
                                                <?php foreach (array('asistio' => 'Asistió', 'falto' => 'Faltó', 'reservada' => 'Reservada') as $estado => $label) : ?>
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                        <?php wp_nonce_field('tp_marcar_asistencia'); ?>
                                                        <input type="hidden" name="action" value="tp_marcar_asistencia">
                                                        <input type="hidden" name="reserva_id" value="<?php echo esc_attr((int) $reserva->id); ?>">
                                                        <input type="hidden" name="estado" value="<?php echo esc_attr($estado); ?>">
                                                        <input type="hidden" name="semana" value="<?php echo esc_attr($rango_ficha['inicio']); ?>">
                                                        <input type="hidden" name="redirect_estudiante" value="<?php echo esc_attr((int) $ficha->id); ?>">
                                                        <?php submit_button($label, 'secondary small', 'submit', false); ?>
                                                    </form>
                                                <?php endforeach; ?>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Cancelar esta reserva libera el cupo. Continuar?', 'tatipilates')); ?>');">
                                                    <?php wp_nonce_field('tp_admin_cancelar_reserva'); ?>
                                                    <input type="hidden" name="action" value="tp_admin_cancelar_reserva">
                                                    <input type="hidden" name="reserva_id" value="<?php echo esc_attr((int) $reserva->id); ?>">
                                                    <input type="hidden" name="alumna_id" value="<?php echo esc_attr((int) $ficha->id); ?>">
                                                    <input type="hidden" name="semana" value="<?php echo esc_attr($rango_ficha['inicio']); ?>">
                                                    <?php submit_button(__('Cancelar', 'tatipilates'), 'secondary small', 'submit', false); ?>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="tp-empty-state"><?php echo esc_html__('No tiene clases en esta semana.', 'tatipilates'); ?></p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="tp-profile-panel">
                        <h3><?php echo esc_html__('Recuperaciones', 'tatipilates'); ?></h3>
                        <form class="tp-compact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('tp_crear_recuperacion'); ?>
                            <input type="hidden" name="action" value="tp_crear_recuperacion">
                            <input type="hidden" name="alumna_id" value="<?php echo esc_attr((int) $ficha->id); ?>">
                            <input type="hidden" name="redirect_estudiante" value="<?php echo esc_attr((int) $ficha->id); ?>">
                            <input type="hidden" name="semana" value="<?php echo esc_attr($rango_ficha['inicio']); ?>">
                            <label class="tp-field">
                                <span><?php echo esc_html__('Fecha origen', 'tatipilates'); ?></span>
                                <input type="date" name="fecha_falta" value="<?php echo esc_attr(gmdate('Y-m-d', current_time('timestamp'))); ?>" required>
                            </label>
                            <label class="tp-field">
                                <span><?php echo esc_html__('Vence', 'tatipilates'); ?></span>
                                <input type="date" name="fecha_limite" value="<?php echo esc_attr(gmdate('Y-m-d', strtotime('+3 months', current_time('timestamp')))); ?>" required>
                            </label>
                            <label class="tp-field tp-field-full">
                                <span><?php echo esc_html__('Motivo', 'tatipilates'); ?></span>
                                <input type="text" name="motivo" placeholder="<?php echo esc_attr__('Ajuste administrativo', 'tatipilates'); ?>">
                            </label>
                            <?php submit_button(__('Habilitar recuperación', 'tatipilates'), 'primary', 'submit', false); ?>
                        </form>

                        <div class="tp-profile-list">
                            <?php if ($recuperaciones_ficha) : ?>
                                <?php foreach ($recuperaciones_ficha as $recuperacion) : ?>
                                    <article class="tp-profile-row">
                                        <div>
                                            <strong><?php echo esc_html('Vence ' . TP_Pagos::formatear_fecha($recuperacion->fecha_limite)); ?></strong>
                                            <span><?php echo esc_html(($recuperacion->fecha_falta ? 'Origen ' . TP_Pagos::formatear_fecha($recuperacion->fecha_falta) : 'Manual') . ($recuperacion->motivo ? ' · ' . $recuperacion->motivo : '')); ?></span>
                                        </div>
                                        <span class="tp-status <?php echo esc_attr('pendiente' === $recuperacion->estado ? 'tp-status-warning' : 'tp-status-muted'); ?>"><?php echo esc_html(ucfirst($recuperacion->estado)); ?></span>
                                        <?php if ('usada' !== $recuperacion->estado) : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Eliminar esta recuperación?', 'tatipilates')); ?>');">
                                                <?php wp_nonce_field('tp_admin_eliminar_recuperacion'); ?>
                                                <input type="hidden" name="action" value="tp_admin_eliminar_recuperacion">
                                                <input type="hidden" name="recuperacion_id" value="<?php echo esc_attr((int) $recuperacion->id); ?>">
                                                <input type="hidden" name="alumna_id" value="<?php echo esc_attr((int) $ficha->id); ?>">
                                                <input type="hidden" name="semana" value="<?php echo esc_attr($rango_ficha['inicio']); ?>">
                                                <?php submit_button(__('Eliminar', 'tatipilates'), 'secondary small', 'submit', false); ?>
                                            </form>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="tp-empty-state"><?php echo esc_html__('No tiene recuperaciones registradas.', 'tatipilates'); ?></p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="tp-profile-panel">
                        <h3><?php echo esc_html__('Logros personales', 'tatipilates'); ?></h3>
                        <form class="tp-compact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('tp_crear_milestone'); ?>
                            <input type="hidden" name="action" value="tp_crear_milestone">
                            <input type="hidden" name="alumna_id" value="<?php echo esc_attr((int) $ficha->id); ?>">
                            <label class="tp-field">
                                <span><?php echo esc_html__('Logro o meta', 'tatipilates'); ?></span>
                                <input type="text" name="titulo" placeholder="<?php echo esc_attr__('Ej: primera clase completa sin dolor', 'tatipilates'); ?>" required>
                            </label>
                            <label class="tp-field">
                                <span><?php echo esc_html__('Fecha', 'tatipilates'); ?></span>
                                <input type="date" name="fecha" value="<?php echo esc_attr(gmdate('Y-m-d', current_time('timestamp'))); ?>" required>
                            </label>
                            <label class="tp-field tp-field-full">
                                <span><?php echo esc_html__('Detalle', 'tatipilates'); ?></span>
                                <input type="text" name="descripcion" placeholder="<?php echo esc_attr__('Nota corta para seguimiento', 'tatipilates'); ?>">
                            </label>
                            <?php submit_button(__('Crear logro', 'tatipilates'), 'primary', 'submit', false); ?>
                        </form>

                        <div class="tp-profile-list">
                            <?php if ($milestones_ficha) : ?>
                                <?php foreach ($milestones_ficha as $milestone) : ?>
                                    <?php
                                    $toggle_estado = 'logrado' === $milestone->estado ? 'activo' : 'logrado';
                                    $toggle_label  = 'logrado' === $milestone->estado ? 'Reabrir' : 'Marcar logrado';
                                    $toggle_url    = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action'       => 'tp_cambiar_milestone',
                                                'milestone_id' => (int) $milestone->id,
                                                'alumna_id'    => (int) $ficha->id,
                                                'estado'       => $toggle_estado,
                                            ),
                                            admin_url('admin-post.php')
                                        ),
                                        'tp_cambiar_milestone'
                                    );
                                    $eliminar_milestone_url = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action'       => 'tp_eliminar_milestone',
                                                'milestone_id' => (int) $milestone->id,
                                                'alumna_id'    => (int) $ficha->id,
                                            ),
                                            admin_url('admin-post.php')
                                        ),
                                        'tp_eliminar_milestone'
                                    );
                                    ?>
                                    <article class="tp-profile-row">
                                        <div>
                                            <strong><?php echo esc_html($milestone->titulo); ?></strong>
                                            <span><?php echo esc_html(TP_Pagos::formatear_fecha($milestone->fecha) . ($milestone->descripcion ? ' - ' . $milestone->descripcion : '')); ?></span>
                                        </div>
                                        <span class="tp-status <?php echo esc_attr('logrado' === $milestone->estado ? 'tp-status-active' : 'tp-status-warning'); ?>"><?php echo esc_html('logrado' === $milestone->estado ? 'Logrado' : 'Activo'); ?></span>
                                        <div class="tp-profile-row-actions">
                                            <a class="button" href="<?php echo esc_url($toggle_url); ?>"><?php echo esc_html($toggle_label); ?></a>
                                            <a class="button" href="<?php echo esc_url($eliminar_milestone_url); ?>" onclick="return confirm('<?php echo esc_js(__('Eliminar este logro?', 'tatipilates')); ?>');"><?php echo esc_html__('Eliminar', 'tatipilates'); ?></a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="tp-empty-state"><?php echo esc_html__('Aun no hay logros personales.', 'tatipilates'); ?></p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="tp-profile-panel tp-profile-danger">
                        <h3><?php echo esc_html__('Rescate administrativo', 'tatipilates'); ?></h3>
                        <p><?php echo esc_html__('Usa esto si el estudiante quedo con reservas o recuperaciones enredadas. No borra pagos, asistencia ni logros.', 'tatipilates'); ?></p>
                        <div class="tp-form-actions">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Esto borrará reservas y recuperaciones de esta semana para este estudiante. Continuar?', 'tatipilates')); ?>');">
                                <?php wp_nonce_field('tp_admin_reset_estudiante'); ?>
                                <input type="hidden" name="action" value="tp_admin_reset_estudiante">
                                <input type="hidden" name="alumna_id" value="<?php echo esc_attr((int) $ficha->id); ?>">
                                <input type="hidden" name="semana" value="<?php echo esc_attr($rango_ficha['inicio']); ?>">
                                <input type="hidden" name="alcance" value="semana">
                                <?php submit_button(__('Resetear semana', 'tatipilates'), 'secondary', 'submit', false); ?>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Esto borrara solo las reservas pendientes desde la semana actual en adelante. Se conserva el historial, asistencia, faltas, pagos y logros. Continuar?', 'tatipilates')); ?>');">
                                <?php wp_nonce_field('tp_admin_reset_estudiante'); ?>
                                <input type="hidden" name="action" value="tp_admin_reset_estudiante">
                                <input type="hidden" name="alumna_id" value="<?php echo esc_attr((int) $ficha->id); ?>">
                                <input type="hidden" name="semana" value="<?php echo esc_attr($rango_ficha['inicio']); ?>">
                                <input type="hidden" name="alcance" value="todo">
                                <?php submit_button(__('Resetear todo', 'tatipilates'), 'delete', 'submit', false); ?>
                            </form>
                        </div>
                    </section>

                    <section class="tp-profile-panel">
                        <h3><?php echo esc_html__('Pagos recientes', 'tatipilates'); ?></h3>
                        <div class="tp-profile-list">
                            <?php if ($pagos_ficha) : ?>
                                <?php foreach ($pagos_ficha as $pago) : ?>
                                    <article class="tp-profile-row">
                                        <div>
                                            <strong><?php echo esc_html(TP_Pagos::formatear_mes($pago->mes)); ?></strong>
                                            <span><?php echo esc_html('Pago ' . TP_Pagos::formatear_fecha($pago->fecha_pago)); ?></span>
                                        </div>
                                        <span class="tp-status tp-status-active"><?php echo esc_html($planes[$pago->plan] ?? $pago->plan); ?></span>
                                    </article>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="tp-empty-state"><?php echo esc_html__('Sin pagos registrados todavía.', 'tatipilates'); ?></p>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    <?php elseif ($ficha_id) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html__('No encontramos ese estudiante.', 'tatipilates'); ?></p>
        </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ('registrar' === $tp_alumnas_vista) : ?>
    <div class="tp-window tp-student-form-panel">
        <div class="tp-window-bar">
            <span></span>
            <span></span>
            <strong><?php echo esc_html($editando ? __('Editar estudiante', 'tatipilates') : __('Nuevo estudiante', 'tatipilates')); ?></strong>
        </div>

        <div class="tp-window-body">

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('tp_guardar_alumna'); ?>
            <input type="hidden" name="action" value="tp_guardar_alumna">
            <input type="hidden" name="alumna_id" value="<?php echo esc_attr($editando ? (int) $editando->id : 0); ?>">

            <div class="tp-student-form-grid">
                <label class="tp-field tp-field-name">
                    <span><?php echo esc_html__('Nombre completo', 'tatipilates'); ?></span>
                    <input type="text" class="regular-text" name="nombre" id="nombre" value="<?php echo esc_attr($editando ? $editando->display_name : ''); ?>" required>
                </label>

                <?php if (!$editando) : ?>
                    <label class="tp-field tp-field-email">
                        <span><?php echo esc_html__('Correo electronico', 'tatipilates'); ?></span>
                        <input type="email" class="regular-text" name="email" id="email" value="" required>
                        <small><?php echo esc_html__('Sera el usuario para iniciar sesion.', 'tatipilates'); ?></small>
                    </label>
                <?php else : ?>
                    <div class="tp-field tp-field-email">
                        <span><?php echo esc_html__('Correo electronico', 'tatipilates'); ?></span>
                        <strong><?php echo esc_html($editando->user_email); ?></strong>
                    </div>
                <?php endif; ?>

                <label class="tp-field tp-field-plan">
                    <span><?php echo esc_html__('Plan', 'tatipilates'); ?></span>
                    <select name="plan" id="plan" required>
                        <?php foreach ($planes as $valor => $label) : ?>
                            <option value="<?php echo esc_attr($valor); ?>" <?php selected($editando ? $editando->plan : '3x', $valor); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="tp-field tp-field-checkbox tp-field-status">
                    <span><?php echo esc_html__('Estado', 'tatipilates'); ?></span>
                    <input type="checkbox" name="activa" value="1" <?php checked($editando ? (int) $editando->activa : 1, 1); ?>>
                    <strong><?php echo esc_html__('Activo', 'tatipilates'); ?></strong>
                </label>

                <?php
                $tp_date_field = array(
                    'name'      => 'fecha_nacimiento',
                    'label'     => __('Fecha de nacimiento', 'tatipilates'),
                    'value'     => $editando && !empty($editando->fecha_nacimiento) ? (string) $editando->fecha_nacimiento : '',
                    'max_years' => 100,
                );
                include TP_PLUGIN_DIR . 'admin/views/partials/date-selects.php';
                ?>

                <?php
                $tp_date_field = array(
                    'name'      => 'fecha_inicio_pilates',
                    'label'     => __('Inicio en Pilates', 'tatipilates'),
                    'value'     => $editando ? (string) $editando->fecha_inicio_pilates : gmdate('Y-m-d', current_time('timestamp')),
                    'max_years' => 40,
                );
                include TP_PLUGIN_DIR . 'admin/views/partials/date-selects.php';
                ?>

                <?php if ($puede_ver_datos_medicos) : ?>
                    <label class="tp-field tp-field-medical">
                        <span><?php echo esc_html__('Historia medica', 'tatipilates'); ?></span>
                        <textarea name="historia_medica" rows="3"><?php echo esc_textarea($editando ? $editando->historia_medica : ''); ?></textarea>
                    </label>

                    <label class="tp-field tp-field-medical">
                        <span><?php echo esc_html__('Alergias', 'tatipilates'); ?></span>
                        <textarea name="alergias" rows="3"><?php echo esc_textarea($editando ? $editando->alergias : ''); ?></textarea>
                    </label>

                    <label class="tp-field tp-field-full">
                        <span><?php echo esc_html__('Causas por las que asiste a Pilates', 'tatipilates'); ?></span>
                        <textarea name="motivo_pilates" rows="3"><?php echo esc_textarea($editando ? $editando->motivo_pilates : ''); ?></textarea>
                    </label>
                <?php endif; ?>

                <label class="tp-field tp-field-full">
                    <span><?php echo esc_html__('Notas', 'tatipilates'); ?></span>
                    <textarea name="notas" id="notas" class="large-text" rows="3"><?php echo esc_textarea($editando ? $editando->notas : ''); ?></textarea>
                </label>
            </div>

            <div class="tp-form-actions">
                <?php submit_button($editando ? __('Actualizar estudiante', 'tatipilates') : __('Crear estudiante', 'tatipilates'), 'primary', 'submit', false); ?>

                <?php if ($editando) : ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-alumnas'), admin_url('admin.php'))); ?>"><?php echo esc_html__('Cancelar edicion', 'tatipilates'); ?></a>
                <?php endif; ?>
            </div>
        </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ('registrados' === $tp_alumnas_vista && !$ficha) : ?>
    <div class="tp-section-title">
        <span class="dashicons dashicons-groups" aria-hidden="true"></span>
        <h2><?php echo esc_html__('Estudiantes registrados', 'tatipilates'); ?></h2>
    </div>

    <div class="tp-window tp-admin-main">
        <div class="tp-window-body">
        <table class="widefat striped tp-table tp-students-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Estudiante', 'tatipilates'); ?></th>
                    <th><?php echo esc_html__('Plan', 'tatipilates'); ?></th>
                    <th><?php echo esc_html__('Pago del mes', 'tatipilates'); ?></th>
                    <th><?php echo esc_html__('Recuperaciones', 'tatipilates'); ?></th>
                    <th><?php echo esc_html__('Estado', 'tatipilates'); ?></th>
                    <th><?php echo esc_html__('Acciones', 'tatipilates'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($alumnas) : ?>
                    <?php foreach ($alumnas as $alumna) : ?>
                        <?php
                        $eliminar_url = wp_nonce_url(
                            add_query_arg(
                                array(
                                    'action'    => 'tp_eliminar_alumna',
                                    'alumna_id' => (int) $alumna->id,
                                ),
                                admin_url('admin-post.php')
                            ),
                            'tp_eliminar_alumna'
                        );
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($alumna->display_name); ?></strong>
                                <br>
                                <a class="tp-muted-link" href="mailto:<?php echo esc_attr($alumna->user_email); ?>"><?php echo esc_html($alumna->user_email); ?></a>
                            </td>
                            <td><?php echo esc_html($planes[$alumna->plan] ?? $alumna->plan); ?></td>
                            <td>
                                <?php if ('individual' === $alumna->plan) : ?>
                                    <span class="tp-status"><?php echo esc_html__('No aplica', 'tatipilates'); ?></span>
                                <?php elseif ($alumna->pago_mes_actual) : ?>
                                    <span class="tp-status tp-status-active"><?php echo esc_html__('Pago', 'tatipilates'); ?></span>
                                <?php else : ?>
                                    <span class="tp-status tp-status-warning"><?php echo esc_html__('Pendiente', 'tatipilates'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) $alumna->recuperaciones_pendientes); ?></td>
                            <td>
                                <?php if ((int) $alumna->activa) : ?>
                                        <span class="tp-status tp-status-active"><?php echo esc_html__('Activo', 'tatipilates'); ?></span>
                                    <?php else : ?>
                                        <span class="tp-status tp-status-inactive"><?php echo esc_html__('Inactivo', 'tatipilates'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="tp-actions">
                    <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-alumnas', 'ficha' => (int) $alumna->id), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Ver ficha del estudiante', 'tatipilates'); ?>" title="<?php echo esc_attr__('Ver ficha del estudiante', 'tatipilates'); ?>">
                                    <span class="dashicons dashicons-id" aria-hidden="true"></span>
                                </a>
                    <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-alumnas-registrar', 'editar' => (int) $alumna->id), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Editar estudiante', 'tatipilates'); ?>" title="<?php echo esc_attr__('Editar estudiante', 'tatipilates'); ?>">
                                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                </a>
                    <a class="tp-icon-button tp-icon-button-danger" href="<?php echo esc_url($eliminar_url); ?>" onclick="return confirm('<?php echo esc_js(__('Seguro que quieres eliminar este estudiante?', 'tatipilates')); ?>');" aria-label="<?php echo esc_attr__('Eliminar estudiante', 'tatipilates'); ?>" title="<?php echo esc_attr__('Eliminar estudiante', 'tatipilates'); ?>">
                                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6"><?php echo esc_html__('Todavia no hay estudiantes registrados.', 'tatipilates'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="tp-section-title tp-section-title-compact">
        <span class="dashicons dashicons-awards" aria-hidden="true"></span>
        <h2><?php echo esc_html__('Logros', 'tatipilates'); ?></h2>
    </div>

    <div class="tp-achievement-grid">
        <?php if ($logros_recientes) : ?>
            <?php foreach ($logros_recientes as $logro) : ?>
                <article class="tp-achievement-card">
                    <div>
                        <strong><?php echo esc_html($logro->titulo); ?></strong>
                        <span><?php echo esc_html($logro->display_name . ' - ' . TP_Pagos::formatear_fecha($logro->fecha)); ?></span>
                        <?php if (!empty($logro->descripcion)) : ?>
                            <p><?php echo esc_html($logro->descripcion); ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="tp-status <?php echo esc_attr('logrado' === $logro->estado ? 'tp-status-active' : 'tp-status-warning'); ?>"><?php echo esc_html('logrado' === $logro->estado ? 'Logrado' : 'Activo'); ?></span>
                </article>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="tp-empty-state"><?php echo esc_html__('Aun no hay logros registrados.', 'tatipilates'); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ('cumpleanos' === $tp_alumnas_vista) : ?>
    <div class="tp-section-title tp-section-title-compact">
        <span class="dashicons dashicons-heart" aria-hidden="true"></span>
        <h2><?php echo esc_html__('Cumpleanos del mes', 'tatipilates'); ?></h2>
    </div>

    <?php if ($cumpleanos_hoy) : ?>
        <div class="tp-birthday-today">
            <span class="dashicons dashicons-heart" aria-hidden="true"></span>
            <div>
                <strong><?php echo esc_html(count($cumpleanos_hoy) === 1 ? 'Cumpleanos hoy' : 'Cumpleanos hoy'); ?></strong>
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
        </div>
    <?php endif; ?>

    <div class="tp-birthday-grid">
        <?php if ($cumpleanos_mes) : ?>
            <?php foreach ($cumpleanos_mes as $cumpleanero) : ?>
                <?php $cumple_hoy = gmdate('m-d', strtotime($cumpleanero->fecha_nacimiento)) === gmdate('m-d', current_time('timestamp')); ?>
                <article class="tp-birthday-card <?php echo esc_attr($cumple_hoy ? 'is-today' : ''); ?>">
                    <?php if ($cumple_hoy) : ?>
                        <em><?php echo esc_html__('Hoy', 'tatipilates'); ?></em>
                    <?php endif; ?>
                    <strong><?php echo esc_html($cumpleanero->display_name); ?></strong>
                    <span><?php echo esc_html(TP_Pagos::formatear_fecha($cumpleanero->fecha_nacimiento)); ?></span>
                </article>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="tp-empty-state"><?php echo esc_html__('No hay cumpleanos registrados este mes.', 'tatipilates'); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
