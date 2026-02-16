<?php
/**
 * REST Profile Upload Avatar Endpoint
 *
 * Uploads a user avatar image via JWT-authenticated request.
 * Saves to WordPress media library and stores as ct_avatar_id user meta.
 * POST /wp-json/ct-auth/v1/profile/upload-avatar
 *
 * Rate limited: 5 uploads per user per minute.
 * Max file size: 5MB, images only.
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RateLimiter;
use BSCustom\RestApi\RestLogger;

class ProfileUploadAvatar {

    use RateLimiter;
    use RestLogger;

    const NAMESPACE     = 'ct-auth/v1';
    const ROUTE         = '/profile/upload-avatar';
    const MAX_FILE_SIZE = 5242880; /* 5 MB */
    const MAX_UPLOADS   = 5;
    const WINDOW_SEC    = 60;

    const ALLOWED_TYPES = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    );

    const MAX_ALLOWED = 4;

    /**
     * Register the route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => 'ct_jwt_or_cookie_permission_check',
        ) );
    }

    /**
     * Handle the avatar upload request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $user_id = get_current_user_id();

        assert( $user_id > 0, 'User must be authenticated' );

        /* Rate limit */
        $rate_key = 'ct_avatar_upload_' . $user_id;
        if ( $this->is_rate_limited_by_key( 'ct_avatar_upload_', (string) $user_id, self::MAX_UPLOADS ) ) {
            $this->log( 'Rate limited: user_id=' . $user_id );
            $remaining = $this->get_rate_limit_remaining( 'ct_avatar_upload_', (string) $user_id );
            $wait_text = $this->format_wait_time( $remaining );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: wait time */
                    __( 'Too many uploads. Please try again in %s.', 'ct-custom' ),
                    $wait_text
                ),
            ), 429 );
        }

        $files = $request->get_file_params();

        if ( empty( $files['avatar'] ) ) {
            $this->log( 'Validation failed: no file uploaded, user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'No file uploaded.', 'ct-custom' ),
            ), 400 );
        }

        $file = $files['avatar'];

        /* Validate file */
        $validation = $this->validate_file( $file );
        if ( is_wp_error( $validation ) ) {
            $this->log( 'Validation failed: ' . $validation->get_error_message() . ', user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => $validation->get_error_message(),
            ), 400 );
        }

        /* Upload to WordPress media library */
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_sideload( array(
            'name'     => sanitize_file_name( $file['name'] ),
            'type'     => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error'    => $file['error'],
            'size'     => $file['size'],
        ), 0 );

        if ( is_wp_error( $attachment_id ) ) {
            $this->log( 'Server error: media_handle_sideload failed, user_id=' . $user_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Failed to upload avatar.', 'ct-custom' ),
            ), 500 );
        }

        /* Store avatar ID in user meta — used by pre_get_avatar_data filter */
        update_user_meta( $user_id, 'ct_avatar_id', $attachment_id );
        $this->increment_rate_limit( 'ct_avatar_upload_', (string) $user_id, self::WINDOW_SEC );

        /* Return the WordPress avatar URL (our filter serves the local image) */
        $avatar_url = get_avatar_url( $user_id, array( 'size' => 150 ) );

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Avatar uploaded successfully.', 'ct-custom' ),
            'data'    => array(
                'avatar_id'  => $attachment_id,
                'avatar_url' => $avatar_url ? $avatar_url : '',
            ),
        ), 200 );
    }

    /**
     * Validate the uploaded file.
     *
     * @param array $file $_FILES array entry.
     * @return true|\WP_Error
     */
    private function validate_file( $file ) {
        assert( is_array( $file ), 'File must be an array' );

        if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
            return new \WP_Error( 'upload_error', __( 'File upload error.', 'ct-custom' ) );
        }

        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            return new \WP_Error( 'file_too_large', __( 'File must be less than 5MB.', 'ct-custom' ) );
        }

        $mime = $file['type'];
        if ( ! in_array( $mime, self::ALLOWED_TYPES, true ) ) {
            return new \WP_Error( 'invalid_type', __( 'Only image files (JPEG, PNG, GIF, WebP) are allowed.', 'ct-custom' ) );
        }

        assert( is_string( $file['tmp_name'] ), 'tmp_name must be set' );

        return true;
    }
}
