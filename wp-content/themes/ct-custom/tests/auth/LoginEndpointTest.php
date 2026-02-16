<?php
/**
 * Tests for the Login REST API endpoint.
 *
 * Verifies authentication logic, rate limiting, inactive account handling,
 * and JWT token issuance via the Login::handle() method.
 *
 * @package CTCustom\Tests\Auth
 */

namespace CTCustom\Tests\Auth;

use CTCustom\RestApi\Endpoints\Login;

class LoginEndpointTest extends AuthTestCase {

    private Login $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new Login();
    }

    /* ── Successful login ────────────────────────────────────────── */

    public function test_successful_login(): void {
        $this->registerUser( 'alice', 'alice@example.com', 'Secret1234!' );

        $request  = $this->makeRequest( array(
            'username_or_email' => 'alice',
            'password'          => 'Secret1234!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertTrue( $data['success'] );
        $this->assertNotEmpty( $data['data']['token'] );
    }

    /* ── Invalid password ────────────────────────────────────────── */

    public function test_invalid_password(): void {
        $this->registerUser( 'alice', 'alice@example.com', 'Secret1234!' );

        $request  = $this->makeRequest( array(
            'username_or_email' => 'alice',
            'password'          => 'WrongPassword9!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 401, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Unknown user ────────────────────────────────────────────── */

    public function test_unknown_user(): void {
        $request  = $this->makeRequest( array(
            'username_or_email' => 'nobody',
            'password'          => 'Secret1234!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 401, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Inactive account returns 403 ────────────────────────────── */

    public function test_inactive_account_returns_403(): void {
        $this->registerUser( 'bob', 'bob@example.com', 'Secret1234!', false );

        $request  = $this->makeRequest( array(
            'username_or_email' => 'bob',
            'password'          => 'Secret1234!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 403, $response->get_status() );

        $data = $response->get_data();
        $this->assertFalse( $data['success'] );
        $this->assertTrue( $data['data']['requires_activation'] );
    }

    /* ── Rate limited returns 429 ────────────────────────────────── */

    public function test_rate_limited_returns_429(): void {
        $ip = '10.0.0.1';
        $this->setClientIp( $ip );

        /* Simulate 5 prior failed attempts */
        $key = 'ct_login_attempts_' . md5( $ip );
        $this->setTransient( $key, 5, 300 );

        $request  = $this->makeRequest( array(
            'username_or_email' => 'anyone',
            'password'          => 'anything',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 429, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Rate limit counter increments on failure ────────────────── */

    public function test_rate_limit_increments_on_failure(): void {
        $ip = '10.0.0.2';
        $this->setClientIp( $ip );

        $request  = $this->makeRequest( array(
            'username_or_email' => 'nobody',
            'password'          => 'wrong',
        ) );
        $this->endpoint->handle( $request );

        $key      = 'ct_login_attempts_' . md5( $ip );
        $attempts = (int) \get_transient( $key );

        $this->assertSame( 1, $attempts );
    }

    /* ── Login with email instead of username ────────────────────── */

    public function test_login_with_email(): void {
        $this->registerUser( 'charlie', 'charlie@example.com', 'Secret1234!' );

        $request  = $this->makeRequest( array(
            'username_or_email' => 'charlie@example.com',
            'password'          => 'Secret1234!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );
    }

    /* ── Empty username returns 401 ──────────────────────────────── */

    public function test_empty_username_returns_401(): void {
        $request  = $this->makeRequest( array(
            'username_or_email' => '',
            'password'          => 'Secret1234!',
        ) );

        /*
         * resolve_username asserts strlen > 0, but with assertions disabled
         * the method would return the empty string, which wp_signon won't find.
         * Either way the endpoint returns 401.
         */
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 401, $response->get_status() );
    }

    /* ── Response contains nonce on success ──────────────────────── */

    public function test_response_contains_nonce(): void {
        $this->registerUser( 'dave', 'dave@example.com', 'Secret1234!' );

        $request  = $this->makeRequest( array(
            'username_or_email' => 'dave',
            'password'          => 'Secret1234!',
        ) );
        $response = $this->endpoint->handle( $request );
        $data     = $response->get_data();

        $this->assertArrayHasKey( 'nonce', $data['data'] );
        $this->assertNotEmpty( $data['data']['nonce'] );
    }

    /* ── Response contains display_name on success ───────────────── */

    public function test_response_contains_display_name(): void {
        $this->registerUser( 'eve', 'eve@example.com', 'Secret1234!' );

        $request  = $this->makeRequest( array(
            'username_or_email' => 'eve',
            'password'          => 'Secret1234!',
        ) );
        $response = $this->endpoint->handle( $request );
        $data     = $response->get_data();

        $this->assertArrayHasKey( 'display_name', $data['data'] );
        $this->assertNotEmpty( $data['data']['display_name'] );
    }
}
