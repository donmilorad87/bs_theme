<?php
/**
 * Tests for the ForgotPassword REST API endpoint.
 *
 * Verifies that password reset requests always return success (to prevent
 * email enumeration), that codes are stored for valid users, and that
 * rate limiting works correctly via ForgotPassword::handle().
 *
 * @package CTCustom\Tests\Auth
 */

namespace CTCustom\Tests\Auth;

use CTCustom\RestApi\Endpoints\ForgotPassword;

class ForgotPasswordEndpointTest extends AuthTestCase {

    private ForgotPassword $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new ForgotPassword();
    }

    /* ── Valid email returns success ──────────────────────────────── */

    public function test_returns_success_for_valid_email(): void {
        $this->registerUser( 'alice', 'alice@example.com', 'Secret1234!' );

        $request  = $this->makeRequest( array( 'email' => 'alice@example.com' ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );
    }

    /* ── Unknown email still returns success (enumeration prevention) */

    public function test_returns_success_for_unknown_email(): void {
        $request  = $this->makeRequest( array( 'email' => 'nobody@example.com' ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );
    }

    /* ── Rate limited still returns 200 (enumeration prevention) ─── */

    public function test_rate_limited_returns_success(): void {
        $email = 'limited@example.com';
        $this->registerUser( 'limited', $email, 'Secret1234!' );

        /* Simulate 3 prior attempts */
        $key = 'ct_forgot_attempts_' . md5( $email );
        $this->setTransient( $key, 3, 3600 );

        $request  = $this->makeRequest( array( 'email' => $email ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );
    }

    /* ── Reset code stored for valid email ───────────────────────── */

    public function test_stores_reset_code(): void {
        $this->registerUser( 'alice', 'alice@example.com', 'Secret1234!' );

        $request = $this->makeRequest( array( 'email' => 'alice@example.com' ) );
        $this->endpoint->handle( $request );

        $transient_key = 'ct_reset_code_' . md5( 'alice@example.com' );
        $stored_code   = \get_transient( $transient_key );

        $this->assertNotFalse( $stored_code );
        $this->assertSame( 6, strlen( $stored_code ) );
    }

    /* ── No code stored for unknown email ────────────────────────── */

    public function test_does_not_store_code_for_unknown_email(): void {
        $request = $this->makeRequest( array( 'email' => 'nobody@example.com' ) );
        $this->endpoint->handle( $request );

        $transient_key = 'ct_reset_code_' . md5( 'nobody@example.com' );
        $stored_code   = \get_transient( $transient_key );

        $this->assertFalse( $stored_code );
    }

    /* ── Rate limit counter increments for valid email ───────────── */

    public function test_rate_limit_increments(): void {
        $this->registerUser( 'alice', 'alice@example.com', 'Secret1234!' );

        $request = $this->makeRequest( array( 'email' => 'alice@example.com' ) );
        $this->endpoint->handle( $request );

        $key      = 'ct_forgot_attempts_' . md5( 'alice@example.com' );
        $attempts = (int) \get_transient( $key );

        $this->assertSame( 1, $attempts );
    }
}
