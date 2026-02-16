<?php
/**
 * REST Resolve Translation Endpoint
 *
 * Resolves ct_translate() patterns in a text string.
 * POST /wp-json/ct-custom/v1/resolve-translation
 *
 * Public endpoint — translations are public strings.
 *
 * @package CTCustom\RestApi\Endpoints
 */

namespace CTCustom\RestApi\Endpoints;

use CTCustom\RestApi\RestLogger;
use CTCustom\Multilang\TranslationService;

class ResolveTranslation {

    use RestLogger;

    const NAMESPACE  = 'ct-custom/v1';
    const ROUTE      = '/resolve-translation';
    const MAX_LENGTH = 2000;

    /**
     * Register the route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );
        assert( class_exists( TranslationService::class ), 'TranslationService must be loaded' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'text' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'iso2' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ),
            ),
        ) );
    }

    /**
     * Handle the translation resolution request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $text = $request->get_param( 'text' );
        $iso2 = $request->get_param( 'iso2' );

        if ( ! is_string( $text ) || '' === $text ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Text parameter is required.', 'ct-custom' ),
            ), 400 );
        }

        if ( mb_strlen( $text ) > self::MAX_LENGTH ) {
            $text = mb_substr( $text, 0, self::MAX_LENGTH );
        }

        $resolved = TranslationService::resolve( $text, $iso2 );

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'resolved' => $resolved,
            ),
        ), 200 );
    }
}
