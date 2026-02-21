<?php

namespace BSCustom\Widgets;

class WidgetLanguageFilter {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue JS + CSS only on widgets.php.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		assert( is_string( $hook ), 'Hook must be a string' );
		assert( is_admin(), 'Must be in admin context' );

		if ( 'widgets.php' !== $hook ) {
			return;
		}

		$lang_data = $this->build_language_data();

		if ( empty( $lang_data['languages'] ) ) {
			return;
		}

		$js_path  = get_template_directory() . '/assets/widgets/js/widget-language-filter.js';
		$css_path = get_template_directory() . '/assets/widgets/css/widget-language-filter.css';

		$js_ver  = is_readable( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0';
		$css_ver = is_readable( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0';

		wp_enqueue_style(
			'ct-widget-language-filter',
			get_template_directory_uri() . '/assets/widgets/css/widget-language-filter.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'ct-widget-language-filter',
			get_template_directory_uri() . '/assets/widgets/js/widget-language-filter.js',
			array(),
			$js_ver,
			true
		);

		wp_localize_script( 'ct-widget-language-filter', 'ctWidgetLangFilter', $lang_data );
	}

	/**
	 * Build the language data array passed to JavaScript.
	 *
	 * @return array{languages: array, defaultIso2: string, sidebarPrefix: string}
	 */
	private function build_language_data() {
		$lang_mgr  = bs_get_language_manager();
		$enabled   = $lang_mgr->get_enabled();
		$default   = $lang_mgr->get_default();
		$default_iso2 = ( null !== $default ) ? $default['iso2'] : 'en';

		$languages = array();
		$max_langs = 50;
		$count     = 0;

		foreach ( $enabled as $lang ) {
			if ( $count >= $max_langs ) {
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
			'languages'  => $languages,
			'defaultIso2' => $default_iso2,
			'knownIso2'  => wp_list_pluck( $enabled, 'iso2' ),
		);
	}
}
