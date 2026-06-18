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
                'roles'           => array_values((array) $usuario->roles),
            );
        }

        return array(
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
     * Creates a backup file in uploads/tatipilates-backups.
     *
     * @return string|WP_Error
     */
    public static function crear_archivo_diario() {
        $directorio = self::directorio_backups();

        if (is_wp_error($directorio)) {
            return $directorio;
        }

        $payload = self::exportar();
        $suffix  = strtolower(wp_generate_password(12, false, false));
        $path    = trailingslashit($directorio) . 'tatipilates-backup-' . gmdate('Ymd-His', current_time('timestamp')) . '-' . $suffix . '.json';
        $bytes   = file_put_contents($path, wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (false === $bytes) {
            tp_log('No se pudo escribir backup diario.', array('path' => $path), 'error');
            return new WP_Error('tp_backup_write_failed', 'No se pudo escribir el archivo de backup.');
        }

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

        $contenido = file_get_contents($tmp_path);

        if (false === $contenido) {
            return new WP_Error('tp_backup_invalid_file', 'No se pudo leer el archivo de backup.');
        }

        $payload = json_decode($contenido, true);

        if (!is_array($payload) || ('tatipilates' !== ($payload['plugin'] ?? '')) || empty($payload['tables']) || !is_array($payload['tables'])) {
            return new WP_Error('tp_backup_invalid_payload', 'El archivo no parece ser un backup valido de Tati Pilates.');
        }

        global $wpdb;

        $resumen = array(
            'usuarios' => 0,
            'filas'    => 0,
        );

        $wpdb->query('START TRANSACTION');
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');

        $user_map = self::importar_usuarios($payload['users'] ?? array(), $resumen);

        if (is_wp_error($user_map)) {
            $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
            $wpdb->query('ROLLBACK');
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
            if (empty($payload['tables'][$nombre]) || empty($tablas[$nombre])) {
                continue;
            }

            foreach ($payload['tables'][$nombre] as $fila) {
                if (!is_array($fila)) {
                    continue;
                }

                $old_id = isset($fila['id']) ? (int) $fila['id'] : 0;
                $fila   = self::remapear_relaciones($nombre, $fila, $user_map, $id_maps);

                $resultado = self::upsert_fila($tablas[$nombre], $nombre, $fila);

                if (is_wp_error($resultado)) {
                    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
                    $wpdb->query('ROLLBACK');
                    return $resultado;
                }

                if ($old_id) {
                    $id_maps[$nombre][$old_id] = (int) $resultado;
                }

                $resumen['filas']++;
            }
        }

        self::actualizar_recuperaciones_en_reservas($payload['tables']['reservas'] ?? array(), $tablas['reservas'], $id_maps);

        if (!empty($payload['options']['tp_notificaciones_config']) && is_array($payload['options']['tp_notificaciones_config'])) {
            update_option('tp_notificaciones_config', $payload['options']['tp_notificaciones_config']);
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        $wpdb->query('COMMIT');

        return $resumen;
    }

    /**
     * Returns the latest saved backup metadata.
     *
     * @return array{path:string,name:string,date:string,size:int}|null
     */
    public static function ultimo_backup() {
        $directorio = self::directorio_backups(false);

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
                'role'         => in_array('tp_admin_pilates', (array) ($usuario['roles'] ?? array()), true) ? 'tp_admin_pilates' : 'tp_alumna',
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

            $resultado = $wpdb->update(
                $tabla,
                $datos,
                array('id' => $id_actual),
                self::formatos($datos),
                array('%d')
            );
        } else {
            $resultado = $wpdb->insert(
                $tabla,
                $fila,
                self::formatos($fila)
            );
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
     * @return void
     */
    private static function actualizar_recuperaciones_en_reservas($reservas, $tabla, $id_maps) {
        global $wpdb;

        if (!is_array($reservas)) {
            return;
        }

        foreach ($reservas as $reserva) {
            if (empty($reserva['id']) || empty($reserva['recuperacion_id'])) {
                continue;
            }

            $old_reserva_id      = (int) $reserva['id'];
            $old_recuperacion_id = (int) $reserva['recuperacion_id'];
            $reserva_id          = $id_maps['reservas'][$old_reserva_id] ?? $old_reserva_id;
            $recuperacion_id     = $id_maps['recuperaciones'][$old_recuperacion_id] ?? $old_recuperacion_id;

            $wpdb->update(
                $tabla,
                array('recuperacion_id' => $recuperacion_id),
                array('id' => $reserva_id),
                array('%d'),
                array('%d')
            );
        }
    }

    /**
     * Builds wpdb formats from row values.
     *
     * @param array<string,mixed> $fila Row data.
     * @return array<int,string>
     */
    private static function formatos($fila) {
        $formatos = array();

        foreach ($fila as $valor) {
            if (is_int($valor)) {
                $formatos[] = '%d';
            } elseif (is_float($valor)) {
                $formatos[] = '%f';
            } else {
                $formatos[] = '%s';
            }
        }

        return $formatos;
    }

    /**
     * Ensures the private backup directory exists.
     *
     * @param bool $crear Whether to create the directory.
     * @return string|WP_Error
     */
    private static function directorio_backups($crear = true) {
        $uploads = wp_upload_dir();

        if (!empty($uploads['error'])) {
            return new WP_Error('tp_backup_upload_dir', $uploads['error']);
        }

        $directorio = trailingslashit($uploads['basedir']) . self::BACKUP_DIR;

        if ($crear && !is_dir($directorio) && !wp_mkdir_p($directorio)) {
            return new WP_Error('tp_backup_mkdir_failed', 'No se pudo crear el directorio de backups.');
        }

        if ($crear) {
            if (!file_exists(trailingslashit($directorio) . 'index.php')) {
                file_put_contents(trailingslashit($directorio) . 'index.php', "<?php\n// Silence is golden.\n");
            }

            if (!file_exists(trailingslashit($directorio) . '.htaccess')) {
                file_put_contents(trailingslashit($directorio) . '.htaccess', "Deny from all\n");
            }
        }

        return $directorio;
    }

    /**
     * Removes old automatic backup files.
     *
     * @return void
     */
    private static function limpiar_backups_antiguos() {
        $directorio = self::directorio_backups(false);

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
