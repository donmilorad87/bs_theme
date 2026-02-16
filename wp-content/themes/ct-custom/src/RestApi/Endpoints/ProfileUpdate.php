<?php
/**
 * REST Profile Update Endpoint
 *
 * Updates user first_name and last_name via JWT-authenticated request.
 * POST /wp-json/ct-auth/v1/profile/update
 *
 * @package CTCustom\RestApi\Endpoints
 */

namespace CTCustom\RestApi\Endpoints;

use CTCustom\RestApi\RestLogger;

class ProfileUpdate {

    use RestLogger;

    const NAMESPACE = 'ct-auth/v1';
    const ROUTE     = '/profile/update';

    /**
     * Register the route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => 'ct_jwt_or_cookie_permission_check',
            'args'                => array(
                'first_name' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'last_name' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Handle the profile update request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $user_id    = get_current_user_id();
        $first_name = trim( $request->get_param( 'first_name' ) );
        $last_name  = trim( $request->get_param( 'last_name' ) );

        assert( $user_id > 0, 'User must be authenticated' );

        /* First name and last name are required */
        if ( empty( $first_name ) || empty( $last_name ) ) {
            $this->log( 'Validation failed: first or last name empty, user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'First name and last name are required.', 'ct-custom' ),
            ), 400 );
        }

        $result = wp_update_user( array(
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim( $first_name . ' ' . $last_name ),
        ) );

        if ( is_wp_error( $result ) ) {
            $this->log( 'Server error: wp_update_user failed, user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Failed to update profile.', 'ct-custom' ),
            ), 500 );
        }

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Profile updated successfully.', 'ct-custom' ),
            'data'    => array(
                'display_name' => trim( $first_name . ' ' . $last_name ),
            ),
        ), 200 );
    }
}
