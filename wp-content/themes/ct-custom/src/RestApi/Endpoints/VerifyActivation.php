<?php
/**
 * REST Verify Activation Endpoint
 *
 * Verifies a 6-digit activation code and activates the user account.
 * POST /wp-json/ct-auth/v1/verify-activation
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RateLimiter;
use BSCustom\RestApi\RestLogger;
use BSCustom\RestApi\CodeGenerator;
use BSCustom\Services\MailService;
use BSCustom\Services\EmailTemplate;

class VerifyActivation {

    use RateLimiter;
    use RestLogger;
    use CodeGenerator;

    const NAMESPACE         = 'ct-auth/v1';
    const ROUTE             = '/verify-activation';
    const ACTIVATION_PREFIX = 'bs_activation_code_';
    const MAX_ATTEMPTS      = 5;
    const WINDOW_SEC        = 300;

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

        if ( function_exists( 'bs_email_enabled' ) && ! bs_email_enabled() ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Email features are disabled.', 'ct-custom' ),
            ), 403 );
        }

        $ip = $this->get_client_ip();

        if ( $this->is_rate_limited_by_ip( 'bs_verify_activation_', $ip, self::MAX_ATTEMPTS ) ) {
            $this->log( 'Rate limited: IP=' . $ip );
            $remaining = $this->get_rate_limit_remaining( 'bs_verify_activation_', $ip );
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

        if ( ! $this->verify_code( self::ACTIVATION_PREFIX, $email, $code ) ) {
            $this->log( 'Validation failed: invalid or expired activation code, email=' . $email );
            $this->increment_rate_limit( 'bs_verify_activation_', $ip, self::WINDOW_SEC );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid or expired activation code.', 'ct-custom' ),
            ), 400 );
        }

        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            $this->log( 'Not found: user with email=' . $email );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'User not found.', 'ct-custom' ),
            ), 404 );
        }

        /* Activate the account */
        update_user_meta( $user->ID, 'bs_account_active', '1' );
        $this->delete_code( self::ACTIVATION_PREFIX, $email );

        /* Send success email */
        $mail_service = new MailService();
        $template     = new EmailTemplate();
        $mail_service->send( $email, __( 'Account Activated', 'ct-custom' ), $template->activation_success() );

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Your account has been activated! You can now log in.', 'ct-custom' ),
        ), 200 );
    }
}
