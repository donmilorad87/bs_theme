<?php
/**
 * REST Contact Delete Endpoint
 *
 * Deletes a contact message.
 * POST /wp-json/ct-auth/v1/contact/delete
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RestLogger;
use BSCustom\Cpt\ContactMessageCpt;

class ContactDelete {

	use RestLogger;

	const NAMESPACE = 'ct-auth/v1';
	const ROUTE     = '/contact/delete';

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

		assert( $message_id > 0, 'Message ID must be positive' );

		$post = get_post( $message_id );

		if ( ! $post || ContactMessageCpt::POST_TYPE !== $post->post_type ) {
			$this->log( 'Not found: message_id=' . $message_id );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Message not found.', 'ct-custom' ),
			), 404 );
		}

		$result = wp_delete_post( $message_id, true );

		if ( ! $result ) {
			$this->log( 'Server error: wp_delete_post failed, message_id=' . $message_id );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Failed to delete message.', 'ct-custom' ),
			), 500 );
		}

		return new \WP_REST_Response( array(
			'success' => true,
			'message' => __( 'Message deleted.', 'ct-custom' ),
		), 200 );
	}
}
