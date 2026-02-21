<?php
/**
 * REST Reset Password Endpoint
 *
 * Resets a user's password using a reset JWT token.
 * POST /wp-json/ct-auth/v1/reset-password
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RestLogger;
use BSCustom\RestApi\PasswordValidator;
use BSCustom\Services\JwtService;
use BSCustom\Services\MailService;
use BSCustom\Services\EmailTemplate;

class ResetPassword {

    use RestLogger;
    use PasswordValidator;

    const NAMESPACE    = 'ct-auth/v1';
    const ROUTE        = '/reset-password';

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
                'reset_token' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
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
     * Handle the password reset request.
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

        $reset_token       = $request->get_param( 'reset_token' );
        $new_password      = $request->get_param( 'new_password' );
        $password_confirm  = $request->get_param( 'new_password_confirm' );

        /* Verify reset token */
        $jwt_service = new JwtService();
        $decoded     = $jwt_service->verify( $reset_token );

        if ( false === $decoded ) {
            $this->log( 'Auth failed: invalid or expired reset token' );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid or expired reset token. Please request a new code.', 'ct-custom' ),
            ), 401 );
        }

        if ( ! isset( $decoded->purpose ) || 'password_reset' !== $decoded->purpose ) {
            $this->log( 'Auth failed: wrong token purpose' );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid token type.', 'ct-custom' ),
            ), 401 );
        }

        if ( ! isset( $decoded->email ) ) {
            $this->log( 'Auth failed: token missing email claim' );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid token data.', 'ct-custom' ),
            ), 401 );
        }

        /* Validate passwords */
        $pw_check = $this->validate_password_strength( $new_password );
        if ( is_wp_error( $pw_check ) ) {
            $this->log( 'Validation failed: ' . $pw_check->get_error_message() );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => $pw_check->get_error_message(),
            ), 400 );
        }

        if ( $new_password !== $password_confirm ) {
            $this->log( 'Validation failed: passwords do not match' );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Passwords do not match.', 'ct-custom' ),
            ), 400 );
        }

        $email = sanitize_email( $decoded->email );
        $user  = get_user_by( 'email', $email );

        if ( ! $user ) {
            $this->log( 'Not found: user with email=' . $email );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'User not found.', 'ct-custom' ),
            ), 404 );
        }

        /* Reject reuse of current password */
        if ( wp_check_password( $new_password, $user->user_pass, $user->ID ) ) {
            $this->log( 'Validation failed: new password same as current for user=' . $user->ID );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'You are using the password that you already have. Please choose a different password.', 'ct-custom' ),
            ), 400 );
        }

        /* Update password */
        wp_set_password( $new_password, $user->ID );

        /* Send confirmation email */
        $mail_service = new MailService();
        $template     = new EmailTemplate();
        $mail_service->send( $email, __( 'Password Reset Successful', 'ct-custom' ), $template->password_reset_success() );

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Your password has been reset. You can now log in.', 'ct-custom' ),
        ), 200 );
    }
}
