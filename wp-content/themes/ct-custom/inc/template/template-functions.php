<?php
/**
 * Functions which enhance the theme — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to CT_Template_Hooks.
 * All hook registrations have moved to CT_Template_Hooks::register_hooks().
 *
 * @package CT_Custom
 */

use CTCustom\Template\TemplateHooks;

/**
 * Output breadcrumbs navigation HTML.
 */
function ct_custom_breadcrumbs() {
	TemplateHooks::instance()->breadcrumbs();
}

/**
 * Render an attachment image by ID, inlining SVG content when applicable.
 *
 * @param int    $attachment_id WordPress attachment ID.
 * @param string $size          Image size. Default 'thumbnail'.
 * @param array  $attr          Extra attributes.
 * @return string HTML markup.
 */
function ct_custom_get_attachment_image( $attachment_id, $size = 'thumbnail', $attr = array() ) {
	return TemplateHooks::instance()->get_attachment_image( $attachment_id, $size, $attr );
}
