<?php
/**
 * REST Contact Mark Read Endpoint
 *
 * Toggles read/unread status on a contact message.
 * POST /wp-json/ct-auth/v1/contact/mark-read
 *
 * @package CTCustom\RestApi\Endpoints
 */

namespace CTCustom\RestApi\Endpoints;

use CTCustom\RestApi\RestLogger;
use CTCustom\Cpt\ContactMessageCpt;

class ContactMarkRead {

	use RestLogger;

	const NAMESPACE = 'ct-auth/v1';
	const ROUTE     = '/contact/mark-read';

	/**
	 * Register the route.
	 */
	public function register() {
		assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

		register_rest_route( self::NAMESPACE, self::ROUTE, array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'message_id' => array(
					'required' => true,
					'type'     => 'integer',
				),
				'is_read' => array(
					'required' => true,
					'type'     => 'boolean',
				),
			),
		) );
	}

	/**
	 * Handle the request.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ) {
		assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

		$message_id = (int) $request->get_param( 'message_id' );
		$is_read    = (bool) $request->get_param( 'is_read' );

		assert( $message_id > 0, 'Message ID must be positive' );

		$post = get_post( $message_id );

		if ( ! $post || ContactMessageCpt::POST_TYPE !== $post->post_type ) {
			$this->log( 'Not found: message_id=' . $message_id );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Message not found.', 'ct-custom' ),
			), 404 );
		}

		update_post_meta( $message_id, '_ct_msg_is_read', $is_read ? '1' : '0' );

		return new \WP_REST_Response( array(
			'success' => true,
			'message' => $is_read
				? __( 'Message marked as read.', 'ct-custom' )
				: __( 'Message marked as unread.', 'ct-custom' ),
		), 200 );
	}
}
