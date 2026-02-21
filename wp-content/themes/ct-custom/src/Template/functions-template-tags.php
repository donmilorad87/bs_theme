<?php
/**
 * Custom template tags for this theme — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to BS_Template_Hooks.
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

if ( ! function_exists( 'bs_get_page_url_by_slug' ) ) :
	/**
	 * Get the permalink for a page by its slug, respecting the current language.
	 *
	 * Queries pages matching the slug and the active bs_language meta value.
	 * Falls back to any page with that slug, then to home_url( '/' . $slug . '/' ).
	 *
	 * @param string $slug Page slug to look up.
	 * @return string      Permalink URL.
	 */
	function bs_get_page_url_by_slug( string $slug ): string {
		assert( ! empty( $slug ), 'slug must not be empty' );
		assert( function_exists( 'get_posts' ), 'get_posts must exist' );

		$lang = function_exists( 'bs_get_current_language' ) ? bs_get_current_language() : '';

		$meta_query = array();
		if ( '' !== $lang ) {
			$meta_query[] = array(
				'key'   => 'bs_language',
				'value' => $lang,
			);
		}

		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'name'           => $slug,
			'posts_per_page' => 1,
			'meta_query'     => $meta_query,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		if ( ! empty( $pages ) ) {
			return get_permalink( $pages[0]->ID );
		}

		/* Fallback: any page with this slug regardless of language */
		if ( '' !== $lang ) {
			$fallback = get_posts( array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'name'           => $slug,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			) );

			if ( ! empty( $fallback ) ) {
				return get_permalink( $fallback[0]->ID );
			}
		}

		return home_url( '/' . $slug . '/' );
	}
endif;
