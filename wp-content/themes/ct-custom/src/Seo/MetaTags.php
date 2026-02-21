<?php
/**
 * MetaTags Trait
 *
 * Outputs SEO meta tags in <head>: title filter, description,
 * keywords, robots, Open Graph, Twitter Cards, canonical.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

trait MetaTags {

	/**
	 * Filter document title parts.
	 *
	 * Hooked on `document_title_parts`.
	 *
	 * @param array $title_parts Title parts array.
	 * @return array Modified title parts.
	 */
	public function filter_title_parts( $title_parts ) {
		assert( is_array( $title_parts ), 'title_parts must be an array' );
		assert( function_exists( 'is_singular' ), 'WordPress must be loaded' );

		if ( ! is_singular() ) {
			return $title_parts;
		}

		$post_id   = (int) get_the_ID();
		$seo_title = SeoMetaHelper::get( $post_id, 'title' );

		if ( '' !== $seo_title ) {
			/* Check if it contains template placeholders */
			if ( false !== strpos( $seo_title, '%%' ) ) {
				$resolved = SeoMetaHelper::resolveTitle( $seo_title, $post_id );
				$title_parts['title'] = $resolved;
			} else {
				$title_parts['title'] = $seo_title;
			}
			/* Remove site name to prevent duplication when using full custom title */
			unset( $title_parts['site'] );
		}

		return $title_parts;
	}

	/**
	 * Filter the document title separator.
	 *
	 * Hooked on `document_title_separator`.
	 *
	 * @param string $sep Current separator.
	 * @return string Modified separator.
	 */
	public function filter_title_separator( $sep ) {
		assert( is_string( $sep ), 'sep must be a string' );

		$custom_sep = SeoMetaHelper::get( 0, 'title_separator' );

		if ( '' !== $custom_sep ) {
			return $custom_sep;
		}

		return $sep;
	}

	/**
	 * Output meta description and keywords tags.
	 *
	 * Hooked on `wp_head` priority 1.
	 *
	 * @return void
	 */
	public function output_meta_tags() {
		assert( function_exists( 'is_singular' ), 'WordPress must be loaded' );
		assert( function_exists( 'esc_attr' ), 'esc_attr must exist' );

		$post_id = is_singular() ? (int) get_the_ID() : 0;

		/* Meta description */
		$description = SeoMetaHelper::get( $post_id, 'description' );

		if ( '' === $description && $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post && ! empty( $post->post_content ) ) {
				$excerpt = wp_strip_all_tags( $post->post_content );
				if ( mb_strlen( $excerpt ) > 160 ) {
					$excerpt = mb_substr( $excerpt, 0, 157 ) . '...';
				}
				$description = $excerpt;
			}
		}

		if ( '' === $description ) {
			$description = get_bloginfo( 'description', 'display' );
		}

		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		}

		/* Meta keywords */
		$keywords = SeoMetaHelper::get( $post_id, 'keywords' );

		if ( '' !== $keywords ) {
			echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '">' . "\n";
		}
	}

	/**
	 * Output canonical link tag.
	 *
	 * Hooked on `wp_head` priority 1.
	 *
	 * @return void
	 */
	public function output_canonical() {
		assert( function_exists( 'is_singular' ), 'WordPress must be loaded' );

		if ( ! is_singular() ) {
			return;
		}

		$post_id   = (int) get_the_ID();
		$canonical = SeoMetaHelper::get( $post_id, 'canonical' );

		if ( '' === $canonical ) {
			$canonical = get_permalink( $post_id );
		}

		if ( '' !== $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}
	}

	/**
	 * Output robots meta tag.
	 *
	 * Hooked on `wp_head` priority 1.
	 *
	 * @return void
	 */
	public function output_robots_meta() {
		assert( function_exists( 'is_singular' ), 'WordPress must be loaded' );

		if ( ! is_singular() ) {
			return;
		}

		$post_id = (int) get_the_ID();
		$index   = SeoMetaHelper::get( $post_id, 'robots_index' );
		$follow  = SeoMetaHelper::get( $post_id, 'robots_follow' );
		$advanced = SeoMetaHelper::get( $post_id, 'robots_advanced' );

		$directives = array();

		if ( 'noindex' === $index ) {
			$directives[] = 'noindex';
		} elseif ( 'index' === $index ) {
			$directives[] = 'index';
		}

		if ( 'nofollow' === $follow ) {
			$directives[] = 'nofollow';
		} elseif ( 'follow' === $follow ) {
			$directives[] = 'follow';
		}

		if ( '' !== $advanced ) {
			$parts = explode( ',', $advanced );
			$max   = 10;
			$count = 0;

			foreach ( $parts as $part ) {
				if ( $count >= $max ) {
					break;
				}
				$count++;

				$clean = trim( $part );
				if ( '' !== $clean ) {
					$directives[] = $clean;
				}
			}
		}

		if ( ! empty( $directives ) ) {
			echo '<meta name="robots" content="' . esc_attr( implode( ', ', $directives ) ) . '">' . "\n";
		}
	}

	/**
	 * Output Open Graph meta tags.
	 *
	 * Hooked on `wp_head` priority 2.
	 *
	 * @return void
	 */
	public function output_open_graph() {
		assert( function_exists( 'is_singular' ), 'WordPress must be loaded' );
		assert( function_exists( 'esc_attr' ), 'esc_attr must exist' );

		$post_id = is_singular() ? (int) get_the_ID() : 0;

		/* og:locale */
		$locale = get_locale();
		echo '<meta property="og:locale" content="' . esc_attr( $locale ) . '">' . "\n";

		/* og:type */
		$og_type = SeoMetaHelper::get( $post_id, 'og_type' );

		if ( '' === $og_type ) {
			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				$og_type = ( $post && 'post' === $post->post_type ) ? 'article' : 'website';
			} else {
				$og_type = 'website';
			}
		}

		echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";

		/* og:title */
		$og_title = SeoMetaHelper::get( $post_id, 'og_title' );

		if ( '' === $og_title ) {
			$og_title = SeoMetaHelper::get( $post_id, 'title' );
		}
		if ( '' === $og_title && $post_id > 0 ) {
			$og_title = get_the_title( $post_id );
		}
		if ( '' === $og_title ) {
			$og_title = get_bloginfo( 'name' );
		}

		echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";

		/* og:description */
		$og_desc = SeoMetaHelper::get( $post_id, 'og_description' );

		if ( '' === $og_desc ) {
			$og_desc = SeoMetaHelper::get( $post_id, 'description' );
		}
		if ( '' === $og_desc ) {
			$og_desc = get_bloginfo( 'description', 'display' );
		}

		if ( '' !== $og_desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
		}

		/* og:url */
		if ( $post_id > 0 ) {
			$og_url = get_permalink( $post_id );
		} else {
			$og_url = home_url( '/' );
		}

		echo '<meta property="og:url" content="' . esc_url( $og_url ) . '">' . "\n";

		/* og:site_name */
		$og_site_name = get_option( 'bs_seo_global_og_site_name', '' );

		if ( '' === $og_site_name ) {
			$og_site_name = get_bloginfo( 'name' );
		}

		echo '<meta property="og:site_name" content="' . esc_attr( $og_site_name ) . '">' . "\n";

		/* og:image */
		$og_image    = SeoMetaHelper::get( $post_id, 'og_image' );
		$og_image_id = SeoMetaHelper::getInt( $post_id, 'og_image_id' );

		if ( '' === $og_image && $post_id > 0 && has_post_thumbnail( $post_id ) ) {
			$og_image = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		if ( '' === $og_image ) {
			$default_og = get_option( 'bs_seo_global_default_og_image', '' );
			if ( '' !== $default_og ) {
				$og_image = $default_og;
			}
		}

		if ( '' !== $og_image ) {
			echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";

			if ( $og_image_id > 0 ) {
				$image_data = wp_get_attachment_image_src( $og_image_id, 'large' );
				if ( $image_data ) {
					echo '<meta property="og:image:width" content="' . esc_attr( $image_data[1] ) . '">' . "\n";
					echo '<meta property="og:image:height" content="' . esc_attr( $image_data[2] ) . '">' . "\n";
				}
			}
		}

		/* Article meta for posts */
		if ( 'article' === $og_type && $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post ) {
				echo '<meta property="article:published_time" content="' . esc_attr( $post->post_date ) . '">' . "\n";
				echo '<meta property="article:modified_time" content="' . esc_attr( $post->post_modified ) . '">' . "\n";

				/* article:author */
				$author = get_the_author_meta( 'display_name', $post->post_author );
				if ( '' !== $author ) {
					echo '<meta property="article:author" content="' . esc_attr( $author ) . '">' . "\n";
				}

				/* article:section — first category */
				$categories = get_the_category( $post_id );
				if ( is_array( $categories ) && count( $categories ) > 0 ) {
					echo '<meta property="article:section" content="' . esc_attr( $categories[0]->name ) . '">' . "\n";
				}

				/* article:tag — post tags (max 10) */
				$tags      = get_the_tags( $post_id );
				$max_tags  = 10;
				$tag_count = 0;

				if ( is_array( $tags ) ) {
					foreach ( $tags as $tag ) {
						if ( $tag_count >= $max_tags ) {
							break;
						}
						$tag_count++;
						echo '<meta property="article:tag" content="' . esc_attr( $tag->name ) . '">' . "\n";
					}
				}
			}
		}
	}

	/**
	 * Output Twitter Card meta tags.
	 *
	 * Hooked on `wp_head` priority 2.
	 *
	 * @return void
	 */
	public function output_twitter_cards() {
		assert( function_exists( 'is_singular' ), 'WordPress must be loaded' );

		$post_id = is_singular() ? (int) get_the_ID() : 0;

		/* twitter:card */
		$card_type = SeoMetaHelper::get( $post_id, 'twitter_card' );

		if ( '' === $card_type ) {
			$card_type = get_option( 'bs_seo_global_default_twitter_card', 'summary_large_image' );
		}

		echo '<meta name="twitter:card" content="' . esc_attr( $card_type ) . '">' . "\n";

		/* twitter:site */
		$twitter_site = get_option( 'bs_seo_global_twitter_username', '' );
		if ( '' !== $twitter_site ) {
			if ( '@' !== substr( $twitter_site, 0, 1 ) ) {
				$twitter_site = '@' . $twitter_site;
			}
			echo '<meta name="twitter:site" content="' . esc_attr( $twitter_site ) . '">' . "\n";
		}

		/* twitter:title */
		$tw_title = SeoMetaHelper::get( $post_id, 'twitter_title' );

		if ( '' === $tw_title ) {
			$tw_title = SeoMetaHelper::get( $post_id, 'og_title' );
		}
		if ( '' === $tw_title && $post_id > 0 ) {
			$tw_title = get_the_title( $post_id );
		}
		if ( '' === $tw_title ) {
			$tw_title = get_bloginfo( 'name' );
		}

		echo '<meta name="twitter:title" content="' . esc_attr( $tw_title ) . '">' . "\n";

		/* twitter:description */
		$tw_desc = SeoMetaHelper::get( $post_id, 'twitter_description' );

		if ( '' === $tw_desc ) {
			$tw_desc = SeoMetaHelper::get( $post_id, 'og_description' );
		}
		if ( '' === $tw_desc ) {
			$tw_desc = SeoMetaHelper::get( $post_id, 'description' );
		}

		if ( '' !== $tw_desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $tw_desc ) . '">' . "\n";
		}

		/* twitter:image */
		$tw_image = SeoMetaHelper::get( $post_id, 'twitter_image' );

		if ( '' === $tw_image ) {
			$tw_image = SeoMetaHelper::get( $post_id, 'og_image' );
		}
		if ( '' === $tw_image && $post_id > 0 && has_post_thumbnail( $post_id ) ) {
			$tw_image = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		if ( '' !== $tw_image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $tw_image ) . '">' . "\n";
		}
	}

	/**
	 * Output Pinterest domain verification.
	 *
	 * Hooked on `wp_head` priority 2.
	 *
	 * @return void
	 */
	public function output_pinterest() {
		$verify = get_option( 'bs_seo_global_pinterest_verify', '' );

		if ( '' !== $verify ) {
			echo '<meta name="p:domain_verify" content="' . esc_attr( $verify ) . '">' . "\n";
		}
	}
}
