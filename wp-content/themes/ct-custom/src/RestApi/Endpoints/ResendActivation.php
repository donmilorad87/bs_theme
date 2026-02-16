<?php
/**
 * REST Resend Activation Code Endpoint
 *
 * Generates a new 6-digit activation code and sends it via email.
 * POST /wp-json/ct-auth/v1/resend-activation
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

class ResendActivation {

    use RateLimiter;
    use RestLogger;
    use CodeGenerator;

    const NAMESPACE         = 'ct-auth/v1';
    const ROUTE             = '/resend-activation';
    const MAX_ATTEMPTS      = 3;
    const WINDOW_SEC        = 3600;
    const ACTIVATION_PREFIX = 'ct_activation_code_';
    const ACTIVATION_TTL    = 1800; /* 30 minutes */

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
            ),
        ) );
    }

    /**
     * Handle the resend request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $email   = $request->get_param( 'email' );
        $message = __( 'If that email is registered, a new activation code has been sent.', 'ct-custom' );

        if ( $this->is_rate_limited_by_key( 'ct_resend_activation_', $email, self::MAX_ATTEMPTS ) ) {
            $this->log( 'Rate limited: email=' . $email );
            return new \WP_REST_Response( array(
                'success' => true,
                'message' => $message,
            ), 200 );
        }

        $user = get_user_by( 'email', $email );

        if ( $user ) {
            $is_active = get_user_meta( $user->ID, 'ct_account_active', true );

            /* Only resend if the account is still inactive */
            if ( '0' === $is_active ) {
                $this->increment_rate_limit( 'ct_resend_activation_', $email, self::WINDOW_SEC );

                $code = $this->generate_code();
                $this->store_code( self::ACTIVATION_PREFIX, $email, $code, self::ACTIVATION_TTL );

                $mail_service = new MailService();
                $template     = new EmailTemplate();
                $mail_service->send(
                    $email,
                    __( 'Activate Your Account', 'ct-custom' ),
                    $template->activation_code( $code )
                );
            }
        }

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => $message,
        ), 200 );
    }
}
