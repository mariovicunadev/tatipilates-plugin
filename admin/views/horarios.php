<?php
/**
 * Schedule admin view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

$dias        = TP_Horarios::dias_semana();
$horarios    = TP_Horarios::obtener_todos(false);
$edit_id     = isset($_GET['editar']) ? absint($_GET['editar']) : 0;
$editando    = $edit_id ? TP_Horarios::obtener($edit_id) : null;
$mensaje     = isset($_GET['tp_mensaje']) ? sanitize_text_field(wp_unslash($_GET['tp_mensaje'])) : '';
$error       = isset($_GET['tp_error']) ? sanitize_text_field(wp_unslash($_GET['tp_error'])) : '';
$horarios_por_dia = array();

foreach ($dias as $numero => $nombre) {
    $horarios_por_dia[$numero] = array();
}

foreach ($horarios as $horario) {
    $dia = (int) $horario->dia_semana;

    if (!isset($horarios_por_dia[$dia])) {
        $horarios_por_dia[$dia] = array();
    }

    $horarios_por_dia[$dia][] = $horario;
}
?>

<div class="wrap tp-admin tp-horarios-page">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Agenda semanal', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Horarios y Cupos', 'tatipilates'); ?></h1>
        <p><?php echo esc_html__('Configura las clases fijas y controla los cupos disponibles por horario.', 'tatipilates'); ?></p>
    </div>

    <?php if ($mensaje) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Horario', 'tatipilates'); ?> <?php echo esc_html($mensaje); ?> <?php echo esc_html__('correctamente.', 'tatipilates'); ?></p>
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
                <strong><?php echo esc_html__('Horarios configurados', 'tatipilates'); ?></strong>
            </div>
            <div class="tp-window-body">
                <?php if ($horarios) : ?>
                    <div class="tp-week-grid">
                        <?php foreach ($horarios_por_dia as $dia_numero => $items) : ?>
                            <section class="tp-day-card">
                                <header class="tp-day-card-header">
                                    <strong><?php echo esc_html($dias[$dia_numero] ?? ''); ?></strong>
                                    <span><?php echo esc_html(sprintf('%d horarios', count($items))); ?></span>
                                </header>

                                <div class="tp-day-slots">
                                    <?php if ($items) : ?>
                                        <?php foreach ($items as $horario) : ?>
                            <?php
                            $eliminar_url = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action'     => 'tp_eliminar_horario',
                                        'horario_id' => (int) $horario->id,
                                    ),
                                    admin_url('admin-post.php')
                                ),
                                'tp_eliminar_horario'
                            );
                            ?>
                                            <article class="tp-slot <?php echo esc_attr((int) $horario->activo ? '' : 'is-inactive'); ?>">
                                                <div class="tp-slot-main">
                                                    <strong><?php echo esc_html(TP_Horarios::formatear_hora($horario->hora_inicio)); ?></strong>
                                                    <span class="tp-slot-cupos"><?php echo esc_html(sprintf('%d cupos', (int) $horario->cupo_maximo)); ?></span>
                                                </div>

                                                <div class="tp-slot-meta">
                                                    <?php if ((int) $horario->activo) : ?>
                                                        <span class="tp-status tp-status-active"><?php echo esc_html__('Activo', 'tatipilates'); ?></span>
                                                    <?php else : ?>
                                                        <span class="tp-status tp-status-inactive"><?php echo esc_html__('Inactivo', 'tatipilates'); ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="tp-actions">
                        <a class="tp-icon-button" href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-horarios', 'editar' => (int) $horario->id), admin_url('admin.php'))); ?>" aria-label="<?php echo esc_attr__('Editar horario', 'tatipilates'); ?>" title="<?php echo esc_attr__('Editar horario', 'tatipilates'); ?>">
                                                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                                    </a>
                        <a class="tp-icon-button tp-icon-button-danger" href="<?php echo esc_url($eliminar_url); ?>" onclick="return confirm('<?php echo esc_js(__('Seguro que quieres eliminar este horario?', 'tatipilates')); ?>');" aria-label="<?php echo esc_attr__('Eliminar horario', 'tatipilates'); ?>" title="<?php echo esc_attr__('Eliminar horario', 'tatipilates'); ?>">
                                                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                                    </a>
                                                </div>
                                            </article>
                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <p class="tp-empty-day"><?php echo esc_html__('Sin horarios', 'tatipilates'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="tp-empty-state"><?php echo esc_html__('Todavia no hay horarios configurados.', 'tatipilates'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="tp-window tp-admin-side">
            <div class="tp-window-bar">
                <span></span>
                <span></span>
                <strong><?php echo esc_html($editando ? __('Editar horario', 'tatipilates') : __('Agregar horario', 'tatipilates')); ?></strong>
            </div>

            <div class="tp-window-body">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('tp_guardar_horario'); ?>
                <input type="hidden" name="action" value="tp_guardar_horario">
                <input type="hidden" name="horario_id" value="<?php echo esc_attr($editando ? (int) $editando->id : 0); ?>">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="dia_semana"><?php echo esc_html__('Dia', 'tatipilates'); ?></label></th>
                            <td>
                                <select name="dia_semana" id="dia_semana" required>
                                    <?php foreach ($dias as $numero => $nombre) : ?>
                                        <option value="<?php echo esc_attr((string) $numero); ?>" <?php selected($editando ? (int) $editando->dia_semana : 1, $numero); ?>>
                                            <?php echo esc_html($nombre); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="hora_inicio"><?php echo esc_html__('Hora', 'tatipilates'); ?></label></th>
                            <td>
                                <input type="time" name="hora_inicio" id="hora_inicio" value="<?php echo esc_attr($editando ? substr($editando->hora_inicio, 0, 5) : '07:30'); ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cupo_maximo"><?php echo esc_html__('Cupo maximo', 'tatipilates'); ?></label></th>
                            <td>
                                <input type="number" min="1" max="30" name="cupo_maximo" id="cupo_maximo" value="<?php echo esc_attr($editando ? (int) $editando->cupo_maximo : 5); ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Estado', 'tatipilates'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="activo" value="1" <?php checked($editando ? (int) $editando->activo : 1, 1); ?>>
                                    <?php echo esc_html__('Activo', 'tatipilates'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

            <?php submit_button($editando ? __('Actualizar horario', 'tatipilates') : __('Agregar horario', 'tatipilates')); ?>

                <?php if ($editando) : ?>
                    <p><a href="<?php echo esc_url(add_query_arg(array('page' => 'tatipilates-horarios'), admin_url('admin.php'))); ?>"><?php echo esc_html__('Cancelar edicion', 'tatipilates'); ?></a></p>
                <?php endif; ?>
            </form>
            </div>
        </div>
    </div>
</div>
