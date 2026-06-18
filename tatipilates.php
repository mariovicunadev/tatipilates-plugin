<?php
/**
 * Plugin Name: Tati Pilates
 * Plugin URI:  https://tatipilates.com
 * Description: Sistema de gestion de clases, reservas y recuperaciones para Tati Pilates.
 * Version:     1.2.0
 * Author:      Tati Pilates
 * Text Domain: tatipilates
 * Domain Path: /languages
 * Update URI:  https://github.com/wefefino/tatipilates-plugin
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TP_VERSION', '1.2.0');
define('TP_PLUGIN_FILE', __FILE__);
define('TP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TP_PLUGIN_URL', plugin_dir_url(__FILE__));

spl_autoload_register(
    function ($class) {
        $prefix = 'TP_';

        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $class_slug = strtolower(str_replace('_', '-', substr($class, strlen($prefix))));
        $paths      = array(
            TP_PLUGIN_DIR . 'includes/class-' . $class_slug . '.php',
            TP_PLUGIN_DIR . 'admin/class-' . $class_slug . '.php',
            TP_PLUGIN_DIR . 'public/class-' . $class_slug . '.php',
        );

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }
    }
);

register_activation_hook(TP_PLUGIN_FILE, array('TP_Activator', 'activate'));
register_deactivation_hook(TP_PLUGIN_FILE, array('TP_Activator', 'deactivate'));

add_action(
    'plugins_loaded',
    function () {
        if (class_exists('TP_Activator')) {
            TP_Activator::ensure_schema();
        }

        if (class_exists('TP_Roles')) {
            new TP_Roles();
        }

        if (is_admin() && class_exists('TP_Admin')) {
            new TP_Admin();
        }

        if (is_admin() && class_exists('TP_Updater')) {
            new TP_Updater();
        }

        if (class_exists('TP_Portal')) {
            new TP_Portal();
        }

        if (class_exists('TP_Recuperaciones')) {
            add_action('tp_cron_diario', array('TP_Recuperaciones', 'expirar_vencidas'));
        }

        if (class_exists('TP_Notificaciones')) {
            add_action('tp_cron_diario', array('TP_Notificaciones', 'generar_recordatorios_clase_proxima'));
            add_action('tp_cron_diario', array('TP_Notificaciones', 'generar_recordatorios_recuperacion_por_vencer'));
            add_action('tp_cron_diario', array('TP_Notificaciones', 'generar_recordatorios_pago_mensual'));
        }

        if (class_exists('TP_Backups')) {
            add_action('tp_backup_diario', array('TP_Backups', 'crear_archivo_diario'));
        }
    }
);
