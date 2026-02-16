<?php
/**
 * REST Get Translations Endpoint
 *
 * Returns the full translation dictionary for a given language.
 * GET /wp-json/ct-custom/v1/translations?iso2=sr
 *
 * Public endpoint — translations are public strings.
 *
 * @package CTCustom\RestApi\Endpoints
 */

namespace CTCustom\RestApi\Endpoints;

use CTCustom\RestApi\RestLogger;
use CTCustom\Multilang\Translator;

class GetTranslations {

    use RestLogger;

    const NAMESPACE    = 'ct-custom/v1';
    const ROUTE        = '/translations';
    const MAX_ISO2_LEN = 10;

    /**
     * Register the route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );
        assert( class_exists( Translator::class ), 'Translator must be loaded' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'iso2' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Handle the translations dictionary request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $iso2 = $request->get_param( 'iso2' );

        if ( ! is_string( $iso2 ) || '' === $iso2 ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'iso2 parameter is required.', 'ct-custom' ),
            ), 400 );
        }

        if ( mb_strlen( $iso2 ) > self::MAX_ISO2_LEN ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'iso2 parameter is too long.', 'ct-custom' ),
            ), 400 );
        }

        assert( mb_strlen( $iso2 ) <= self::MAX_ISO2_LEN, 'iso2 must be within length limit' );

        $translator   = new Translator( $iso2 );
        $translations = $translator->get_all_translations();

        assert( is_array( $translations ), 'Translations must be an array' );

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'iso2'         => $iso2,
                'translations' => $translations,
            ),
        ), 200 );
    }
}
