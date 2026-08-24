<?php
/**
 * Pricing admin view.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

$mensaje    = isset($_GET['tp_mensaje']) ? sanitize_text_field(wp_unslash($_GET['tp_mensaje'])) : '';
$error      = isset($_GET['tp_error']) ? sanitize_text_field(wp_unslash($_GET['tp_error'])) : '';
$precios    = class_exists('TP_Precios') ? TP_Precios::configuracion() : array();
$etiquetas  = class_exists('TP_Precios') ? TP_Precios::etiquetas() : array();

$grupo_planes = array('plan_2x', 'plan_3x', 'plan_4x', 'plan_5x');
$grupo_individual = array('clase_individual', 'clase_mat');
?>

<div class="wrap tp-admin tp-precios-page">
    <div class="tp-page-hero">
        <p class="tp-kicker"><?php echo esc_html__('Precios', 'tatipilates'); ?></p>
        <h1><?php echo esc_html__('Precios', 'tatipilates'); ?></h1>
        <p><?php echo esc_html__('Estos valores se muestran automaticamente en la seccion de precios del sitio.', 'tatipilates'); ?></p>
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

    <div class="tp-window tp-admin-main">
        <div class="tp-window-bar">
            <span></span>
            <span></span>
            <strong><?php echo esc_html__('Precios', 'tatipilates'); ?></strong>
        </div>

        <div class="tp-window-body">
            <?php if (!class_exists('TP_Precios')) : ?>
                <p><?php echo esc_html__('El modulo de precios no esta disponible.', 'tatipilates'); ?></p>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tp-precios-form">
                    <?php wp_nonce_field('tp_guardar_precios'); ?>
                    <input type="hidden" name="action" value="tp_guardar_precios">

                    <h2><?php echo esc_html__('Planes mensuales', 'tatipilates'); ?></h2>
                    <div class="tp-reminders-grid">
                        <?php foreach ($grupo_planes as $clave) : $clave_usd = TP_Precios::clave_usd($clave); ?>
                            <div class="tp-field tp-precio-par">
                                <span><?php echo esc_html($etiquetas[$clave]); ?></span>
                                <div class="tp-precio-par-inputs">
                                    <label class="tp-precio-subcampo">
                                        <small><?php echo esc_html__('Referencia', 'tatipilates'); ?></small>
                                        <span class="tp-precio-input">
                                            <span class="tp-precio-prefijo">ref.</span>
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                name="<?php echo esc_attr($clave); ?>"
                                                value="<?php echo esc_attr(number_format((int) $precios[$clave], 0, ',', '.')); ?>"
                                            >
                                        </span>
                                    </label>
                                    <label class="tp-precio-subcampo">
                                        <small><?php echo esc_html__('Efectivo USD', 'tatipilates'); ?></small>
                                        <span class="tp-precio-input">
                                            <span class="tp-precio-prefijo">$</span>
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                name="<?php echo esc_attr($clave_usd); ?>"
                                                value="<?php echo esc_attr(number_format((int) $precios[$clave_usd], 0, ',', '.')); ?>"
                                            >
                                        </span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h2><?php echo esc_html__('Opciones individuales', 'tatipilates'); ?></h2>
                    <div class="tp-reminders-grid">
                        <?php foreach ($grupo_individual as $clave) : $clave_usd = TP_Precios::clave_usd($clave); ?>
                            <div class="tp-field tp-precio-par">
                                <span><?php echo esc_html($etiquetas[$clave]); ?></span>
                                <div class="tp-precio-par-inputs">
                                    <label class="tp-precio-subcampo">
                                        <small><?php echo esc_html__('Referencia', 'tatipilates'); ?></small>
                                        <span class="tp-precio-input">
                                            <span class="tp-precio-prefijo">ref.</span>
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                name="<?php echo esc_attr($clave); ?>"
                                                value="<?php echo esc_attr(number_format((int) $precios[$clave], 0, ',', '.')); ?>"
                                            >
                                        </span>
                                    </label>
                                    <label class="tp-precio-subcampo">
                                        <small><?php echo esc_html__('Efectivo USD', 'tatipilates'); ?></small>
                                        <span class="tp-precio-input">
                                            <span class="tp-precio-prefijo">$</span>
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                name="<?php echo esc_attr($clave_usd); ?>"
                                                value="<?php echo esc_attr(number_format((int) $precios[$clave_usd], 0, ',', '.')); ?>"
                                            >
                                        </span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="tp-form-actions">
                        <?php submit_button(__('Guardar cambios', 'tatipilates'), 'primary', 'submit', false); ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
