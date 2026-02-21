<?php
/**
 * Tests for JwtAuth permission callbacks.
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use BSCustom\Services\JwtAuth;

class JwtAuthTest extends AuthTestCase {

    /* ── jwt_permission_check ────────────────────────────────────── */

    public function test_valid_token_returns_true(): void {
        $user = $this->registerUser();
        $token = $this->issueToken( $user->ID );

        $request = $this->makeRequest( array(), 'POST', array(
            'Authorization' => 'Bearer ' . $token,
        ) );

        $result = JwtAuth::jwt_permission_check( $request );

        $this->assertTrue( $result );
        $this->assertSame( $user->ID, \get_current_user_id() );
    }

    public function test_missing_header_returns_error(): void {
        $request = $this->makeRequest();

        $result = JwtAuth::jwt_permission_check( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'bs_jwt_missing', $result->get_error_code() );
    }

    public function test_invalid_format_returns_error(): void {
        $request = $this->makeRequest( array(), 'POST', array(
            'Authorization' => 'Basic credentials',
        ) );

        $result = JwtAuth::jwt_permission_check( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'bs_jwt_invalid_format', $result->get_error_code() );
    }

    public function test_invalid_token_returns_error(): void {
        $request = $this->makeRequest( array(), 'POST', array(
            'Authorization' => 'Bearer invalid.token.here',
        ) );

        $result = JwtAuth::jwt_permission_check( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'bs_jwt_invalid', $result->get_error_code() );
    }

    public function test_token_without_user_id_returns_error(): void {
        /* Issue a reset token (no user_id claim) */
        $service = new \BSCustom\Services\JwtService();
        $token   = $service->issue_reset_token( 'test@test.com', 10 );

        $request = $this->makeRequest( array(), 'POST', array(
            'Authorization' => 'Bearer ' . $token,
        ) );

        $result = JwtAuth::jwt_permission_check( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'bs_jwt_no_user', $result->get_error_code() );
    }

    public function test_user_not_found_returns_error(): void {
        /* Issue token for user ID 999 that doesn't exist */
        $token = $this->issueToken( 999 );

        $request = $this->makeRequest( array(), 'POST', array(
            'Authorization' => 'Bearer ' . $token,
        ) );

        $result = JwtAuth::jwt_permission_check( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'bs_jwt_user_not_found', $result->get_error_code() );
    }

    /* ── jwt_or_cookie_permission_check ──────────────────────────── */

    public function test_cookie_fallback_when_logged_in(): void {
        $user = $this->registerUser();
        $this->loginAs( $user->ID );

        $request = $this->makeRequest(); /* No Authorization header */

        $result = JwtAuth::jwt_or_cookie_permission_check( $request );

        $this->assertTrue( $result );
    }

    public function test_no_auth_returns_error(): void {
        /* Not logged in, no header */
        $request = $this->makeRequest();

        $result = JwtAuth::jwt_or_cookie_permission_check( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'bs_auth_required', $result->get_error_code() );
    }
}
