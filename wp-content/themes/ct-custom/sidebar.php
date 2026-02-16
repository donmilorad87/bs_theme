<?php
/**
 * The sidebar containing the main widget area
 *
 * This file is kept for backward compatibility but sidebars
 * are now rendered via ct_sidebar_render() in templates.
 *
 * @package CT_Custom
 */

if ( function_exists( 'ct_sidebar_render' ) ) {
	ct_sidebar_render( 'left' );
	ct_sidebar_render( 'right' );
	return;
}
