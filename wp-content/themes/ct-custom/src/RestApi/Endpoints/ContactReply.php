<?php
/**
 * REST Contact Reply Endpoint
 *
 * Sends a reply to a contact message from an authenticated user.
 * POST /wp-json/ct-auth/v1/contact/reply
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RestLogger;
use BSCustom\Cpt\ContactMessageCpt;
use BSCustom\Services\MailService;
use BSCustom\Services\EmailTemplate;

class ContactReply {

	use RestLogger;

	const NAMESPACE    = 'ct-auth/v1';
	const ROUTE        = '/contact/reply';
	const MAX_REPLIES  = 100;

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
				'reply_body' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				),
			),
		) );
	}

	/**
	 * Handle the reply request.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ) {
		assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

		$message_id = (int) $request->get_param( 'message_id' );
		$reply_body = $request->get_param( 'reply_body' );

		assert( $message_id > 0, 'Message ID must be positive' );
		assert( is_string( $reply_body ) && strlen( $reply_body ) > 0, 'Reply body must be non-empty' );

		$post = get_post( $message_id );

		if ( ! $post || ContactMessageCpt::POST_TYPE !== $post->post_type ) {
			$this->log( 'Not found: message_id=' . $message_id );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Message not found.', 'ct-custom' ),
			), 404 );
		}

		$msg_user_id = (int) get_post_meta( $message_id, '_ct_msg_user_id', true );

		if ( $msg_user_id < 1 ) {
			$this->log( 'Validation failed: guest message cannot be replied to, message_id=' . $message_id );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Cannot reply to guest messages.', 'ct-custom' ),
			), 400 );
		}

		$current_user = wp_get_current_user();
		$replies_raw  = get_post_meta( $message_id, '_ct_msg_replies', true );
		$replies      = json_decode( $replies_raw, true );

		if ( ! is_array( $replies ) ) {
			$replies = array();
		}

		if ( count( $replies ) >= self::MAX_REPLIES ) {
			$this->log( 'Validation failed: max replies reached, message_id=' . $message_id );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Maximum replies reached.', 'ct-custom' ),
			), 400 );
		}

		$reply = array(
			'author_id'   => $current_user->ID,
			'author_name' => $current_user->display_name,
			'body'        => $reply_body,
			'date'        => current_time( 'mysql' ),
		);

		$replies[] = $reply;

		update_post_meta( $message_id, '_ct_msg_replies', wp_json_encode( $replies ) );
		update_post_meta( $message_id, '_ct_msg_is_read', '1' );

		$this->send_reply_email( $message_id, $reply_body, $current_user->display_name );

		return new \WP_REST_Response( array(
			'success' => true,
			'message' => __( 'Reply sent successfully.', 'ct-custom' ),
			'data'    => array(
				'reply' => $reply,
			),
		), 200 );
	}

	/**
	 * Send reply email to the original sender.
	 *
	 * @param int    $message_id Message post ID.
	 * @param string $reply_body Reply text.
	 * @param string $admin_name Admin display name.
	 */
	private function send_reply_email( $message_id, $reply_body, $admin_name ) {
		assert( $message_id > 0, 'Message ID must be positive' );
		assert( is_string( $reply_body ), 'Reply body must be a string' );

		$sender_email = get_post_meta( $message_id, '_ct_msg_sender_email', true );

		if ( empty( $sender_email ) || ! is_email( $sender_email ) ) {
			return;
		}

		$original_subject = get_the_title( $message_id );
		$email_tpl        = new EmailTemplate();
		$mail_service     = new MailService();
		$html             = $email_tpl->contact_reply( $original_subject, $reply_body, $admin_name );
		$subject          = __( 'Reply to Your Message', 'ct-custom' ) . ' — ' . get_bloginfo( 'name' );

		$mail_service->send( $sender_email, $subject, $html );
	}
}
