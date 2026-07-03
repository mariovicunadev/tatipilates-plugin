<?php
/**
 * Strict validation and normalization for Tati Pilates backup payloads.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates the complete backup contract before any database mutation.
 */
class TP_Backup_Validator {

    /**
     * Validates and normalizes a decoded backup payload.
     *
     * @param mixed $payload Decoded JSON payload.
     * @return array<string,mixed>|WP_Error
     */
    public static function validate($payload) {
        if (!is_array($payload) || self::is_list($payload)) {
            return self::error('tp_backup_invalid_payload', 'El backup debe contener un objeto JSON en la raiz.');
        }

        $allowed_top = array('format_version', 'plugin', 'version', 'site_url', 'created_at', 'schema', 'options', 'users', 'tables');
        $unknown_top = array_diff(array_keys($payload), $allowed_top);

        if ($unknown_top) {
            return self::error('tp_backup_unknown_field', 'El backup contiene campos desconocidos en la raiz: ' . implode(', ', $unknown_top) . '.');
        }

        $format_version = array_key_exists('format_version', $payload)
            ? self::normalize_integer($payload['format_version'], 1, PHP_INT_MAX)
            : 1;

        if (is_wp_error($format_version)) {
            return self::error('tp_backup_invalid_format_version', 'format_version debe ser un entero positivo.');
        }

        if ($format_version > TP_Backups::BACKUP_FORMAT_VERSION) {
            return self::error(
                'tp_backup_future_format',
                sprintf(
                    'Este backup usa format_version %1$d, pero esta version del plugin solo admite hasta %2$d. Actualiza Tati Pilates antes de importarlo.',
                    $format_version,
                    TP_Backups::BACKUP_FORMAT_VERSION
                )
            );
        }

        if ('tatipilates' !== ($payload['plugin'] ?? null)) {
            return self::error('tp_backup_invalid_plugin', 'El archivo no pertenece al plugin Tati Pilates.');
        }

        if (!isset($payload['users']) || !is_array($payload['users']) || !self::is_list($payload['users'])) {
            return self::error('tp_backup_invalid_users', 'El campo users debe ser una lista.');
        }

        if (count($payload['users']) > TP_Backups::max_users()) {
            return self::error(
                'tp_backup_too_many_users',
                sprintf('El backup contiene %1$d usuarios; el maximo permitido es %2$d.', count($payload['users']), TP_Backups::max_users())
            );
        }

        if (!isset($payload['tables']) || !is_array($payload['tables']) || self::is_list($payload['tables'])) {
            return self::error('tp_backup_invalid_tables', 'El campo tables debe ser un objeto con las tablas del plugin.');
        }

        $schemas       = self::table_schemas();
        $table_names   = array_keys($schemas);
        $unknown_tables = array_diff(array_keys($payload['tables']), $table_names);
        $missing_tables = array_diff($table_names, array_keys($payload['tables']));

        if ($unknown_tables) {
            return self::error('tp_backup_unknown_table', 'El backup contiene tablas desconocidas: ' . implode(', ', $unknown_tables) . '.');
        }

        if ($missing_tables) {
            return self::error('tp_backup_missing_table', 'Al backup le faltan tablas requeridas: ' . implode(', ', $missing_tables) . '.');
        }

        $total_rows = 0;

        foreach ($table_names as $table_name) {
            if (!is_array($payload['tables'][$table_name]) || !self::is_list($payload['tables'][$table_name])) {
                return self::error('tp_backup_invalid_table_rows', sprintf('La tabla %s debe ser una lista de filas.', $table_name));
            }

            $total_rows += count($payload['tables'][$table_name]);
        }

        if ($total_rows > TP_Backups::max_rows()) {
            return self::error(
                'tp_backup_too_many_rows',
                sprintf('El backup contiene %1$d filas; el maximo permitido es %2$d.', $total_rows, TP_Backups::max_rows())
            );
        }

        $normalized_users = self::normalize_users($payload['users']);

        if (is_wp_error($normalized_users)) {
            return $normalized_users;
        }

        $normalized_tables = array();

        foreach ($schemas as $table_name => $schema) {
            $normalized_tables[$table_name] = array();

            foreach ($payload['tables'][$table_name] as $index => $row) {
                $normalized_row = self::normalize_object(
                    $row,
                    $schema,
                    sprintf('tables.%s[%d]', $table_name, $index)
                );

                if (is_wp_error($normalized_row)) {
                    return $normalized_row;
                }

                $normalized_tables[$table_name][] = $normalized_row;
            }
        }

        $relationship_error = self::validate_relationships($normalized_users, $normalized_tables);

        if (is_wp_error($relationship_error)) {
            return $relationship_error;
        }

        $normalized_options = self::normalize_options($payload['options'] ?? array());

        if (is_wp_error($normalized_options)) {
            return $normalized_options;
        }

        $metadata = self::normalize_metadata($payload);

        if (is_wp_error($metadata)) {
            return $metadata;
        }

        return array_merge(
            $metadata,
            array(
                'format_version' => TP_Backups::BACKUP_FORMAT_VERSION,
                'plugin'         => 'tatipilates',
                'options'        => $normalized_options,
                'users'          => $normalized_users,
                'tables'         => $normalized_tables,
            )
        );
    }

