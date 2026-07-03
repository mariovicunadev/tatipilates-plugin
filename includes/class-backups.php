<?php
/**
 * Plugin data export/import and daily backups.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin-only backups for the custom Tati Pilates data.
 */
class TP_Backups {

    const OPTION_DELETE_ON_UNINSTALL = 'tp_delete_data_on_uninstall';
    const BACKUP_HOOK                = 'tp_backup_diario';
    const BACKUP_DIR                 = 'tatipilates-backups';
    const RETENTION_DAYS             = 30;
    const OPTION_STORAGE_NOTICE      = 'tp_backup_storage_notice';
    const FAILURE_ALERT_TRANSIENT    = 'tp_backup_failure_alert';
    const BACKUP_FORMAT_VERSION      = 2;
    const DEFAULT_MAX_UPLOAD_BYTES   = 10485760;
    const DEFAULT_MAX_USERS          = 5000;
    const DEFAULT_MAX_ROWS           = 50000;

    /**
     * Custom tables included in plugin backups.
     *
     * @return array<string,string>
     */
    public static function tablas() {
        global $wpdb;

        return array(
            'horarios'       => $wpdb->prefix . 'tp_horarios',
            'alumnas'        => $wpdb->prefix . 'tp_alumnas',
            'reservas'       => $wpdb->prefix . 'tp_reservas',
            'recuperaciones' => $wpdb->prefix . 'tp_recuperaciones',
            'pagos'          => $wpdb->prefix . 'tp_pagos',
            'milestones'     => $wpdb->prefix . 'tp_milestones',
            'notificaciones' => $wpdb->prefix . 'tp_notificaciones',
        );
    }

    /**
     * Maximum accepted backup file size.
     *
     * @return int
     */
    public static function max_upload_bytes() {
        return max(1024, (int) apply_filters('tp_backup_max_bytes', self::DEFAULT_MAX_UPLOAD_BYTES));
    }

    /**
     * Maximum accepted users in one backup.
     *
     * @return int
     */
    public static function max_users() {
        return max(1, (int) apply_filters('tp_backup_max_users', self::DEFAULT_MAX_USERS));
    }

    /**
     * Maximum accepted table rows in one backup.
     *
     * @return int
     */
    public static function max_rows() {
        return max(1, (int) apply_filters('tp_backup_max_rows', self::DEFAULT_MAX_ROWS));
    }

    /**
     * Generates a backup payload.
     *
     * @return array<string,mixed>
     */
    public static function exportar() {
        global $wpdb;

        $tablas = array();

        foreach (self::tablas() as $nombre => $tabla) {
            $tablas[$nombre] = $wpdb->get_results("SELECT * FROM {$tabla} ORDER BY id ASC", ARRAY_A);
        }

        $usuarios = array();

        foreach ($tablas['alumnas'] as $alumna) {
            $user_id = isset($alumna['wp_user_id']) ? absint($alumna['wp_user_id']) : 0;
            $usuario = $user_id ? get_userdata($user_id) : false;

            if (!$usuario) {
                continue;
            }

            $usuarios[(string) $user_id] = array(
                'ID'              => (int) $usuario->ID,
                'user_login'      => $usuario->user_login,
                'user_email'      => $usuario->user_email,
                'display_name'    => $usuario->display_name,
                'first_name'      => get_user_meta($usuario->ID, 'first_name', true),
                'last_name'       => get_user_meta($usuario->ID, 'last_name', true),
                'nickname'        => get_user_meta($usuario->ID, 'nickname', true),
                'user_registered' => $usuario->user_registered,
                'roles'           => array_values(
                    array_intersect(
                        (array) $usuario->roles,
                        array('tp_alumna', 'tp_admin_pilates', 'tp_tatiana')
                    )
                ) ?: array('tp_alumna'),
            );
        }

        return array(
            'format_version' => self::BACKUP_FORMAT_VERSION,
            'plugin'       => 'tatipilates',
            'version'      => TP_VERSION,
            'site_url'     => home_url(),
            'created_at'   => gmdate('c', current_time('timestamp')),
            'schema'       => get_option('tp_schema_version', ''),
            'options'      => array(
                'tp_notificaciones_config' => get_option('tp_notificaciones_config', array()),
            ),
            'users'        => array_values($usuarios),
            'tables'       => $tablas,
        );
    }

