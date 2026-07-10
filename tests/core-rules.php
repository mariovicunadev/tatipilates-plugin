<?php
/**
 * Focused helper, reservation and transaction rule checks.
 *
 * Run with the PHP CLI; WordPress and the database are stubbed intentionally.
 *
 * @package TatiPilates
 */

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);

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

/**
 * Query-capturing wpdb double for core rule checks.
 */
class TP_Core_Test_Wpdb {
    public $prefix = 'wp_';
    public $users = 'wp_users';
    public $last_error = '';
    public $insert_id = 1000;
    public $queries = array();
    public $inserted = array();
    public $updated = array();
    public $deleted = array();
    public $active_reservations = 0;
    public $weekly_normal_count = 0;
    public $duplicate_id = 0;
    public $canceled_reservation = null;
    public $pending_recovery = null;
    public $matching_schedule = null;
    public $fail_insert_table = '';
    public $fail_update_table = '';

    public function reset() {
        $this->last_error = '';
        $this->insert_id = 1000;
        $this->queries = array();
        $this->inserted = array();
        $this->updated = array();
        $this->deleted = array();
        $this->active_reservations = 0;
        $this->weekly_normal_count = 0;
        $this->duplicate_id = 0;
        $this->canceled_reservation = null;
        $this->pending_recovery = null;
        $this->matching_schedule = null;
        $this->fail_insert_table = '';
        $this->fail_update_table = '';
    }

    public function prepare($query, ...$args) {
        return $query;
    }

    public function query($query) {
        $this->queries[] = $query;

        return 1;
    }

    public function get_var($query) {
        $this->queries[] = $query;

        if (false !== strpos($query, "estado IN ('reservada', 'asistio')")) {
            return $this->active_reservations;
        }

        if (false !== strpos($query, "r.tipo = 'normal'")) {
            return $this->weekly_normal_count;
        }

        if (false !== strpos($query, "estado != 'cancelada'")) {
            return $this->duplicate_id;
        }

        return 0;
    }

    public function get_row($query) {
        $this->queries[] = $query;

        if (false !== strpos($query, 'FOR UPDATE')) {
            return (object) array('id' => 1);
        }

        if (false !== strpos($query, "estado = 'cancelada'")) {
            return $this->canceled_reservation;
        }

        if (false !== strpos($query, 'tp_recuperaciones')) {
            return $this->pending_recovery;
        }

        if (false !== strpos($query, 'tp_horarios')) {
            return $this->matching_schedule;
        }

        return null;
    }

    public function get_results($query) {
        $this->queries[] = $query;

        return array();
    }

    public function insert($table, $data, $format = null) {
        if ($this->fail_insert_table && false !== strpos($table, $this->fail_insert_table)) {
            $this->last_error = 'forced insert failure';
            return false;
        }

        $this->insert_id++;
        $this->inserted[] = array(
            'table' => $table,
            'data'  => $data,
        );

        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        $this->updated[] = array(
            'table' => $table,
            'data'  => $data,
            'where' => $where,
        );

        if ($this->fail_update_table && false !== strpos($table, $this->fail_update_table)) {
            $this->last_error = 'forced update failure';
            return false;
        }

        return 1;
    }

    public function delete($table, $where, $where_format = null) {
        $this->deleted[] = array(
            'table' => $table,
            'where' => $where,
        );

        return 1;
    }

    public function saw_query($needle) {
        foreach ($this->queries as $query) {
            if (false !== strpos($query, $needle)) {
                return true;
            }
        }

        return false;
    }
}

$wpdb = new TP_Core_Test_Wpdb();
$tp_core_test_alumnas = array();
$tp_core_test_horarios = array();
$tp_core_test_payment = array(
    'puede_reservar' => true,
    'estado'         => 'ok',
    'mensaje'        => '',
);

function __($text, $domain = 'default') {
    return $text;
}

function absint($value) {
    return abs((int) $value);
}

function sanitize_text_field($value) {
    return trim((string) $value);
}

function wp_unslash($value) {
    return $value;
}

function current_time($type) {
    return 'timestamp' === $type ? strtotime('2026-07-09 12:00:00 UTC') : '2026-07-09 12:00:00';
}

function is_wp_error($value) {
    return $value instanceof WP_Error;
}

