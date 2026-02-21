<?php
/**
 * Base test case for auth tests.
 *
 * Requires WP stubs, resets global state between tests,
 * and provides helpers for creating mock requests and test users.
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use PHPUnit\Framework\TestCase;

abstract class AuthTestCase extends TestCase {

    /** @var string Default JWT secret for tests. */
    protected const TEST_JWT_SECRET = 'test_secret_key_for_jwt_testing_1234';

    /** @var int Default JWT expiration hours. */
    protected const TEST_JWT_EXPIRY = 24;

    public static function setUpBeforeClass(): void {
        require_once dirname( __DIR__ ) . '/bootstrap-wp-stubs.php';
    }

    protected function setUp(): void {
        /* Reset all global state */
        $GLOBALS['bs_test_transients']    = array();
        $GLOBALS['bs_test_transient_ttl'] = array();
        $GLOBALS['bs_test_users']         = array();
        $GLOBALS['bs_test_user_meta']     = array();
        $GLOBALS['bs_test_options']       = array();
        $GLOBALS['bs_test_current_user']  = 0;
        $GLOBALS['bs_test_error_log']     = array();
        $GLOBALS['bs_test_next_user_id']  = 1;
        $GLOBALS['bs_test_server']        = array();
        $_SERVER['REMOTE_ADDR']           = '127.0.0.1';

        /* Set up default JWT config */
        $this->setJwtConfig( self::TEST_JWT_SECRET, self::TEST_JWT_EXPIRY );
    }

    /* ── Helpers ─────────────────────────────────────────────────── */

    /**
     * Create a WP_REST_Request with given params.
     *
     * @param array  $params  Request parameters.
     * @param string $method  HTTP method.
     * @param array  $headers Optional headers.
     * @return \WP_REST_Request
     */
    protected function makeRequest( array $params = array(), string $method = 'POST', array $headers = array() ): \WP_REST_Request {
        $request = new \WP_REST_Request( $method );

        $max = 50;
        $count = 0;
        foreach ( $params as $key => $value ) {
            if ( $count >= $max ) { break; }
            $count++;
            $request->set_param( $key, $value );
        }

        $count = 0;
        foreach ( $headers as $name => $value ) {
            if ( $count >= $max ) { break; }
            $count++;
            $request->set_header( $name, $value );
        }

        return $request;
    }

    /**
     * Register a test user in the global store.
     *
     * @param string $username  Username.
     * @param string $email     Email.
     * @param string $password  Plain-text password.
     * @param bool   $active    Whether account is active.
     * @param string $role      User role.
     * @return object User object.
     */
    protected function registerUser(
        string $username = 'testuser',
        string $email = 'test@example.com',
        string $password = 'Test1234!',
        bool $active = true,
        string $role = 'subscriber'
    ): object {
        $id = \wp_insert_user( array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => 'Test',
            'last_name'  => 'User',
            'role'       => $role,
        ) );

        \update_user_meta( $id, 'bs_account_active', $active ? '1' : '0' );

        return $GLOBALS['bs_test_users'][ $id ];
    }

    /**
     * Set the JWT configuration in options.
     *
     * @param string $secret          JWT secret key.
     * @param int    $expiration_hours Token lifetime in hours.
     */
    protected function setJwtConfig( string $secret, int $expiration_hours = 24 ): void {
        $GLOBALS['bs_test_options']['bs_custom_jwt_auth'] = json_encode( array(
            'secret'           => $secret,
            'expiration_hours' => $expiration_hours,
        ) );
    }

    /**
     * Set a transient value directly.
     *
     * @param string $key   Transient key.
     * @param mixed  $value Transient value.
     * @param int    $ttl   TTL in seconds.
     */
    protected function setTransient( string $key, $value, int $ttl = 3600 ): void {
        \set_transient( $key, $value, $ttl );
    }

    /**
     * Set the current user to the given user ID.
     *
     * @param int $user_id User ID.
     */
    protected function loginAs( int $user_id ): void {
        \wp_set_current_user( $user_id );
    }

    /**
     * Set the remote IP address.
     *
     * @param string $ip IP address.
     */
    protected function setClientIp( string $ip ): void {
        $_SERVER['REMOTE_ADDR'] = $ip;
    }

    /**
     * Issue a JWT token for a given user.
     *
     * @param int $user_id User ID.
     * @return string JWT token.
     */
    protected function issueToken( int $user_id ): string {
        $service = new \BSCustom\Services\JwtService();
        $token   = $service->issue( $user_id );
        assert( is_string( $token ), 'Token must be a string' );
        return $token;
    }

    /**
     * Create an authenticated request with a JWT Bearer token.
     *
     * @param int   $user_id User ID.
     * @param array $params  Request parameters.
     * @return \WP_REST_Request
     */
    protected function makeAuthenticatedRequest( int $user_id, array $params = array() ): \WP_REST_Request {
        $token = $this->issueToken( $user_id );
        return $this->makeRequest( $params, 'POST', array(
            'Authorization' => 'Bearer ' . $token,
        ) );
    }
}
