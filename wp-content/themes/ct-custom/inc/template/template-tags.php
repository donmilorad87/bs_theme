<?php
/**
 * Custom template tags for this theme — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to CT_Template_Hooks.
 *
 * @package BS_Custom
 */

use BSCustom\Template\TemplateHooks;

if ( ! function_exists( 'bs_custom_posted_on' ) ) :
	/**
	 * Prints HTML with meta information for the current post-date/time.
	 */
	function bs_custom_posted_on() {
		TemplateHooks::instance()->posted_on();
	}
endif;

if ( ! function_exists( 'bs_custom_posted_by' ) ) :
	/**
	 * Prints HTML with meta information for the current author.
	 */
	function bs_custom_posted_by() {
		TemplateHooks::instance()->posted_by();
	}
endif;

if ( ! function_exists( 'bs_custom_entry_footer' ) ) :
	/**
	 * Prints HTML with meta information for the categories, tags and comments.
	 */
	function bs_custom_entry_footer() {
		TemplateHooks::instance()->entry_footer();
	}
endif;

if ( ! function_exists( 'bs_custom_post_thumbnail' ) ) :
	/**
	 * Displays an optional post thumbnail.
	 */
	function bs_custom_post_thumbnail() {
		TemplateHooks::instance()->post_thumbnail();
	}
endif;
