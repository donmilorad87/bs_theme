<?php
/**
 * Language template functions — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to BS_Language
 * and BS_Template_Hooks. All hook registrations have moved to
 * BS_Template_Hooks::register_hooks().
 *
 * @package BS_Custom
 */

use BSCustom\Template\Language;

/**
 * Build the language switcher data array.
 *
 * @return array{items: array, current_iso2: string, is_multilingual: bool}
 */
function bs_custom_get_language_switcher_data(): array {
	if ( ! bs_languages_enabled() ) {
		return array(
			'items'          => array(),
			'current_iso2'   => '',
			'is_multilingual' => false,
		);
	}

	return Language::instance()->get_language_switcher_data();
}

/**
 * Check if the language system is enabled.
 *
 * @return bool
 */
function bs_languages_enabled(): bool {
	$enabled = get_theme_mod( 'bs_languages_enabled', true );

	if ( is_string( $enabled ) ) {
		$normalized = strtolower( $enabled );
		if ( '0' === $enabled || 'off' === $normalized || 'false' === $normalized ) {
			return false;
		}
	}

	return ! empty( $enabled );
}

/**
 * Check if the site has multiple languages enabled.
 *
 * @return bool
 */
function bs_is_multilingual(): bool {
	if ( ! bs_languages_enabled() ) {
		return false;
	}

	return Language::instance()->is_multilingual();
}

/**
 * Get the correct menu location for the current language.
 *
 * @param string $base_location Base menu location (e.g. 'main-menu').
 * @return string Resolved menu location slug.
 */
function bs_get_menu_location( string $base_location ): string {
	if ( ! bs_languages_enabled() ) {
		return $base_location;
	}

	return Language::instance()->get_menu_location( $base_location );
}

/**
 * Render the language switcher dropdown.
 *
 * @param string $extra_classes Extra classes for the switcher wrapper.
 * @return void
 */
function bs_custom_render_language_switcher( $extra_classes = '' ) {
	if ( ! bs_is_multilingual() ) {
		return;
	}

	$bs_switcher_data  = bs_custom_get_language_switcher_data();
	$bs_switcher_class = 'ct-lang-switcher';
	if ( is_string( $extra_classes ) && '' !== trim( $extra_classes ) ) {
		$bs_switcher_class .= ' ' . trim( $extra_classes );
	}

	include get_template_directory() . '/template-parts/language-switcher.php';
}
