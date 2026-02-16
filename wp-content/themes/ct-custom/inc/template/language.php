<?php
/**
 * Language template functions — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to CT_Language
 * and CT_Template_Hooks. All hook registrations have moved to
 * CT_Template_Hooks::register_hooks().
 *
 * @package CT_Custom
 */

use CTCustom\Template\Language;

/**
 * Build the language switcher data array.
 *
 * @return array{items: array, current_iso2: string, is_multilingual: bool}
 */
function ct_custom_get_language_switcher_data(): array {
	return Language::instance()->get_language_switcher_data();
}

/**
 * Check if the site has multiple languages enabled.
 *
 * @return bool
 */
function ct_is_multilingual(): bool {
	return Language::instance()->is_multilingual();
}

/**
 * Get the correct menu location for the current language.
 *
 * @param string $base_location Base menu location (e.g. 'main-menu').
 * @return string Resolved menu location slug.
 */
function ct_get_menu_location( string $base_location ): string {
	return Language::instance()->get_menu_location( $base_location );
}
