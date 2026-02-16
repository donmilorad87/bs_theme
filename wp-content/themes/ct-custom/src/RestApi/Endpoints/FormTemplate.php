<?php
/**
 * REST Form Template Endpoint
 *
 * Returns HTML template-part content for auth forms.
 * GET /wp-json/ct-auth/v1/form/{form_name}
 *
 * Public forms: login, register, forgot-password, activation-code, reset-code, reset-password
 * JWT-protected: profile
 *
 * @package CTCustom\RestApi\Endpoints
 */

namespace CTCustom\RestApi\Endpoints;

use CTCustom\RestApi\RestLogger;

class FormTemplate {

    use RestLogger;

    const NAMESPACE = 'ct-auth/v1';
    const ROUTE     = '/form/(?P<form_name>[a-z-]+)';

    const PUBLIC_FORMS = array(
        'login',
        'register',
        'forgot-password',
        'activation-code',
        'reset-code',
        'reset-password',
    );

    const PROTECTED_FORMS = array(
        'profile',
    );

    const MAX_FORMS = 10;

    /**
     * Register the route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'form_name' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Permission callback: public forms are open, protected require JWT or cookie auth.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error
     */
    public function check_permission( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $form_name = $request->get_param( 'form_name' );

        if ( in_array( $form_name, self::PUBLIC_FORMS, true ) ) {
            return true;
        }

        if ( in_array( $form_name, self::PROTECTED_FORMS, true ) ) {
            return ct_jwt_or_cookie_permission_check( $request );
        }

        return new \WP_Error(
            'ct_form_not_found',
            __( 'Form not found.', 'ct-custom' ),
            array( 'status' => 404 )
        );
    }

    /**
     * Handle the form template request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $form_name = $request->get_param( 'form_name' );

        $all_forms = array_merge( self::PUBLIC_FORMS, self::PROTECTED_FORMS );

        if ( ! in_array( $form_name, $all_forms, true ) ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Form not found.', 'ct-custom' ),
            ), 404 );
        }

        ob_start();
        get_template_part( 'template-parts/auth/' . $form_name );
        $html = ob_get_clean();

        assert( is_string( $html ), 'Template output must be a string' );

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'html' => $html,
            ),
        ), 200 );
    }
}
