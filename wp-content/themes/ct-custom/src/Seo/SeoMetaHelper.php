<?php
/**
 * SEO Meta Helper
 *
 * Provides a priority cascade for retrieving SEO values:
 * page meta -> global option -> WP default -> empty string.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class SeoMetaHelper {

	/** @var string Option key prefix for global SEO defaults. */
	const OPTION_PREFIX = 'bs_seo_global_';

	/**
	 * Get an SEO value with priority cascade.
	 *
	 * 1. Per-page post meta (bs_seo_*)
	 * 2. Global SEO option (bs_seo_global_*)
	 * 3. Empty string fallback
	 *
	 * @param int    $post_id Post ID (0 to skip page-level lookup).
	 * @param string $field   Field name without prefix (e.g. 'title', 'description').
	 * @return string Resolved value.
	 */
	public static function get( $post_id, $field ) {
		assert( is_int( $post_id ) || is_numeric( $post_id ), 'post_id must be numeric' );
		assert( is_string( $field ) && '' !== $field, 'field must be a non-empty string' );

		$post_id  = (int) $post_id;
		$meta_key = 'bs_seo_' . $field;

		/* 1. Per-page post meta */
		if ( $post_id > 0 ) {
			$meta_value = get_post_meta( $post_id, $meta_key, true );

			if ( is_string( $meta_value ) && '' !== $meta_value ) {
				return $meta_value;
			}
		}

		/* 2. Global SEO option */
		$option_key   = self::OPTION_PREFIX . $field;
		$option_value = get_option( $option_key, '' );

		if ( is_string( $option_value ) && '' !== $option_value ) {
			return $option_value;
		}

		/* 3. Empty string fallback */
		return '';
	}

	/**
	 * Get an integer SEO value with cascade.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   Field name without prefix.
	 * @return int Resolved value.
	 */
	public static function getInt( $post_id, $field ) {
		assert( is_int( $post_id ) || is_numeric( $post_id ), 'post_id must be numeric' );
		assert( is_string( $field ) && '' !== $field, 'field must be a non-empty string' );

		$post_id  = (int) $post_id;
		$meta_key = 'bs_seo_' . $field;

		if ( $post_id > 0 ) {
			$meta_value = (int) get_post_meta( $post_id, $meta_key, true );

			if ( $meta_value > 0 ) {
				return $meta_value;
			}
		}

		$option_key   = self::OPTION_PREFIX . $field;
		$option_value = (int) get_option( $option_key, 0 );

		return $option_value > 0 ? $option_value : 0;
	}

	/**
	 * Resolve title template placeholders.
	 *
	 * Supported: %%title%%, %%sitename%%, %%sep%%, %%page%%
	 *
	 * @param string $template Template string with placeholders.
	 * @param int    $post_id  Current post ID.
	 * @return string Resolved title.
	 */
	public static function resolveTitle( $template, $post_id = 0 ) {
		assert( is_string( $template ), 'template must be a string' );
		assert( is_int( $post_id ) || is_numeric( $post_id ), 'post_id must be numeric' );

		if ( '' === $template ) {
			return '';
		}

		$post_id   = (int) $post_id;
		$post_title = '';

		if ( $post_id > 0 ) {
			$post_title = get_the_title( $post_id );
		}

		$site_name = get_bloginfo( 'name' );
		$separator = self::get( 0, 'title_separator' );

		if ( '' === $separator ) {
			$separator = '-';
		}

		$page_num = '';
		$paged    = get_query_var( 'paged', 0 );

		if ( $paged > 1 ) {
			$page_num = sprintf( __( 'Page %d', 'ct-custom' ), $paged );
		}

		$replacements = array(
			'%%title%%'    => $post_title,
			'%%sitename%%' => $site_name,
			'%%sep%%'      => $separator,
			'%%page%%'     => $page_num,
		);

		$max_replacements = 10;
		$count            = 0;
		$result           = $template;

		foreach ( $replacements as $placeholder => $value ) {
			if ( $count >= $max_replacements ) {
				break;
			}
			$count++;

			$result = str_replace( $placeholder, $value, $result );
		}

		/* Clean up double spaces and trim */
		$result = preg_replace( '/\s+/', ' ', $result );

		return trim( $result );
	}
}
