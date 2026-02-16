<?php
/**
 * Footer template functions — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to CT_Footer.
 *
 * @package CT_Custom
 */

use CTCustom\Template\Footer;

/**
 * Gather all data needed by the footer template.
 *
 * @return array{footer_columns: int, has_footer_widgets: bool, footer_copyright: string, current_language: string}
 */
function ct_custom_get_footer_data() {
	return Footer::instance()->get_footer_data();
}
