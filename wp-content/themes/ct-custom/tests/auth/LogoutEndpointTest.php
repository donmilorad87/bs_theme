<?php
/**
 * Tests for the Logout REST API endpoint.
 *
 * Verifies that authenticated users can log out and that the session
 * is properly cleared via Logout::handle().
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use BSCustom\RestApi\Endpoints\Logout;

class LogoutEndpointTest extends AuthTestCase {

    private Logout $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new Logout();
    }

    /* ── Logged-in user can logout ───────────────────────────────── */

    public function test_logged_in_user_can_logout(): void {
        $user = $this->registerUser( 'alice', 'alice@example.com', 'Secret1234!' );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest();
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );
    }

    /* ── Current user is 0 after logout ──────────────────────────── */

    public function test_logout_clears_session(): void {
        $user = $this->registerUser( 'bob', 'bob@example.com', 'Secret1234!' );
        $this->loginAs( $user->ID );

        $this->assertSame( $user->ID, \get_current_user_id() );

        $request = $this->makeRequest();
        $this->endpoint->handle( $request );

        $this->assertSame( 0, \get_current_user_id() );
    }
}
