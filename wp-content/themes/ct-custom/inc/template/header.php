<?php
/**
 * Header template functions — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to CT_Header.
 *
 * @package BS_Custom
 */

use BSCustom\Template\Header;

/**
 * Gather all data needed by the header template.
 *
 * @return array{logo_id: int, topbar_text1: string, topbar_phone: string, ct_data_theme: string}
 */
function bs_custom_get_header_data() {
	return Header::instance()->get_header_data();
}
