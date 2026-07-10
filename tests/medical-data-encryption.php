<?php
/**
 * Focused medical data encryption checks.
 *
 * Run with the PHP CLI; WordPress and the database are stubbed intentionally.
 *
 * @package TatiPilates
 */

define('ABSPATH', __DIR__ . '/');
define('TP_VERSION', 'test');
define('TP_DATA_ENCRYPTION_KEY', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f');

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

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
 * Query-capturing wpdb double for migration checks.
 */
class TP_Encryption_Test_Wpdb {
    public $prefix = 'wp_';
    public $rows = array();
    public $updates = array();

    public function prepare($query, ...$args) {
        return $query;
    }

    public function get_results($query, $output = OBJECT) {
        return $this->rows;
    }

    public function update($table, $data, $where, $formats = null, $where_formats = null) {
        $this->updates[] = array(
            'table' => $table,
            'data'  => $data,
            'where' => $where,
        );

        return 1;
    }
}

$wpdb = new TP_Encryption_Test_Wpdb();

function is_wp_error($value) {
    return $value instanceof WP_Error;
}

function absint($value) {
    return abs((int) $value);
}

function update_option($name, $value, $autoload = true) {
    return true;
}

function tp_log($message, $context = array(), $level = 'info') {
    return true;
}

function tp_encryption_test_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tp_encryption_test_other_key_value($plaintext) {
    $key        = hex2bin('ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff');
    $nonce      = random_bytes(TP_Data_Encryption::NONCE_BYTES);
    $ciphertext = sodium_crypto_secretbox((string) $plaintext, $nonce, $key);

    return TP_Data_Encryption::PREFIX . base64_encode($nonce . $ciphertext);
}

require_once dirname(__DIR__) . '/includes/class-data-encryption.php';
require_once dirname(__DIR__) . '/includes/class-alumnas.php';

$checks = array();

try {
    $checks['environment_is_ready'] = function () {
        $status = TP_Data_Encryption::status();

        tp_encryption_test_assert(!empty($status['sodium_available']), 'Sodium no esta disponible.');
        tp_encryption_test_assert(!empty($status['key_valid']), 'La clave de prueba no es valida.');
        tp_encryption_test_assert(TP_Data_Encryption::is_ready(), 'El cifrado no quedo listo.');
    };

    $checks['encrypts_and_decrypts_value'] = function () {
        $encrypted = TP_Data_Encryption::encrypt('Dato sensible');

        tp_encryption_test_assert(!is_wp_error($encrypted), 'El cifrado devolvio error.');
        tp_encryption_test_assert(TP_Data_Encryption::is_encrypted($encrypted), 'El valor no quedo con envelope cifrado.');
        tp_encryption_test_assert('Dato sensible' === TP_Data_Encryption::decrypt($encrypted), 'El valor descifrado no coincide.');
    };

    $checks['legacy_plaintext_decrypts_unchanged'] = function () {
        tp_encryption_test_assert('Texto plano' === TP_Data_Encryption::decrypt('Texto plano'), 'El texto legacy no se conserva.');
    };

    $checks['backup_plaintext_medical_fields_are_encrypted'] = function () {
        $row = array(
            'id'              => 1,
            'historia_medica' => 'Lesion antigua',
            'alergias'        => '',
            'motivo_pilates'  => 'Fortalecer',
        );

        $prepared = TP_Alumnas::preparar_fila_backup($row);

        tp_encryption_test_assert(!is_wp_error($prepared), 'El backup con texto plano fue rechazado.');
        tp_encryption_test_assert(TP_Data_Encryption::is_encrypted($prepared['historia_medica']), 'Historia medica no se cifro.');
        tp_encryption_test_assert(TP_Data_Encryption::is_encrypted($prepared['motivo_pilates']), 'Motivo Pilates no se cifro.');
        tp_encryption_test_assert('Lesion antigua' === TP_Data_Encryption::decrypt($prepared['historia_medica']), 'Historia medica no descifra.');
    };

    $checks['backup_current_encrypted_fields_are_accepted'] = function () {
        $encrypted = TP_Data_Encryption::encrypt('Alergia sensible');
        $row       = array(
            'id'              => 1,
            'historia_medica' => '',
            'alergias'        => $encrypted,
            'motivo_pilates'  => '',
        );

        $prepared = TP_Alumnas::preparar_fila_backup($row);

        tp_encryption_test_assert(!is_wp_error($prepared), 'El backup cifrado con la clave actual fue rechazado.');
        tp_encryption_test_assert($encrypted === $prepared['alergias'], 'El valor cifrado no se preservo.');
    };

    $checks['backup_foreign_encrypted_fields_are_rejected'] = function () {
        $row = array(
            'id'              => 1,
            'historia_medica' => tp_encryption_test_other_key_value('Dato ajeno'),
            'alergias'        => '',
            'motivo_pilates'  => '',
        );

        $prepared = TP_Alumnas::preparar_fila_backup($row);

        tp_encryption_test_assert(is_wp_error($prepared), 'El backup cifrado con otra clave no fue rechazado.');
        tp_encryption_test_assert('tp_backup_medical_key_mismatch' === $prepared->get_error_code(), 'Codigo inesperado para clave ajena.');
    };

    $checks['migration_encrypts_plaintext_rows'] = function () use ($wpdb) {
        $wpdb->rows = array(
            array(
                'id'              => 12,
                'historia_medica' => 'Molestia lumbar',
                'alergias'        => '',
                'motivo_pilates'  => 'Postura',
            ),
        );

        $result = TP_Alumnas::migrar_datos_medicos_cifrados();

        tp_encryption_test_assert(!is_wp_error($result), 'La migracion devolvio error.');
        tp_encryption_test_assert(1 === $result['migradas'], 'La migracion no conto la fila actualizada.');
        tp_encryption_test_assert(1 === count($wpdb->updates), 'La migracion no actualizo una fila.');
        tp_encryption_test_assert(TP_Data_Encryption::is_encrypted($wpdb->updates[0]['data']['historia_medica']), 'Historia migrada no quedo cifrada.');
        tp_encryption_test_assert(TP_Data_Encryption::is_encrypted($wpdb->updates[0]['data']['motivo_pilates']), 'Motivo migrado no quedo cifrado.');
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