    /**
     * Downloads the current backup as JSON.
     *
     * @return void
     */
    public static function descargar() {
        $payload  = self::exportar();
        $filename = 'tatipilates-backup-' . gmdate('Ymd-His', current_time('timestamp')) . '.json';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Creates a backup file in private storage.
     *
     * @param bool $notificar_fallo Whether to alert the administrator on failure.
     * @return string|WP_Error
     */
    public static function crear_archivo_diario($notificar_fallo = true) {
        $directorio = self::preparar_almacenamiento_privado();

        if (is_wp_error($directorio)) {
            if ($notificar_fallo) {
                self::notificar_fallo_automatico($directorio);
            }

            return $directorio;
        }

        $payload = self::exportar();
        $suffix  = strtolower(wp_generate_password(12, false, false));
        $path    = trailingslashit($directorio) . 'tatipilates-backup-' . gmdate('Ymd-His', current_time('timestamp')) . '-' . $suffix . '.json';
        $temporal = $path . '.tmp-' . wp_generate_password(8, false, false);
        $json     = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $bytes    = false !== $json ? file_put_contents($temporal, $json, LOCK_EX) : false;

        if (
            false === $bytes ||
            strlen($json) !== $bytes ||
            !@rename($temporal, $path)
        ) {
            @unlink($temporal);
            tp_log('No se pudo escribir backup diario.', array('path' => $path), 'error');
            $error = new WP_Error('tp_backup_write_failed', 'No se pudo escribir el archivo de backup privado.');

            self::guardar_aviso_almacenamiento($error);

            if ($notificar_fallo) {
                self::notificar_fallo_automatico($error);
            }

            return $error;
        }

        @chmod($path, 0640);
        delete_transient(self::FAILURE_ALERT_TRANSIENT);
        self::limpiar_aviso_tras_backup_exitoso();
        self::limpiar_backups_antiguos();

        return $path;
    }

    /**
     * Imports a backup JSON file idempotently.
     *
     * Existing rows with the same ID are updated; missing rows are inserted.
     *
     * @param string $tmp_path Uploaded temporary path.
     * @return array<string,int>|WP_Error
     */
    public static function importar_archivo($tmp_path) {
        if (!$tmp_path || !is_readable($tmp_path)) {
            return new WP_Error('tp_backup_invalid_file', 'No se pudo leer el archivo de backup.');
        }

        $size = filesize($tmp_path);

        if (false === $size) {
            return new WP_Error('tp_backup_invalid_file', 'No se pudo determinar el tamano del archivo de backup.');
        }

        if ($size > self::max_upload_bytes()) {
            return new WP_Error(
                'tp_backup_file_too_large',
                sprintf(
                    'El backup pesa %1$s y supera el limite permitido de %2$s.',
                    size_format($size),
                    size_format(self::max_upload_bytes())
                )
            );
        }

        $contenido = file_get_contents($tmp_path);

        if (false === $contenido) {
            return new WP_Error('tp_backup_invalid_file', 'No se pudo leer el archivo de backup.');
        }

        if (strlen($contenido) > self::max_upload_bytes()) {
            return new WP_Error('tp_backup_file_too_large', 'El archivo cambio de tamano durante la lectura y supera el limite permitido.');
        }

        try {
            $payload = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return new WP_Error(
                'tp_backup_invalid_json',
                'El archivo no contiene JSON valido: ' . $exception->getMessage()
            );
        }

        $payload = self::validar_payload($payload);

        if (is_wp_error($payload)) {
            return $payload;
        }

        return self::importar_payload_validado($payload);
    }

    /**
     * Validates and normalizes a decoded backup payload.
     *
     * @param mixed $payload Decoded payload.
     * @return array<string,mixed>|WP_Error
     */
    public static function validar_payload($payload) {
        if (!class_exists('TP_Backup_Validator')) {
            return new WP_Error('tp_backup_validator_unavailable', 'El validador de backups no esta disponible.');
        }

        return TP_Backup_Validator::validate($payload);
    }

    /**
     * Applies one fully validated payload inside a checked transaction.
     *
     * @param array<string,mixed> $payload Validated payload.
     * @return array<string,int>|WP_Error
     */
    private static function importar_payload_validado($payload) {
        global $wpdb;

        if (false === $wpdb->query('START TRANSACTION')) {
            TP_Helpers::log_db_error('TP_Backups::importar_payload_validado START TRANSACTION');
            return new WP_Error('tp_backup_transaction_failed', 'No se pudo iniciar la transaccion de importacion.');
        }

        if (false === $wpdb->query('SET FOREIGN_KEY_CHECKS=0')) {
            self::rollback_importacion();
            self::restaurar_foreign_keys();
            TP_Helpers::log_db_error('TP_Backups::importar_payload_validado FOREIGN_KEY_CHECKS=0');
            return new WP_Error('tp_backup_transaction_failed', 'No se pudieron preparar las relaciones para importar.');
        }

        try {
            $resultado = self::aplicar_payload_validado($payload);
        } catch (Throwable $exception) {
            tp_log(
                'Excepcion durante la importacion de backup.',
                array(
                    'type'    => get_class($exception),
                    'message' => $exception->getMessage(),
                ),
                'error'
            );
            $resultado = new WP_Error('tp_backup_unexpected_error', 'Ocurrio un error inesperado durante la importacion.');
        }

        if (is_wp_error($resultado)) {
            self::rollback_importacion();
            self::restaurar_foreign_keys();
            return $resultado;
        }

        if (!self::restaurar_foreign_keys()) {
            self::rollback_importacion();
            self::restaurar_foreign_keys();
            return new WP_Error('tp_backup_transaction_failed', 'No se pudieron restaurar las restricciones de la base de datos.');
        }

        if (false === $wpdb->query('COMMIT')) {
            TP_Helpers::log_db_error('TP_Backups::importar_payload_validado COMMIT');
            self::rollback_importacion();
            return new WP_Error('tp_backup_commit_failed', 'No se pudo confirmar la importacion; no se aplicaron cambios.');
        }

        return $resultado;
    }

    /**
     * Applies validated users, rows and options.
     *
     * @param array<string,mixed> $payload Validated payload.
     * @return array<string,int>|WP_Error
     */
    private static function aplicar_payload_validado($payload) {
        $resumen = array(
            'usuarios' => 0,
            'filas'    => 0,
        );
        $user_map = self::importar_usuarios($payload['users'], $resumen);

        if (is_wp_error($user_map)) {
            return $user_map;
        }

        $tablas = self::tablas();
        $orden  = array('horarios', 'alumnas', 'reservas', 'recuperaciones', 'pagos', 'milestones', 'notificaciones');
        $id_maps = array(
            'horarios'       => array(),
            'alumnas'        => array(),
            'reservas'       => array(),
            'recuperaciones' => array(),
            'pagos'          => array(),
            'milestones'     => array(),
            'notificaciones' => array(),
        );

        foreach ($orden as $nombre) {
            foreach ($payload['tables'][$nombre] as $fila) {
                $old_id = (int) $fila['id'];
                $fila   = self::remapear_relaciones($nombre, $fila, $user_map, $id_maps);

                $resultado = self::upsert_fila($tablas[$nombre], $nombre, $fila);

                if (is_wp_error($resultado)) {
                    return $resultado;
                }

                $id_maps[$nombre][$old_id] = (int) $resultado;
                $resumen['filas']++;
            }
        }

        $referencias = self::actualizar_recuperaciones_en_reservas($payload['tables']['reservas'], $tablas['reservas'], $id_maps);

        if (is_wp_error($referencias)) {
            return $referencias;
        }

        if (isset($payload['options']['tp_notificaciones_config'])) {
            update_option('tp_notificaciones_config', $payload['options']['tp_notificaciones_config']);
        }

        return $resumen;
    }

    /**
     * Rolls back an import and logs a rollback failure.
     *
     * @return void
     */
    private static function rollback_importacion() {
        global $wpdb;

        if (false === $wpdb->query('ROLLBACK')) {
            TP_Helpers::log_db_error('TP_Backups::rollback_importacion');
        }
    }

    /**
     * Restores foreign key checks for the current database connection.
     *
     * @return bool
     */
    private static function restaurar_foreign_keys() {
        global $wpdb;

        $restaurado = false !== $wpdb->query('SET FOREIGN_KEY_CHECKS=1');

        if (!$restaurado) {
            TP_Helpers::log_db_error('TP_Backups::restaurar_foreign_keys');
        }

        return $restaurado;
    }

    /**
     * Returns the latest saved backup metadata.
     *
     * @return array{path:string,name:string,date:string,size:int}|null
     */
    public static function ultimo_backup() {
        $directorio = self::preparar_almacenamiento_privado(false);

        if (is_wp_error($directorio) || !is_dir($directorio)) {
            return null;
        }

        $archivos = glob(trailingslashit($directorio) . 'tatipilates-backup-*.json');

        if (!$archivos) {
            return null;
        }

        rsort($archivos);
        $path = $archivos[0];

        return array(
            'path' => $path,
            'name' => basename($path),
            'date' => gmdate('Y-m-d H:i:s', filemtime($path)),
            'size' => (int) filesize($path),
        );
    }

    /**
     * Returns the current private storage warning, if any.
     *
     * @return array<string,string>|null
     */
    public static function aviso_almacenamiento() {
        $aviso = get_option(self::OPTION_STORAGE_NOTICE, array());

        return is_array($aviso) && !empty($aviso['message']) ? $aviso : null;
    }

    /**
     * Streams one saved backup after validating its filename and location.
     *
     * @param string $nombre Backup filename.
     * @return void
     */
    public static function descargar_archivo($nombre) {
        $nombre = sanitize_file_name($nombre);

        if (!self::nombre_backup_valido($nombre)) {
            wp_die(esc_html__('El archivo de backup solicitado no es valido.', 'tatipilates'), '', array('response' => 400));
        }

        $directorio = self::preparar_almacenamiento_privado(false);

        if (is_wp_error($directorio)) {
            wp_die(esc_html($directorio->get_error_message()), '', array('response' => 500));
        }

        $path      = trailingslashit($directorio) . $nombre;
        $real_path = realpath($path);
        $real_dir  = realpath($directorio);

        if (
            !$real_path ||
            !$real_dir ||
            dirname(wp_normalize_path($real_path)) !== wp_normalize_path($real_dir) ||
            !is_file($real_path) ||
            !is_readable($real_path)
        ) {
            wp_die(esc_html__('El archivo de backup no existe o no se puede leer.', 'tatipilates'), '', array('response' => 404));
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . (string) filesize($real_path));
        readfile($real_path);
        exit;
    }

    /**
     * Whether uninstall is allowed to delete plugin data.
     *
     * @return bool
     */
    public static function borrar_datos_al_desinstalar() {
        return '1' === (string) get_option(self::OPTION_DELETE_ON_UNINSTALL, '0');
    }

    /**
     * Saves the dangerous uninstall behavior flag.
     *
     * @param bool $borrar Whether uninstall should delete data.
     * @return void
     */
    public static function guardar_borrado_desinstalacion($borrar) {
        update_option(self::OPTION_DELETE_ON_UNINSTALL, $borrar ? '1' : '0', false);
    }

    /**
     * Imports WordPress users included in the backup.
     *
     * @param array<int,array<string,mixed>> $usuarios Users payload.
     * @param array<string,int>              $resumen  Mutable summary.
     * @return array<int,int>|WP_Error
     */
    private static function importar_usuarios($usuarios, &$resumen) {
        $map = array();

        if (!is_array($usuarios)) {
            return $map;
        }

        foreach ($usuarios as $usuario) {
            if (!is_array($usuario)) {
                continue;
            }

            $old_id = isset($usuario['ID']) ? absint($usuario['ID']) : 0;
            $email  = isset($usuario['user_email']) ? sanitize_email($usuario['user_email']) : '';
            $login  = isset($usuario['user_login']) ? sanitize_user($usuario['user_login'], true) : '';

            if (!$old_id || !$email || !$login) {
                continue;
            }

            $user = get_userdata($old_id);

            if ($user && $email !== $user->user_email && $login !== $user->user_login) {
                $user = false;
            }

            if (!$user && $email) {
                $user = get_user_by('email', $email);
            }

            if (!$user && $login) {
                $user = get_user_by('login', $login);
            }

            $userdata = array(
                'user_login'   => $login,
                'user_email'   => $email,
                'display_name' => sanitize_text_field($usuario['display_name'] ?? $login),
                'first_name'   => sanitize_text_field($usuario['first_name'] ?? ''),
                'last_name'    => sanitize_text_field($usuario['last_name'] ?? ''),
                'nickname'     => sanitize_text_field($usuario['nickname'] ?? $login),
                'role'         => self::rol_usuario_importado((array) ($usuario['roles'] ?? array())),
            );

            if ($user) {
                $userdata['ID'] = $user->ID;
                $resultado = wp_update_user($userdata);
            } else {
                $userdata['user_pass'] = wp_generate_password(24, true, true);
                $resultado = wp_insert_user($userdata);
            }

            if (is_wp_error($resultado)) {
                return $resultado;
            }

            $map[$old_id] = (int) $resultado;
            $resumen['usuarios']++;
        }

        return $map;
    }

    /**
     * Inserts or updates one row by primary key `id`.
     *
     * @param string              $tabla Table name.
     * @param array<string,mixed> $fila  Row data.
     * @return int|WP_Error
     */
    private static function upsert_fila($tabla, $nombre, $fila) {
        global $wpdb;

        if (empty($fila['id'])) {
            return new WP_Error('tp_backup_missing_id', 'Una fila del backup no tiene ID.');
        }

        $id        = absint($fila['id']);
        $id_actual = self::encontrar_id_existente($tabla, $nombre, $fila);

        if ($id_actual) {
            $datos = $fila;
            unset($datos['id']);
            $formatos = TP_Backup_Validator::formats_for($nombre, $datos);

            if (is_wp_error($formatos)) {
                return $formatos;
            }

            $resultado = $wpdb->update($tabla, $datos, array('id' => $id_actual), $formatos, array('%d'));
        } else {
            $formatos = TP_Backup_Validator::formats_for($nombre, $fila);

            if (is_wp_error($formatos)) {
                return $formatos;
            }

            $resultado = $wpdb->insert($tabla, $fila, $formatos);
        }

        if (false === $resultado) {
            TP_Helpers::log_db_error('TP_Backups::upsert_fila');
            return new WP_Error('tp_backup_db_error', 'No se pudo importar una fila del backup.');
        }

        return $id_actual ? (int) $id_actual : $id;
    }

    /**
     * Remaps foreign keys when logical rows already exist with different IDs.
     *
     * @param string              $nombre  Logical table name.
     * @param array<string,mixed> $fila    Row data.
     * @param array<int,int>      $user_map Imported user ID map.
     * @param array<string,array<int,int>> $id_maps Imported row ID maps.
     * @return array<string,mixed>
     */
    private static function remapear_relaciones($nombre, $fila, $user_map, $id_maps) {
        if ('alumnas' === $nombre && isset($fila['wp_user_id'])) {
            $old_user_id = (int) $fila['wp_user_id'];
            $fila['wp_user_id'] = $user_map[$old_user_id] ?? $old_user_id;
        }

        if (isset($fila['alumna_id'])) {
            $old_alumna_id = (int) $fila['alumna_id'];
            $fila['alumna_id'] = $id_maps['alumnas'][$old_alumna_id] ?? $old_alumna_id;
        }

        if (isset($fila['horario_id'])) {
            $old_horario_id = (int) $fila['horario_id'];
            $fila['horario_id'] = $id_maps['horarios'][$old_horario_id] ?? $old_horario_id;
        }

        if (isset($fila['reserva_origen_id'])) {
            $old_reserva_id = (int) $fila['reserva_origen_id'];
            $fila['reserva_origen_id'] = $id_maps['reservas'][$old_reserva_id] ?? $old_reserva_id;
        }

        if (isset($fila['recuperacion_id']) && $fila['recuperacion_id']) {
            $old_recuperacion_id = (int) $fila['recuperacion_id'];
            $fila['recuperacion_id'] = $id_maps['recuperaciones'][$old_recuperacion_id] ?? $old_recuperacion_id;
        }

        return $fila;
    }

    /**
     * Finds an existing row by ID or known logical unique keys.
     *
     * @param string              $tabla  Table name.
     * @param string              $nombre Logical table name.
     * @param array<string,mixed> $fila   Row data.
     * @return int
     */
    private static function encontrar_id_existente($tabla, $nombre, $fila) {
        global $wpdb;

        $id = isset($fila['id']) ? absint($fila['id']) : 0;

        if ('alumnas' === $nombre && !empty($fila['wp_user_id'])) {
            $existente = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tabla} WHERE wp_user_id = %d", (int) $fila['wp_user_id']));

            if ($existente) {
                return $existente;
            }
        }

        if ('reservas' === $nombre && !empty($fila['alumna_id']) && !empty($fila['horario_id']) && !empty($fila['fecha'])) {
            $existente = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$tabla} WHERE alumna_id = %d AND horario_id = %d AND fecha = %s",
                    (int) $fila['alumna_id'],
                    (int) $fila['horario_id'],
                    (string) $fila['fecha']
                )
            );

            if ($existente) {
                return $existente;
            }
        }

        if ('recuperaciones' === $nombre && !empty($fila['reserva_origen_id'])) {
            $existente = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tabla} WHERE reserva_origen_id = %d", (int) $fila['reserva_origen_id']));

            if ($existente) {
                return $existente;
            }
        }

        if ('pagos' === $nombre && !empty($fila['alumna_id']) && !empty($fila['mes'])) {
            $existente = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$tabla} WHERE alumna_id = %d AND mes = %s",
                    (int) $fila['alumna_id'],
                    (string) $fila['mes']
                )
            );

            if ($existente) {
                return $existente;
            }
        }

        if ('notificaciones' === $nombre && !empty($fila['tipo']) && !empty($fila['referencia'])) {
            $existente = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$tabla} WHERE tipo = %s AND referencia = %s",
                    (string) $fila['tipo'],
                    (string) $fila['referencia']
                )
            );

            if ($existente) {
                return $existente;
            }
        }

        if ('horarios' === $nombre && !empty($fila['dia_semana']) && !empty($fila['hora_inicio'])) {
            $existente = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$tabla} WHERE dia_semana = %d AND hora_inicio = %s AND modalidad = %s",
                    (int) $fila['dia_semana'],
                    (string) $fila['hora_inicio'],
                    (string) ($fila['modalidad'] ?? 'reformer')
                )
            );

            if ($existente) {
                return $existente;
            }
        }

        if ($id) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tabla} WHERE id = %d", $id));
        }

        return 0;
    }

    /**
     * Updates reservation recovery references after recovery rows are mapped.
     *
     * @param array<int,array<string,mixed>> $reservas Reservation payload rows.
     * @param string                         $tabla    Reservations table.
     * @param array<string,array<int,int>>   $id_maps  Imported row ID maps.
     * @return true|WP_Error
     */
    private static function actualizar_recuperaciones_en_reservas($reservas, $tabla, $id_maps) {
        global $wpdb;

        foreach ($reservas as $reserva) {
            if (empty($reserva['id']) || empty($reserva['recuperacion_id'])) {
                continue;
            }

            $old_reserva_id      = (int) $reserva['id'];
            $old_recuperacion_id = (int) $reserva['recuperacion_id'];
            $reserva_id          = $id_maps['reservas'][$old_reserva_id] ?? $old_reserva_id;
            $recuperacion_id     = $id_maps['recuperaciones'][$old_recuperacion_id] ?? $old_recuperacion_id;

            $actualizado = $wpdb->update(
                $tabla,
                array('recuperacion_id' => $recuperacion_id),
                array('id' => $reserva_id),
                array('%d'),
                array('%d')
            );

            if (false === $actualizado) {
                TP_Helpers::log_db_error('TP_Backups::actualizar_recuperaciones_en_reservas');
                return new WP_Error('tp_backup_db_error', 'No se pudieron restaurar las referencias de recuperaciones.');
            }
        }

        return true;
    }

    /**
     * Resolves the primary plugin role restored from a backup.
     *
     * @param array<int,string> $roles Validated role list.
     * @return string
     */
    private static function rol_usuario_importado($roles) {
        if (in_array('tp_tatiana', $roles, true)) {
            return 'tp_tatiana';
        }

        if (in_array('tp_admin_pilates', $roles, true)) {
            return 'tp_admin_pilates';
        }

        return 'tp_alumna';
    }

    /**
     * Ensures private storage exists and migrates legacy public backups.
     *
     * @param bool $requerir_escritura Whether storage must be writable.
     * @return string|WP_Error
     */
    private static function preparar_almacenamiento_privado($requerir_escritura = true) {
        $directorio = self::directorio_privado($requerir_escritura);

        if (is_wp_error($directorio)) {
            self::guardar_aviso_almacenamiento($directorio);
            return $directorio;
        }

        if (!is_writable($directorio)) {
            self::guardar_aviso_almacenamiento(
                new WP_Error('tp_backup_dir_not_writable', 'El directorio privado de backups no tiene permisos de escritura.')
            );
            return $directorio;
        }

        $errores_migracion = self::migrar_backups_legacy($directorio);

        if ($errores_migracion) {
            $error = new WP_Error(
                'tp_backup_legacy_migration_failed',
                'Hay backups antiguos que no pudieron moverse fuera de uploads: ' . implode(' ', $errores_migracion)
            );
            self::guardar_aviso_almacenamiento($error);
        } else {
            $aviso = self::aviso_almacenamiento();

            if ($aviso && 'tp_backup_legacy_migration_failed' === ($aviso['code'] ?? '')) {
                delete_option(self::OPTION_STORAGE_NOTICE);
            }
        }

        return $directorio;
    }

    /**
     * Resolves and creates the private backup directory.
     *
     * @param bool $requerir_escritura Whether storage must be writable.
     * @return string|WP_Error
     */
    private static function directorio_privado($requerir_escritura = true) {
        $directorio = defined('TP_BACKUP_DIR') && TP_BACKUP_DIR
            ? (string) TP_BACKUP_DIR
            : dirname(untrailingslashit(ABSPATH)) . '/tatipilates-private/backups';
        $directorio = untrailingslashit(wp_normalize_path($directorio));

        if (!self::ruta_absoluta($directorio) || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $directorio)) {
            return new WP_Error('tp_backup_invalid_private_dir', 'TP_BACKUP_DIR debe ser una ruta absoluta sin segmentos relativos.');
        }

        if (self::ruta_dentro_webroot($directorio)) {
            return new WP_Error('tp_backup_public_private_dir', 'El directorio privado de backups debe estar fuera del webroot.');
        }

        if (!is_dir($directorio)) {
            if (!wp_mkdir_p($directorio)) {
                return new WP_Error('tp_backup_mkdir_failed', 'No se pudo crear el directorio privado de backups.');
            }

            @chmod($directorio, 0750);
        }

        $real_path = realpath($directorio);

        if (!$real_path) {
            return new WP_Error('tp_backup_invalid_private_dir', 'No se pudo resolver el directorio privado de backups.');
        }

        $directorio = untrailingslashit(wp_normalize_path($real_path));

        if (self::ruta_dentro_webroot($directorio)) {
            return new WP_Error('tp_backup_public_private_dir', 'El directorio privado de backups debe estar fuera del webroot.');
        }

        if ($requerir_escritura && !is_writable($directorio)) {
            return new WP_Error('tp_backup_dir_not_writable', 'El directorio privado de backups no tiene permisos de escritura.');
        }

        return $directorio;
    }

    /**
     * Moves legacy backups from uploads into private storage.
     *
     * @param string $destino Private backup directory.
     * @return array<int,string>
     */
    private static function migrar_backups_legacy($destino) {
        $uploads = wp_upload_dir();

        if (!empty($uploads['error'])) {
            return array((string) $uploads['error']);
        }

        $origen = trailingslashit($uploads['basedir']) . self::BACKUP_DIR;

        if (!is_dir($origen)) {
            return array();
        }

        $errores = array();

        foreach ((array) glob(trailingslashit($origen) . 'tatipilates-backup-*.json') as $path_origen) {
            $nombre = basename($path_origen);

            if (!self::nombre_backup_valido($nombre)) {
                continue;
            }

            $path_destino = trailingslashit($destino) . $nombre;

            if (file_exists($path_destino)) {
                if (self::archivos_iguales($path_origen, $path_destino) && @unlink($path_origen)) {
                    continue;
                }

                $errores[] = sprintf('No se pudo retirar %s del directorio publico.', $nombre);
                continue;
            }

            $path_temporal = $path_destino . '.migrating-' . wp_generate_password(8, false, false);

            if (
                !@copy($path_origen, $path_temporal) ||
                !self::archivos_iguales($path_origen, $path_temporal) ||
                !@rename($path_temporal, $path_destino)
            ) {
                @unlink($path_temporal);
                $errores[] = sprintf('No se pudo copiar y verificar %s.', $nombre);
                continue;
            }

            @chmod($path_destino, 0640);

            if (!@unlink($path_origen)) {
                $errores[] = sprintf('Se copio %s, pero no se pudo borrar el original publico.', $nombre);
            }
        }

        if ($errores) {
            tp_log('No se completó la migración de backups públicos.', array('errores' => $errores), 'error');
        }

        return $errores;
    }

    /**
     * Checks whether two files have the same size and SHA-256 hash.
     *
     * @param string $primero First path.
     * @param string $segundo Second path.
     * @return bool
     */
    private static function archivos_iguales($primero, $segundo) {
        return is_file($primero)
            && is_file($segundo)
            && filesize($primero) === filesize($segundo)
            && hash_file('sha256', $primero) === hash_file('sha256', $segundo);
    }

    /**
     * Whether a path is absolute.
     *
     * @param string $path Filesystem path.
     * @return bool
     */
    private static function ruta_absoluta($path) {
        return 1 === preg_match('#^(?:[A-Za-z]:/|/)#', $path);
    }

    /**
     * Whether a path points inside a known webroot.
     *
     * @param string $path Filesystem path.
     * @return bool
     */
    private static function ruta_dentro_webroot($path) {
        $roots = array(untrailingslashit(wp_normalize_path(ABSPATH)));

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $roots[] = untrailingslashit(wp_normalize_path((string) $_SERVER['DOCUMENT_ROOT']));
        }

        foreach (array_unique(array_filter($roots)) as $root) {
            if ($path === $root || 0 === strpos($path, trailingslashit($root))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a backup filename follows a supported safe pattern.
     *
     * @param string $nombre Filename without path.
     * @return bool
     */
    private static function nombre_backup_valido($nombre) {
        return 1 === preg_match('/^tatipilates-backup-\d{8}-\d{6}(?:-[a-z0-9]{12})?\.json$/', $nombre);
    }

    /**
     * Stores a persistent warning for the configuration screen.
     *
     * @param WP_Error $error Storage error.
     * @return void
     */
    private static function guardar_aviso_almacenamiento($error) {
        update_option(
            self::OPTION_STORAGE_NOTICE,
            array(
                'code'       => $error->get_error_code(),
                'message'    => $error->get_error_message(),
                'created_at' => gmdate('c', current_time('timestamp')),
            ),
            false
        );
    }

    /**
     * Clears resolved write errors without hiding an unfinished legacy migration.
     *
     * @return void
     */
    private static function limpiar_aviso_tras_backup_exitoso() {
        $aviso = self::aviso_almacenamiento();

        if (!$aviso || 'tp_backup_legacy_migration_failed' !== ($aviso['code'] ?? '')) {
            delete_option(self::OPTION_STORAGE_NOTICE);
        }
    }

    /**
     * Sends one daily internal and email alert after an automatic backup fails.
     *
     * @param WP_Error $error Backup error.
     * @return void
     */
    private static function notificar_fallo_automatico($error) {
        if (get_transient(self::FAILURE_ALERT_TRANSIENT)) {
            return;
        }

        $mensaje = sprintf(
            'El backup automático no pudo guardarse en el directorio privado. Motivo: %s',
            $error->get_error_message()
        );

        if (class_exists('TP_Notificaciones')) {
            TP_Notificaciones::crear(
                array(
                    'tipo'           => 'backup_fallido',
                    'referencia'     => 'backup:' . gmdate('Y-m-d', current_time('timestamp')),
                    'titulo'         => __('Falló el backup automático', 'tatipilates'),
                    'mensaje'        => $mensaje,
                    'url_accion'     => add_query_arg('page', 'tatipilates-configuracion', admin_url('admin.php')),
                    'canal'          => 'interno_email',
                    'visible_admin'  => 1,
                    'visible_alumna' => 0,
                )
            );
        }

        $admin_email = sanitize_email(get_option('admin_email'));

        if ($admin_email) {
            $asunto = sprintf('[%s] Falló el backup automático de Tati Pilates', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
            $cuerpo = $mensaje . "\n\n";
            $cuerpo .= 'Sitio: ' . home_url('/') . "\n";
            $cuerpo .= 'Fecha: ' . gmdate('Y-m-d H:i:s', current_time('timestamp')) . "\n";

            if (!wp_mail($admin_email, $asunto, $cuerpo, array('Content-Type: text/plain; charset=UTF-8'))) {
                tp_log('No se pudo enviar el email de fallo del backup.', array('admin_email' => $admin_email), 'error');
            }
        }

        set_transient(self::FAILURE_ALERT_TRANSIENT, 1, 23 * HOUR_IN_SECONDS);
    }

    /**
     * Removes old automatic backup files.
     *
     * @return void
     */
    private static function limpiar_backups_antiguos() {
        $directorio = self::directorio_privado();

        if (is_wp_error($directorio) || !is_dir($directorio)) {
            return;
        }

        $limite = time() - (self::RETENTION_DAYS * DAY_IN_SECONDS);

        foreach ((array) glob(trailingslashit($directorio) . 'tatipilates-backup-*.json') as $path) {
            if (filemtime($path) < $limite) {
                unlink($path);
            }
        }
    }
}