    /**
     * Returns wpdb formats for an already-normalized table row.
     *
     * @param string              $table_name Logical table name.
     * @param array<string,mixed> $row        Normalized row.
     * @return array<int,string>|WP_Error
     */
    public static function formats_for($table_name, $row) {
        $schemas = self::table_schemas();

        if (empty($schemas[$table_name])) {
            return self::error('tp_backup_unknown_table', 'No existe contrato para la tabla ' . $table_name . '.');
        }

        $formats = array();

        foreach (array_keys($row) as $column) {
            if (!isset($schemas[$table_name][$column])) {
                return self::error('tp_backup_unknown_field', sprintf('La columna %1$s no pertenece a %2$s.', $column, $table_name));
            }

            $type      = $schemas[$table_name][$column]['type'];
            $formats[] = in_array($type, array('integer', 'boolean'), true) ? '%d' : '%s';
        }

        return $formats;
    }

    /**
     * Normalizes top-level metadata retained for traceability.
     *
     * @param array<string,mixed> $payload Raw payload.
     * @return array<string,mixed>|WP_Error
     */
    private static function normalize_metadata($payload) {
        $metadata = array();
        $fields   = array(
            'version'    => array('type' => 'string', 'max' => 40, 'default' => ''),
            'site_url'   => array('type' => 'string', 'max' => 2048, 'default' => ''),
            'created_at' => array('type' => 'iso_datetime', 'default' => ''),
            'schema'     => array('type' => 'string', 'max' => 120, 'default' => ''),
        );

        foreach ($fields as $name => $rules) {
            $value = array_key_exists($name, $payload) ? $payload[$name] : $rules['default'];
            $value = self::normalize_value($value, $rules, $name);

            if (is_wp_error($value)) {
                return $value;
            }

            $metadata[$name] = $value;
        }

        return $metadata;
    }

    /**
     * Normalizes WordPress users included in the backup.
     *
     * @param array<int,mixed> $users Raw users.
     * @return array<int,array<string,mixed>>|WP_Error
     */
    private static function normalize_users($users) {
        $schema = array(
            'ID'              => array('type' => 'integer', 'min' => 1, 'required' => true),
            'user_login'      => array('type' => 'login', 'max' => 60, 'required' => true),
            'user_email'      => array('type' => 'email', 'max' => 100, 'required' => true),
            'display_name'    => array('type' => 'string', 'max' => 250, 'required' => true),
            'first_name'      => array('type' => 'string', 'max' => 250, 'default' => ''),
            'last_name'       => array('type' => 'string', 'max' => 250, 'default' => ''),
            'nickname'        => array('type' => 'string', 'max' => 250, 'default' => ''),
            'user_registered' => array('type' => 'datetime', 'nullable' => true, 'default' => null),
            'roles'           => array('type' => 'roles', 'default' => array('tp_alumna')),
        );
        $normalized = array();
        $seen_ids   = array();
        $seen_emails = array();
        $seen_logins = array();

        foreach ($users as $index => $user) {
            $item = self::normalize_object($user, $schema, sprintf('users[%d]', $index));

            if (is_wp_error($item)) {
                return $item;
            }

            if (isset($seen_ids[$item['ID']])) {
                return self::error('tp_backup_duplicate_user', sprintf('users contiene el ID duplicado %d.', $item['ID']));
            }

            $email_key = strtolower($item['user_email']);
            $login_key = strtolower($item['user_login']);

            if (isset($seen_emails[$email_key]) || isset($seen_logins[$login_key])) {
                return self::error('tp_backup_duplicate_user', 'users contiene correos o logins duplicados.');
            }

            $seen_ids[$item['ID']]       = true;
            $seen_emails[$email_key]     = true;
            $seen_logins[$login_key]     = true;
            $normalized[]                = $item;
        }

        return $normalized;
    }