function tp_core_test_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tp_core_test_expect_error($result, $code) {
    tp_core_test_assert(is_wp_error($result), 'Se esperaba WP_Error.');
    tp_core_test_assert($code === $result->get_error_code(), 'Codigo inesperado: ' . $result->get_error_code());
}

function tp_core_test_reset() {
    global $wpdb, $tp_core_test_alumnas, $tp_core_test_horarios, $tp_core_test_payment;

    $wpdb->reset();
    $tp_core_test_alumnas = array(
        1 => (object) array(
            'id'     => 1,
            'plan'   => '2x',
            'activa' => 1,
        ),
    );
    $tp_core_test_horarios = array(
        10 => (object) array(
            'id'          => 10,
            'dia_semana'  => 1,
            'activo'      => 1,
            'cupo_maximo' => 5,
            'modalidad'   => 'reformer',
            'hora_inicio' => '08:00:00',
        ),
    );
    $tp_core_test_payment = array(
        'puede_reservar' => true,
        'estado'         => 'ok',
        'mensaje'        => '',
    );
}

class TP_Alumnas {
    public static function obtener($alumna_id) {
        global $tp_core_test_alumnas;

        return $tp_core_test_alumnas[(int) $alumna_id] ?? null;
    }
}

class TP_Horarios {
    public static function obtener($horario_id) {
        global $tp_core_test_horarios;

        return $tp_core_test_horarios[(int) $horario_id] ?? null;
    }

    public static function formatear_hora($hora) {
        return substr((string) $hora, 0, 5);
    }
}

class TP_Pagos {
    public static function normalizar_mes($fecha) {
        return gmdate('Y-m-01', strtotime($fecha));
    }

    public static function formatear_fecha($fecha) {
        return $fecha;
    }

    public static function estado_para_reservas($alumna_id, $mes) {
        global $tp_core_test_payment;

        return $tp_core_test_payment;
    }
}

require_once dirname(__DIR__) . '/includes/class-helpers.php';
require_once dirname(__DIR__) . '/includes/class-reservas.php';
require_once dirname(__DIR__) . '/includes/class-recuperaciones.php';

$checks = array();

$checks['helpers_normalize_week_and_expiration'] = function () {
    tp_core_test_assert('2026-07-09' === TP_Helpers::normalizar_fecha('2026-07-09'), 'No normalizo fecha valida.');
    tp_core_test_assert('fallback' === TP_Helpers::normalizar_fecha('', 'fallback'), 'No respeto default vacio.');
    tp_core_test_assert('2026-06-15' === TP_Helpers::rango_semana('2026-06-21')['inicio'], 'Inicio de semana incorrecto.');
    tp_core_test_assert('2026-06-21' === TP_Helpers::rango_semana('2026-06-21')['fin'], 'Fin de semana incorrecto.');
    tp_core_test_assert('2026-09-11' === TP_Helpers::fecha_vencimiento_recuperacion('2026-06-11'), 'Vencimiento de recuperacion incorrecto.');
    tp_core_test_assert(5 === TP_Helpers::clases_por_plan('5x'), 'Limite de plan 5x incorrecto.');
    tp_core_test_assert(0 === TP_Helpers::clases_por_plan('individual'), 'Limite de plan individual incorrecto.');
};

$checks['weekly_plan_limit_counts_remaining'] = function () {
    global $wpdb;

    tp_core_test_reset();
    $wpdb->weekly_normal_count = 1;
    $cupo = TP_Reservas::cupo_plan_semana(1, '2026-07-06', '2x');

    tp_core_test_assert(2 === $cupo['limite'], 'Limite semanal incorrecto.');
    tp_core_test_assert(1 === $cupo['usadas'], 'Usadas incorrectas.');
    tp_core_test_assert(1 === $cupo['restantes'], 'Restantes incorrectas.');
    tp_core_test_assert(false === $cupo['completo'], 'No deberia estar completo.');

    $wpdb->weekly_normal_count = 2;
    $cupo = TP_Reservas::cupo_plan_semana(1, '2026-07-06', '2x');
    tp_core_test_assert(true === $cupo['completo'], 'Deberia estar completo.');
};

