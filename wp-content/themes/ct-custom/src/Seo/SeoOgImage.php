<?php
/**
 * SEO OG Image Generator
 *
 * Auto-generates branded Open Graph images (1200x630px) using PHP GD.
 * Cached in wp-content/uploads/seo-og/ directory.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class SeoOgImage {

	/** @var int Image width in pixels. */
	const WIDTH = 1200;

	/** @var int Image height in pixels. */
	const HEIGHT = 630;

	/** @var string Upload subdirectory. */
	const UPLOAD_DIR = 'seo-og';

	/** @var int Max title length before truncation. */
	const MAX_TITLE_LENGTH = 80;

	/** @var int Max site name length. */
	const MAX_SITE_NAME_LENGTH = 40;

	/**
	 * Get or generate the OG image URL for a post.
	 *
	 * Returns the custom OG image if set, otherwise generates one.
	 *
	 * @param int $post_id Post ID.
	 * @return string Image URL or empty string.
	 */
	public static function getImageUrl( $post_id ) {
		assert( is_int( $post_id ) || is_numeric( $post_id ), 'post_id must be numeric' );

		$post_id = (int) $post_id;

		if ( $post_id <= 0 ) {
			return '';
		}

		/* Check for custom OG image first */
		$custom_image = SeoMetaHelper::get( $post_id, 'og_image' );
		if ( '' !== $custom_image ) {
			return $custom_image;
		}

		/* Check for featured image */
		if ( has_post_thumbnail( $post_id ) ) {
			$thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
			if ( $thumb_url ) {
				return $thumb_url;
			}
		}

		/* Generate auto OG image */
		if ( ! extension_loaded( 'gd' ) ) {
			return '';
		}

		return self::generateImage( $post_id );
	}

	/**
	 * Generate a branded OG image for a post.
	 *
	 * Creates a 1200x630px image with the post title and site name
	 * on a branded background.
	 *
	 * @param int $post_id Post ID.
	 * @return string Generated image URL or empty string on failure.
	 */
	public static function generateImage( $post_id ) {
		assert( extension_loaded( 'gd' ), 'GD extension must be loaded' );
		assert( is_int( $post_id ) || is_numeric( $post_id ), 'post_id must be numeric' );

		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		$upload_dir = wp_upload_dir();
		$og_dir     = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR;
		$og_url_dir = $upload_dir['baseurl'] . '/' . self::UPLOAD_DIR;

		/* Create directory if needed */
		if ( ! is_dir( $og_dir ) ) {
			wp_mkdir_p( $og_dir );
		}

		$filename = 'og-' . $post_id . '-' . md5( $post->post_title ) . '.png';
		$filepath = $og_dir . '/' . $filename;
		$fileurl  = $og_url_dir . '/' . $filename;

		/* Return cached file if it exists */
		if ( file_exists( $filepath ) ) {
			return $fileurl;
		}

		/* Create image */
		$image = imagecreatetruecolor( self::WIDTH, self::HEIGHT );

		if ( false === $image ) {
			return '';
		}

		/* Colors */
		$bg_color      = imagecolorallocate( $image, 26, 26, 46 );    /* #1A1A2E */
		$accent_color  = imagecolorallocate( $image, 255, 107, 53 );  /* #FF6B35 */
		$white_color   = imagecolorallocate( $image, 255, 255, 255 ); /* #FFFFFF */
		$gray_color    = imagecolorallocate( $image, 156, 163, 175 ); /* #9CA3AF */

		/* Background */
		imagefilledrectangle( $image, 0, 0, self::WIDTH - 1, self::HEIGHT - 1, $bg_color );

		/* Accent bar at top */
		imagefilledrectangle( $image, 0, 0, self::WIDTH - 1, 6, $accent_color );

		/* Accent bar at bottom */
		imagefilledrectangle( $image, 0, self::HEIGHT - 6, self::WIDTH - 1, self::HEIGHT - 1, $accent_color );

		/* Title text */
		$title = $post->post_title;

		if ( mb_strlen( $title ) > self::MAX_TITLE_LENGTH ) {
			$title = mb_substr( $title, 0, self::MAX_TITLE_LENGTH - 3 ) . '...';
		}

		/* Use default font (no TTF required) */
		$font_size = 5; /* Built-in font 1-5 */

		/* Word wrap title */
		$max_chars_per_line = 40;
		$wrapped = wordwrap( $title, $max_chars_per_line, "\n", true );
		$lines   = explode( "\n", $wrapped );
		$max_lines = 4;
		$line_height = 28;

		$title_y = ( self::HEIGHT / 2 ) - ( ( min( count( $lines ), $max_lines ) * $line_height ) / 2 );
		$line_count = 0;

		foreach ( $lines as $line ) {
			if ( $line_count >= $max_lines ) {
				break;
			}

			$text_width = strlen( $line ) * imagefontwidth( $font_size );
			$text_x     = ( self::WIDTH - $text_width ) / 2;

			imagestring( $image, $font_size, (int) $text_x, (int) $title_y + ( $line_count * $line_height ), $line, $white_color );
			$line_count++;
		}

		/* Site name at bottom */
		$site_name = get_bloginfo( 'name' );

		if ( mb_strlen( $site_name ) > self::MAX_SITE_NAME_LENGTH ) {
			$site_name = mb_substr( $site_name, 0, self::MAX_SITE_NAME_LENGTH - 3 ) . '...';
		}

		$site_width = strlen( $site_name ) * imagefontwidth( 3 );
		$site_x     = ( self::WIDTH - $site_width ) / 2;
		$site_y     = self::HEIGHT - 50;

		imagestring( $image, 3, (int) $site_x, (int) $site_y, $site_name, $gray_color );

		/* Save image */
		$saved = imagepng( $image, $filepath );
		imagedestroy( $image );

		if ( ! $saved ) {
			return '';
		}

		return $fileurl;
	}

	/**
	 * Invalidate the cached OG image for a post.
	 *
	 * Called when the post title changes.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function invalidate( $post_id ) {
		$upload_dir = wp_upload_dir();
		$og_dir     = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR;

		if ( ! is_dir( $og_dir ) ) {
			return;
		}

		/* Remove any existing OG images for this post */
		$pattern = $og_dir . '/og-' . (int) $post_id . '-*.png';
		$files   = glob( $pattern );

		if ( ! is_array( $files ) ) {
			return;
		}

		$max_files = 10;
		$count     = 0;

		foreach ( $files as $file ) {
			if ( $count >= $max_files ) {
				break;
			}
			$count++;

			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}
}
