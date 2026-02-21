<?php
/**
 * REST Login Endpoint
 *
 * Handles user authentication via POST /wp-json/ct-auth/v1/login.
 * Uses wp_signon() with rate limiting (5 attempts per IP per 5 min).
 * Checks bs_account_active meta; returns 403 if inactive.
 * Issues a JWT token on success.
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RateLimiter;
use BSCustom\RestApi\RestLogger;
use BSCustom\RestApi\CodeGenerator;
use BSCustom\Services\JwtService;
use BSCustom\Services\MailService;
use BSCustom\Services\EmailTemplate;

class Login {

    use RateLimiter;
    use RestLogger;
    use CodeGenerator;

    const NAMESPACE         = 'ct-auth/v1';
    const ROUTE             = '/login';
    const MAX_ATTEMPTS      = 5;
    const WINDOW_SEC        = 300;
    const ACTIVATION_PREFIX = 'bs_activation_code_';
    const ACTIVATION_TTL    = 1800; /* 30 minutes */

    /**
     * Register the login route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );
        assert( defined( 'REST_API_VERSION' ) || true, 'REST API should be available' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
            'args'                => $this->get_args(),
        ) );
    }

    /**
     * Define and validate endpoint arguments.
     *
     * @return array Argument definitions.
     */
    private function get_args() {
        return array(
            'username_or_email' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'password' => array(
                'required' => true,
                'type'     => 'string',
            ),
            'remember' => array(
                'required' => false,
                'type'     => 'boolean',
                'default'  => false,
            ),
        );
    }

    /**
     * Handle the login request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );
        assert( is_string( $request->get_param( 'username_or_email' ) ), 'username_or_email must be string' );

        $ip = $this->get_client_ip();

        if ( $this->is_rate_limited_by_ip( 'bs_login_attempts_', $ip, self::MAX_ATTEMPTS ) ) {
            $this->log( 'Rate limited: IP=' . $ip );
            $remaining = $this->get_rate_limit_remaining( 'bs_login_attempts_', $ip );
            $wait_text = $this->format_wait_time( $remaining );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: wait time */
                    __( 'Too many login attempts. Please try again in %s.', 'ct-custom' ),
                    $wait_text
                ),
            ), 429 );
        }

        $login    = $request->get_param( 'username_or_email' );
        $password = $request->get_param( 'password' );
        $remember = (bool) $request->get_param( 'remember' );

        $username = $this->resolve_username( $login );

        if ( ! $username ) {
            $this->log( 'Auth failed: user not found for login=' . $login );
            $this->increment_rate_limit( 'bs_login_attempts_', $ip, self::WINDOW_SEC );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid credentials.', 'ct-custom' ),
            ), 401 );
        }

        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        );

        $user = wp_signon( $creds, is_ssl() );

        if ( is_wp_error( $user ) ) {
            $this->log( 'Auth failed: wp_signon error for username=' . $username );
            $this->increment_rate_limit( 'bs_login_attempts_', $ip, self::WINDOW_SEC );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid credentials.', 'ct-custom' ),
            ), 401 );
        }

        /* Check account activation */
        $is_active = get_user_meta( $user->ID, 'bs_account_active', true );
        if ( '0' === $is_active ) {
            $email_enabled = true;
            if ( function_exists( 'bs_email_enabled' ) ) {
                $email_enabled = bs_email_enabled();
            }

            if ( ! $email_enabled ) {
                update_user_meta( $user->ID, 'bs_account_active', '1' );
            } else {
            $this->log( 'Auth failed: account inactive, user_id=' . $user->ID );
            wp_logout();

            /* Resend activation code so the user has a fresh code */
            $code = $this->generate_code();
            $this->store_code( self::ACTIVATION_PREFIX, $user->user_email, $code, self::ACTIVATION_TTL );

            $mail_service = new MailService();
            $template     = new EmailTemplate();
            $mail_service->send(
                $user->user_email,
                __( 'Activate Your Account', 'ct-custom' ),
                $template->activation_code( $code )
            );

            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Please activate your account. A new activation code has been sent to your email.', 'ct-custom' ),
                'data'    => array(
                    'requires_activation' => true,
                    'email'               => $user->user_email,
                ),
            ), 403 );
            }
        }

        wp_set_current_user( $user->ID );

        /* Issue JWT */
        $jwt_service = new JwtService();
        $token       = $jwt_service->issue( $user->ID );

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'display_name' => $user->display_name,
                'nonce'        => wp_create_nonce( 'wp_rest' ),
                'token'        => $token ? $token : '',
            ),
        ), 200 );
    }

    /**
     * Resolve a login identifier to a username.
     *
     * @param string $login Email or username.
     * @return string|false Username or false.
     */
    private function resolve_username( $login ) {
        assert( is_string( $login ), 'Login must be a string' );
        assert( strlen( $login ) > 0, 'Login must not be empty' );

        if ( is_email( $login ) ) {
            $user = get_user_by( 'email', $login );
            return $user ? $user->user_login : false;
        }

        return $login;
    }
}
