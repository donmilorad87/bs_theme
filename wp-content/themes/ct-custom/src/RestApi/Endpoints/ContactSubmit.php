<?php
/**
 * REST Contact Submit Endpoint
 *
 * Handles public contact form submissions via POST /wp-json/ct-auth/v1/contact/submit.
 * Rate-limited to 5 per IP per hour. Creates a ct_contact_message CPT post,
 * sends notification emails to pointer-linked addresses.
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RateLimiter;
use BSCustom\RestApi\RestLogger;
use BSCustom\Cpt\ContactMessageCpt;
use BSCustom\Services\MailService;
use BSCustom\Services\EmailTemplate;

class ContactSubmit {

	use RateLimiter;
	use RestLogger;

	const NAMESPACE    = 'ct-auth/v1';
	const ROUTE        = '/contact/submit';
	const MAX_ATTEMPTS = 5;
	const WINDOW_SEC   = 3600;

	/**
	 * Register the route.
	 */
	public function register() {
		assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

		register_rest_route( self::NAMESPACE, self::ROUTE, array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => '__return_true',
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
			'name' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'email' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => function ( $value ) {
					return is_email( $value );
				},
			),
			'phone' => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'message' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'pointer' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Handle the contact form submission.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ) {
		assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

		$ip = $this->get_client_ip();

		if ( $this->is_rate_limited_by_ip( 'ct_contact_submit_', $ip, self::MAX_ATTEMPTS ) ) {
			$this->log( 'Rate limited: IP=' . $ip );
			$remaining = $this->get_rate_limit_remaining( 'ct_contact_submit_', $ip );
			$wait_text = $this->format_wait_time( $remaining );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: wait time */
					__( 'Too many submissions. Please try again in %s.', 'ct-custom' ),
					$wait_text
				),
			), 429 );
		}

		$name    = $request->get_param( 'name' );
		$email   = $request->get_param( 'email' );
		$phone   = $request->get_param( 'phone' );
		$message = $request->get_param( 'message' );
		$pointer = $request->get_param( 'pointer' );

		assert( is_string( $name ) && strlen( $name ) > 0, 'Name must be non-empty' );
		assert( is_string( $email ) && strlen( $email ) > 0, 'Email must be non-empty' );

		if ( empty( $name ) || empty( $email ) || empty( $message ) ) {
			$this->log( 'Validation failed: missing required fields' );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Please fill in all required fields.', 'ct-custom' ),
			), 400 );
		}

		$pointer_data = ContactMessageCpt::get_pointer_by_slug( $pointer );
		if ( ! $pointer_data ) {
			$this->log( 'Validation failed: invalid pointer=' . $pointer );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Invalid contact pointer.', 'ct-custom' ),
			), 400 );
		}

		$user_id = get_current_user_id();

		$post_id = wp_insert_post( array(
			'post_type'   => ContactMessageCpt::POST_TYPE,
			'post_title'  => sanitize_text_field( $name ) . ' — ' . sanitize_text_field( $pointer_data['label'] ),
			'post_status' => 'publish',
		) );

		if ( is_wp_error( $post_id ) || 0 === $post_id ) {
			$this->log( 'Server error: wp_insert_post failed for contact message' );
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Failed to save message.', 'ct-custom' ),
			), 500 );
		}

		update_post_meta( $post_id, '_ct_msg_sender_name', $name );
		update_post_meta( $post_id, '_ct_msg_sender_email', $email );
		update_post_meta( $post_id, '_ct_msg_sender_phone', $phone );
		update_post_meta( $post_id, '_ct_msg_body', $message );
		update_post_meta( $post_id, '_ct_msg_pointer', $pointer );
		update_post_meta( $post_id, '_ct_msg_user_id', $user_id );
		update_post_meta( $post_id, '_ct_msg_is_read', '0' );
		update_post_meta( $post_id, '_ct_msg_replies', '[]' );
		update_post_meta( $post_id, '_ct_msg_ip', $ip );

		$this->increment_rate_limit( 'ct_contact_submit_', $ip, self::WINDOW_SEC );

		$this->send_notification( $pointer_data, $name, $email, $phone, $message );
		$this->send_confirmation( $email, $name, $pointer_data );

		return new \WP_REST_Response( array(
			'success' => true,
			'message' => __( 'Your message has been sent successfully.', 'ct-custom' ),
		), 200 );
	}

	/**
	 * Send confirmation email to the sender.
	 *
	 * @param string $sender_email Sender email address.
	 * @param string $sender_name  Sender name.
	 * @param array  $pointer_data Pointer configuration.
	 */
	private function send_confirmation( $sender_email, $sender_name, $pointer_data ) {
		assert( is_string( $sender_email ), 'Sender email must be a string' );
		assert( is_array( $pointer_data ), 'Pointer data must be an array' );

		$label        = isset( $pointer_data['label'] ) ? $pointer_data['label'] : $pointer_data['slug'];
		$email_tpl    = new EmailTemplate();
		$mail_service = new MailService();
		$html         = $email_tpl->contact_confirmation( $sender_name, $label );
		$subject      = __( 'Message Received', 'ct-custom' ) . ' — ' . esc_html( get_bloginfo( 'name' ) );

		$mail_service->send( $sender_email, $subject, $html );
	}

	/**
	 * Send email notification to pointer-linked addresses.
	 *
	 * @param array  $pointer_data Pointer configuration.
	 * @param string $name         Sender name.
	 * @param string $email        Sender email.
	 * @param string $phone        Sender phone.
	 * @param string $message      Message body.
	 */
	private function send_notification( $pointer_data, $name, $email, $phone, $message ) {
		assert( is_array( $pointer_data ), 'Pointer data must be an array' );
		assert( is_string( $name ), 'Name must be a string' );

		$emails = ContactMessageCpt::get_pointer_emails( $pointer_data['slug'] );

		if ( empty( $emails ) ) {
			return;
		}

		$label         = isset( $pointer_data['label'] ) ? $pointer_data['label'] : $pointer_data['slug'];
		$email_tpl     = new EmailTemplate();
		$mail_service  = new MailService();
		$html          = $email_tpl->contact_notification( $name, $email, $phone, $message, $label );
		$subject       = __( 'New Contact Message', 'ct-custom' ) . ' — ' . $label;

		$max_recipients = ContactMessageCpt::MAX_EMAILS_PER;
		$count          = 0;

		foreach ( $emails as $recipient ) {
			if ( $count >= $max_recipients ) {
				break;
			}
			$count++;

			$mail_service->send( $recipient, $subject, $html );
		}
	}
}
