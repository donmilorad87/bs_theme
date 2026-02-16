<?php

namespace BSCustom\Cpt;

class ContactMessageCpt {

	const POST_TYPE      = 'ct_contact_message';
	const MAX_POINTERS   = 20;
	const MAX_EMAILS_PER = 10;

	/**
	 * Register the custom post type and seed default pointers.
	 */
	public function register() {
		assert( function_exists( 'register_post_type' ), 'register_post_type must exist' );
		assert( ! post_type_exists( self::POST_TYPE ), 'CPT must not already be registered' );

		register_post_type( self::POST_TYPE, array(
			'labels'       => array(
				'name'          => __( 'Contact Messages', 'ct-custom' ),
				'singular_name' => __( 'Contact Message', 'ct-custom' ),
			),
			'public'             => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'show_in_rest'       => false,
			'supports'           => array( 'title' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'exclude_from_search' => true,
		) );

		$this->maybe_seed_default_pointers();
	}

	/**
	 * Seed a default contact_us pointer if the option does not exist yet.
	 */
	private function maybe_seed_default_pointers() {
		$existing = get_option( 'bs_custom_contact_pointers' );

		if ( false !== $existing ) {
			return;
		}

		$admin_email = get_option( 'admin_email', '' );

		assert( is_string( $admin_email ), 'Admin email must be a string' );

		$default = array(
			array(
				'slug'   => 'contact_us',
				'label'  => 'Contact Us',
				'emails' => $admin_email ? array( $admin_email ) : array(),
			),
		);

		update_option( 'bs_custom_contact_pointers', wp_json_encode( $default ) );
	}

	/**
	 * Get the contact pointers configuration from options.
	 *
	 * @return array List of pointer arrays with slug, label, emails keys.
	 */
	public static function get_pointers() {
		$raw = get_option( 'bs_custom_contact_pointers', '[]' );

		assert( is_string( $raw ), 'Pointers raw must be a string' );

		$pointers = json_decode( $raw, true );

		if ( ! is_array( $pointers ) ) {
			$pointers = array();
		}

		assert( count( $pointers ) <= self::MAX_POINTERS, 'Pointers count must be within bounds' );

		return $pointers;
	}

	/**
	 * Find a pointer by slug.
	 *
	 * @param string $slug Pointer slug.
	 * @return array|null Pointer data or null.
	 */
	public static function get_pointer_by_slug( $slug ) {
		assert( is_string( $slug ), 'Slug must be a string' );
		assert( strlen( $slug ) > 0, 'Slug must not be empty' );

		$pointers   = self::get_pointers();
		$max_search = self::MAX_POINTERS;
		$count      = 0;

		foreach ( $pointers as $pointer ) {
			if ( $count >= $max_search ) {
				break;
			}
			$count++;

			if ( isset( $pointer['slug'] ) && $pointer['slug'] === $slug ) {
				return $pointer;
			}
		}

		return null;
	}

	/**
	 * Get emails for a pointer slug.
	 *
	 * @param string $slug Pointer slug.
	 * @return array List of email addresses.
	 */
	public static function get_pointer_emails( $slug ) {
		assert( is_string( $slug ), 'Slug must be a string' );

		$pointer = self::get_pointer_by_slug( $slug );

		if ( ! $pointer || ! isset( $pointer['emails'] ) || ! is_array( $pointer['emails'] ) ) {
			return array();
		}

		$emails    = array();
		$max_emails = self::MAX_EMAILS_PER;
		$count     = 0;

		foreach ( $pointer['emails'] as $email ) {
			if ( $count >= $max_emails ) {
				break;
			}
			$count++;

			if ( is_email( $email ) ) {
				$emails[] = sanitize_email( $email );
			}
		}

		return $emails;
	}
}
