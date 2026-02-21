<?php
/**
 * REST Contact Messages Endpoint
 *
 * Returns paginated contact messages for admin.
 * GET /wp-json/ct-auth/v1/contact/messages
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RestLogger;
use BSCustom\Cpt\ContactMessageCpt;

class ContactMessages {

	use RestLogger;

	const NAMESPACE    = 'ct-auth/v1';
	const ROUTE        = '/contact/messages';
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
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => $this->get_args(),
		) );
	}

	/**
	 * Define endpoint arguments.
	 *
	 * @return array Argument definitions.
	 */
	private function get_args() {
		return array(
			'page' => array(
				'required' => false,
				'type'     => 'integer',
				'default'  => 1,
			),
			'form_id' => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 0,
			),
			'status' => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => 'all',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Handle the request.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ) {
		assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

		$page    = max( 1, (int) $request->get_param( 'page' ) );
		$form_id = (int) $request->get_param( 'form_id' );
		$status  = $request->get_param( 'status' );

		$meta_query = array();

		if ( $form_id > 0 ) {
			$meta_query[] = array(
				'key'   => '_ct_msg_form_id',
				'value' => $form_id,
				'type'  => 'NUMERIC',
			);
		}

		if ( 'read' === $status ) {
			$meta_query[] = array(
				'key'   => '_ct_msg_is_read',
				'value' => '1',
			);
		} elseif ( 'unread' === $status ) {
			$meta_query[] = array(
				'key'   => '_ct_msg_is_read',
				'value' => '0',
			);
		}

		$query_args = array(
			'post_type'      => ContactMessageCpt::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		$query = new \WP_Query( $query_args );

		assert( $query instanceof \WP_Query, 'Query must be WP_Query instance' );

		$messages  = array();
		$max_posts = self::MAX_PER_PAGE;
		$count     = 0;

		foreach ( $query->posts as $post ) {
			if ( $count >= $max_posts ) {
				break;
			}
			$count++;

			$messages[] = $this->format_message( $post );
		}

		$unread_query = new \WP_Query( array(
			'post_type'      => ContactMessageCpt::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_ct_msg_is_read',
					'value' => '0',
				),
			),
		) );

		return new \WP_REST_Response( array(
			'success' => true,
			'data'    => array(
				'messages'     => $messages,
				'total'        => (int) $query->found_posts,
				'total_pages'  => (int) $query->max_num_pages,
				'current_page' => $page,
				'unread_count' => (int) $unread_query->found_posts,
			),
		), 200 );
	}

	/**
	 * Format a message post into an array.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array Formatted message data.
	 */
	private function format_message( $post ) {
		assert( $post instanceof \WP_Post, 'Post must be WP_Post' );

		$replies_raw = get_post_meta( $post->ID, '_ct_msg_replies', true );
		$replies     = json_decode( $replies_raw, true );

		if ( ! is_array( $replies ) ) {
			$replies = array();
		}

		assert( is_array( $replies ), 'Replies must be an array' );

		return array(
			'id'           => $post->ID,
			'sender_name'  => get_post_meta( $post->ID, '_ct_msg_sender_name', true ),
			'sender_email' => get_post_meta( $post->ID, '_ct_msg_sender_email', true ),
			'sender_phone' => get_post_meta( $post->ID, '_ct_msg_sender_phone', true ),
			'body'         => get_post_meta( $post->ID, '_ct_msg_body', true ),
			'form_id'      => (int) get_post_meta( $post->ID, '_ct_msg_form_id', true ),
			'form_label'   => get_post_meta( $post->ID, '_ct_msg_form_label', true ),
			'pointer'      => get_post_meta( $post->ID, '_ct_msg_pointer', true ),
			'fields'       => $this->decode_meta_json( $post->ID, '_ct_msg_fields' ),
			'attachments'  => $this->decode_meta_json( $post->ID, '_ct_msg_attachments' ),
			'user_id'      => (int) get_post_meta( $post->ID, '_ct_msg_user_id', true ),
			'is_read'      => get_post_meta( $post->ID, '_ct_msg_is_read', true ) === '1',
			'replies'      => $replies,
			'date'         => $post->post_date,
		);
	}

	/**
	 * Decode JSON meta field safely.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return array
	 */
	private function decode_meta_json( $post_id, $key ) {
		$raw = get_post_meta( $post_id, $key, true );
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}
}
