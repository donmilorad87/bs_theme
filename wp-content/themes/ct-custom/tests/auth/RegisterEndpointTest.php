<?php
/**
 * Tests for the Register REST API endpoint.
 *
 * Verifies input validation, rate limiting, account creation,
 * activation code generation, and response structure via Register::handle().
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use BSCustom\RestApi\Endpoints\Register;

class RegisterEndpointTest extends AuthTestCase {

    private Register $endpoint;

    /** @var array Default valid registration parameters. */
    private array $valid_params;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new Register();

        $this->valid_params = array(
            'username'         => 'newuser',
            'email'            => 'newuser@example.com',
            'password'         => 'Strong1234!',
            'password_confirm' => 'Strong1234!',
            'first_name'       => 'New',
            'last_name'        => 'User',
        );
    }

    /* ── Successful registration ─────────────────────────────────── */

    public function test_successful_registration(): void {
        $request  = $this->makeRequest( $this->valid_params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertTrue( $data['success'] );
        $this->assertSame( 'newuser@example.com', $data['data']['email'] );
    }

    /* ── Username too short ──────────────────────────────────────── */

    public function test_username_too_short(): void {
        $params            = $this->valid_params;
        $params['username'] = 'ab';

        $request  = $this->makeRequest( $params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Username invalid characters ─────────────────────────────── */

    public function test_username_invalid_chars(): void {
        $params            = $this->valid_params;
        $params['username'] = 'user@name!';

        $request  = $this->makeRequest( $params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Username too many special characters ────────────────────── */

    public function test_username_too_many_specials(): void {
        $params            = $this->valid_params;
        $params['username'] = 'u.s-e_r'; /* 3 specials: . - _ */

        $request  = $this->makeRequest( $params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Invalid email ───────────────────────────────────────────── */

    public function test_invalid_email(): void {
        $params          = $this->valid_params;
        $params['email'] = 'not-an-email';

        $request  = $this->makeRequest( $params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Weak password ───────────────────────────────────────────── */

    public function test_weak_password(): void {
        $params             = $this->valid_params;
        $params['password']         = 'short';
        $params['password_confirm'] = 'short';

        $request  = $this->makeRequest( $params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Password mismatch ───────────────────────────────────────── */

    public function test_password_mismatch(): void {
        $params                     = $this->valid_params;
        $params['password_confirm'] = 'Different1234!';

        $request  = $this->makeRequest( $params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Username taken ──────────────────────────────────────────── */

    public function test_username_taken(): void {
        $this->registerUser( 'newuser', 'other@example.com', 'Secret1234!' );

        $request  = $this->makeRequest( $this->valid_params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Email taken ─────────────────────────────────────────────── */

    public function test_email_taken(): void {
        $this->registerUser( 'otheruser', 'newuser@example.com', 'Secret1234!' );

        $request  = $this->makeRequest( $this->valid_params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Empty first name ────────────────────────────────────────── */

    public function test_empty_first_name(): void {
        $params               = $this->valid_params;
        $params['first_name'] = '';

        $request  = $this->makeRequest( $params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Empty last name ─────────────────────────────────────────── */

    public function test_empty_last_name(): void {
        $params              = $this->valid_params;
        $params['last_name'] = '';

        $request  = $this->makeRequest( $params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── Rate limited returns 429 ────────────────────────────────── */

    public function test_rate_limited(): void {
        $ip = '10.0.0.5';
        $this->setClientIp( $ip );

        /* Simulate 3 prior registration attempts */
        $key = 'bs_register_attempts_' . md5( $ip );
        $this->setTransient( $key, 3, 3600 );

        $request  = $this->makeRequest( $this->valid_params );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 429, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /* ── New user has bs_account_active = 0 ──────────────────────── */

    public function test_sets_inactive_meta(): void {
        $request = $this->makeRequest( $this->valid_params );
        $this->endpoint->handle( $request );

        /* Find the newly created user by email */
        $user = \get_user_by( 'email', 'newuser@example.com' );
        $this->assertNotFalse( $user );

        $active = \get_user_meta( $user->ID, 'bs_account_active', true );
        $this->assertSame( '0', $active );
    }

    /* ── Activation code stored in transient ─────────────────────── */

    public function test_stores_activation_code(): void {
        $request = $this->makeRequest( $this->valid_params );
        $this->endpoint->handle( $request );

        $transient_key = 'bs_activation_code_' . md5( 'newuser@example.com' );
        $stored_code   = \get_transient( $transient_key );

        $this->assertNotFalse( $stored_code );
        $this->assertSame( 6, strlen( $stored_code ) );
    }

    /* ── Response includes email field ───────────────────────────── */

    public function test_response_includes_email(): void {
        $request  = $this->makeRequest( $this->valid_params );
        $response = $this->endpoint->handle( $request );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'email', $data['data'] );
        $this->assertSame( 'newuser@example.com', $data['data']['email'] );
    }
}
