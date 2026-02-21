<?php
/**
 * Tests for the ResendActivation REST API endpoint.
 *
 * Verifies that new activation codes are generated for inactive users,
 * that active users and unknown emails are handled without enumeration leaks,
 * and that rate limiting works via ResendActivation::handle().
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use BSCustom\RestApi\Endpoints\ResendActivation;

class ResendActivationEndpointTest extends AuthTestCase {

    private ResendActivation $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new ResendActivation();
    }

    /* ── Valid inactive email returns success ─────────────────────── */

    public function test_returns_success_for_valid_inactive_email(): void {
        $this->registerUser( 'alice', 'alice@example.com', 'Secret1234!', false );

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

    /* ── Active account does not generate a new code ─────────────── */

    public function test_does_not_resend_for_active_account(): void {
        $this->registerUser( 'bob', 'bob@example.com', 'Secret1234!', true );

        $request = $this->makeRequest( array( 'email' => 'bob@example.com' ) );
        $this->endpoint->handle( $request );

        $transient_key = 'bs_activation_code_' . md5( 'bob@example.com' );
        $stored_code   = \get_transient( $transient_key );

        $this->assertFalse( $stored_code );
    }

    /* ── Rate limited still returns success (enumeration prevention) */

    public function test_rate_limited_returns_success(): void {
        $email = 'limited@example.com';
        $this->registerUser( 'limited', $email, 'Secret1234!', false );

        /* Simulate 3 prior attempts */
        $key = 'bs_resend_activation_' . md5( $email );
        $this->setTransient( $key, 3, 3600 );

        $request  = $this->makeRequest( array( 'email' => $email ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );
    }

    /* ── New code stored for inactive user ───────────────────────── */

    public function test_stores_new_code(): void {
        $email = 'charlie@example.com';
        $this->registerUser( 'charlie', $email, 'Secret1234!', false );

        $request = $this->makeRequest( array( 'email' => $email ) );
        $this->endpoint->handle( $request );

        $transient_key = 'bs_activation_code_' . md5( $email );
        $stored_code   = \get_transient( $transient_key );

        $this->assertNotFalse( $stored_code );
        $this->assertSame( 6, strlen( $stored_code ) );
    }
}