$checks['reservation_success_commits_once'] = function () {
    global $wpdb;

    tp_core_test_reset();
    $result = TP_Reservas::intentar_reserva(1, 10, '2026-07-06');

    tp_core_test_assert(!is_wp_error($result), 'Reserva valida fallo: ' . (is_wp_error($result) ? $result->get_error_message() : ''));
    tp_core_test_assert(true === $result['success'], 'Reserva valida no devolvio success.');
    tp_core_test_assert('normal' === $result['tipo'], 'Reserva valida deberia ser normal.');
    tp_core_test_assert($wpdb->saw_query('START TRANSACTION'), 'No inicio transaccion.');
    tp_core_test_assert($wpdb->saw_query('COMMIT'), 'No hizo commit.');
    tp_core_test_assert(!$wpdb->saw_query('ROLLBACK'), 'No debio hacer rollback.');
    tp_core_test_assert(1 === count($wpdb->inserted), 'Debe insertar una reserva.');
};

$checks['full_class_rolls_back_without_insert'] = function () {
    global $wpdb;

    tp_core_test_reset();
    $wpdb->active_reservations = 5;
    $result = TP_Reservas::intentar_reserva(1, 10, '2026-07-06');

    tp_core_test_expect_error($result, 'tp_clase_llena');
    tp_core_test_assert($wpdb->saw_query('START TRANSACTION'), 'No inicio transaccion.');
    tp_core_test_assert($wpdb->saw_query('ROLLBACK'), 'No hizo rollback por clase llena.');
    tp_core_test_assert(0 === count($wpdb->inserted), 'No debe insertar si la clase esta llena.');
};

$checks['duplicate_reservation_rolls_back_without_insert'] = function () {
    global $wpdb;

    tp_core_test_reset();
    $wpdb->duplicate_id = 77;
    $result = TP_Reservas::intentar_reserva(1, 10, '2026-07-06');

    tp_core_test_expect_error($result, 'tp_reserva_duplicada');
    tp_core_test_assert($wpdb->saw_query('ROLLBACK'), 'No hizo rollback por duplicado.');
    tp_core_test_assert(0 === count($wpdb->inserted), 'No debe insertar una reserva duplicada.');
};

$checks['recovery_update_failure_rolls_back'] = function () {
    global $wpdb;

    tp_core_test_reset();
    $wpdb->weekly_normal_count = 2;
    $wpdb->pending_recovery = (object) array(
        'id'           => 25,
        'alumna_id'    => 1,
        'fecha_limite' => '2026-08-01',
        'estado'       => 'pendiente',
    );
    $wpdb->fail_update_table = 'tp_recuperaciones';

    $result = TP_Reservas::intentar_reserva(1, 10, '2026-07-06');

    tp_core_test_expect_error($result, 'tp_recuperacion_no_usada');
    tp_core_test_assert($wpdb->saw_query('ROLLBACK'), 'No hizo rollback cuando fallo usar recuperacion.');
    tp_core_test_assert(1 === count($wpdb->inserted), 'Debe intentar insertar la reserva antes del fallo.');
    tp_core_test_assert(1 === count($wpdb->updated), 'Debe intentar marcar la recuperacion como usada.');
    tp_core_test_assert('recuperacion' === $wpdb->inserted[0]['data']['tipo'], 'La reserva debia usar recuperacion.');
};

$checks['manual_recovery_failure_rolls_back_origin_reservation'] = function () {
    global $wpdb;

    tp_core_test_reset();
    $wpdb->matching_schedule = (object) array(
        'id'          => 10,
        'dia_semana'  => 1,
        'activo'      => 1,
        'hora_inicio' => '08:00:00',
    );
    $wpdb->fail_insert_table = 'tp_recuperaciones';

    $result = TP_Recuperaciones::crear_manual(
        array(
            'alumna_id'    => 1,
            'fecha_falta'  => '2026-07-06',
            'fecha_limite' => '2026-08-06',
            'motivo'       => 'Test',
        )
    );

    tp_core_test_expect_error($result, 'tp_recuperacion_no_creada');
    tp_core_test_assert($wpdb->saw_query('START TRANSACTION'), 'No inicio transaccion en recuperacion manual.');
    tp_core_test_assert($wpdb->saw_query('ROLLBACK'), 'No hizo rollback si falla crear recuperacion.');
    tp_core_test_assert(1 === count($wpdb->inserted), 'Debe haber insertado solo la reserva origen antes del fallo.');
};

try {
    foreach ($checks as $name => $check) {
        $check();
        echo 'PASS ' . $name . PHP_EOL;
    }

    echo 'PASS total=' . count($checks) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
