<?php
/**
 * Homepage template functions — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to CT_Homepage.
 *
 * @package BS_Custom
 */

use BSCustom\Template\Homepage;

/**
 * Gather hero section data for the homepage.
 *
 * @return array{hero_title: string, hero_description: string, section2_title: string, section2_desc: string}
 */
function bs_custom_get_homepage_hero_data() {
	return Homepage::instance()->get_homepage_hero_data();
}

/**
 * Render image grid items HTML.
 */
function bs_custom_render_image_grid_items() {
	Homepage::instance()->render_image_grid_items();
}
