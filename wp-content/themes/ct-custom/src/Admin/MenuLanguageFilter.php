<?php
/**
 * Menu Language Filter — language select boxes on nav-menus.php.
 *
 * Pure PHP implementation using output buffering. Adds language
 * select dropdowns to:
 *   1. Manage Locations tab — filters the locations table rows
 *   2. Edit Menus tab — filters "Display location" checkboxes
 *   3. Pages metabox — filters pages by language hierarchy
 *
 * Select boxes use onchange navigation with pre-built URLs in
 * data-url attributes. Filtering is done server-side by injecting
 * a hidden CSS class into non-matching rows/checkboxes, and by
 * hooking into WordPress nav menu item filters.
 *
 * @package BSCustom\Admin
 */

namespace BSCustom\Admin;

class MenuLanguageFilter {

	/** Maximum languages we will iterate over. */
	private const MAX_LANGUAGES = 50;

	/** Maximum pages for the page-language hierarchy BFS. */
	private const MAX_PAGES = 2000;

	/** URL query parameter for the selected language. */
	private const QUERY_PARAM = 'bs_menu_lang';

	/** URL query parameter for the page-metabox language filter. */
	private const NAV_PAGE_QUERY_PARAM = 'bs_nav_page_lang';

	/** @var array Language objects from the language manager. */
	private $languages = array();

	/** @var string Default language iso2 code. */
	private $default_iso2 = 'en';

	/** @var string[] All known iso2 codes. */
	private $known_iso2 = array();

	/** @var string Currently selected language filter key. */
	private $selected_lang = '';

	/** @var string Currently selected page-metabox language (empty = all). */
	private $selected_page_lang = '';

	/** @var array<string, int[]> Cached page IDs per iso2 code. */
	private $page_id_cache = array();

	/** @var bool Whether output buffering is active. */
	private $buffering = false;

	/**
	 * Register WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'load-nav-menus.php', array( $this, 'start_buffer' ) );
		add_filter( 'nav_menu_items_page', array( $this, 'filter_nav_menu_pages' ), 10, 3 );
		add_filter( 'nav_menu_items_page_recent', array( $this, 'filter_nav_menu_pages_recent' ), 10, 4 );
		add_action( 'pre_get_posts', array( $this, 'filter_nav_menu_page_search' ) );
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

		/*
		 * Redirect to include bs_nav_page_lang in the URL if missing.
		 * This ensures the AJAX search referer always carries the param.
		 * Runs before any output so wp_safe_redirect() works.
		 */
		if ( ! isset( $_GET[ self::NAV_PAGE_QUERY_PARAM ] ) ) {
			$redirect_url = add_query_arg( self::NAV_PAGE_QUERY_PARAM, $this->default_iso2 );
			wp_safe_redirect( $redirect_url );
			exit;
		}

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

		/* Determine selected page-metabox language */
		$this->selected_page_lang = sanitize_text_field( wp_unslash( $_GET[ self::NAV_PAGE_QUERY_PARAM ] ) );

