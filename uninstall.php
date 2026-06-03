<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package TatiPilates
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

wp_clear_scheduled_hook('tp_cron_diario');

$administrator = get_role('administrator');

if ($administrator && $administrator->has_cap('tp_manage_pilates')) {
    $administrator->remove_cap('tp_manage_pilates');
}

remove_role('tp_alumna');
remove_role('tp_admin_pilates');

delete_option('tp_db_version');
delete_option('tp_schema_version');
delete_option('tp_portal_page_id');
delete_option('tp_rewrite_version');
delete_option('tp_notificaciones_config');

$tablas = array(
    $wpdb->prefix . 'tp_notificaciones',
    $wpdb->prefix . 'tp_milestones',
    $wpdb->prefix . 'tp_recuperaciones',
    $wpdb->prefix . 'tp_pagos',
    $wpdb->prefix . 'tp_reservas',
    $wpdb->prefix . 'tp_alumnas',
    $wpdb->prefix . 'tp_horarios',
);

foreach ($tablas as $tabla) {
    $wpdb->query("DROP TABLE IF EXISTS {$tabla}");
}
