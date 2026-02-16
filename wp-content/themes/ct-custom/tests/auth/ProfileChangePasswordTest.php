<?php
/**
 * Tests for the ProfileChangePassword REST endpoint.
 *
 * Covers: successful change, wrong current password, same password,
 * weak password, mismatch, user not found, and actual update.
 *
 * @package CTCustom\Tests\Auth
 */

namespace CTCustom\Tests\Auth;

use CTCustom\RestApi\Endpoints\ProfileChangePassword;

class ProfileChangePasswordTest extends AuthTestCase {

    private ProfileChangePassword $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new ProfileChangePassword();
    }

    /* ── tests ───────────────────────────────────────────────────── */

    public function test_successful_change(): void {
        $user = $this->registerUser( 'cpuser', 'cp@example.com', 'OldPass1!' );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest( array(
            'current_password'     => 'OldPass1!',
            'new_password'         => 'NewPass2@',
            'new_password_confirm' => 'NewPass2@',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );
    }

    public function test_wrong_current_password(): void {
        $user = $this->registerUser( 'cpuser2', 'cp2@example.com', 'OldPass1!' );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest( array(
            'current_password'     => 'WrongPass9!',
            'new_password'         => 'NewPass2@',
            'new_password_confirm' => 'NewPass2@',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
        $this->assertStringContainsString( 'incorrect', strtolower( $response->get_data()['message'] ) );
    }

    public function test_same_password_returns_400(): void {
        $password = 'SamePass1!';
        $user     = $this->registerUser( 'cpuser3', 'cp3@example.com', $password );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest( array(
            'current_password'     => $password,
            'new_password'         => $password,
            'new_password_confirm' => $password,
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_weak_new_password_returns_400(): void {
        $user = $this->registerUser( 'cpuser4', 'cp4@example.com', 'OldPass1!' );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest( array(
            'current_password'     => 'OldPass1!',
            'new_password'         => 'weak',
            'new_password_confirm' => 'weak',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_password_mismatch_returns_400(): void {
        $user = $this->registerUser( 'cpuser5', 'cp5@example.com', 'OldPass1!' );
        $this->loginAs( $user->ID );

        $request  = $this->makeRequest( array(
            'current_password'     => 'OldPass1!',
            'new_password'         => 'NewPass2@',
            'new_password_confirm' => 'Different3#',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_user_not_found(): void {
        /*
         * Simulate a logged-in user whose ID does not exist in the
         * global store (edge case: user was deleted between auth and handler).
         */
        $this->loginAs( 9999 );

        $request  = $this->makeRequest( array(
            'current_password'     => 'OldPass1!',
            'new_password'         => 'NewPass2@',
            'new_password_confirm' => 'NewPass2@',
        ) );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 404, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_password_actually_updated(): void {
        $oldPassword = 'OldPass1!';
        $newPassword = 'BrandNew2@';
        $user        = $this->registerUser( 'cpuser6', 'cp6@example.com', $oldPassword );
        $this->loginAs( $user->ID );

        $request = $this->makeRequest( array(
            'current_password'     => $oldPassword,
            'new_password'         => $newPassword,
            'new_password_confirm' => $newPassword,
        ) );
        $this->endpoint->handle( $request );

        $updated = \get_user_by( 'id', $user->ID );
        $this->assertTrue( \wp_check_password( $newPassword, $updated->user_pass, $updated->ID ) );
        $this->assertFalse( \wp_check_password( $oldPassword, $updated->user_pass, $updated->ID ) );
    }
}
