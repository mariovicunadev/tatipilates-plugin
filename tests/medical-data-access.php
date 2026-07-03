<?php
/**
 * Focused medical data capability and query checks.
 *
 * Run with the PHP CLI; WordPress and the database are stubbed intentionally.
 *
 * @package TatiPilates
 */

define('ABSPATH', __DIR__ . '/');

/**
 * Minimal WP_Error implementation.
 */
class WP_Error {
    private $code;
    private $message;

    public function __construct($code = '', $message = '') {
        $this->code = $code;
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
 * Minimal role object.
 */
class TP_Medical_Test_Role {
    public $capabilities;

    public function __construct($capabilities = array()) {
        $this->capabilities = $capabilities;
    }

    public function has_cap($capability) {
        return !empty($this->capabilities[$capability]);
    }

    public function add_cap($capability) {
        $this->capabilities[$capability] = true;
    }

    public function remove_cap($capability) {
        unset($this->capabilities[$capability]);
    }
}

/**
 * Minimal role registry.
 */
class TP_Medical_Test_Roles_Registry {
    public $roles = array();
    public $role_names = array();
    public $role_key = 'wp_user_roles';
}

/**
 * Query-capturing wpdb double.
 */
class TP_Medical_Test_Wpdb {
    public $prefix = 'wp_';
    public $users = 'wp_users';
    public $last_query = '';

    public function prepare($query, ...$args) {
        return $query;
    }

    public function get_results($query) {
        $this->last_query = $query;
        return array();
    }

    public function get_row($query) {
        $this->last_query = $query;

        return (object) array(
            'id'                   => 1,
            'wp_user_id'           => 10,
            'plan'                 => '3x',
            'activa'               => 1,
            'notas'                => '',
            'historia_medica'      => 'test',
            'alergias'             => 'test',
            'motivo_pilates'       => 'test',
            'fecha_nacimiento'     => null,
            'fecha_inicio_pilates' => null,
            'created_at'           => '2026-01-01 00:00:00',
            'display_name'         => 'Test',
            'user_email'           => 'test@example.test',
        );
    }
}

class TP_Helpers {
    public static function normalizar_fecha($value, $default = null) {
        return $value ?: $default;
    }
}

$wp_roles = new TP_Medical_Test_Roles_Registry();
$tp_medical_test_roles = array(
    'administrator' => new TP_Medical_Test_Role(array('read' => true)),
);
$tp_medical_test_can_view = false;
$wpdb = new TP_Medical_Test_Wpdb();

function add_action($hook, $callback, $priority = 10, $args = 1) {
    return true;
}

function add_filter($hook, $callback, $priority = 10, $args = 1) {
    return true;
}

function get_role($role) {
    global $tp_medical_test_roles;

    return $tp_medical_test_roles[$role] ?? null;
}

function add_role($role, $name, $capabilities = array()) {
    global $tp_medical_test_roles, $wp_roles;

    $tp_medical_test_roles[$role] = new TP_Medical_Test_Role($capabilities);
    $wp_roles->roles[$role] = array(
        'name'         => $name,
        'capabilities' => $capabilities,
    );
    $wp_roles->role_names[$role] = $name;

    return $tp_medical_test_roles[$role];
}

function wp_roles() {
    global $wp_roles;

    return $wp_roles;
}

function update_option($name, $value, $autoload = true) {
    return true;
}

function current_user_can($capability) {
    global $tp_medical_test_can_view;

    return 'tp_view_medical_data' === $capability ? $tp_medical_test_can_view : true;
}

function current_time($type) {
    return 'timestamp' === $type ? strtotime('2026-07-02 12:00:00 UTC') : '2026-07-02 12:00:00';
}

function absint($value) {
    return abs((int) $value);
}

function sanitize_text_field($value) {
    return trim((string) $value);
}

function sanitize_textarea_field($value) {
    return trim((string) $value);
}

function wp_unslash($value) {
    return $value;
}

function is_wp_error($value) {
    return $value instanceof WP_Error;
}

function tp_medical_test_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tp_medical_test_query_excludes_sensitive($query, $context) {
    tp_medical_test_assert(false === strpos($query, 'a.*'), $context . ' usa a.*.');

    foreach (array('historia_medica', 'alergias', 'motivo_pilates') as $field) {
        tp_medical_test_assert(false === strpos($query, $field), $context . ' recupera ' . $field . '.');
    }
}

require_once dirname(__DIR__) . '/includes/class-roles.php';
require_once dirname(__DIR__) . '/includes/class-alumnas.php';

$checks = array();

try {
    $checks['roles_are_separated'] = function () use (&$tp_medical_test_roles) {
        TP_Roles::registrar_roles();

        $administrator = $tp_medical_test_roles['administrator'];
        $pilates_admin = $tp_medical_test_roles[TP_Roles::ROLE_ADMIN_PILATES];

        tp_medical_test_assert($administrator->has_cap(TP_Roles::CAP_MANAGE_PILATES), 'Administrator no recibio gestion.');
        tp_medical_test_assert($administrator->has_cap(TP_Roles::CAP_VIEW_MEDICAL_DATA), 'Administrator no recibio acceso medico.');
        tp_medical_test_assert($pilates_admin->has_cap(TP_Roles::CAP_MANAGE_PILATES), 'Admin Pilates no recibio gestion.');
        tp_medical_test_assert(!$pilates_admin->has_cap(TP_Roles::CAP_VIEW_MEDICAL_DATA), 'Admin Pilates heredo acceso medico.');
    };

    $checks['student_listing_excludes_medical_fields'] = function () use ($wpdb) {
        TP_Alumnas::obtener_todas();
        tp_medical_test_query_excludes_sensitive($wpdb->last_query, 'obtener_todas');
    };

    $checks['operational_profile_excludes_medical_fields'] = function () use ($wpdb) {
        TP_Alumnas::obtener(1);
        tp_medical_test_query_excludes_sensitive($wpdb->last_query, 'obtener');
    };

    $checks['portal_profile_excludes_medical_fields'] = function () use ($wpdb) {
        TP_Alumnas::obtener_por_wp_user_id(10);
        tp_medical_test_query_excludes_sensitive($wpdb->last_query, 'obtener_por_wp_user_id');
    };

    $checks['birthday_listing_excludes_medical_fields'] = function () use ($wpdb) {
        TP_Alumnas::cumpleanos_mes(7);
        tp_medical_test_query_excludes_sensitive($wpdb->last_query, 'cumpleanos_mes');
    };

    $checks['medical_read_is_rejected_without_capability'] = function () use (&$tp_medical_test_can_view) {
        $tp_medical_test_can_view = false;
        $result = TP_Alumnas::obtener_con_datos_medicos(1);

        tp_medical_test_assert(is_wp_error($result), 'La lectura medica sin permiso no fue rechazada.');
        tp_medical_test_assert('tp_datos_medicos_prohibidos' === $result->get_error_code(), 'Codigo de rechazo medico inesperado.');
    };

    $checks['medical_read_is_explicit_with_capability'] = function () use (&$tp_medical_test_can_view, $wpdb) {
        $tp_medical_test_can_view = true;
        TP_Alumnas::obtener_con_datos_medicos(1);

        foreach (array('historia_medica', 'alergias', 'motivo_pilates') as $field) {
            tp_medical_test_assert(false !== strpos($wpdb->last_query, $field), 'La ficha autorizada no recupera ' . $field . '.');
        }
    };

    $checks['medical_write_is_rejected_without_capability'] = function () use (&$tp_medical_test_can_view) {
        $tp_medical_test_can_view = false;
        $method = new ReflectionMethod('TP_Alumnas', 'validar_acceso_datos_medicos');
        $method->setAccessible(true);
        $result = $method->invoke(null, array('historia_medica' => 'dato'));

        tp_medical_test_assert(is_wp_error($result), 'La escritura medica sin permiso no fue rechazada.');
    };

    $checks['non_medical_edit_preserves_sensitive_columns'] = function () use (&$tp_medical_test_can_view) {
        $tp_medical_test_can_view = false;
        $method = new ReflectionMethod('TP_Alumnas', 'validar_datos_edicion');
        $method->setAccessible(true);
        $result = $method->invoke(
            null,
            array(
                'nombre' => 'Test',
                'plan'   => '3x',
                'activa' => 1,
                'notas'  => 'Nota operativa',
            )
        );

        tp_medical_test_assert(!is_wp_error($result), 'La edicion operativa valida fue rechazada.');

        foreach (array('historia_medica', 'alergias', 'motivo_pilates') as $field) {
            tp_medical_test_assert(!array_key_exists($field, $result), 'La edicion operativa intenta sobrescribir ' . $field . '.');
        }
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
