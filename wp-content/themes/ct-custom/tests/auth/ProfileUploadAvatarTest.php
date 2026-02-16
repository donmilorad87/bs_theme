<?php
/**
 * Tests for the ProfileUploadAvatar REST endpoint.
 *
 * Covers: successful upload, no file, file too large, invalid MIME,
 * rate limiting, user meta storage, and upload error code.
 *
 * @package CTCustom\Tests\Auth
 */

namespace CTCustom\Tests\Auth;

use CTCustom\RestApi\Endpoints\ProfileUploadAvatar;

class ProfileUploadAvatarTest extends AuthTestCase {

    private ProfileUploadAvatar $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new ProfileUploadAvatar();
    }

    /* ── helpers ──────────────────────────────────────────────────── */

    /**
     * Build a WP_REST_Request with file params attached.
     *
     * @param int   $user_id User to log in as.
     * @param array $file    File array (name, type, tmp_name, error, size).
     * @return \WP_REST_Request
     */
    private function makeUploadRequest( int $user_id, array $file ): \WP_REST_Request {
        $this->loginAs( $user_id );
        $request = $this->makeRequest();
        $request->set_file_params( array( 'avatar' => $file ) );
        return $request;
    }

    /**
     * Return a valid file array for a small JPEG.
     *
     * @return array
     */
    private function validFile(): array {
        return array(
            'name'     => 'avatar.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/avatar.jpg',
            'error'    => 0,
            'size'     => 1024,
        );
    }

    /* ── tests ───────────────────────────────────────────────────── */

    public function test_successful_upload(): void {
        $user    = $this->registerUser( 'avuser', 'av@example.com', 'Test1234!' );
        $request = $this->makeUploadRequest( $user->ID, $this->validFile() );

        $response = $this->endpoint->handle( $request );

        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertTrue( $data['success'] );
        $this->assertArrayHasKey( 'data', $data );
        $this->assertArrayHasKey( 'avatar_url', $data['data'] );
        $this->assertNotEmpty( $data['data']['avatar_url'] );
        $this->assertArrayHasKey( 'avatar_id', $data['data'] );
    }

    public function test_no_file_returns_400(): void {
        $user = $this->registerUser( 'avuser2', 'av2@example.com', 'Test1234!' );
        $this->loginAs( $user->ID );

        /* Request with no file params at all */
        $request  = $this->makeRequest();
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_file_too_large_returns_400(): void {
        $user = $this->registerUser( 'avuser3', 'av3@example.com', 'Test1234!' );
        $file = $this->validFile();
        $file['size'] = 6000000; /* ~6 MB, above the 5 MB limit */

        $request  = $this->makeUploadRequest( $user->ID, $file );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
        $this->assertStringContainsString( '5MB', $response->get_data()['message'] );
    }

    public function test_invalid_mime_returns_400(): void {
        $user = $this->registerUser( 'avuser4', 'av4@example.com', 'Test1234!' );
        $file = $this->validFile();
        $file['type'] = 'application/pdf';
        $file['name'] = 'document.pdf';

        $request  = $this->makeUploadRequest( $user->ID, $file );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_rate_limited_returns_429(): void {
        $user = $this->registerUser( 'avuser5', 'av5@example.com', 'Test1234!' );
        $this->loginAs( $user->ID );

        /* Simulate 5 prior uploads (MAX_UPLOADS = 5) */
        $rate_key = 'ct_avatar_upload_' . md5( (string) $user->ID );
        $this->setTransient( $rate_key, 5, 60 );

        $request = $this->makeRequest();
        $request->set_file_params( array( 'avatar' => $this->validFile() ) );

        $response = $this->endpoint->handle( $request );

        $this->assertSame( 429, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
        $this->assertStringContainsString( 'Too many uploads', $response->get_data()['message'] );
    }

    public function test_stores_avatar_meta(): void {
        $user    = $this->registerUser( 'avuser6', 'av6@example.com', 'Test1234!' );
        $request = $this->makeUploadRequest( $user->ID, $this->validFile() );

        $this->endpoint->handle( $request );

        $avatarId = \get_user_meta( $user->ID, 'ct_avatar_id', true );
        $this->assertNotEmpty( $avatarId );
        $this->assertIsInt( $avatarId );
    }

    public function test_upload_error_returns_400(): void {
        $user = $this->registerUser( 'avuser7', 'av7@example.com', 'Test1234!' );
        $file = $this->validFile();
        $file['error'] = 1; /* PHP upload error code */

        $request  = $this->makeUploadRequest( $user->ID, $file );
        $response = $this->endpoint->handle( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }
}
