<?php
/**
 * Password Validator Trait
 *
 * Provides shared password strength validation for REST API endpoints.
 * Enforces: min 8 chars, lowercase, uppercase, digit, special character.
 *
 * @package CTCustom\RestApi
 */

namespace CTCustom\RestApi;

trait PasswordValidator {

    const PW_MIN_LENGTH = 8;

    /**
     * Validate password strength against all rules.
     *
     * @param string $password The password to validate.
     * @return true|\WP_Error True if valid, WP_Error otherwise.
     */
    private function validate_password_strength( $password ) {
        assert( is_string( $password ), 'Password must be a string' );

        if ( strlen( $password ) < self::PW_MIN_LENGTH ) {
            return new \WP_Error( 'weak_password', __( 'Password must be at least 8 characters.', 'ct-custom' ) );
        }

        if ( ! preg_match( '/[a-z]/', $password ) ) {
            return new \WP_Error( 'weak_password', __( 'Password must contain at least one lowercase letter.', 'ct-custom' ) );
        }

        if ( ! preg_match( '/[A-Z]/', $password ) ) {
            return new \WP_Error( 'weak_password', __( 'Password must contain at least one uppercase letter.', 'ct-custom' ) );
        }

        if ( ! preg_match( '/\d/', $password ) ) {
            return new \WP_Error( 'weak_password', __( 'Password must contain at least one digit.', 'ct-custom' ) );
        }

        if ( ! preg_match( '/[^a-zA-Z0-9]/', $password ) ) {
            return new \WP_Error( 'weak_password', __( 'Password must contain at least one special character.', 'ct-custom' ) );
        }

        return true;
    }
}
