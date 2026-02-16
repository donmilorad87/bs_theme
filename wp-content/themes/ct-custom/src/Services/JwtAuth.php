<?php

namespace BSCustom\Services;

/**
 * JWT Authentication Helpers
 *
 * Static methods extracted from the standalone functions
 * ct_jwt_permission_check() and ct_jwt_or_cookie_permission_check().
 * These are REST API permission callbacks for JWT-protected endpoints.
 */
class JwtAuth {

    /**
     * REST API permission callback that accepts JWT or cookie auth.
     *
     * Tries JWT Bearer token first; falls back to WordPress cookie login.
     * Use this for endpoints where both auth methods are acceptable (e.g. profile).
     *
     * @param \WP_REST_Request $request The REST request.
     * @return bool|\WP_Error True if valid, WP_Error otherwise.
     */
    public static function jwt_or_cookie_permission_check( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Must receive WP_REST_Request' );

        /* Try JWT first if Authorization header is present */
        $auth_header = $request->get_header( 'Authorization' );
        if ( ! empty( $auth_header ) && 0 === strpos( $auth_header, 'Bearer ' ) ) {
            return self::jwt_permission_check( $request );
        }

        /* Fallback: WordPress cookie-based login */
        if ( is_user_logged_in() ) {
            return true;
        }

        return new \WP_Error(
            'ct_auth_required',
            __( 'Authentication is required.', 'ct-custom' ),
            array( 'status' => 401 )
        );
    }

    /**
     * REST API permission callback for JWT-protected endpoints.
     *
     * Extracts the Bearer token from the Authorization header,
     * verifies it, and sets the current user.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return bool|\WP_Error True if valid, WP_Error otherwise.
     */
    public static function jwt_permission_check( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Must receive WP_REST_Request' );

        $auth_header = $request->get_header( 'Authorization' );

        if ( empty( $auth_header ) ) {
            return new \WP_Error(
                'ct_jwt_missing',
                __( 'Authorization header is required.', 'ct-custom' ),
                array( 'status' => 401 )
            );
        }

        assert( is_string( $auth_header ), 'Auth header must be a string' );

        if ( 0 !== strpos( $auth_header, 'Bearer ' ) ) {
            return new \WP_Error(
                'ct_jwt_invalid_format',
                __( 'Authorization header must use Bearer scheme.', 'ct-custom' ),
                array( 'status' => 401 )
            );
        }

        $token   = substr( $auth_header, 7 );
        $service = new JwtService();
        $decoded = $service->verify( $token );

        if ( false === $decoded ) {
            return new \WP_Error(
                'ct_jwt_invalid',
                __( 'Invalid or expired token.', 'ct-custom' ),
                array( 'status' => 401 )
            );
        }

        if ( ! isset( $decoded->user_id ) ) {
            return new \WP_Error(
                'ct_jwt_no_user',
                __( 'Token does not contain user information.', 'ct-custom' ),
                array( 'status' => 401 )
            );
        }

        $user_id = (int) $decoded->user_id;
        $user    = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new \WP_Error(
                'ct_jwt_user_not_found',
                __( 'User not found.', 'ct-custom' ),
                array( 'status' => 401 )
            );
        }

        wp_set_current_user( $user_id );

        return true;
    }
}