		/* Validate: must be 'all' or a known iso2 */
		if ( 'all' !== $this->selected_page_lang
			&& ! in_array( $this->selected_page_lang, $this->known_iso2, true )
		) {
			$this->selected_page_lang = $this->default_iso2;
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

		/* --- Pages metabox: inject page language dropdown --- */
		if ( false !== strpos( $html, 'id="posttype-page"' ) ) {
			$html = $this->inject_page_language_dropdown( $html );
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
	 * Build the menu-language filter as a select box.
	 *
	 * Each <option> carries a data-url attribute with the pre-built
	 * target URL. The onchange handler reads the selected option's
	 * data-url and navigates automatically.
	 *
	 * @return string Select markup.
	 */
	private function build_tab_bar_html() {
		$param    = self::QUERY_PARAM;
		$base_url = remove_query_arg( $param );

		$html = '<div class="ct-menu-lang-filter-bar">';

		$html .= '<label for="ct-menu-lang-select" class="ct-menu-lang-filter-bar__label">';
		$html .= esc_html__( 'Language:', 'ct-custom' );
		$html .= '</label>';

		$html .= '<select id="ct-menu-lang-select" class="ct-menu-lang-filter-bar__select"'
			. ' onchange="window.location.href=this.options[this.selectedIndex].getAttribute(\'data-url\');">';

		$count = 0;

		for ( $i = 0, $len = count( $this->languages ); $i < $len && $count < self::MAX_LANGUAGES; $i++ ) {
			$lang     = $this->languages[ $i ];
			$url      = esc_url( add_query_arg( $param, $lang['iso2'], $base_url ) );
			$selected = ( $this->selected_lang === $lang['iso2'] ) ? ' selected' : '';
			$count++;

			$html .= sprintf(
				'<option value="%s" data-url="%s"%s>%s</option>',
				esc_attr( $lang['iso2'] ),
				esc_attr( $url ),
				$selected,
				esc_html( $lang['name'] )
			);
		}

		/* "Show All" option */
		$all_url = esc_url( add_query_arg( $param, 'all', $base_url ) );
		$html   .= sprintf(
			'<option value="all" data-url="%s"%s>%s</option>',
			esc_attr( $all_url ),
			'all' === $this->selected_lang ? ' selected' : '',
			esc_html__( 'Show All', 'ct-custom' )
		);

		$html .= '</select>';
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
	 * Pages metabox — language dropdown + query filters
	 * ---------------------------------------------------------------- */

	/**
	 * Build the page-language filter as a select box.
	 *
	 * Each <option> carries a data-url attribute with the pre-built
	 * target URL. The onchange handler reads the selected option's
	 * data-url and navigates automatically.
	 *
	 * Cannot use a <form> here because the metabox sits inside
	 * WordPress's outer <form id="nav-menu-meta"> and browsers
	 * ignore nested forms.
	 *
	 * @return string Select markup.
	 */
	private function build_page_dropdown_html() {
		assert( is_array( $this->languages ), 'Languages must be loaded' );
		assert( is_array( $this->known_iso2 ), 'Known iso2 must be loaded' );

		$param    = self::NAV_PAGE_QUERY_PARAM;
		$base_url = remove_query_arg( $param );

		$html = '<div class="ct-nav-page-lang-filter">';

		$html .= '<label for="ct-nav-page-lang" class="ct-nav-page-lang-filter__label">';
		$html .= esc_html__( 'Language:', 'ct-custom' );
		$html .= '</label>';

		$html .= '<select id="ct-nav-page-lang" class="ct-nav-page-lang-filter__select"'
			. ' onchange="window.location.href=this.options[this.selectedIndex].getAttribute(\'data-url\');">';

		/* Language options */
		$lang_count = 0;

		for ( $i = 0, $len = count( $this->languages ); $i < $len && $lang_count < self::MAX_LANGUAGES; $i++ ) {
			$lang     = $this->languages[ $i ];
			$url      = esc_url( add_query_arg( $param, $lang['iso2'], $base_url ) );
			$selected = ( $this->selected_page_lang === $lang['iso2'] ) ? ' selected' : '';
			$lang_count++;

			$html .= sprintf(
				'<option value="%s" data-url="%s"%s>%s</option>',
				esc_attr( $lang['iso2'] ),
				esc_attr( $url ),
				$selected,
				esc_html( $lang['name'] )
			);
		}

		/* "All Languages" option */
		$all_url = esc_url( add_query_arg( $param, 'all', $base_url ) );
		$html   .= sprintf(
			'<option value="all" data-url="%s"%s>%s</option>',
			esc_attr( $all_url ),
			'all' === $this->selected_page_lang ? ' selected' : '',
			esc_html__( 'All Languages', 'ct-custom' )
		);

		$html .= '</select>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Inject the page-language dropdown into the #posttype-page metabox.
	 *
	 * Inserts the dropdown after the opening <div id="posttype-page" …>
	 * and before the <ul> tabs element.
	 *
	 * @param string $html Full page HTML.
	 * @return string Modified HTML.
	 */
	private function inject_page_language_dropdown( $html ) {
		assert( is_string( $html ), 'HTML must be a string' );

		$dropdown = $this->build_page_dropdown_html();

		/* Insert after the opening div#posttype-page, before the <ul> tabs */
		$result = preg_replace(
			'/(<div\s+id="posttype-page"\s+class="posttypediv">)\s*(<ul)/',
			'$1' . "\n" . $dropdown . "\n" . '$2',
			$html,
			1
		);

		return ( null !== $result ) ? $result : $html;
	}

	/**
	 * Filter the "View All" tab in the Pages metabox.
	 *
	 * Hooks into nav_menu_items_page which fires after WP_Query
	 * has fetched pages for the checklist. Uses the page parent
	 * hierarchy (root page slug = iso2, descendants via BFS) to
	 * keep only pages belonging to the selected language.
	 *
	 * @param object[] $posts     Page objects for the checklist.
	 * @param array    $args      WP_Query arguments.
	 * @param \WP_Post_Type $post_type Post type object.
	 * @return object[] Filtered page objects.
	 */
	public function filter_nav_menu_pages( $posts, $args, $post_type ) {
		assert( is_array( $posts ), 'Posts must be an array' );

		return $this->filter_page_list( $posts );
	}

	/**
	 * Filter the "Most Recent" tab in the Pages metabox.
	 *
	 * Hooks into nav_menu_items_page_recent which fires after
	 * WP_Query has fetched the most recent pages.
	 *
	 * @param \WP_Post[] $posts       Page objects for the checklist.
	 * @param array      $args        WP_Query arguments.
	 * @param array      $box         Meta box arguments.
	 * @param array      $recent_args WP_Query arguments for recent tab.
	 * @return \WP_Post[] Filtered page objects.
	 */
	public function filter_nav_menu_pages_recent( $posts, $args, $box, $recent_args ) {
		assert( is_array( $posts ), 'Posts must be an array' );

		return $this->filter_page_list( $posts );
	}

	/**
	 * Shared filtering logic for nav menu page lists.
	 *
	 * Reads the selected language from the request and filters
	 * pages to only include the language root and its descendants.
	 *
	 * @param object[] $posts Array of post objects.
	 * @return object[] Filtered array.
	 */
	private function filter_page_list( $posts ) {
		$lang = $this->get_page_lang_from_request();

		if ( '' === $lang || 'all' === $lang ) {
			return $posts;
		}

		$allowed_ids = $this->get_language_page_ids( $lang );

		if ( empty( $allowed_ids ) ) {
			return $posts;
		}

		/* Build lookup map for fast filtering */
		$id_map = array();
		for ( $i = 0, $len = count( $allowed_ids ); $i < $len && $i < self::MAX_PAGES; $i++ ) {
			$id_map[ $allowed_ids[ $i ] ] = true;
		}

		$filtered = array();
		for ( $i = 0, $len = count( $posts ); $i < $len && $i < self::MAX_PAGES; $i++ ) {
			$post_id = isset( $posts[ $i ]->ID ) ? (int) $posts[ $i ]->ID : 0;

			/* Keep non-real posts (e.g. "Home" placeholder with ID=0) */
			if ( 0 === $post_id || isset( $id_map[ $post_id ] ) ) {
				$filtered[] = $posts[ $i ];
			}
		}

		return $filtered;
	}

	/**
	 * Filter the Search tab results via pre_get_posts.
	 *
	 * Fires for all WP_Query instances. Restricts page search
	 * queries on nav-menus.php (and the AJAX menu-quick-search
	 * handler) to only return pages in the selected language
	 * hierarchy by setting post__in.
	 *
	 * pre_get_posts fires before suppress_filters is checked,
	 * so post__in is respected even when suppress_filters=true.
	 *
	 * @param \WP_Query $query The query being modified.
	 */
	public function filter_nav_menu_page_search( $query ) {
		assert( is_object( $query ), 'Query must be an object' );

		if ( ! is_admin() ) {
			return;
		}

		if ( $query->is_main_query() ) {
			return;
		}

		if ( 'page' !== $query->get( 'post_type' ) ) {
			return;
		}

		/* Only intercept search queries (non-empty 's' parameter) */
		$search_term = $query->get( 's' );
		if ( empty( $search_term ) ) {
			return;
		}

		/* Restrict to nav-menus.php or AJAX menu-quick-search */
		$is_target = false;

		if ( wp_doing_ajax() ) {
			$action = isset( $_POST['action'] ) ? $_POST['action'] : '';
			$is_target = ( 'menu-quick-search' === $action );
		} else {
			global $pagenow;
			$is_target = ( 'nav-menus.php' === ( $pagenow ?? '' ) );
		}

		if ( ! $is_target ) {
			return;
		}

		$lang = $this->get_page_lang_from_request();

		if ( '' === $lang || 'all' === $lang ) {
			return;
		}

		$allowed_ids = $this->get_language_page_ids( $lang );

		if ( empty( $allowed_ids ) ) {
			return;
		}

		$query->set( 'post__in', $allowed_ids );
	}

	/**
	 * Read the page-language filter value from the request.
	 *
	 * Checks $_GET first (regular page loads). For AJAX requests,
	 * falls back to parsing the HTTP Referer header, since the
	 * AJAX POST to admin-ajax.php does not include our GET param.
	 *
	 * When the parameter is not present, defaults to the default
	 * language (so pages are always filtered on first visit).
	 * Returns 'all' only when explicitly set.
	 *
	 * @return string iso2 code, 'all', or '' (empty = use default).
	 */
	private function get_page_lang_from_request() {
		$param = self::NAV_PAGE_QUERY_PARAM;
		$lang  = null;

		/* Direct GET parameter (regular page load) */
		if ( isset( $_GET[ $param ] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );

			if ( 'all' === $raw ) {
				return 'all';
			}

			if ( preg_match( '/^[a-z]{2,3}$/', $raw ) ) {
				$lang = $raw;
			}
		}

		/* AJAX fallback: parse the Referer URL */
		if ( null === $lang && wp_doing_ajax() && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer   = sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$query_str = wp_parse_url( $referer, PHP_URL_QUERY );

			if ( is_string( $query_str ) ) {
				$params = array();
				wp_parse_str( $query_str, $params );

				if ( isset( $params[ $param ] ) ) {
					$raw = sanitize_text_field( $params[ $param ] );

					if ( 'all' === $raw ) {
						return 'all';
					}

					if ( preg_match( '/^[a-z]{2,3}$/', $raw ) ) {
						$lang = $raw;
					}
				}
			}
		}

		/* Default to the default language when param is absent */
		if ( null === $lang ) {
			$lang = $this->get_default_iso2();
		}

		return $lang;
	}

	/**
	 * Get the default language iso2 code.
	 *
	 * Uses the cached value from start_buffer() if available,
	 * otherwise loads it from the language manager directly.
	 *
	 * @return string iso2 code.
	 */
	private function get_default_iso2() {
		if ( '' !== $this->default_iso2 ) {
			return $this->default_iso2;
		}

		$lang_mgr = bs_get_language_manager();
		$default  = $lang_mgr->get_default();

		return ( null !== $default ) ? $default['iso2'] : 'en';
	}

	/**
	 * Get all page IDs belonging to a language via the page hierarchy.
	 *
	 * Finds the language root page (post_name = iso2) and collects
	 * all descendant page IDs using a bounded BFS traversal.
	 * Results are cached per iso2 for the duration of the request.
	 *
	 * @param string $iso2 Two-letter language code.
	 * @return int[] Array of page IDs (root + all descendants).
	 */
	private function get_language_page_ids( $iso2 ) {
		assert( is_string( $iso2 ), 'iso2 must be a string' );

		/* Return cached result if available */
		if ( isset( $this->page_id_cache[ $iso2 ] ) ) {
			return $this->page_id_cache[ $iso2 ];
		}

		global $wpdb;

		assert( $wpdb instanceof \wpdb, 'wpdb must be available' );

		/* Find root page whose slug matches the iso2 code */
		$root_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_name = %s
				   AND post_type = 'page'
				   AND post_status IN ('publish','draft','pending','private')
				 LIMIT 1",
				$iso2
			)
		);

		if ( ! $root_id ) {
			$this->page_id_cache[ $iso2 ] = array();
			return array();
		}

		$root_id = (int) $root_id;

		/* Fetch all page IDs and their parents in one query */
		$all_pages = $wpdb->get_results(
			"SELECT ID, post_parent FROM {$wpdb->posts}
			 WHERE post_type = 'page'
			   AND post_status IN ('publish','draft','pending','private')"
		);

		if ( ! is_array( $all_pages ) ) {
			$this->page_id_cache[ $iso2 ] = array( $root_id );
			return array( $root_id );
		}

		/* Build parent → children map */
		$children_map = array();
		for ( $i = 0, $len = count( $all_pages ); $i < $len && $i < self::MAX_PAGES; $i++ ) {
			$parent = (int) $all_pages[ $i ]->post_parent;

			if ( ! isset( $children_map[ $parent ] ) ) {
				$children_map[ $parent ] = array();
			}

			$children_map[ $parent ][] = (int) $all_pages[ $i ]->ID;
		}

		/* BFS: collect root + all descendants */
		$result = array( $root_id );
		$queue  = array( $root_id );
		$idx    = 0;

		while ( $idx < count( $queue ) && $idx < self::MAX_PAGES ) {
			$current = $queue[ $idx ];
			$idx++;

			if ( ! isset( $children_map[ $current ] ) ) {
				continue;
			}

			$child_count = count( $children_map[ $current ] );

			for ( $c = 0; $c < $child_count && $c < self::MAX_PAGES; $c++ ) {
				$result[] = $children_map[ $current ][ $c ];
				$queue[]  = $children_map[ $current ][ $c ];
			}
		}

		assert( is_array( $result ), 'Result must be an array' );

		$this->page_id_cache[ $iso2 ] = $result;

		return $result;
	}

	/**
	 * Check whether we are on the nav-menus.php admin page.
	 *
	 * @return bool
	 */
	private function is_nav_menus_page() {
		if ( ! is_admin() ) {
			return false;
		}

		global $pagenow;

		return 'nav-menus.php' === $pagenow;
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
		$lang_mgr     = bs_get_language_manager();
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