    /**
     * Normalizes notification configuration.
     *
     * @param mixed $options Raw options object.
     * @return array<string,mixed>|WP_Error
     */
    private static function normalize_options($options) {
        if (!is_array($options) || (self::is_list($options) && !empty($options))) {
            return self::error('tp_backup_invalid_options', 'El campo options debe ser un objeto.');
        }

        $unknown = array_diff(array_keys($options), array('tp_notificaciones_config'));

        if ($unknown) {
            return self::error('tp_backup_unknown_option', 'El backup contiene opciones desconocidas: ' . implode(', ', $unknown) . '.');
        }

        if (!array_key_exists('tp_notificaciones_config', $options)) {
            return array();
        }

        $config = $options['tp_notificaciones_config'];

        if (!is_array($config) || (self::is_list($config) && !empty($config))) {
            return self::error('tp_backup_invalid_option', 'tp_notificaciones_config debe ser un objeto.');
        }

        $schema = array(
            'clase_proxima_activa'           => array('type' => 'boolean'),
            'recuperacion_por_vencer_activa' => array('type' => 'boolean'),
            'recuperacion_dias'               => array('type' => 'integer', 'min' => 1, 'max' => 30),
            'pago_pendiente_activa'           => array('type' => 'boolean'),
            'pago_vencido_activa'             => array('type' => 'boolean'),
            'texto_clase_proxima'             => array('type' => 'string', 'max' => 2000),
            'texto_recuperacion'              => array('type' => 'string', 'max' => 2000),
            'texto_recuperacion_hoy'          => array('type' => 'string', 'max' => 2000),
            'texto_pago_pendiente'            => array('type' => 'string', 'max' => 2000),
            'texto_pago_vencido'              => array('type' => 'string', 'max' => 2000),
        );
        $unknown_config = array_diff(array_keys($config), array_keys($schema));

        if ($unknown_config) {
            return self::error('tp_backup_unknown_option_field', 'tp_notificaciones_config contiene campos desconocidos: ' . implode(', ', $unknown_config) . '.');
        }

        $normalized = array();

        foreach ($config as $name => $value) {
            $value = self::normalize_value($value, $schema[$name], 'options.tp_notificaciones_config.' . $name);

            if (is_wp_error($value)) {
                return $value;
            }

            $normalized[$name] = $value;
        }

        return array('tp_notificaciones_config' => $normalized);
    }

