<?php
/**
 * Focused backup import contract checks.
 *
 * Run with TP_WP_LOAD pointing to the target wp-load.php.
 *
 * @package TatiPilates
 */

$wp_load = getenv('TP_WP_LOAD');

if (!$wp_load || !is_readable($wp_load)) {
    fwrite(STDERR, "Define TP_WP_LOAD con la ruta absoluta a wp-load.php.\n");
    exit(1);
}

require_once $wp_load;

/**
 * Fails one check.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function tp_backup_test_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * Expects a specific WP_Error.
 *
 * @param mixed  $result   Result.
 * @param string $code     Expected code.
 * @param string $contains Expected message fragment.
 * @return void
 */
function tp_backup_test_error($result, $code, $contains = '') {
    tp_backup_test_assert(is_wp_error($result), 'Se esperaba WP_Error.');
    tp_backup_test_assert($code === $result->get_error_code(), 'Codigo inesperado: ' . $result->get_error_code());

    if ($contains) {
        tp_backup_test_assert(false !== strpos($result->get_error_message(), $contains), 'Mensaje inesperado: ' . $result->get_error_message());
    }
}

/**
 * Builds a small valid standalone payload.
 *
 * @return array<string,mixed>
 */
function tp_backup_test_fixture() {
    return array(
        'format_version' => TP_Backups::BACKUP_FORMAT_VERSION,
        'plugin'         => 'tatipilates',
        'version'        => TP_VERSION,
        'site_url'       => home_url(),
        'created_at'     => gmdate('c'),
        'schema'         => get_option('tp_schema_version', ''),
        'options'        => array(),
        'users'          => array(
            array(
                'ID'              => 900001,
                'user_login'      => 'backup.fixture@example.test',
                'user_email'      => 'backup.fixture@example.test',
                'display_name'    => 'Backup Fixture',
                'first_name'      => 'Backup',
                'last_name'       => 'Fixture',
                'nickname'        => 'Backup Fixture',
                'user_registered' => '2026-01-01 00:00:00',
                'roles'           => array('tp_alumna'),
            ),
        ),
        'tables'        => array(
            'horarios' => array(
                array(
                    'id'          => 900001,
                    'dia_semana'  => 1,
                    'hora_inicio' => '08:00:00',
                    'modalidad'   => 'reformer',
                    'cupo_maximo' => 5,
                    'activo'      => 1,
                    'created_at'  => '2026-01-01 00:00:00',
                ),
            ),
            'alumnas' => array(
                array(
                    'id'                   => 900001,
                    'wp_user_id'           => 900001,
                    'plan'                 => '2x',
                    'activa'               => 1,
                    'notas'                => null,
                    'historia_medica'      => null,
                    'alergias'             => null,
                    'motivo_pilates'       => null,
                    'fecha_nacimiento'     => null,
                    'fecha_inicio_pilates' => null,
                    'created_at'           => '2026-01-01 00:00:00',
                ),
            ),
            'reservas'       => array(),
            'recuperaciones' => array(),
            'pagos'          => array(),
            'milestones'     => array(),
            'notificaciones' => array(),
        ),
    );
}

/**
 * Hashes mutable data while excluding backup metadata timestamps.
 *
 * @return string
 */
function tp_backup_test_state_hash() {
    $payload = TP_Backups::exportar();

    return hash(
        'sha256',
        wp_json_encode(
            array(
                'users'   => $payload['users'],
                'tables'  => $payload['tables'],
                'options' => $payload['options'],
            )
        )
    );
}

/**
 * Writes a temporary JSON file.
 *
 * @param mixed $payload Payload or raw string.
 * @param bool  $raw     Whether payload is already raw.
 * @return string
 */
function tp_backup_test_temp_file($payload, $raw = false) {
    $path = wp_tempnam('tp-backup-test.json');
    file_put_contents($path, $raw ? $payload : wp_json_encode($payload));

    return $path;
}

$checks = array();

