<?php
/**
 * REST Logout Endpoint
 *
 * Handles user logout via POST /wp-json/ct-auth/v1/logout.
 * Requires the user to be logged in.
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RestLogger;

class Logout {

    use RestLogger;

    const NAMESPACE = 'ct-auth/v1';
    const ROUTE     = '/logout';

    /**
     * Register the logout route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );
        assert( function_exists( 'wp_logout' ), 'wp_logout must exist' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    /**
     * Only allow logged-in users to log out.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error True if the user is logged in.
     */
    public function check_permission( \WP_REST_Request $request ) {
        assert( function_exists( 'is_user_logged_in' ), 'is_user_logged_in must exist' );

        return bs_jwt_or_cookie_permission_check( $request );
    }

    /**
     * Handle the logout request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );
        assert( is_user_logged_in(), 'User must be logged in to log out' );

        wp_logout();

        return new \WP_REST_Response( array(
            'success' => true,
        ), 200 );
    }
}
