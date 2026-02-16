<?php
/**
 * Tests for the ResetPassword REST endpoint.
 *
 * Covers: successful reset, invalid/expired/wrong-purpose tokens,
 * missing email claim, password validation, mismatch, user not found,
 * same password reuse, and actual password change.
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use BSCustom\RestApi\Endpoints\ResetPassword;
use BSCustom\Services\JwtService;

class ResetPasswordEndpointTest extends AuthTestCase {

    private ResetPassword $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new ResetPassword();
    }

    /* ── helpers ──────────────────────────────────────────────────── */

    /**
     * Issue a valid password-reset JWT for the given email.
     *
     * @param string $email       User email.
     * @param int    $ttl_minutes Token lifetime.
     * @return string JWT string.
     */
    private function issueResetToken( string $email, int $ttl_minutes = 10 ): string {
        $jwt = new JwtService();
        $token = $jwt->issue_reset_token( $email, $ttl_minutes );
        $this->assertIsString( $token );
        return $token;
    }

    /**
     * Build a JWT with a custom payload (for wrong-purpose / missing-email tests).
     *
     * @param array $payload JWT claims.
     * @return string Encoded JWT.
     */
    private function buildCustomToken( array $payload ): string {
        $config = json_decode(
            \get_option( 'bs_custom_jwt_auth', '{}' ),
            true
        );
        $secret = $config['secret'];

        return \Firebase\JWT\JWT::encode( $payload, $secret, 'HS256' );
    }

    /* ── tests ───────────────────────────────────────────────────── */

    public function test_successful_reset(): void {
        $email = 'reset@example.com';
        $user  = $this->registerUser( 'resetuser', $email, 'OldPass1!' );
        $token = $this->issueResetToken( $email );

        $request  = $this->makeRequest( array(
            'reset_token'          => $token,
            'new_password'         => 'NewPass1!',
            'new_password_confirm' => 'NewPass1!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );
    }

    public function test_invalid_reset_token(): void {
        $request  = $this->makeRequest( array(
            'reset_token'          => 'totally.invalid.token',
            'new_password'         => 'NewPass1!',
            'new_password_confirm' => 'NewPass1!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 401, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_wrong_purpose_returns_401(): void {
        /* Issue a regular auth token (purpose is absent) */
        $user  = $this->registerUser( 'wrongpurpose', 'wp@example.com', 'Test1234!' );
        $token = $this->issueToken( $user->ID );

        $request  = $this->makeRequest( array(
            'reset_token'          => $token,
            'new_password'         => 'NewPass1!',
            'new_password_confirm' => 'NewPass1!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 401, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_missing_email_in_token(): void {
        /* Token with purpose=password_reset but no email claim */
        $payload = array(
            'iss'     => \get_site_url(),
            'iat'     => time(),
            'exp'     => time() + 600,
            'purpose' => 'password_reset',
        );
        $token = $this->buildCustomToken( $payload );

        $request  = $this->makeRequest( array(
            'reset_token'          => $token,
            'new_password'         => 'NewPass1!',
            'new_password_confirm' => 'NewPass1!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 401, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_weak_password_returns_400(): void {
        $email = 'weak@example.com';
        $this->registerUser( 'weakpw', $email, 'OldPass1!' );
        $token = $this->issueResetToken( $email );

        $request  = $this->makeRequest( array(
            'reset_token'          => $token,
            'new_password'         => 'short',
            'new_password_confirm' => 'short',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_password_mismatch_returns_400(): void {
        $email = 'mismatch@example.com';
        $this->registerUser( 'mismatch', $email, 'OldPass1!' );
        $token = $this->issueResetToken( $email );

        $request  = $this->makeRequest( array(
            'reset_token'          => $token,
            'new_password'         => 'NewPass1!',
            'new_password_confirm' => 'Different1!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_user_not_found_returns_404(): void {
        /* Issue token for a non-existent user email */
        $token = $this->issueResetToken( 'ghost@example.com' );

        $request  = $this->makeRequest( array(
            'reset_token'          => $token,
            'new_password'         => 'NewPass1!',
            'new_password_confirm' => 'NewPass1!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 404, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_same_password_returns_400(): void {
        $email    = 'same@example.com';
        $password = 'OldPass1!';
        $this->registerUser( 'sameuser', $email, $password );
        $token = $this->issueResetToken( $email );

        $request  = $this->makeRequest( array(
            'reset_token'          => $token,
            'new_password'         => $password,
            'new_password_confirm' => $password,
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_password_actually_changed(): void {
        $email       = 'changed@example.com';
        $oldPassword = 'OldPass1!';
        $newPassword = 'BrandNew2@';
        $user        = $this->registerUser( 'changeduser', $email, $oldPassword );
        $token       = $this->issueResetToken( $email );

        $request = $this->makeRequest( array(
            'reset_token'          => $token,
            'new_password'         => $newPassword,
            'new_password_confirm' => $newPassword,
        ) );
        $this->endpoint->handle( $request );

        /* Verify the stored hash now matches the new password */
        $updated = \get_user_by( 'id', $user->ID );
        $this->assertTrue( \wp_check_password( $newPassword, $updated->user_pass, $updated->ID ) );
        $this->assertFalse( \wp_check_password( $oldPassword, $updated->user_pass, $updated->ID ) );
    }

    public function test_expired_token_returns_401(): void {
        $email = 'expired@example.com';
        $this->registerUser( 'expuser', $email, 'OldPass1!' );

        /* Build a token that expired 10 seconds ago */
        $payload = array(
            'iss'     => \get_site_url(),
            'iat'     => time() - 700,
            'exp'     => time() - 10,
            'email'   => $email,
            'purpose' => 'password_reset',
        );
        $token = $this->buildCustomToken( $payload );

        $request  = $this->makeRequest( array(
            'reset_token'          => $token,
            'new_password'         => 'NewPass1!',
            'new_password_confirm' => 'NewPass1!',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 401, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }
}
