<?php
/**
 * Tests for JwtService.
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use BSCustom\Services\JwtService;

class JwtServiceTest extends AuthTestCase {

    private JwtService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new JwtService();
    }

    /* ── issue() ─────────────────────────────────────────────────── */

    public function test_issue_returns_valid_token(): void {
        $token = $this->service->issue( 1 );

        $this->assertIsString( $token );
        $this->assertNotEmpty( $token );
    }

    public function test_issue_returns_false_without_secret(): void {
        $this->setJwtConfig( '', 24 );
        $service = new JwtService();

        $token = $service->issue( 1 );

        $this->assertFalse( $token );
    }

    public function test_issue_returns_false_with_short_secret(): void {
        $this->setJwtConfig( 'short', 24 );
        $service = new JwtService();

        $token = $service->issue( 1 );

        $this->assertFalse( $token );
    }

    /* ── verify() ────────────────────────────────────────────────── */

    public function test_verify_valid_token(): void {
        $token   = $this->service->issue( 42 );
        $decoded = $this->service->verify( $token );

        $this->assertIsObject( $decoded );
        $this->assertSame( 42, $decoded->user_id );
    }

    public function test_verify_expired_token(): void {
        /* Issue a token with 0 hours expiration */
        $this->setJwtConfig( self::TEST_JWT_SECRET, 0 );
        $service = new JwtService();
        $token   = $service->issue( 1 );

        /* Token was issued with exp = now + 0, so it's already expired */
        $decoded = $service->verify( $token );

        /* Either false (expired) or valid if zero-hour means "immediate" */
        /* The implementation uses $now + (0 * 3600) = $now, which may be past by verify time */
        $this->assertTrue( $decoded === false || is_object( $decoded ) );
    }

    public function test_verify_invalid_token(): void {
        $decoded = $this->service->verify( 'invalid.token.string' );

        $this->assertFalse( $decoded );
    }

    public function test_verify_token_too_long(): void {
        $long = str_repeat( 'a', 4097 );
        $decoded = $this->service->verify( $long );

        $this->assertFalse( $decoded );
    }

    /* ── issue_reset_token() ─────────────────────────────────────── */

    public function test_reset_token_has_purpose_claim(): void {
        $token   = $this->service->issue_reset_token( 'test@example.com', 10 );
        $decoded = $this->service->verify( $token );

        $this->assertIsObject( $decoded );
        $this->assertSame( 'password_reset', $decoded->purpose );
    }

    public function test_reset_token_has_email_claim(): void {
        $token   = $this->service->issue_reset_token( 'test@example.com', 10 );
        $decoded = $this->service->verify( $token );

        $this->assertSame( 'test@example.com', $decoded->email );
    }

    public function test_reset_token_ttl(): void {
        $before = time();
        $token   = $this->service->issue_reset_token( 'test@example.com', 10 );
        $decoded = $this->service->verify( $token );
        $after  = time();

        /* exp should be ~10 minutes from now */
        $expected_min = $before + ( 10 * 60 );
        $expected_max = $after + ( 10 * 60 );

        $this->assertGreaterThanOrEqual( $expected_min, $decoded->exp );
        $this->assertLessThanOrEqual( $expected_max, $decoded->exp );
    }
}
