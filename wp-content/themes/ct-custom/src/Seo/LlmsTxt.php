<?php
/**
 * LLMs.txt Generator
 *
 * Generates /llms.txt content map for AI crawlers.
 * Includes site info, main pages, blog posts, categories, tags.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class LlmsTxt {

	/** @var int Maximum pages to include. */
	const MAX_PAGES = 200;

	/** @var int Maximum posts to include. */
	const MAX_POSTS = 200;

	/** @var int Maximum categories/tags to include. */
	const MAX_TERMS = 50;

	/** @var int Transient TTL in seconds (1 hour). */
	const CACHE_TTL = 3600;

	/**
	 * Boot cache invalidation hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		assert( function_exists( 'add_action' ), 'add_action must exist' );

		add_action( 'save_post', array( self::class, 'invalidateCache' ) );
		add_action( 'delete_post', array( self::class, 'invalidateCache' ) );
		add_action( 'transition_post_status', array( self::class, 'invalidateCache' ) );
	}

	/**
	 * Delete the cached llms.txt transient.
	 *
	 * @return void
	 */
	public static function invalidateCache() {
		delete_transient( 'bs_seo_llms_txt' );
	}

	/**
	 * Generate the llms.txt content.
	 *
	 * @return string Full llms.txt content.
	 */
	public static function generate() {
		assert( function_exists( 'get_bloginfo' ), 'WordPress must be loaded' );
		assert( function_exists( 'home_url' ), 'home_url must exist' );

		$cached = get_transient( 'bs_seo_llms_txt' );

		if ( false !== $cached ) {
			return $cached;
		}

		$lines = array();

		/* Header */
		$site_name   = get_bloginfo( 'name' );
		$description = get_bloginfo( 'description' );
		$site_url    = home_url( '/' );

		$lines[] = '# ' . $site_name;
		$lines[] = '';

		if ( '' !== $description ) {
			$lines[] = '> ' . $description;
			$lines[] = '';
		}

		$lines[] = '## Site Information';
		$lines[] = '';
		$lines[] = '- URL: ' . $site_url;
		$lines[] = '- Sitemap: ' . home_url( '/sitemap_index.xml' );
		$lines[] = '';

		/* Main pages */
		$lines[] = '## Main Pages';
		$lines[] = '';

		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => self::MAX_PAGES,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'bs_seo_robots_index',
					'value'   => 'noindex',
					'compare' => '!=',
				),
				array(
					'key'     => 'bs_seo_robots_index',
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		$page_count = 0;

		foreach ( $pages as $page ) {
			if ( $page_count >= self::MAX_PAGES ) {
				break;
			}
			$page_count++;

			$url   = get_permalink( $page->ID );
			$title = $page->post_title;

			if ( '' === $title ) {
				continue;
			}

			$lines[] = '- [' . $title . '](' . $url . ')';
		}

		$lines[] = '';

		/* Blog posts */
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => self::MAX_POSTS,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'bs_seo_robots_index',
					'value'   => 'noindex',
					'compare' => '!=',
				),
				array(
					'key'     => 'bs_seo_robots_index',
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		if ( ! empty( $posts ) ) {
			$lines[] = '## Blog Posts';
			$lines[] = '';

			$post_count = 0;

			foreach ( $posts as $post ) {
				if ( $post_count >= self::MAX_POSTS ) {
					break;
				}
				$post_count++;

				$url   = get_permalink( $post->ID );
				$title = $post->post_title;

				if ( '' === $title ) {
					continue;
				}

				$desc = '';
				$seo_desc = get_post_meta( $post->ID, 'bs_seo_description', true );

				if ( '' !== $seo_desc ) {
					$desc = ': ' . $seo_desc;
				}

				$lines[] = '- [' . $title . '](' . $url . ')' . $desc;
			}

			$lines[] = '';
		}

		/* Categories */
		$categories = get_categories( array(
			'hide_empty' => true,
			'number'     => self::MAX_TERMS,
		) );

		if ( ! empty( $categories ) ) {
			$lines[] = '## Categories';
			$lines[] = '';

			$cat_count = 0;

			foreach ( $categories as $cat ) {
				if ( $cat_count >= self::MAX_TERMS ) {
					break;
				}
				$cat_count++;

				$lines[] = '- [' . $cat->name . '](' . get_category_link( $cat->term_id ) . ')';
			}

			$lines[] = '';
		}

		/* Tags */
		$tags = get_tags( array(
			'hide_empty' => true,
			'number'     => self::MAX_TERMS,
		) );

		if ( ! empty( $tags ) ) {
			$lines[] = '## Tags';
			$lines[] = '';

			$tag_count = 0;

			foreach ( $tags as $tag ) {
				if ( $tag_count >= self::MAX_TERMS ) {
					break;
				}
				$tag_count++;

				$lines[] = '- [' . $tag->name . '](' . get_tag_link( $tag->term_id ) . ')';
			}

			$lines[] = '';
		}

		/* Languages */
		if ( function_exists( 'bs_get_language_manager' ) ) {
			$lang_mgr  = bs_get_language_manager();
			$languages = $lang_mgr->get_enabled();

			if ( count( $languages ) > 1 ) {
				$lines[] = '## Available Languages';
				$lines[] = '';

				$lang_count = 0;
				$max_langs  = 20;

				foreach ( $languages as $lang ) {
					if ( $lang_count >= $max_langs ) {
						break;
					}
					$lang_count++;

					$lines[] = '- ' . $lang['native_name'] . ' (' . $lang['iso2'] . ')';
				}

				$lines[] = '';
			}
		}

		/* Custom additions */
		$custom = get_option( 'bs_seo_llms_custom', '' );

		if ( '' !== $custom ) {
			$lines[] = '## Additional Information';
			$lines[] = '';
			$lines[] = $custom;
			$lines[] = '';
		}

		$content = implode( "\n", $lines );

		set_transient( 'bs_seo_llms_txt', $content, self::CACHE_TTL );

		return $content;
	}

	/**
	 * Output the llms.txt response.
	 *
	 * @return void
	 */
	public static function serve() {
		$enabled = get_option( 'bs_seo_llms_enabled', 'on' );

		if ( 'on' !== $enabled ) {
			status_header( 404 );
			exit;
		}

		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );

		echo self::generate();
		exit;
	}
}
