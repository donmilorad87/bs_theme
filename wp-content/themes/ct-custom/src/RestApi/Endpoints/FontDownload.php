<?php
/**
 * REST Font Download Endpoint
 *
 * Downloads Google Font files and generates @font-face CSS.
 * POST /wp-json/ct-custom/v1/font/download
 *
 * Admin-only endpoint. Triggered when a font is selected
 * in the Customizer, not on save.
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\Customizer\FontManager;
use BSCustom\RestApi\RateLimiter;
use BSCustom\RestApi\RestLogger;

class FontDownload {

    use RateLimiter;
    use RestLogger;

    const NAMESPACE_V1    = 'ct-custom/v1';
    const ROUTE           = '/font/download';
    const RATE_LIMIT      = 30;
    const RATE_WINDOW_SEC = 60;
    const RATE_PREFIX     = 'bs_font_dl_';

    /**
     * Register the route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );
        assert( class_exists( FontManager::class ), 'FontManager must be loaded' );

        register_rest_route( self::NAMESPACE_V1, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'family' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'weights' => array(
                    'required'          => false,
                    'default'           => '',
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Permission check — admin only.
     *
     * @return bool
     */
    public function check_permission() {
        assert( function_exists( 'current_user_can' ), 'current_user_can must exist' );

        return current_user_can( 'manage_options' );
    }

    /**
     * Handle the font download request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        /* Rate limit check */
        $ip = $this->get_client_ip();

        if ( $this->is_rate_limited_by_ip( self::RATE_PREFIX, $ip, self::RATE_LIMIT ) ) {
            $this->log( 'Rate limited: IP=' . $ip );
            $remaining = $this->get_rate_limit_remaining( self::RATE_PREFIX, $ip );
            $wait_text = $this->format_wait_time( $remaining );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: wait time */
                    __( 'Too many requests. Please try again in %s.', 'ct-custom' ),
                    $wait_text
                ),
            ), 429 );
        }

        $this->increment_rate_limit( self::RATE_PREFIX, $ip, self::RATE_WINDOW_SEC );

        $family  = $request->get_param( 'family' );
        $weights = $request->get_param( 'weights' );

        assert( is_string( $family ), 'Family param must be a string' );

        $weights = is_string( $weights ) ? $weights : '';

        /* Always clean existing font files before any operation */
        $font_manager = new FontManager();
        $font_manager->cleanup_all();

        /* The family param is the API name. Resolve to display name for CSS. */
        $api_family   = $family;
        $display_name = $family;

        /* Look up the display name from the catalog */
        $catalog    = FontManager::get_font_catalog();
        $max_fonts  = 2000;
        $font_count = 0;

        foreach ( $catalog as $font ) {
            if ( $font_count >= $max_fonts ) {
                break;
            }
            $font_count++;

            if ( isset( $font['family'] ) && $font['family'] === $api_family ) {
                $display_name = isset( $font['displayName'] ) ? $font['displayName'] : $api_family;
                break;
            }
        }

        /* Empty weights = cleanup-only request (e.g. fonts disabled) */
        if ( '' === trim( $weights ) ) {
            set_theme_mod( 'bs_font_face_css', '' );
            delete_transient( 'bs_dynamic_css' );

            return new \WP_REST_Response( array(
                'success' => true,
                'data'    => array(
                    'face_css' => '',
                ),
            ), 200 );
        }

        /* Validate family exists in catalog */
        if ( ! FontManager::validate_font_in_catalog( $api_family ) ) {
            $this->log( 'Validation failed: family not in catalog, family=' . $api_family );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Font family not found in catalog.', 'ct-custom' ),
            ), 400 );
        }

        /* Validate requested weights exist for this font */
        $valid_weights = FontManager::validate_weights_for_font( $api_family, $weights );

        if ( empty( $valid_weights ) ) {
            set_theme_mod( 'bs_font_face_css', '' );
            delete_transient( 'bs_dynamic_css' );

            return new \WP_REST_Response( array(
                'success' => true,
                'data'    => array(
                    'face_css' => '',
                ),
            ), 200 );
        }

        $weights_string = implode( ',', $valid_weights );

        /* Download font files and generate @font-face CSS.
         * API name for Google URL, display name for @font-face CSS. */
        $face_css = $font_manager->download_font( $api_family, $weights_string, $display_name );

        if ( '' === $face_css ) {
            $this->log( 'Server error: download_font returned empty, family=' . $api_family . ', weights=' . $weights_string );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Failed to download font files.', 'ct-custom' ),
            ), 500 );
        }

        /* Store result in theme mod */
        set_theme_mod( 'bs_font_face_css', $face_css );
        set_theme_mod( 'bs_font_prev_family', $display_name );

        /* Invalidate dynamic CSS transient */
        delete_transient( 'bs_dynamic_css' );

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'face_css' => $face_css,
            ),
        ), 200 );
    }
}
