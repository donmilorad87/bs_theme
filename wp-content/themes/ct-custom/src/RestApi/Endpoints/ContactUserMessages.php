<?php
/**
 * REST Contact User Messages Endpoint
 *
 * Returns the authenticated user's contact messages with replies.
 * GET /wp-json/ct-auth/v1/contact/user-messages
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RestLogger;
use BSCustom\Cpt\ContactMessageCpt;

class ContactUserMessages {

	use RestLogger;

	const NAMESPACE    = 'ct-auth/v1';
	const ROUTE        = '/contact/user-messages';
	const PER_PAGE     = 20;
	const MAX_PER_PAGE = 100;

	/**
	 * Register the route.
	 */
	public function register() {
		assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

		register_rest_route( self::NAMESPACE, self::ROUTE, array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => 'bs_jwt_or_cookie_permission_check',
			'args'                => array(
				'page' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 1,
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

		$user_id = get_current_user_id();
		$page    = max( 1, (int) $request->get_param( 'page' ) );

		assert( $user_id > 0, 'User must be authenticated' );

		$query = new \WP_Query( array(
			'post_type'      => ContactMessageCpt::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'   => '_ct_msg_user_id',
					'value' => $user_id,
					'type'  => 'NUMERIC',
				),
			),
		) );

		assert( $query instanceof \WP_Query, 'Query must be WP_Query instance' );

		$messages  = array();
		$max_posts = self::MAX_PER_PAGE;
		$count     = 0;

		foreach ( $query->posts as $post ) {
			if ( $count >= $max_posts ) {
				break;
			}
			$count++;

			$replies_raw = get_post_meta( $post->ID, '_ct_msg_replies', true );
			$replies     = json_decode( $replies_raw, true );

			if ( ! is_array( $replies ) ) {
				$replies = array();
			}

			$messages[] = array(
				'id'      => $post->ID,
				'subject' => get_the_title( $post->ID ),
				'body'    => get_post_meta( $post->ID, '_ct_msg_body', true ),
				'form_id'    => (int) get_post_meta( $post->ID, '_ct_msg_form_id', true ),
				'form_label' => get_post_meta( $post->ID, '_ct_msg_form_label', true ),
				'pointer'    => get_post_meta( $post->ID, '_ct_msg_pointer', true ),
				'date'    => $post->post_date,
				'replies' => $replies,
			);
		}

		return new \WP_REST_Response( array(
			'success' => true,
			'data'    => array(
				'messages'     => $messages,
				'total'        => (int) $query->found_posts,
				'total_pages'  => (int) $query->max_num_pages,
				'current_page' => $page,
			),
		), 200 );
	}
}
