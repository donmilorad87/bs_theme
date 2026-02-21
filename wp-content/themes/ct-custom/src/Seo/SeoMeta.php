<?php
/**
 * SEO Meta Registration
 *
 * Registers all bs_seo_* post meta fields for per-page SEO configuration.
 * Follows the SidebarMeta::boot() pattern exactly.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class SeoMeta {

	/**
	 * Boot the SEO meta — hooks registerMeta onto 'init'.
	 *
	 * @return void
	 */
	public static function boot() {
		assert( function_exists( 'add_action' ), 'add_action must exist' );
		assert( is_callable( array( self::class, 'registerMeta' ) ), 'registerMeta must be callable' );

		add_action( 'init', array( self::class, 'registerMeta' ) );
		add_action( 'save_post', array( self::class, 'syncSitemapExcludedToOption' ), 20, 1 );
	}

	/**
	 * Register all SEO post meta for pages and posts.
	 *
	 * Hooked on `init`.
	 *
	 * @return void
	 */
	public static function registerMeta() {
		assert( function_exists( 'register_post_meta' ), 'register_post_meta must exist' );
		assert( function_exists( 'sanitize_text_field' ), 'sanitize_text_field must exist' );

		$post_types = array( 'page', 'post' );

		$string_meta_keys = array(
			'bs_seo_title',
			'bs_seo_description',
			'bs_seo_keywords',
			'bs_seo_canonical',
			'bs_seo_focus_keyword',
			'bs_seo_robots_index',
			'bs_seo_robots_follow',
			'bs_seo_robots_advanced',
			'bs_seo_og_title',
			'bs_seo_og_description',
			'bs_seo_og_image',
			'bs_seo_og_type',
			'bs_seo_twitter_card',
			'bs_seo_twitter_title',
			'bs_seo_twitter_description',
			'bs_seo_twitter_image',
			'bs_seo_schema_type',
			'bs_seo_schema_data',
			'bs_seo_breadcrumb_hide',
			'bs_seo_sitemap_excluded',
		);

		$integer_meta_keys = array(
			'bs_seo_og_image_id',
			'bs_seo_twitter_image_id',
			'bs_seo_score',
		);

		$max_types   = 10;
		$max_strings = 30;
		$max_ints    = 10;
		$type_count  = 0;

		foreach ( $post_types as $post_type ) {
			if ( $type_count >= $max_types ) {
				break;
			}
			$type_count++;

			$string_count = 0;
			foreach ( $string_meta_keys as $meta_key ) {
				if ( $string_count >= $max_strings ) {
					break;
				}
				$string_count++;

				register_post_meta( $post_type, $meta_key, array(
					'type'              => 'string',
					'default'           => '',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( self::class, 'sanitizeString' ),
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				) );
			}

			$int_count = 0;
			foreach ( $integer_meta_keys as $meta_key ) {
				if ( $int_count >= $max_ints ) {
					break;
				}
				$int_count++;

				register_post_meta( $post_type, $meta_key, array(
					'type'              => 'integer',
					'default'           => 0,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( self::class, 'sanitizeInteger' ),
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				) );
			}
		}
	}

	/**
	 * Sanitize a string meta value.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string Sanitized string.
	 */
	public static function sanitizeString( $value ) {
		assert( is_string( $value ) || is_null( $value ), 'Value must be a string or null' );
		assert( function_exists( 'sanitize_text_field' ), 'sanitize_text_field must exist' );

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize an integer meta value.
	 *
	 * @param mixed $value Raw meta value.
	 * @return int Sanitized non-negative integer.
	 */
	public static function sanitizeInteger( $value ) {
		assert( is_numeric( $value ) || empty( $value ), 'Value must be numeric or empty' );

		$clean = (int) $value;

		return $clean >= 0 ? $clean : 0;
	}

	/**
	 * Get all registered SEO string meta keys.
	 *
	 * @return array List of meta key strings.
	 */
	public static function getStringKeys() {
		return array(
			'bs_seo_title',
			'bs_seo_description',
			'bs_seo_keywords',
			'bs_seo_canonical',
			'bs_seo_focus_keyword',
			'bs_seo_robots_index',
			'bs_seo_robots_follow',
			'bs_seo_robots_advanced',
			'bs_seo_og_title',
			'bs_seo_og_description',
			'bs_seo_og_image',
			'bs_seo_og_type',
			'bs_seo_twitter_card',
			'bs_seo_twitter_title',
			'bs_seo_twitter_description',
			'bs_seo_twitter_image',
			'bs_seo_schema_type',
			'bs_seo_schema_data',
			'bs_seo_breadcrumb_hide',
			'bs_seo_sitemap_excluded',
		);
	}

	/**
	 * Get all registered SEO integer meta keys.
	 *
	 * @return array List of meta key strings.
	 */
	public static function getIntegerKeys() {
		return array(
			'bs_seo_og_image_id',
			'bs_seo_twitter_image_id',
			'bs_seo_score',
		);
	}

	/**
	 * Sync the bs_seo_sitemap_excluded post meta to the global option list.
	 *
	 * Hooked on save_post (priority 20) so it fires after the block editor
	 * has written the meta via the REST API. Keeps the global option used by
	 * SitemapPages::build() in sync with the per-post meta used by the editor.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return void
	 */
	public static function syncSitemapExcludedToOption( $post_id ) {
		assert( is_int( $post_id ) || is_numeric( $post_id ), 'post_id must be numeric' );
		assert( function_exists( 'get_post_meta' ), 'get_post_meta must exist' );

		/* Skip auto-saves and revisions */
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return;
		}

		$is_excluded  = 'on' === get_post_meta( $post_id, 'bs_seo_sitemap_excluded', true );
		$excluded_raw = get_option( 'bs_seo_sitemap_excluded', '' );
		$excluded_ids = array_values( array_filter( array_map( 'intval', explode( ',', $excluded_raw ) ) ) );

		$in_list = in_array( $post_id, $excluded_ids, true );

		if ( $is_excluded === $in_list ) {
			/* Already in sync — nothing to do */
			return;
		}

		if ( $is_excluded ) {
			$excluded_ids[] = (int) $post_id;
		} else {
			$max   = 1000;
			$count = 0;
			$new   = array();

			foreach ( $excluded_ids as $eid ) {
				if ( $count >= $max ) {
					break;
				}
				$count++;

				if ( $eid !== (int) $post_id ) {
					$new[] = $eid;
				}
			}

			$excluded_ids = $new;
		}

		update_option( 'bs_seo_sitemap_excluded', implode( ',', $excluded_ids ) );

		/* Bust sitemap cache for this post type + language */
		delete_transient( 'bs_sitemap_' . $post->post_type );
		\BSCustom\Seo\SitemapIndex::clearCache();

		$post_lang = get_post_meta( $post_id, 'bs_language', true );

		if ( '' !== $post_lang ) {
			delete_transient( 'bs_sitemap_' . $post->post_type . '_' . $post_lang );
		}
	}
}
