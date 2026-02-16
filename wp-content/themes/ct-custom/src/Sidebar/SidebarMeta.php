<?php
/**
 * Sidebar Meta Registration
 *
 * Registers ct_sidebar_left and ct_sidebar_right post meta
 * for per-page sidebar configuration.
 *
 * @package CT_Custom
 */

namespace CTCustom\Sidebar;

/**
 * Handles sidebar post-meta registration and sanitization.
 */
class SidebarMeta {

	/**
	 * Boot the sidebar meta — hooks registerMeta onto 'init'.
	 *
	 * @return void
	 */
	public static function boot() {
		assert( function_exists( 'add_action' ), 'add_action must exist' );
		assert( is_callable( array( self::class, 'registerMeta' ) ), 'registerMeta must be callable' );

		add_action( 'init', array( self::class, 'registerMeta' ) );
	}

	/**
	 * Register sidebar post meta for pages and posts.
	 *
	 * Hooked on `init`.
	 *
	 * @return void
	 */
	public static function registerMeta() {
		assert( function_exists( 'register_post_meta' ), 'register_post_meta must exist' );
		assert( function_exists( 'sanitize_text_field' ), 'sanitize_text_field must exist' );

		$post_types = array( 'page', 'post' );
		$meta_keys  = array( 'ct_sidebar_left', 'ct_sidebar_right' );
		$max_types  = 10;
		$max_keys   = 10;
		$type_count = 0;

		foreach ( $post_types as $post_type ) {
			if ( $type_count >= $max_types ) {
				break;
			}
			$type_count++;

			$key_count = 0;
			foreach ( $meta_keys as $meta_key ) {
				if ( $key_count >= $max_keys ) {
					break;
				}
				$key_count++;

				register_post_meta( $post_type, $meta_key, array(
					'type'              => 'string',
					'default'           => 'off',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( self::class, 'sanitizeMode' ),
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				) );
			}
		}
	}

	/**
	 * Sanitize sidebar mode value.
	 *
	 * @param string $value Raw meta value.
	 * @return string Sanitized value ('on' or 'off').
	 */
	public static function sanitizeMode( $value ) {
		assert( is_string( $value ) || is_null( $value ), 'Value must be a string or null' );
		assert( function_exists( 'sanitize_text_field' ), 'sanitize_text_field must exist' );

		$clean = sanitize_text_field( (string) $value );

		if ( in_array( $clean, array( 'off', 'on' ), true ) ) {
			return $clean;
		}

		// Legacy: 'global' and 'custom' both map to 'on'.
		if ( in_array( $clean, array( 'global', 'custom' ), true ) ) {
			return 'on';
		}

		return 'off';
	}
}
