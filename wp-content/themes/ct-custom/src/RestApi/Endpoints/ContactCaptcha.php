<?php
/**
 * REST Contact Captcha Endpoint
 *
 * Generates a 6-character captcha code and token.
 * GET /wp-json/ct-auth/v1/contact/captcha
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

class ContactCaptcha {

    const NAMESPACE = 'ct-auth/v1';
    const ROUTE     = '/contact/captcha';

    /**
     * Register the route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Handle captcha generation.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );
        $payload = self::create_challenge();

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => $payload,
        ), 200 );
    }

    /**
     * Create a captcha challenge and store the hash transient.
     *
     * @return array{token:string,code:string,expires_in:int}
     */
    public static function create_challenge(): array {
        $token = wp_generate_uuid4();
        $code  = self::generate_code( 6 );

        $hash = wp_hash_password( strtolower( $code ) );
        set_transient( 'bs_contact_captcha_' . $token, $hash, 10 * MINUTE_IN_SECONDS );

        return array(
            'token'      => $token,
            'code'       => $code,
            'expires_in' => 600,
        );
    }

    /**
     * Generate a random captcha code.
     *
     * @param int $length Length of the code.
     * @return string
     */
    private static function generate_code( int $length ): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $max      = strlen( $alphabet ) - 1;
        $code     = '';

        for ( $i = 0; $i < $length; $i++ ) {
            $code .= $alphabet[ wp_rand( 0, $max ) ];
        }

        return $code;
    }
}
