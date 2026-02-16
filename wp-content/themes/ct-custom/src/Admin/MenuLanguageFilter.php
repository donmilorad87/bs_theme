<?php
/**
 * Menu Language Filter — language tabs on nav-menus.php.
 *
 * Pure PHP implementation using output buffering. Adds language
 * tabs to both:
 *   1. Manage Locations tab — filters the locations table rows
 *   2. Edit Menus tab — filters "Display location" checkboxes
 *      inside the Menu Settings section
 *
 * Tabs are <a> links with a ct_menu_lang query parameter.
 * Filtering is done server-side by injecting a hidden CSS class
 * into non-matching rows/checkboxes before the HTML is sent.
 *
 * Hidden elements remain in the DOM, so form submissions still
 * include all location assignments (display:none doesn't prevent
 * form submission).
 *
 * @package CTCustom\Admin
 */

namespace CTCustom\Admin;

class MenuLanguageFilter {

	/** Maximum languages we will iterate over. */
	private const MAX_LANGUAGES = 50;

	/** URL query parameter for the selected language. */
	private const QUERY_PARAM = 'ct_menu_lang';

	/** @var array Language objects from the language manager. */
	private $languages = array();

	/** @var string Default language iso2 code. */
	private $default_iso2 = 'en';

	/** @var string[] All known iso2 codes. */
	private $known_iso2 = array();

	/** @var string Currently selected language filter key. */
	private $selected_lang = '';

	/** @var bool Whether output buffering is active. */
	private $buffering = false;

