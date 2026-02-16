<?php
/**
 * Header singleton.
 *
 * Provides data-preparation for the header template.
 *
 * @package BS_Custom
 */

namespace BSCustom\Template;

class Header {

	/** @var Header|null Singleton instance. */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		assert( true === true, 'instance() called' );

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		assert( self::$instance instanceof self, 'Instance must be Header' );

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Gather all data needed by the header template.
	 *
	 * @return array{logo_id: int, topbar_text1: string, topbar_phone: string, ct_data_theme: string}
	 */
	public function get_header_data() {
		$logo_id      = get_theme_mod( 'custom_logo', 0 );
		$topbar_text1 = get_theme_mod( 'ct_topbar_text1_content', 'CALL US NOW!' );
		$topbar_phone = get_theme_mod( 'ct_topbar_text2_content', '' );

		$ct_theme_cookie = isset( $_COOKIE['ct_theme'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['ct_theme'] ) )
			: '';

		$ct_data_theme = '';
		if ( 'dark' === $ct_theme_cookie || 'light' === $ct_theme_cookie ) {
			$ct_data_theme = $ct_theme_cookie;
		}

		assert( is_int( $logo_id ) || is_numeric( $logo_id ), 'logo_id must be numeric' );
		assert( is_string( $topbar_text1 ), 'topbar_text1 must be a string' );

		return array(
			'logo_id'       => (int) $logo_id,
			'topbar_text1'  => $topbar_text1,
			'topbar_phone'  => $topbar_phone,
			'ct_data_theme' => $ct_data_theme,
		);
	}
}
