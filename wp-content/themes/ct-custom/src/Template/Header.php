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
	 * @return array{logo_id: int, topbar_text1: string, topbar_phone: string, bs_data_theme: string, bs_theme_toggle_enabled: bool, bs_theme_toggle_position: string}
	 */
	public function get_header_data() {
		$logo_id      = get_theme_mod( 'custom_logo', 0 );
		$topbar_text1 = get_theme_mod( 'bs_topbar_text1_content', 'CALL US NOW!' );
		$topbar_phone = get_theme_mod( 'bs_topbar_text2_content', '' );

		$theme_toggle_enabled = get_theme_mod( 'bs_theme_toggle_enabled', true );
		$theme_color_mode     = get_theme_mod( 'bs_theme_color_mode', 'light' );
		$theme_color_mode     = ( 'dark' === $theme_color_mode ) ? 'dark' : 'light';
		$theme_toggle_position = get_theme_mod( 'bs_theme_toggle_position', 'header' );
		$theme_toggle_position = is_string( $theme_toggle_position ) ? $theme_toggle_position : 'header';

		$bs_theme_cookie = isset( $_COOKIE['bs_theme'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['bs_theme'] ) )
			: '';

		$bs_data_theme = $theme_color_mode;
		if ( ! empty( $theme_toggle_enabled ) ) {
			if ( 'dark' === $bs_theme_cookie || 'light' === $bs_theme_cookie ) {
				$bs_data_theme = $bs_theme_cookie;
			}
		}

		assert( is_int( $logo_id ) || is_numeric( $logo_id ), 'logo_id must be numeric' );
		assert( is_string( $topbar_text1 ), 'topbar_text1 must be a string' );

		return array(
			'logo_id'                  => (int) $logo_id,
			'topbar_text1'             => $topbar_text1,
			'topbar_phone'             => $topbar_phone,
			'bs_data_theme'            => $bs_data_theme,
			'bs_theme_toggle_enabled'  => (bool) $theme_toggle_enabled,
			'bs_theme_toggle_position' => $theme_toggle_position,
		);
	}
}
