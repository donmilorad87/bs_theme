<?php
/**
 * REST Profile Change Password Endpoint
 *
 * Changes the current user's password via JWT-authenticated request.
 * Verifies the current password before updating.
 * POST /wp-json/ct-auth/v1/profile/change-password
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RestLogger;
use BSCustom\RestApi\PasswordValidator;
use BSCustom\Services\MailService;
use BSCustom\Services\EmailTemplate;

class ProfileChangePassword {

    use RestLogger;
    use PasswordValidator;

    const NAMESPACE    = 'ct-auth/v1';
    const ROUTE        = '/profile/change-password';

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
                'current_password' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
                'new_password' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
                'new_password_confirm' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
            ),
        ) );
    }

    /**
     * Handle the password change request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $user_id          = get_current_user_id();
        $current_password = $request->get_param( 'current_password' );
        $new_password     = $request->get_param( 'new_password' );
        $password_confirm = $request->get_param( 'new_password_confirm' );

        assert( $user_id > 0, 'User must be authenticated' );

        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            $this->log( 'Not found: user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'User not found.', 'ct-custom' ),
            ), 404 );
        }

        /* Verify current password */
        if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
            $this->log( 'Validation failed: current password incorrect, user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Current password is incorrect.', 'ct-custom' ),
            ), 400 );
        }

        /* New password must differ from current password */
        if ( $current_password === $new_password ) {
            $this->log( 'Validation failed: new password same as current, user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'New password must be different from your current password.', 'ct-custom' ),
            ), 400 );
        }

        /* Validate new password */
        $pw_check = $this->validate_password_strength( $new_password );
        if ( is_wp_error( $pw_check ) ) {
            $this->log( 'Validation failed: ' . $pw_check->get_error_message() . ', user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => $pw_check->get_error_message(),
            ), 400 );
        }

        if ( $new_password !== $password_confirm ) {
            $this->log( 'Validation failed: passwords do not match, user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Passwords do not match.', 'ct-custom' ),
            ), 400 );
        }

        /* Update password */
        wp_set_password( $new_password, $user_id );

        /* Send notification email */
        $mail_service = new MailService();
        $template     = new EmailTemplate();
        $mail_service->send( $user->user_email, __( 'Password Changed', 'ct-custom' ), $template->password_changed_from_profile() );

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Password changed successfully.', 'ct-custom' ),
        ), 200 );
    }
}