	/**
	 * Register WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'load-nav-menus.php', array( $this, 'start_buffer' ) );
	}

	/**
	 * Enqueue CSS on nav-menus.php.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		assert( is_string( $hook ), 'Hook must be a string' );
		assert( is_admin(), 'Must be in admin context' );

		if ( 'nav-menus.php' !== $hook ) {
			return;
		}

		$lang_data = $this->build_language_data();

		if ( empty( $lang_data['languages'] ) ) {
			return;
		}

		$css_path = get_template_directory() . '/assets/admin/css/menu-language-filter.css';
		$css_ver  = is_readable( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0';

		wp_enqueue_style(
			'ct-menu-language-filter',
			get_template_directory_uri() . '/assets/admin/css/menu-language-filter.css',
			array(),
			$css_ver
		);
	}

	/**
	 * Start output buffering on nav-menus.php.
	 *
	 * Fires on the load-nav-menus.php action (before any output).
	 * The buffer is captured and filtered at shutdown.
	 */
	public function start_buffer() {
		$lang_data = $this->build_language_data();

		if ( empty( $lang_data['languages'] ) ) {
			return;
		}

		$this->languages    = $lang_data['languages'];
		$this->default_iso2 = $lang_data['defaultIso2'];
		$this->known_iso2   = $lang_data['knownIso2'];

		/* Determine selected language from query param */
		$this->selected_lang = isset( $_GET[ self::QUERY_PARAM ] )
			? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PARAM ] ) )
			: $this->default_iso2;

		/* Validate: must be a known iso2 or 'all' */
		if ( 'all' !== $this->selected_lang
			&& ! in_array( $this->selected_lang, $this->known_iso2, true )
		) {
			$this->selected_lang = $this->default_iso2;
		}

		$this->buffering = true;
		ob_start();

		add_action( 'shutdown', array( $this, 'end_buffer' ), 0 );
	}

	/**
	 * Capture the output buffer, filter it, and echo the result.
	 *
	 * Runs at shutdown priority 0 (before default handlers).
	 */
	public function end_buffer() {
		if ( ! $this->buffering ) {
			return;
		}

		$this->buffering = false;
		$html = ob_get_clean();

		if ( false === $html ) {
			return;
		}

		echo $this->filter_output( $html );
	}

	/**
	 * Apply tab bar injection and row filtering to the page HTML.
	 *
	 * @param string $html Full page HTML.
	 * @return string Modified HTML.
	 */
	private function filter_output( $html ) {
		assert( is_string( $html ), 'HTML must be a string' );

		$tab_bar = $this->build_tab_bar_html();

		/* --- Manage Locations tab --- */
		if ( false !== strpos( $html, 'id="menu-locations-wrap"' ) ) {
			$html = $this->inject_locations_tab_bar( $html, $tab_bar );

			if ( 'all' !== $this->selected_lang ) {
				$html = $this->filter_location_rows( $html );
			}
		}

		/* --- Edit Menus tab (Menu Settings > Display location) --- */
		if ( false !== strpos( $html, 'menu-theme-locations' ) ) {
			$html = $this->inject_settings_tab_bar( $html, $tab_bar );

			if ( 'all' !== $this->selected_lang ) {
				$html = $this->filter_settings_checkboxes( $html );
			}
		}

		return $html;
	}

	/* ------------------------------------------------------------------
	 * Tab bar HTML
	 * ---------------------------------------------------------------- */

	/**
	 * Build the tab bar HTML with anchor links.
	 *
	 * @return string Tab bar markup.
	 */
	private function build_tab_bar_html() {
		$base_url = remove_query_arg( self::QUERY_PARAM );

		$html  = '<div class="ct-menu-lang-filter-bar" role="tablist"';
		$html .= ' aria-label="' . esc_attr__( 'Filter menu locations by language', 'ct-custom' ) . '">';

		$count = 0;

		foreach ( $this->languages as $lang ) {
			if ( $count >= self::MAX_LANGUAGES ) {
				break;
			}
			$count++;

			$url    = add_query_arg( self::QUERY_PARAM, $lang['iso2'], $base_url );
			$active = ( $this->selected_lang === $lang['iso2'] );
			$class  = 'ct-menu-lang-tab' . ( $active ? ' ct-menu-lang-tab--active' : '' );

			$html .= sprintf(
				'<a href="%s" class="%s" role="tab" aria-selected="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				$active ? 'true' : 'false',
				esc_html( $lang['name'] )
			);
		}

		/* "Show All" tab */
		$all_url    = add_query_arg( self::QUERY_PARAM, 'all', $base_url );
		$all_active = ( 'all' === $this->selected_lang );
		$all_class  = 'ct-menu-lang-tab' . ( $all_active ? ' ct-menu-lang-tab--active' : '' );

		$html .= sprintf(
			'<a href="%s" class="%s" role="tab" aria-selected="%s">%s</a>',
			esc_url( $all_url ),
			esc_attr( $all_class ),
			$all_active ? 'true' : 'false',
			esc_html__( 'Show All', 'ct-custom' )
		);

		$html .= '</div>';

		assert( is_string( $html ), 'Tab bar HTML must be a string' );

		return $html;
	}

	/* ------------------------------------------------------------------
	 * Manage Locations tab
	 * ---------------------------------------------------------------- */

	/**
	 * Insert tab bar inside #menu-locations-wrap, before the form.
	 *
	 * @param string $html    Page HTML.
	 * @param string $tab_bar Tab bar markup.
	 * @return string Modified HTML.
	 */
	private function inject_locations_tab_bar( $html, $tab_bar ) {
		return str_replace(
			'<div id="menu-locations-wrap">',
			'<div id="menu-locations-wrap">' . "\n" . $tab_bar,
			$html
		);
	}

	/**
	 * Add hidden class to location rows that don't match the selected language.
	 * Strip "(Language Name)" suffix from visible rows.
	 *
	 * @param string $html Page HTML.
	 * @return string Modified HTML.
	 */
	private function filter_location_rows( $html ) {
		$count = 0;
		$max   = 200;

		$result = preg_replace_callback(
			'/<tr class="menu-locations-row">([\s\S]*?)<\/tr>\s*<!-- \.menu-locations-row -->/',
			function ( $m ) use ( &$count, $max ) {
				if ( $count >= $max ) {
					return $m[0];
				}
				$count++;

				/* Extract location key from select name */
				if ( ! preg_match( '/name="menu-locations\[([^\]]+)\]"/', $m[1], $km ) ) {
					return $m[0];
				}

				$iso2 = $this->extract_iso2_from_key( $km[1] );

				if ( null === $iso2 || $iso2 !== $this->selected_lang ) {
					return str_replace(
						'class="menu-locations-row"',
						'class="menu-locations-row ct-menu-lang-filter-hidden"',
						$m[0]
					);
				}

				return $this->strip_lang_suffix( $m[0], $iso2 );
			},
			$html
		);

		return ( null !== $result ) ? $result : $html;
	}

	/* ------------------------------------------------------------------
	 * Edit Menus tab — Menu Settings > Display location
	 * ---------------------------------------------------------------- */

	/**
	 * Insert tab bar after the <legend> inside .menu-theme-locations.
	 *
	 * @param string $html    Page HTML.
	 * @param string $tab_bar Tab bar markup.
	 * @return string Modified HTML.
	 */
	private function inject_settings_tab_bar( $html, $tab_bar ) {
		$result = preg_replace(
			'/(<fieldset\s+class="menu-settings-group\s+menu-theme-locations">\s*<legend[^>]*>[^<]*<\/legend>)/',
			'$1' . "\n" . $tab_bar,
			$html,
			1
		);

		return ( null !== $result ) ? $result : $html;
	}

	/**
	 * Add hidden class to checkbox wrappers that don't match the selected
	 * language. Only modifies checkboxes inside the .menu-theme-locations
	 * fieldset (matched via the menu-locations[] input name).
	 *
	 * @param string $html Page HTML.
	 * @return string Modified HTML.
	 */
	private function filter_settings_checkboxes( $html ) {
		/* Extract and process only the .menu-theme-locations fieldset */
		$result = preg_replace_callback(
			'/(<fieldset\s+class="menu-settings-group\s+menu-theme-locations">)([\s\S]*?)(<\/fieldset>)/',
			function ( $fm ) {
				$content = $fm[2];
				$count   = 0;
				$max     = 200;

				$content = preg_replace_callback(
					'/<div class="menu-settings-input checkbox-input">([\s\S]*?)<\/div>/',
					function ( $m ) use ( &$count, $max ) {
						if ( $count >= $max ) {
							return $m[0];
						}
						$count++;

						/* Only process divs with a menu-locations[] input */
						if ( ! preg_match( '/name="menu-locations\[([^\]]+)\]"/', $m[1], $km ) ) {
							return $m[0];
						}

						$iso2 = $this->extract_iso2_from_key( $km[1] );

						if ( null === $iso2 || $iso2 !== $this->selected_lang ) {
							return str_replace(
								'class="menu-settings-input checkbox-input"',
								'class="menu-settings-input checkbox-input ct-menu-lang-filter-hidden"',
								$m[0]
							);
						}

						return $this->strip_lang_suffix( $m[0], $iso2 );
					},
					$content
				);

				if ( null === $content ) {
					return $fm[0];
				}

				return $fm[1] . $content . $fm[3];
			},
			$html,
			1
		);

		return ( null !== $result ) ? $result : $html;
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	/**
	 * Extract the iso2 language code from a location key.
	 *
	 * Location keys follow the pattern: {base-menu}-{iso2}
	 * e.g. "main-menu-en", "footer-copyright-menu-sr"
	 *
	 * @param string $key Location key.
	 * @return string|null iso2 code or null if no match.
	 */
	private function extract_iso2_from_key( $key ) {
		assert( is_string( $key ), 'Key must be a string' );

		$max = count( $this->known_iso2 );

		for ( $i = 0; $i < $max; $i++ ) {
			$iso2   = $this->known_iso2[ $i ];
			$suffix = '-' . $iso2;

			if ( substr( $key, -strlen( $suffix ) ) === $suffix ) {
				return $iso2;
			}
		}

		return null;
	}

	/**
	 * Get the native language name for an iso2 code.
	 *
	 * @param string $iso2 Two-letter language code.
	 * @return string|null Language name or null.
	 */
	private function get_lang_name( $iso2 ) {
		$max   = count( $this->languages );
		$count = 0;

		foreach ( $this->languages as $lang ) {
			if ( $count >= $max ) {
				break;
			}
			$count++;

			if ( $lang['iso2'] === $iso2 ) {
				return $lang['name'];
			}
		}

		return null;
	}

	/**
	 * Strip "(Language Name)" suffix from label text in an HTML fragment.
	 *
	 * Targets text like "Main Menu (English)" and removes " (English)".
	 *
	 * @param string $html HTML fragment (row or checkbox div).
	 * @param string $iso2 Language code to look up the name.
	 * @return string Modified HTML.
	 */
	private function strip_lang_suffix( $html, $iso2 ) {
		$lang_name = $this->get_lang_name( $iso2 );

		if ( null === $lang_name ) {
			return $html;
		}

		$suffix = ' (' . $lang_name . ')';

		/* Remove suffix immediately before </label> */
		return str_replace(
			esc_html( $suffix ) . '</label>',
			'</label>',
			$html
		);
	}

	/**
	 * Build the language data array.
	 *
	 * @return array{languages: array, defaultIso2: string, knownIso2: array}
	 */
	private function build_language_data() {
		$lang_mgr     = ct_get_language_manager();
		$enabled      = $lang_mgr->get_enabled();
		$default      = $lang_mgr->get_default();
		$default_iso2 = ( null !== $default ) ? $default['iso2'] : 'en';

		$languages = array();
		$count     = 0;

		foreach ( $enabled as $lang ) {
			if ( $count >= self::MAX_LANGUAGES ) {
				break;
			}
			$count++;

			$languages[] = array(
				'iso2'      => $lang['iso2'],
				'name'      => $lang['native_name'],
				'isDefault' => ( $lang['iso2'] === $default_iso2 ),
			);
		}

		assert( is_array( $languages ), 'Languages must be an array' );
		assert( is_string( $default_iso2 ), 'Default ISO2 must be a string' );

		return array(
			'languages'   => $languages,
			'defaultIso2' => $default_iso2,
			'knownIso2'   => wp_list_pluck( $enabled, 'iso2' ),
		);
	}
}
