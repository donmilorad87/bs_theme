<?php
/**
 * Redirect Manager
 *
 * Handles 301/302 redirects, slug change detection, and hit tracking.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class RedirectManager {

	/** @var int Maximum redirect rules. */
	const MAX_REDIRECTS = 500;

	/**
	 * Boot the redirect manager — hooks onto template_redirect and post_updated.
	 *
	 * @return void
	 */
	public static function boot() {
		assert( function_exists( 'add_action' ), 'add_action must exist' );
		assert( is_callable( array( self::class, 'handle_redirect' ) ), 'handle_redirect must be callable' );

		add_action( 'template_redirect', array( self::class, 'handle_redirect' ), 1 );
		add_action( 'post_updated', array( self::class, 'detect_slug_change' ), 10, 3 );
	}

	/**
	 * Check if the current URL matches a stored redirect and execute it.
	 *
	 * Hooked on `template_redirect` at priority 1.
	 *
	 * @return void
	 */
	public static function handle_redirect() {
		assert( function_exists( 'wp_redirect' ), 'wp_redirect must exist' );

		if ( is_admin() ) {
			return;
		}

		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

		if ( '' === $request_path ) {
			return;
		}

		/* Parse to get just the path */
		$parsed = wp_parse_url( $request_path );
		$path   = isset( $parsed['path'] ) ? $parsed['path'] : $request_path;

		/* Normalize: strip trailing slash for comparison */
		$normalized = rtrim( $path, '/' );

		if ( '' === $normalized ) {
			return;
		}

		$redirects = SeoSettings::getRedirects();

		if ( empty( $redirects ) ) {
			return;
		}

		$max_checks = self::MAX_REDIRECTS;
		$count      = 0;

		foreach ( $redirects as $index => $redirect ) {
			if ( $count >= $max_checks ) {
				break;
			}
			$count++;

			if ( ! isset( $redirect['from'] ) || ! isset( $redirect['to'] ) ) {
				continue;
			}

			$from       = $redirect['from'];
			$type       = isset( $redirect['type'] ) ? (int) $redirect['type'] : 301;
			$from_clean = rtrim( $from, '/' );

			/* Exact match */
			if ( $normalized === $from_clean || $path === $from ) {
				self::increment_hit( $index );
				wp_redirect( $redirect['to'], $type );
				exit;
			}

			/* Regex match (if starts with ~) */
			if ( 0 === strpos( $from, '~' ) ) {
				$pattern = substr( $from, 1 );

				if ( @preg_match( '#' . $pattern . '#', $path ) ) {
					self::increment_hit( $index );
					$target = preg_replace( '#' . $pattern . '#', $redirect['to'], $path, 1 );
					wp_redirect( $target, $type );
					exit;
				}
			}
		}
	}

	/**
	 * Detect slug changes on post update and auto-create redirect.
	 *
	 * Hooked on `post_updated`.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post_after  Post after update.
	 * @param \WP_Post $post_before Post before update.
	 * @return void
	 */
	public static function detect_slug_change( $post_id, $post_after, $post_before ) {
		assert( is_int( $post_id ) || is_numeric( $post_id ), 'post_id must be numeric' );

		if ( ! in_array( $post_after->post_type, array( 'page', 'post' ), true ) ) {
			return;
		}

		if ( 'publish' !== $post_after->post_status ) {
			return;
		}

		$old_slug = isset( $post_before->post_name ) ? $post_before->post_name : '';
		$new_slug = isset( $post_after->post_name ) ? $post_after->post_name : '';

		if ( '' === $old_slug || $old_slug === $new_slug ) {
			return;
		}

		/* Build old and new URLs */
		$old_url = str_replace( $new_slug, $old_slug, get_permalink( $post_id ) );
		$new_url = get_permalink( $post_id );

		if ( $old_url === $new_url ) {
			return;
		}

		/* Parse to relative paths */
		$old_path = wp_parse_url( $old_url, PHP_URL_PATH );
		$new_path = $new_url;

		if ( ! $old_path ) {
			return;
		}

		/* Add redirect if not already at max */
		$redirects = SeoSettings::getRedirects();

		if ( count( $redirects ) >= self::MAX_REDIRECTS ) {
			return;
		}

		/* Check if this redirect already exists */
		$exists     = false;
		$max_checks = self::MAX_REDIRECTS;
		$check_count = 0;

		foreach ( $redirects as $redirect ) {
			if ( $check_count >= $max_checks ) {
				break;
			}
			$check_count++;

			if ( isset( $redirect['from'] ) && $redirect['from'] === $old_path ) {
				$exists = true;
				break;
			}
		}

		if ( $exists ) {
			return;
		}

		$redirects[] = array(
			'from' => $old_path,
			'to'   => $new_path,
			'type' => 301,
			'hits' => 0,
		);

		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );
	}

	/**
	 * Increment the hit counter for a redirect by index.
	 *
	 * @param int $index Redirect array index.
	 * @return void
	 */
	private static function increment_hit( $index ) {
		$raw      = get_option( 'bs_seo_redirects', '[]' );
		$decoded  = json_decode( $raw, true );

		if ( ! is_array( $decoded ) || ! isset( $decoded[ $index ] ) ) {
			return;
		}

		$hits = isset( $decoded[ $index ]['hits'] ) ? (int) $decoded[ $index ]['hits'] : 0;
		$decoded[ $index ]['hits'] = $hits + 1;

		update_option( 'bs_seo_redirects', wp_json_encode( $decoded ) );
	}
}
