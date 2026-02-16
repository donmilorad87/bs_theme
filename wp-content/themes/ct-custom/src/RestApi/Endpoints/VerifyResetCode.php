<?php
/**
 * REST Verify Reset Code Endpoint
 *
 * Verifies a 6-digit reset code and returns a short-lived JWT reset token.
 * POST /wp-json/ct-auth/v1/verify-reset-code
 *
 * @package CTCustom\RestApi\Endpoints
 */

namespace CTCustom\RestApi\Endpoints;

use CTCustom\RestApi\RateLimiter;
use CTCustom\RestApi\RestLogger;
use CTCustom\RestApi\CodeGenerator;
use CTCustom\Services\JwtService;

class VerifyResetCode {

    use RateLimiter;
    use RestLogger;
    use CodeGenerator;

    const NAMESPACE    = 'ct-auth/v1';
    const ROUTE        = '/verify-reset-code';
    const RESET_PREFIX = 'ct_reset_code_';
    const MAX_ATTEMPTS = 5;
    const WINDOW_SEC   = 300;
    const TOKEN_TTL    = 10; /* minutes */

    /**
     * Register the route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'email' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ),
                'code' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Handle the verification request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $ip = $this->get_client_ip();

        if ( $this->is_rate_limited_by_ip( 'ct_verify_reset_', $ip, self::MAX_ATTEMPTS ) ) {
            $this->log( 'Rate limited: IP=' . $ip );
            $remaining = $this->get_rate_limit_remaining( 'ct_verify_reset_', $ip );
            $wait_text = $this->format_wait_time( $remaining );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: wait time */
                    __( 'Too many attempts. Please try again in %s.', 'ct-custom' ),
                    $wait_text
                ),
            ), 429 );
        }

        $email = $request->get_param( 'email' );
        $code  = $request->get_param( 'code' );

        if ( ! $this->verify_code( self::RESET_PREFIX, $email, $code ) ) {
            $this->log( 'Validation failed: invalid or expired reset code, email=' . $email );
            $this->increment_rate_limit( 'ct_verify_reset_', $ip, self::WINDOW_SEC );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid or expired reset code.', 'ct-custom' ),
            ), 400 );
        }

        /* Delete the used code */
        $this->delete_code( self::RESET_PREFIX, $email );

        /* Issue a short-lived reset token */
        $jwt_service = new JwtService();
        $reset_token = $jwt_service->issue_reset_token( $email, self::TOKEN_TTL );

        if ( false === $reset_token ) {
            $this->log( 'Server error: JWT issue_reset_token failed, email=' . $email );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Server configuration error. Please contact support.', 'ct-custom' ),
            ), 500 );
        }

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Code verified. Please set your new password.', 'ct-custom' ),
            'data'    => array(
                'reset_token' => $reset_token,
            ),
        ), 200 );
    }
}
