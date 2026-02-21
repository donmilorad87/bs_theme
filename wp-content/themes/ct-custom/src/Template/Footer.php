<?php
/**
 * Footer singleton.
 *
 * Provides data-preparation for the footer template.
 *
 * @package BS_Custom
 */

namespace BSCustom\Template;

class Footer {

	/** @var Footer|null Singleton instance. */
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

		assert( self::$instance instanceof self, 'Instance must be Footer' );

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Gather all data needed by the footer template.
	 *
	 * @return array{footer_columns: int, has_footer_widgets: bool, footer_copyright: string, current_language: string}
	 */
	public function get_footer_data() {
		$footer_columns = absint( get_theme_mod( 'bs_footer_columns', 3 ) );

		if ( $footer_columns < 2 ) {
			$footer_columns = 2;
		}
		if ( $footer_columns > 5 ) {
			$footer_columns = 5;
		}

		$current_language = function_exists( 'bs_get_current_language' ) ? bs_get_current_language() : 'en';

		$has_footer_widgets = false;
		$max_columns        = 5;

		for ( $i = 1; $i <= $max_columns; $i++ ) {
			if ( $i > $footer_columns ) {
				continue;
			}
			if ( is_active_sidebar( 'footer-column-' . $i . '-' . $current_language ) ) {
				$has_footer_widgets = true;
				break;
			}
		}

		$copyright_raw = get_theme_mod(
			'bs_footer_copyright',
			'© {year} Blazing Sun'
		);
		$footer_copyright = str_replace( '{year}', date_i18n( 'Y' ), $copyright_raw );

		assert( is_int( $footer_columns ), 'footer_columns must be an int' );
		assert( is_bool( $has_footer_widgets ), 'has_footer_widgets must be a bool' );

		return array(
			'footer_columns'     => $footer_columns,
			'has_footer_widgets' => $has_footer_widgets,
			'footer_copyright'   => $footer_copyright,
			'current_language'   => $current_language,
		);
	}
}
