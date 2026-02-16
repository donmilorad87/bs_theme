<?php
/**
 * Tests for the ProfileUpdate REST endpoint.
 *
 * Covers: successful update, empty first/last name validation,
 * and display_name composition.
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use BSCustom\RestApi\Endpoints\ProfileUpdate;

class ProfileUpdateEndpointTest extends AuthTestCase {

    private ProfileUpdate $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new ProfileUpdate();
    }

    /* ── tests ───────────────────────────────────────────────────── */

    public function test_successful_update(): void {
        $user = $this->registerUser( 'profuser', 'prof@example.com', 'Test1234!' );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest( array(
            'first_name' => 'Alice',
            'last_name'  => 'Smith',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertTrue( $data['success'] );
        $this->assertArrayHasKey( 'data', $data );
        $this->assertSame( 'Alice Smith', $data['data']['display_name'] );

        /* Verify the user object was updated in the store */
        $updated = \get_user_by( 'id', $user->ID );
        $this->assertSame( 'Alice', $updated->first_name );
        $this->assertSame( 'Smith', $updated->last_name );
    }

    public function test_empty_first_name_returns_400(): void {
        $user = $this->registerUser( 'profuser2', 'prof2@example.com', 'Test1234!' );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest( array(
            'first_name' => '',
            'last_name'  => 'Smith',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_empty_last_name_returns_400(): void {
        $user = $this->registerUser( 'profuser3', 'prof3@example.com', 'Test1234!' );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest( array(
            'first_name' => 'Alice',
            'last_name'  => '',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_updates_display_name(): void {
        $user = $this->registerUser( 'profuser4', 'prof4@example.com', 'Test1234!' );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest( array(
            'first_name' => 'Bob',
            'last_name'  => 'Jones',
        ) );
        $this->endpoint->handle( $request );

        $updated = \get_user_by( 'id', $user->ID );
        $this->assertSame( 'Bob Jones', $updated->display_name );
    }
}
