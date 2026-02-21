<?php
/**
 * The sidebar containing the main widget area
 *
 * This file is kept for backward compatibility but sidebars
 * are now rendered via bs_sidebar_render() in templates.
 *
 * @package BS_Custom
 */

if ( function_exists( 'bs_sidebar_render' ) ) {
	bs_sidebar_render( 'left' );
	bs_sidebar_render( 'right' );
	return;
}
