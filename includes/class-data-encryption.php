<?php
/**
 * Application-level encryption for sensitive plugin data.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encrypts and decrypts sensitive values with a server-held key.
 */
class TP_Data_Encryption {

    const KEY_CONSTANT = 'TP_DATA_ENCRYPTION_KEY';
    const PREFIX       = 'tpenc:v1:';
    const NONCE_BYTES  = 24;
    const KEY_BYTES    = 32;

    /**
     * Returns a normalized runtime status for diagnostics and guards.
     *
     * @return array<string,mixed>
     */
    public static function status() {
        $key = self::key();

        return array(
            'sodium_available' => self::sodium_available(),
            'key_defined'      => defined(self::KEY_CONSTANT) || false !== getenv(self::KEY_CONSTANT),
            'key_valid'        => is_string($key) && self::KEY_BYTES === strlen($key),
            'ready'            => self::sodium_available() && is_string($key) && self::KEY_BYTES === strlen($key),
        );
    }

    /**
     * Whether encryption/decryption can run in this environment.
     *
     * @return bool
     */
    public static function is_ready() {
        $status = self::status();

        return (bool) $status['ready'];
    }

    /**
     * Whether a stored value uses the plugin encrypted envelope.
     *
     * @param mixed $value Stored value.
     * @return bool
     */
    public static function is_encrypted($value) {
        return is_string($value) && 0 === strpos($value, self::PREFIX);
    }

    /**
     * Encrypts one plaintext value.
     *
     * Empty strings stay empty to keep forms and backups readable around blanks.
     *
     * @param string|null $plaintext Plain text.
     * @return string|WP_Error
     */
    public static function encrypt($plaintext) {
        $plaintext = null === $plaintext ? '' : (string) $plaintext;

        if ('' === $plaintext) {
            return $plaintext;
        }

        if (self::is_encrypted($plaintext)) {
            $verified = self::decrypt($plaintext);

            return is_wp_error($verified) ? $verified : $plaintext;
        }

        $key = self::key();

        if (!self::sodium_available() || !is_string($key)) {
            return new WP_Error('tp_encryption_unavailable', 'Configura TP_DATA_ENCRYPTION_KEY para guardar datos medicos.');
        }

        try {
            $nonce      = random_bytes(self::NONCE_BYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        } catch (Throwable $exception) {
            tp_log(
                'No se pudo cifrar un campo sensible.',
                array('message' => $exception->getMessage()),
                'error'
            );

            return new WP_Error('tp_encryption_failed', 'No se pudo cifrar el dato medico.');
        }

        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypts one stored value.
     *
     * Legacy plaintext is returned unchanged so the migration can be gradual.
     *
     * @param string|null $stored Stored value.
     * @return string|WP_Error
     */
    public static function decrypt($stored) {
        $stored = null === $stored ? '' : (string) $stored;

        if ('' === $stored || !self::is_encrypted($stored)) {
            return $stored;
        }

        $key = self::key();

        if (!self::sodium_available() || !is_string($key)) {
            return new WP_Error('tp_encryption_unavailable', 'Configura TP_DATA_ENCRYPTION_KEY para ver los datos medicos.');
        }

        $encoded = substr($stored, strlen(self::PREFIX));
        $payload = base64_decode($encoded, true);

        if (!is_string($payload) || strlen($payload) <= self::NONCE_BYTES) {
            return new WP_Error('tp_encryption_invalid_payload', 'El dato medico cifrado no tiene un formato valido.');
        }

        $nonce      = substr($payload, 0, self::NONCE_BYTES);
        $ciphertext = substr($payload, self::NONCE_BYTES);
        $plaintext  = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if (false === $plaintext) {
            return new WP_Error('tp_encryption_decrypt_failed', 'La clave configurada no puede descifrar los datos medicos.');
        }

        return $plaintext;
    }

    /**
     * Returns a human-readable setup instruction.
     *
     * @return string
     */
    public static function setup_hint() {
        return "define('TP_DATA_ENCRYPTION_KEY', 'clave_hex_de_64_caracteres');";
    }

    /**
     * Whether the Sodium API required by this class is available.
     *
     * @return bool
     */
    private static function sodium_available() {
        return function_exists('sodium_crypto_secretbox')
            && function_exists('sodium_crypto_secretbox_open');
    }

    /**
     * Reads and decodes the encryption key from server configuration.
     *
     * @return string|null
     */
    private static function key() {
        $raw = defined(self::KEY_CONSTANT) ? constant(self::KEY_CONSTANT) : getenv(self::KEY_CONSTANT);
        $raw = is_string($raw) ? trim($raw) : '';

        if ('' === $raw) {
            return null;
        }

        if (1 === preg_match('/^[a-f0-9]{64}$/i', $raw)) {
            $decoded = hex2bin($raw);

            return false === $decoded ? null : $decoded;
        }

        return null;
    }
}
