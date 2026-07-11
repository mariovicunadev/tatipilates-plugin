<?php
/**
 * Focused backup selective-restore checks.
 *
 * @package TatiPilates
 */

define('ABSPATH', __DIR__ . '/');
define('TP_VERSION', 'test');

/**
 * Minimal WP_Error implementation.
 */
class WP_Error {
    private $code;
    private $message;

    public function __construct($code = '', $message = '') {
        $this->code    = $code;
        $this->message = $message;
    }

    public function get_error_code() {
        return $this->code;
    }

    public function get_error_message() {
        return $this->message;
    }
}

class TP_Backup_Selective_Test_Wpdb {
    public $prefix = 'wp_';
}

$wpdb = new TP_Backup_Selective_Test_Wpdb();

function is_wp_error($value) {
    return $value instanceof WP_Error;
}

function sanitize_key($value) {
    return strtolower(preg_replace('/[^a-z0-9_\\-]/', '', (string) $value));
}

function tp_backup_selective_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tp_backup_selective_payload() {
    $tables = array();

    foreach (array('horarios', 'alumnas', 'reservas', 'recuperaciones', 'pagos', 'milestones', 'notificaciones') as $table) {
        $tables[$table] = array(array('id' => 1));
    }

    return array(
        'users'   => array(array('ID' => 1)),
        'tables'  => $tables,
        'options' => array('tp_notificaciones_config' => array('x' => 'y')),
    );
}

require_once dirname(__DIR__) . '/includes/class-backups.php';

$checks = array();

try {
    $method = new ReflectionMethod('TP_Backups', 'filtrar_payload_tablas');

    $checks['empty_selection_keeps_full_payload'] = function () use ($method) {
        $payload = tp_backup_selective_payload();
        $result  = $method->invoke(null, $payload, array());

        tp_backup_selective_assert(!is_wp_error($result), 'El payload completo fue rechazado.');
        tp_backup_selective_assert($payload === $result, 'Sin seleccion debe conservar todo el payload.');
    };

    $checks['single_table_selection_keeps_only_that_table'] = function () use ($method) {
        $payload = tp_backup_selective_payload();
        $result  = $method->invoke(null, $payload, array('pagos'));

        tp_backup_selective_assert(!is_wp_error($result), 'La seleccion parcial fue rechazada.');
        tp_backup_selective_assert(array(array('id' => 1)) === $result['tables']['pagos'], 'Pagos no se preservo.');
        tp_backup_selective_assert(array() === $result['tables']['reservas'], 'Reservas debio vaciarse.');
        tp_backup_selective_assert(array() === $result['tables']['alumnas'], 'Alumnas debio vaciarse.');
        tp_backup_selective_assert(array() === $result['users'], 'Usuarios debieron omitirse sin alumnas.');
        tp_backup_selective_assert(array() === $result['options'], 'Opciones debieron omitirse en restore parcial.');
        tp_backup_selective_assert(array('pagos') === $result['selected_tables'], 'No se registro seleccion normalizada.');
    };

    $checks['students_selection_keeps_users'] = function () use ($method) {
        $payload = tp_backup_selective_payload();
        $result  = $method->invoke(null, $payload, array('alumnas'));

        tp_backup_selective_assert(!empty($result['users']), 'Seleccionar alumnas debe conservar usuarios vinculados.');
        tp_backup_selective_assert(!empty($result['tables']['alumnas']), 'Alumnas no se preservo.');
        tp_backup_selective_assert(array() === $result['tables']['pagos'], 'Pagos debio vaciarse.');
    };

    foreach ($checks as $name => $check) {
        $check();
        echo 'PASS ' . $name . PHP_EOL;
    }

    echo 'PASS total=' . count($checks) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