    /**
     * Normalizes an object using an explicit field allowlist.
     *
     * @param mixed                              $object  Raw object.
     * @param array<string,array<string,mixed>> $schema  Field schema.
     * @param string                             $path    Error path.
     * @return array<string,mixed>|WP_Error
     */
    private static function normalize_object($object, $schema, $path) {
        if (!is_array($object) || self::is_list($object)) {
            return self::error('tp_backup_invalid_row', $path . ' debe ser un objeto.');
        }

        $unknown = array_diff(array_keys($object), array_keys($schema));

        if ($unknown) {
            return self::error('tp_backup_unknown_field', $path . ' contiene campos desconocidos: ' . implode(', ', $unknown) . '.');
        }

        $normalized = array();

        foreach ($schema as $name => $rules) {
            if (!array_key_exists($name, $object)) {
                if (!empty($rules['required'])) {
                    return self::error('tp_backup_missing_field', sprintf('%1$s.%2$s es requerido.', $path, $name));
                }

                if (array_key_exists('default', $rules)) {
                    $normalized[$name] = $rules['default'];
                }

                continue;
            }

            $value = self::normalize_value($object[$name], $rules, $path . '.' . $name);

            if (is_wp_error($value)) {
                return $value;
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /**
     * Normalizes one scalar or structured value.
     *
     * @param mixed               $value Raw value.
     * @param array<string,mixed> $rules Validation rules.
     * @param string              $path  Error path.
     * @return mixed|WP_Error
     */
    private static function normalize_value($value, $rules, $path) {
        if (null === $value) {
            return !empty($rules['nullable'])
                ? null
                : self::error('tp_backup_null_not_allowed', $path . ' no permite null.');
        }

        switch ($rules['type']) {
            case 'integer':
                $normalized = self::normalize_integer($value, $rules['min'] ?? PHP_INT_MIN, $rules['max'] ?? PHP_INT_MAX);
                break;
            case 'boolean':
                $normalized = self::normalize_integer($value, 0, 1);
                break;
            case 'string':
                $normalized = self::normalize_string(
                    $value,
                    $rules['max'] ?? 65535,
                    !empty($rules['non_empty']),
                    $rules['max_bytes'] ?? 0
                );
                break;
            case 'email':
                $normalized = self::normalize_email($value, $rules['max'] ?? 100);
                break;
            case 'login':
                $normalized = self::normalize_login($value, $rules['max'] ?? 60);
                break;
            case 'enum':
                $normalized = self::normalize_enum($value, $rules['values']);
                break;
            case 'date':
                $normalized = self::normalize_date($value);
                break;
            case 'datetime':
                $normalized = self::normalize_datetime($value);
                break;
            case 'iso_datetime':
                $normalized = self::normalize_iso_datetime($value);
                break;
            case 'time':
                $normalized = self::normalize_time($value);
                break;
            case 'roles':
                $normalized = self::normalize_roles($value);
                break;
            default:
                return self::error('tp_backup_unknown_type', 'No existe validador para ' . $path . '.');
        }

        if (is_wp_error($normalized)) {
            return self::error('tp_backup_invalid_value', sprintf('%1$s tiene un valor invalido: %2$s', $path, $normalized->get_error_message()));
        }

        return $normalized;
    }

    /**
     * Validates foreign keys and logical uniqueness inside the payload.
     *
     * @param array<int,array<string,mixed>>              $users  Users.
     * @param array<string,array<int,array<string,mixed>>> $tables Tables.
     * @return true|WP_Error
     */
    private static function validate_relationships($users, $tables) {
        $user_ids = array_fill_keys(array_column($users, 'ID'), true);
        $ids      = array();

        foreach ($tables as $table_name => $rows) {
            $ids[$table_name] = array();

            foreach ($rows as $row) {
                if (isset($ids[$table_name][$row['id']])) {
                    return self::error('tp_backup_duplicate_id', sprintf('La tabla %1$s contiene el ID duplicado %2$d.', $table_name, $row['id']));
                }

                $ids[$table_name][$row['id']] = true;
            }
        }

        foreach ($tables['alumnas'] as $row) {
            if (!isset($user_ids[$row['wp_user_id']])) {
                return self::error('tp_backup_missing_reference', sprintf('alumnas.%d referencia al usuario inexistente %d.', $row['id'], $row['wp_user_id']));
            }
        }

        $unique_reservations = array();

        foreach ($tables['reservas'] as $row) {
            if (!isset($ids['alumnas'][$row['alumna_id']]) || !isset($ids['horarios'][$row['horario_id']])) {
                return self::error('tp_backup_missing_reference', sprintf('reservas.%d contiene una referencia de alumna u horario inexistente.', $row['id']));
            }

            if (null !== $row['recuperacion_id'] && !isset($ids['recuperaciones'][$row['recuperacion_id']])) {
                return self::error('tp_backup_missing_reference', sprintf('reservas.%d referencia la recuperacion inexistente %d.', $row['id'], $row['recuperacion_id']));
            }

            $key = $row['alumna_id'] . '|' . $row['horario_id'] . '|' . $row['fecha'];

            if (isset($unique_reservations[$key])) {
                return self::error('tp_backup_duplicate_logical_row', 'El backup contiene reservas duplicadas para la misma alumna, horario y fecha.');
            }

            $unique_reservations[$key] = true;
        }

        $unique_recoveries = array();

        foreach ($tables['recuperaciones'] as $row) {
            if (!isset($ids['alumnas'][$row['alumna_id']]) || !isset($ids['reservas'][$row['reserva_origen_id']])) {
                return self::error('tp_backup_missing_reference', sprintf('recuperaciones.%d contiene una referencia inexistente.', $row['id']));
            }

            if (isset($unique_recoveries[$row['reserva_origen_id']])) {
                return self::error('tp_backup_duplicate_logical_row', 'El backup contiene varias recuperaciones para una misma reserva origen.');
            }

            $unique_recoveries[$row['reserva_origen_id']] = true;
        }

        $unique_payments = array();

        foreach ($tables['pagos'] as $row) {
            if (!isset($ids['alumnas'][$row['alumna_id']])) {
                return self::error('tp_backup_missing_reference', sprintf('pagos.%d referencia una alumna inexistente.', $row['id']));
            }

            $key = $row['alumna_id'] . '|' . $row['mes'];

            if (isset($unique_payments[$key])) {
                return self::error('tp_backup_duplicate_logical_row', 'El backup contiene pagos duplicados para la misma alumna y mes.');
            }

            $unique_payments[$key] = true;
        }

        foreach ($tables['milestones'] as $row) {
            if (!isset($ids['alumnas'][$row['alumna_id']])) {
                return self::error('tp_backup_missing_reference', sprintf('milestones.%d referencia una alumna inexistente.', $row['id']));
            }
        }

        $unique_notifications = array();

        foreach ($tables['notificaciones'] as $row) {
            if (null !== $row['alumna_id'] && !isset($ids['alumnas'][$row['alumna_id']])) {
                return self::error('tp_backup_missing_reference', sprintf('notificaciones.%d referencia una alumna inexistente.', $row['id']));
            }

            if (null !== $row['referencia'] && '' !== $row['referencia']) {
                $key = $row['tipo'] . '|' . $row['referencia'];

                if (isset($unique_notifications[$key])) {
                    return self::error('tp_backup_duplicate_logical_row', 'El backup contiene notificaciones duplicadas por tipo y referencia.');
                }

                $unique_notifications[$key] = true;
            }
        }

        return true;
    }

    /**
     * Explicit table and column allowlist.
     *
     * @return array<string,array<string,array<string,mixed>>>
     */
    private static function table_schemas() {
        $id       = array('type' => 'integer', 'min' => 1, 'required' => true);
        $nullable_id = array('type' => 'integer', 'min' => 1, 'nullable' => true, 'default' => null);
        $created  = array('type' => 'datetime', 'nullable' => true, 'default' => null);
        $text     = array('type' => 'string', 'max' => 65535, 'max_bytes' => 65535, 'nullable' => true, 'default' => null);

        return array(
            'horarios' => array(
                'id'           => $id,
                'dia_semana'   => array('type' => 'integer', 'min' => 1, 'max' => 6, 'required' => true),
                'hora_inicio'  => array('type' => 'time', 'required' => true),
                'modalidad'    => array('type' => 'enum', 'values' => array('reformer', 'mat'), 'required' => true),
                'cupo_maximo'  => array('type' => 'integer', 'min' => 1, 'max' => 255, 'required' => true),
                'activo'       => array('type' => 'boolean', 'required' => true),
                'created_at'   => $created,
            ),
            'alumnas' => array(
                'id'                   => $id,
                'wp_user_id'           => array('type' => 'integer', 'min' => 1, 'required' => true),
                'plan'                 => array('type' => 'enum', 'values' => array('2x', '3x', '4x', '5x', 'individual'), 'required' => true),
                'activa'               => array('type' => 'boolean', 'required' => true),
                'notas'                => $text,
                'historia_medica'      => $text,
                'alergias'             => $text,
                'motivo_pilates'       => $text,
                'fecha_nacimiento'     => array('type' => 'date', 'nullable' => true, 'default' => null),
                'fecha_inicio_pilates' => array('type' => 'date', 'nullable' => true, 'default' => null),
                'created_at'           => $created,
            ),
            'reservas' => array(
                'id'              => $id,
                'alumna_id'       => array('type' => 'integer', 'min' => 1, 'required' => true),
                'horario_id'      => array('type' => 'integer', 'min' => 1, 'required' => true),
                'fecha'           => array('type' => 'date', 'required' => true),
                'tipo'            => array('type' => 'enum', 'values' => array('normal', 'recuperacion'), 'required' => true),
                'recuperacion_id' => $nullable_id,
                'estado'          => array('type' => 'enum', 'values' => array('reservada', 'asistio', 'falto', 'cancelada'), 'required' => true),
                'creada_por'      => array('type' => 'enum', 'values' => array('alumna', 'admin'), 'required' => true),
                'created_at'      => $created,
            ),
            'recuperaciones' => array(
                'id'                => $id,
                'alumna_id'         => array('type' => 'integer', 'min' => 1, 'required' => true),
                'reserva_origen_id' => array('type' => 'integer', 'min' => 1, 'required' => true),
                'motivo'            => array('type' => 'string', 'max' => 255, 'nullable' => true, 'default' => null),
                'fecha_limite'      => array('type' => 'date', 'required' => true),
                'estado'            => array('type' => 'enum', 'values' => array('pendiente', 'usada', 'expirada'), 'required' => true),
                'created_at'        => $created,
            ),
            'pagos' => array(
                'id'         => $id,
                'alumna_id'  => array('type' => 'integer', 'min' => 1, 'required' => true),
                'mes'        => array('type' => 'date', 'required' => true),
                'plan'       => array('type' => 'enum', 'values' => array('2x', '3x', '4x', '5x', 'individual'), 'required' => true),
                'fecha_pago' => array('type' => 'date', 'required' => true),
                'notas'      => array('type' => 'string', 'max' => 255, 'nullable' => true, 'default' => null),
                'created_at' => $created,
            ),
            'milestones' => array(
                'id'          => $id,
                'alumna_id'   => array('type' => 'integer', 'min' => 1, 'required' => true),
                'titulo'      => array('type' => 'string', 'max' => 120, 'non_empty' => true, 'required' => true),
                'descripcion' => $text,
                'fecha'       => array('type' => 'date', 'required' => true),
                'estado'      => array('type' => 'enum', 'values' => array('activo', 'logrado'), 'required' => true),
                'created_at'  => $created,
            ),
            'notificaciones' => array(
                'id'               => $id,
                'alumna_id'        => $nullable_id,
                'tipo'             => array('type' => 'string', 'max' => 60, 'non_empty' => true, 'required' => true),
                'referencia'       => array('type' => 'string', 'max' => 120, 'nullable' => true, 'default' => null),
                'titulo'           => array('type' => 'string', 'max' => 140, 'non_empty' => true, 'required' => true),
                'mensaje'          => array('type' => 'string', 'max' => 65535, 'max_bytes' => 65535, 'non_empty' => true, 'required' => true),
                'url_accion'       => array('type' => 'string', 'max' => 255, 'nullable' => true, 'default' => null),
                'canal'            => array('type' => 'string', 'max' => 30, 'non_empty' => true, 'required' => true),
                'visible_admin'    => array('type' => 'boolean', 'required' => true),
                'visible_alumna'   => array('type' => 'boolean', 'required' => true),
                'visto_admin'      => array('type' => 'boolean', 'required' => true),
                'visto_alumna'     => array('type' => 'boolean', 'required' => true),
                'eliminado_admin'  => array('type' => 'boolean', 'required' => true),
                'eliminado_alumna' => array('type' => 'boolean', 'required' => true),
                'created_at'       => $created,
                'visto_admin_at'   => array('type' => 'datetime', 'nullable' => true, 'default' => null),
                'visto_alumna_at'  => array('type' => 'datetime', 'nullable' => true, 'default' => null),
            ),
        );
    }

    /**
     * Normalizes an integer or canonical integer string.
     *
     * @param mixed $value Raw value.
     * @param int   $min   Minimum.
     * @param int   $max   Maximum.
     * @return int|WP_Error
     */
    private static function normalize_integer($value, $min, $max) {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/', $value)) {
            $integer = (int) $value;
        } else {
            return self::error('invalid_integer', 'debe ser un entero canonico.');
        }

        if ($integer < $min || $integer > $max) {
            return self::error('integer_out_of_range', sprintf('debe estar entre %1$d y %2$d.', $min, $max));
        }

        return $integer;
    }

    /**
     * Normalizes a UTF-8 string.
     *
     * @param mixed $value     Raw value.
     * @param int   $max       Maximum characters.
     * @param bool  $non_empty Whether empty is invalid.
     * @param int   $max_bytes Maximum bytes, or zero when not constrained.
     * @return string|WP_Error
     */
    private static function normalize_string($value, $max, $non_empty = false, $max_bytes = 0) {
        if (!is_string($value) || 1 !== preg_match('//u', $value)) {
            return self::error('invalid_string', 'debe ser texto UTF-8.');
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

        if ($length > $max || ($max_bytes && strlen($value) > $max_bytes) || ($non_empty && '' === trim($value))) {
            return self::error('invalid_string_length', sprintf('debe contener entre %1$d y %2$d caracteres.', $non_empty ? 1 : 0, $max));
        }

        return $value;
    }

    /**
     * Normalizes an email.
     *
     * @param mixed $value Raw value.
     * @param int   $max   Maximum characters.
     * @return string|WP_Error
     */
    private static function normalize_email($value, $max) {
        $value = self::normalize_string($value, $max, true);

        if (is_wp_error($value) || !is_email($value)) {
            return self::error('invalid_email', 'debe ser un correo valido.');
        }

        return $value;
    }

    /**
     * Normalizes a WordPress login.
     *
     * @param mixed $value Raw value.
     * @param int   $max   Maximum characters.
     * @return string|WP_Error
     */
    private static function normalize_login($value, $max) {
        $value = self::normalize_string($value, $max, true);

        if (is_wp_error($value) || sanitize_user($value, true) !== $value) {
            return self::error('invalid_login', 'debe ser un login valido sin caracteres descartados.');
        }

        return $value;
    }

    /**
     * Normalizes an enum.
     *
     * @param mixed             $value  Raw value.
     * @param array<int,string> $values Allowed values.
     * @return string|WP_Error
     */
    private static function normalize_enum($value, $values) {
        return is_string($value) && in_array($value, $values, true)
            ? $value
            : self::error('invalid_enum', 'no pertenece a los valores permitidos.');
    }

    /**
     * Normalizes a date.
     *
     * @param mixed $value Raw value.
     * @return string|WP_Error
     */
    private static function normalize_date($value) {
        if (!is_string($value)) {
            return self::error('invalid_date', 'debe usar YYYY-MM-DD.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value
            ? $value
            : self::error('invalid_date', 'debe usar una fecha YYYY-MM-DD valida.');
    }

    /**
     * Normalizes a MySQL datetime.
     *
     * @param mixed $value Raw value.
     * @return string|WP_Error
     */
    private static function normalize_datetime($value) {
        if (!is_string($value)) {
            return self::error('invalid_datetime', 'debe usar YYYY-MM-DD HH:MM:SS.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);

        return $date && $date->format('Y-m-d H:i:s') === $value
            ? $value
            : self::error('invalid_datetime', 'debe usar un datetime valido.');
    }

    /**
     * Normalizes an ISO-8601 datetime or an empty legacy value.
     *
     * @param mixed $value Raw value.
     * @return string|WP_Error
     */
    private static function normalize_iso_datetime($value) {
        if ('' === $value) {
            return '';
        }

        if (!is_string($value) || strlen($value) > 40 || false === strtotime($value)) {
            return self::error('invalid_iso_datetime', 'debe usar un datetime ISO-8601 valido.');
        }

        return $value;
    }

    /**
     * Normalizes a MySQL time.
     *
     * @param mixed $value Raw value.
     * @return string|WP_Error
     */
    private static function normalize_time($value) {
        if (!is_string($value) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $value)) {
            return self::error('invalid_time', 'debe usar HH:MM:SS.');
        }

        return $value;
    }

    /**
     * Normalizes known plugin roles.
     *
     * @param mixed $value Raw roles.
     * @return array<int,string>|WP_Error
     */
    private static function normalize_roles($value) {
        if (!is_array($value) || !self::is_list($value) || count($value) > 5) {
            return self::error('invalid_roles', 'debe ser una lista corta de roles.');
        }

        $allowed = array('tp_alumna', 'tp_admin_pilates');
        $roles   = array_values(array_unique($value));

        foreach ($roles as $role) {
            if (!is_string($role) || !in_array($role, $allowed, true)) {
                return self::error('invalid_role', 'contiene un rol no permitido.');
            }
        }

        return $roles ?: array('tp_alumna');
    }

    /**
     * PHP 8.0-compatible list check.
     *
     * @param array<mixed> $value Array.
     * @return bool
     */
    private static function is_list($value) {
        if (!$value) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * Creates a validation error.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @return WP_Error
     */
    private static function error($code, $message) {
        return new WP_Error($code, $message);
    }
}
