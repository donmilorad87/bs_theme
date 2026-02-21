<?php
/**
 * Header template functions — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to BS_Header.
 *
 * @package BS_Custom
 */

use BSCustom\Template\Header;

/**
 * Gather all data needed by the header template.
 *
 * @return array{logo_id: int, topbar_text1: string, topbar_phone: string, bs_data_theme: string, bs_theme_toggle_enabled: bool, bs_theme_toggle_position: string}
 */
function bs_custom_get_header_data() {
	return Header::instance()->get_header_data();
}

/**
 * Render the theme toggle button.
 *
 * @param string $extra_classes Extra classes for the button.
 * @return void
 */
function bs_custom_render_theme_toggle( $extra_classes = '' ) {
	$enabled = get_theme_mod( 'bs_theme_toggle_enabled', true );
	if ( empty( $enabled ) ) {
		return;
	}

	$classes = trim( 'theme-toggle dif aic cp p0 ' . $extra_classes );
	?>
	<button type="button" class="<?php echo esc_attr( $classes ); ?>" role="switch" aria-checked="false" aria-label="<?php esc_attr_e( 'Toggle dark/light mode', 'ct-custom' ); ?>">
		<span class="theme-toggle__track df aic jcsb">
			<svg class="theme-toggle__icon theme-toggle__icon--sun" xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><circle cx="12" cy="12" r="5"/><g stroke="currentColor" stroke-width="2" fill="none"><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></g></svg>
			<svg class="theme-toggle__icon theme-toggle__icon--moon" xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
			<span class="theme-toggle__thumb"></span>
		</span>
	</button>
	<?php
}