try {
    $fixture = tp_backup_test_fixture();

    $checks['valid_v2'] = function () use ($fixture) {
        tp_backup_test_assert(!is_wp_error(TP_Backups::validar_payload($fixture)), 'El fixture v2 valido fue rechazado.');
    };

    $checks['valid_legacy_v1'] = function () use ($fixture) {
        $legacy = $fixture;
        unset($legacy['format_version']);
        $result = TP_Backups::validar_payload($legacy);
        tp_backup_test_assert(!is_wp_error($result) && 2 === $result['format_version'], 'El backup legacy v1 no se normalizo a v2.');
    };

    $checks['valid_tatiana_role'] = function () use ($fixture) {
        $payload = $fixture;
        $payload['users'][] = array(
            'ID'              => 900002,
            'user_login'      => 'tatiana.fixture@example.test',
            'user_email'      => 'tatiana.fixture@example.test',
            'display_name'    => 'Tatiana Fixture',
            'first_name'      => 'Tatiana',
            'last_name'       => 'Fixture',
            'nickname'        => 'Tatiana Fixture',
            'user_registered' => '2026-01-01 00:00:00',
            'roles'           => array('tp_tatiana'),
        );

        tp_backup_test_assert(!is_wp_error(TP_Backups::validar_payload($payload)), 'El rol Tatiana fue rechazado por el contrato de backup.');
    };

    $checks['truncated_json'] = function () {
        $path = tp_backup_test_temp_file('{"plugin":"tatipilates"', true);

        try {
            tp_backup_test_error(TP_Backups::importar_archivo($path), 'tp_backup_invalid_json', 'JSON valido');
        } finally {
            @unlink($path);
        }
    };

    $checks['oversized_file'] = function () {
        $path   = tp_backup_test_temp_file(str_repeat('x', 2048), true);
        $filter = function () {
            return 1024;
        };
        add_filter('tp_backup_max_bytes', $filter);

        try {
            tp_backup_test_error(TP_Backups::importar_archivo($path), 'tp_backup_file_too_large', 'limite');
        } finally {
            remove_filter('tp_backup_max_bytes', $filter);
            @unlink($path);
        }
    };

    $checks['unknown_table'] = function () use ($fixture) {
        $payload = $fixture;
        $payload['tables']['intrusa'] = array();
        tp_backup_test_error(TP_Backups::validar_payload($payload), 'tp_backup_unknown_table', 'intrusa');
    };

    $checks['unknown_field'] = function () use ($fixture) {
        $payload = $fixture;
        $payload['tables']['horarios'][0]['intruso'] = true;
        tp_backup_test_error(TP_Backups::validar_payload($payload), 'tp_backup_unknown_field', 'intruso');
    };

    $checks['invalid_type'] = function () use ($fixture) {
        $payload = $fixture;
        $payload['tables']['horarios'][0]['id'] = '900001x';
        tp_backup_test_error(TP_Backups::validar_payload($payload), 'tp_backup_invalid_value', 'entero');
    };

    $checks['string_too_long'] = function () use ($fixture) {
        $payload = $fixture;
        $payload['tables']['milestones'][] = array(
            'id'          => 900001,
            'alumna_id'   => 900001,
            'titulo'      => str_repeat('x', 121),
            'descripcion' => null,
            'fecha'       => '2026-01-01',
            'estado'      => 'activo',
            'created_at'  => '2026-01-01 00:00:00',
        );
        tp_backup_test_error(TP_Backups::validar_payload($payload), 'tp_backup_invalid_value', '120');
    };

    $checks['invalid_enum'] = function () use ($fixture) {
        $payload = $fixture;
        $payload['tables']['alumnas'][0]['plan'] = 'ilimitado';
        tp_backup_test_error(TP_Backups::validar_payload($payload), 'tp_backup_invalid_value', 'valores permitidos');
    };

    $checks['missing_reference'] = function () use ($fixture) {
        $payload = $fixture;
        $payload['tables']['alumnas'][0]['wp_user_id'] = 999999;
        tp_backup_test_error(TP_Backups::validar_payload($payload), 'tp_backup_missing_reference', 'usuario inexistente');
    };

    $checks['future_format_no_partial_import'] = function () use ($fixture) {
        $payload = $fixture;
        $payload['format_version'] = TP_Backups::BACKUP_FORMAT_VERSION + 1;
        $path   = tp_backup_test_temp_file($payload);
        $before = tp_backup_test_state_hash();

        try {
            $result = TP_Backups::importar_archivo($path);
            tp_backup_test_error($result, 'tp_backup_future_format', 'solo admite hasta');
            tp_backup_test_assert($before === tp_backup_test_state_hash(), 'El formato futuro modifico datos parcialmente.');
        } finally {
            @unlink($path);
        }
    };

    $checks['valid_v2_import'] = function () {
        $payload = TP_Backups::exportar();
        $path    = tp_backup_test_temp_file($payload);
        $before  = tp_backup_test_state_hash();

        try {
            $result = TP_Backups::importar_archivo($path);
            tp_backup_test_assert(!is_wp_error($result), 'La importacion v2 valida fallo.');
            tp_backup_test_assert($before === tp_backup_test_state_hash(), 'La importacion idempotente altero el estado logico.');
        } finally {
            @unlink($path);
        }
    };

    $checks['transaction_rollback'] = function () {
        global $wpdb;

        $payload = TP_Backups::exportar();
        tp_backup_test_assert(!empty($payload['tables']['alumnas']), 'Se necesita una alumna local para probar rollback.');

        $test_id = 2147483000;
        $payload['tables']['milestones'][] = array(
            'id'          => $test_id,
            'alumna_id'   => (int) $payload['tables']['alumnas'][0]['id'],
            'titulo'      => 'Rollback test',
            'descripcion' => null,
            'fecha'       => gmdate('Y-m-d'),
            'estado'      => 'activo',
            'created_at'  => gmdate('Y-m-d H:i:s'),
        );
        $path   = tp_backup_test_temp_file($payload);
        $before = tp_backup_test_state_hash();
        $filter = function ($query) use ($wpdb) {
            return false !== strpos($query, "INSERT INTO `{$wpdb->prefix}tp_milestones`")
                ? 'INVALID SQL FOR TP BACKUP ROLLBACK TEST'
                : $query;
        };
        $previous_errors = $wpdb->suppress_errors(true);
        add_filter('query', $filter);

        try {
            $result = TP_Backups::importar_archivo($path);
        } finally {
            remove_filter('query', $filter);
            $wpdb->suppress_errors($previous_errors);
            @unlink($path);
        }

        tp_backup_test_error($result, 'tp_backup_db_error');
        tp_backup_test_assert($before === tp_backup_test_state_hash(), 'El rollback no restauro el hash logico.');
        tp_backup_test_assert(
            0 === (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}tp_milestones WHERE id = %d", $test_id)),
            'El rollback dejo la fila de prueba.'
        );
        tp_backup_test_assert('1' === (string) $wpdb->get_var('SELECT @@FOREIGN_KEY_CHECKS'), 'FOREIGN_KEY_CHECKS no fue restaurado.');
    };

    foreach ($checks as $name => $check) {
        $check();
        echo "PASS {$name}\n";
    }

    echo 'PASS total=' . count($checks) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL ' . $exception->getMessage() . "\n");
    exit(1);
}
