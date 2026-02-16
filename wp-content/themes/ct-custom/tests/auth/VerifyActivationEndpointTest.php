<?php
/**
 * Tests for the VerifyActivation REST API endpoint.
 *
 * Verifies that a valid activation code activates the user account,
 * that invalid/expired codes return 400, and that rate limiting is enforced
 * via VerifyActivation::handle().
 *
 * @package CTCustom\Tests\Auth
 */

namespace CTCustom\Tests\Auth;

use CTCustom\RestApi\Endpoints\VerifyActivation;

class VerifyActivationEndpointTest extends AuthTestCase {

    private VerifyActivation $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new VerifyActivation();
    }

    /* ── Valid code activates account ────────────────────────────── */

    public function test_valid_code_activates_account(): void {
        $email = 'alice@example.com';
        $code  = '123456';

        $this->registerUser( 'alice', $email, 'Secret1234!', false );

        /* Store the activation code */
        $transient_key = 'ct_activation_code_' . md5( $email );
        $this->setTransient( $transient_key, $code, 1800 );

        $request  = $this->makeRequest( array(
            'email' => $email,
            'code'  => $code,
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );

        /* Verify the meta was updated */
        $user   = \get_user_by( 'email', $email );
        $active = \get_user_meta( $user->ID, 'ct_account_active', true );
        $this->assertSame( '1', $active );
    }

    /* ── Invalid code returns 400 ────────────────────────────────── */

    public function test_invalid_code_returns_400(): void {
        $email = 'bob@example.com';
        $this->registerUser( 'bob', $email, 'Secret1234!', false );

        /* Store a different code */
        $transient_key = 'ct_activation_code_' . md5( $email );
        $this->setTransient( $transient_key, '999999', 1800 );

        $request  = $this->makeRequest( array(
            'email' => $email,
            'code'  => '000000',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Expired code returns 400 ────────────────────────────────── */

    public function test_expired_code_returns_400(): void {
        $email = 'charlie@example.com';
        $this->registerUser( 'charlie', $email, 'Secret1234!', false );

        /*
         * Do not store any transient. The code lookup will return false
         * because there is no stored code, simulating expiration.
         */

        $request  = $this->makeRequest( array(
            'email' => $email,
            'code'  => '123456',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── User not found returns 404 ──────────────────────────────── */

    public function test_user_not_found_returns_404(): void {
        $email = 'ghost@example.com';
        $code  = '654321';

        /* Store a valid code but no user exists with this email */
        $transient_key = 'ct_activation_code_' . md5( $email );
        $this->setTransient( $transient_key, $code, 1800 );

        $request  = $this->makeRequest( array(
            'email' => $email,
            'code'  => $code,
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 404, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Rate limited returns 429 ────────────────────────────────── */

    public function test_rate_limited_returns_429(): void {
        $ip = '10.0.0.10';
        $this->setClientIp( $ip );

        /* Simulate 5 prior attempts */
        $key = 'ct_verify_activation_' . md5( $ip );
        $this->setTransient( $key, 5, 300 );

        $request  = $this->makeRequest( array(
            'email' => 'any@example.com',
            'code'  => '000000',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 429, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Code deleted after successful activation ────────────────── */

    public function test_deletes_code_after_success(): void {
        $email = 'dave@example.com';
        $code  = '111222';

        $this->registerUser( 'dave', $email, 'Secret1234!', false );

        $transient_key = 'ct_activation_code_' . md5( $email );
        $this->setTransient( $transient_key, $code, 1800 );

        $request = $this->makeRequest( array(
            'email' => $email,
            'code'  => $code,
        ) );
        $this->endpoint->handle( $request );

        $remaining = \get_transient( $transient_key );
        $this->assertFalse( $remaining );
    }

    /* ── Success response contains correct message ───────────────── */

    public function test_activation_success_response(): void {
        $email = 'eve@example.com';
        $code  = '333444';

        $this->registerUser( 'eve', $email, 'Secret1234!', false );

        $transient_key = 'ct_activation_code_' . md5( $email );
        $this->setTransient( $transient_key, $code, 1800 );

        $request  = $this->makeRequest( array(
            'email' => $email,
            'code'  => $code,
        ) );
        $response = $this->endpoint->handle( $request );

        $data = $response->get_data();
        $this->assertTrue( $data['success'] );
        $this->assertStringContainsString( 'activated', $data['message'] );
    }
}
