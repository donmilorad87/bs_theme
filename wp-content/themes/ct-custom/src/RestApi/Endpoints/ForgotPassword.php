<?php
/**
 * REST Forgot Password Endpoint
 *
 * Handles password reset requests via POST /wp-json/ct-auth/v1/forgot-password.
 * Generates a 6-digit code and sends it via the CT Mail Service.
 * Always returns success to prevent email enumeration.
 * Rate limited: 3 attempts per email per hour.
 *
 * @package CTCustom\RestApi\Endpoints
 */

namespace CTCustom\RestApi\Endpoints;

use CTCustom\RestApi\RateLimiter;
use CTCustom\RestApi\RestLogger;
use CTCustom\RestApi\CodeGenerator;
use CTCustom\Services\MailService;
use CTCustom\Services\EmailTemplate;

class ForgotPassword {

    use RateLimiter;
    use RestLogger;
    use CodeGenerator;

    const NAMESPACE    = 'ct-auth/v1';
    const ROUTE        = '/forgot-password';
    const MAX_ATTEMPTS = 3;
    const WINDOW_SEC   = 3600;
    const RESET_PREFIX = 'ct_reset_code_';
    const RESET_TTL    = 900; /* 15 minutes */

    /**
     * Register the forgot-password route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
            'args'                => $this->get_args(),
        ) );
    }

    /**
     * Define endpoint arguments.
     *
     * @return array Argument definitions.
     */
    private function get_args() {
        return array(
            'email' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ),
        );
    }

    /**
     * Handle the forgot-password request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );
        assert( is_string( $request->get_param( 'email' ) ), 'email must be string' );

        $email   = $request->get_param( 'email' );
        $message = __( 'If that email is registered, a reset code has been sent.', 'ct-custom' );

        if ( $this->is_rate_limited_by_key( 'ct_forgot_attempts_', $email, self::MAX_ATTEMPTS ) ) {
            $this->log( 'Rate limited: email=' . $email );
            return new \WP_REST_Response( array(
                'success' => true,
                'message' => $message,
            ), 200 );
        }

        $user = get_user_by( 'email', $email );

        if ( $user ) {
            $this->increment_rate_limit( 'ct_forgot_attempts_', $email, self::WINDOW_SEC );

            $code = $this->generate_code();
            $this->store_code( self::RESET_PREFIX, $email, $code, self::RESET_TTL );

            $mail_service = new MailService();
            $template     = new EmailTemplate();
            $mail_service->send( $email, __( 'Password Reset Code', 'ct-custom' ), $template->forgot_password_code( $code ) );
        }

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => $message,
        ), 200 );
    }
}
