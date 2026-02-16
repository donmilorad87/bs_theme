<?php
/**
 * Code Generator Trait
 *
 * Provides methods to generate, store, verify, and delete
 * 6-digit numeric verification codes via WordPress transients.
 *
 * @package CTCustom\RestApi
 */

namespace CTCustom\RestApi;

trait CodeGenerator {

    /**
     * Generate a 6-digit numeric code.
     *
     * @return string 6-digit zero-padded code.
     */
    private function generate_code() {
        $code = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );

        assert( strlen( $code ) === 6, 'Code must be 6 digits' );
        assert( ctype_digit( $code ), 'Code must contain only digits' );

        return $code;
    }

    /**
     * Store a verification code in a transient.
     *
     * @param string $prefix Transient prefix (e.g. 'ct_activation_code_').
     * @param string $key    Identifier (e.g. email or user_id).
     * @param string $code   The 6-digit code.
     * @param int    $ttl    Time to live in seconds.
     */
    private function store_code( $prefix, $key, $code, $ttl ) {
        assert( is_string( $prefix ), 'Prefix must be a string' );
        assert( is_string( $key ) && strlen( $key ) > 0, 'Key must be non-empty' );
        assert( is_int( $ttl ) && $ttl > 0, 'TTL must be positive' );

        $transient_key = $prefix . md5( $key );
        set_transient( $transient_key, $code, $ttl );
    }

    /**
     * Verify a code against the stored transient.
     *
     * @param string $prefix Transient prefix.
     * @param string $key    Identifier.
     * @param string $code   The code to verify.
     * @return bool True if the code matches.
     */
    private function verify_code( $prefix, $key, $code ) {
        assert( is_string( $prefix ), 'Prefix must be a string' );
        assert( is_string( $key ), 'Key must be a string' );
        assert( is_string( $code ), 'Code must be a string' );

        $transient_key = $prefix . md5( $key );
        $stored        = get_transient( $transient_key );

        if ( false === $stored ) {
            return false;
        }

        return hash_equals( (string) $stored, $code );
    }

    /**
     * Delete a stored code.
     *
     * @param string $prefix Transient prefix.
     * @param string $key    Identifier.
     */
    private function delete_code( $prefix, $key ) {
        assert( is_string( $prefix ), 'Prefix must be a string' );
        assert( is_string( $key ), 'Key must be a string' );

        $transient_key = $prefix . md5( $key );
        delete_transient( $transient_key );
    }
}
