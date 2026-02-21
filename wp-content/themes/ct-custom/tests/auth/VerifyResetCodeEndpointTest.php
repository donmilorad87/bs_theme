<?php
/**
 * Tests for the VerifyResetCode REST endpoint.
 *
 * Covers: valid code, invalid code, expired code, rate limiting,
 * and code deletion after success.
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use BSCustom\RestApi\Endpoints\VerifyResetCode;

class VerifyResetCodeEndpointTest extends AuthTestCase {

    private VerifyResetCode $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new VerifyResetCode();
    }

    /* ── helpers ──────────────────────────────────────────────────── */

    /**
     * Store a reset code for the given email using the same transient
     * key format the endpoint expects (RESET_PREFIX + md5(email)).
     *
     * @param string $email Email address.
     * @param string $code  6-digit code.
     * @param int    $ttl   TTL in seconds.
     */
    private function storeResetCode( string $email, string $code, int $ttl = 900 ): void {
        $key = 'bs_reset_code_' . md5( $email );
        $this->setTransient( $key, $code, $ttl );
    }

    /**
     * Check whether a reset code transient still exists.
     *
     * @param string $email Email address.
     * @return bool
     */
    private function resetCodeExists( string $email ): bool {
        $key = 'bs_reset_code_' . md5( $email );
        return false !== \get_transient( $key );
    }

    /* ── tests ───────────────────────────────────────────────────── */

    public function test_valid_code_returns_reset_token(): void {
        $email = 'reset@example.com';
        $code  = '123456';

        $this->storeResetCode( $email, $code );

        $request  = $this->makeRequest( array(
            'email' => $email,
            'code'  => $code,
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertTrue( $data['success'] );
        $this->assertArrayHasKey( 'data', $data );
        $this->assertArrayHasKey( 'reset_token', $data['data'] );
        $this->assertNotEmpty( $data['data']['reset_token'] );
    }

    public function test_invalid_code_returns_400(): void {
        $email = 'reset@example.com';

        $this->storeResetCode( $email, '123456' );

        $request  = $this->makeRequest( array(
            'email' => $email,
            'code'  => '000000',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );

        $data = $response->get_data();
        $this->assertFalse( $data['success'] );
    }

    public function test_expired_code_returns_400(): void {
        $email = 'reset@example.com';
        $code  = '123456';

        /* Store with a TTL that has already elapsed. */
        $key = 'bs_reset_code_' . md5( $email );
        $GLOBALS['bs_test_transients'][ $key ]    = $code;
        $GLOBALS['bs_test_transient_ttl'][ $key ] = time() - 1;

        $request  = $this->makeRequest( array(
            'email' => $email,
            'code'  => $code,
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );

        $data = $response->get_data();
        $this->assertFalse( $data['success'] );
    }

    public function test_rate_limited_returns_429(): void {
        $ip = '10.0.0.1';
        $this->setClientIp( $ip );

        /* Simulate 5 prior failed attempts. */
        $rate_key = 'bs_verify_reset_' . md5( $ip );
        $this->setTransient( $rate_key, 5, 300 );

        $request  = $this->makeRequest( array(
            'email' => 'any@example.com',
            'code'  => '000000',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 429, $response->get_status() );

        $data = $response->get_data();
        $this->assertFalse( $data['success'] );
        $this->assertStringContainsString( 'Too many attempts', $data['message'] );
    }

    public function test_deletes_code_after_success(): void {
        $email = 'reset@example.com';
        $code  = '654321';

        $this->storeResetCode( $email, $code );
        $this->assertTrue( $this->resetCodeExists( $email ) );

        $request = $this->makeRequest( array(
            'email' => $email,
            'code'  => $code,
        ) );
        $this->endpoint->handle( $request );

        $this->assertFalse( $this->resetCodeExists( $email ) );
    }
}
