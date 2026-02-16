<?php
/**
 * REST Register Endpoint
 *
 * Handles user registration via POST /wp-json/ct-auth/v1/register.
 * Creates a subscriber account with ct_account_active = 0,
 * generates a 6-digit activation code, and sends it via email.
 * Rate limited: 3 registrations per IP per hour.
 *
 * @package CTCustom\RestApi\Endpoints
 */

namespace CTCustom\RestApi\Endpoints;

use CTCustom\RestApi\RateLimiter;
use CTCustom\RestApi\RestLogger;
use CTCustom\RestApi\CodeGenerator;
use CTCustom\RestApi\PasswordValidator;
use CTCustom\Services\MailService;
use CTCustom\Services\EmailTemplate;

class Register {

    use RateLimiter;
    use RestLogger;
    use CodeGenerator;
    use PasswordValidator;

    const NAMESPACE           = 'ct-auth/v1';
    const ROUTE               = '/register';
    const MAX_ATTEMPTS        = 3;
    const WINDOW_SEC          = 3600;
    const MIN_USERNAME        = 4;
    const MAX_USERNAME_SPECIAL = 2;
    const ACTIVATION_PREFIX   = 'ct_activation_code_';
    const ACTIVATION_TTL      = 1800; /* 30 minutes */

    /**
     * Register the register route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );
        assert( function_exists( 'wp_insert_user' ), 'wp_insert_user must exist' );

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
            'username' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_user',
            ),
            'email' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ),
            'password' => array(
                'required' => true,
                'type'     => 'string',
            ),
            'password_confirm' => array(
                'required' => true,
                'type'     => 'string',
            ),
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
        );
    }

    /**
     * Handle the registration request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );
        assert( is_string( $request->get_param( 'username' ) ), 'username must be string' );

        $ip = $this->get_client_ip();

        if ( $this->is_rate_limited_by_ip( 'ct_register_attempts_', $ip, self::MAX_ATTEMPTS ) ) {
            $this->log( 'Rate limited: IP=' . $ip );
            $remaining = $this->get_rate_limit_remaining( 'ct_register_attempts_', $ip );
            $wait_text = $this->format_wait_time( $remaining );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: wait time */
                    __( 'Too many registration attempts. Please try again in %s.', 'ct-custom' ),
                    $wait_text
                ),
            ), 429 );
        }

        $username         = $request->get_param( 'username' );
        $email            = $request->get_param( 'email' );
        $password         = $request->get_param( 'password' );
        $password_confirm = $request->get_param( 'password_confirm' );
        $first_name       = $request->get_param( 'first_name' );
        $last_name        = $request->get_param( 'last_name' );

        $validation = $this->validate( $username, $email, $password, $password_confirm, $first_name, $last_name );

        if ( is_wp_error( $validation ) ) {
            $this->log( 'Validation failed: ' . $validation->get_error_message() );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => $validation->get_error_message(),
            ), 400 );
        }

        $user_id = wp_insert_user( array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'role'       => 'subscriber',
        ) );

        if ( is_wp_error( $user_id ) ) {
            $this->log( 'Server error: wp_insert_user failed, username=' . $username );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Registration failed. Please try again.', 'ct-custom' ),
            ), 500 );
        }

        $this->increment_rate_limit( 'ct_register_attempts_', $ip, self::WINDOW_SEC );

        /* Mark account as inactive */
        update_user_meta( $user_id, 'ct_account_active', '0' );

        /* Generate and store activation code */
        $code = $this->generate_code();
        $this->store_code( self::ACTIVATION_PREFIX, $email, $code, self::ACTIVATION_TTL );

        /* Send activation email */
        $mail_service = new MailService();
        $template     = new EmailTemplate();
        $mail_service->send( $email, __( 'Activate Your Account', 'ct-custom' ), $template->activation_code( $code ) );

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Account created! Please check your email for the activation code.', 'ct-custom' ),
            'data'    => array(
                'email' => $email,
            ),
        ), 200 );
    }

    /**
     * Validate registration inputs.
     *
     * @param string $username         Username.
     * @param string $email            Email.
     * @param string $password         Password.
     * @param string $password_confirm Confirmation.
     * @param string $first_name       First name.
     * @param string $last_name        Last name.
     * @return true|\WP_Error
     */
    private function validate( $username, $email, $password, $password_confirm, $first_name, $last_name ) {
        assert( is_string( $username ), 'Username must be a string' );
        assert( is_string( $email ), 'Email must be a string' );

        /* First name required */
        if ( empty( trim( $first_name ) ) ) {
            return new \WP_Error( 'invalid_first_name', __( 'First name is required.', 'ct-custom' ) );
        }

        /* Last name required */
        if ( empty( trim( $last_name ) ) ) {
            return new \WP_Error( 'invalid_last_name', __( 'Last name is required.', 'ct-custom' ) );
        }

        /* Username min length */
        if ( strlen( $username ) < self::MIN_USERNAME ) {
            return new \WP_Error( 'invalid_username', __( 'Username must be at least 4 characters.', 'ct-custom' ) );
        }

        /* Username allowed characters: alphanumeric, hyphen, underscore, dot */
        if ( ! preg_match( '/^[a-zA-Z0-9._-]+$/', $username ) ) {
            return new \WP_Error( 'invalid_username', __( 'Username may only contain letters, numbers, hyphens, underscores, and dots.', 'ct-custom' ) );
        }

        /* Username max 2 special characters (-, _, .) */
        $special_count = preg_match_all( '/[._-]/', $username );
        if ( $special_count > self::MAX_USERNAME_SPECIAL ) {
            return new \WP_Error( 'invalid_username', __( 'Username may contain at most 2 special characters (-, _, .).', 'ct-custom' ) );
        }

        /* Email valid format */
        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'ct-custom' ) );
        }

        /* Password strength (min 8, lowercase, uppercase, digit, special) */
        $pw_check = $this->validate_password_strength( $password );
        if ( is_wp_error( $pw_check ) ) {
            return $pw_check;
        }

        if ( $password !== $password_confirm ) {
            return new \WP_Error( 'password_mismatch', __( 'Passwords do not match.', 'ct-custom' ) );
        }

        if ( username_exists( $username ) ) {
            return new \WP_Error( 'username_taken', __( 'That username is already taken.', 'ct-custom' ) );
        }

        if ( email_exists( $email ) ) {
            return new \WP_Error( 'email_taken', __( 'That email is already registered.', 'ct-custom' ) );
        }

        return true;
    }
}
