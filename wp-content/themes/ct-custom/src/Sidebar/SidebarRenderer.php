<?php
/**
 * Sidebar Renderer
 *
 * Provides helper methods for rendering page/post sidebars
 * on the frontend. Supports global widget areas and custom
 * content extracted from ct-custom/sidebar-content blocks.
 *
 * @package CT_Custom
 */

namespace CTCustom\Sidebar;

/**
 * Singleton renderer for sidebar output.
 */
class SidebarRenderer {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/** @var array Parsed blocks cache keyed by post ID. */
	private $parsed_blocks_cache = array();

	/** @var array Mode cache keyed by "position_postId". */
	private $mode_cache = array();

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Return the singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		assert( true, 'instance() called' ); // entry assertion
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		assert( self::$instance instanceof self, 'Instance must be SidebarRenderer' );

		return self::$instance;
	}

	/**
	 * Get parsed blocks for a post, using cache to avoid redundant parse_blocks() calls.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array Parsed blocks array.
	 */
	private function get_parsed_blocks( $post ) {
		$post_id = $post->ID;

		if ( isset( $this->parsed_blocks_cache[ $post_id ] ) ) {
			return $this->parsed_blocks_cache[ $post_id ];
		}

		$blocks = parse_blocks( $post->post_content );

		assert( is_array( $blocks ), 'parse_blocks must return an array' );

		$this->parsed_blocks_cache[ $post_id ] = $blocks;

		return $blocks;
	}

	/**
	 * Check whether a ct-custom/sidebar-content block exists for the given position.
	 *
	 * Parses the current post content and scans top-level blocks (bounded to 200).
	 *
	 * @param string $position 'left' or 'right'.
	 * @return bool True if a matching sidebar-content block is found.
	 */
	public function hasContentBlock( $position ) {
		assert( is_string( $position ), 'Position must be a string' );
		assert( in_array( $position, array( 'left', 'right' ), true ), 'Position must be left or right' );

		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		assert( is_object( $post ) && isset( $post->post_content ), 'Post must have content property' );

		$blocks = $this->get_parsed_blocks( $post );

		$max_scan = 200;
		$count    = 0;

		for ( $i = 0, $len = count( $blocks ); $i < $len; $i++ ) {
			if ( $count >= $max_scan ) {
				break;
			}
			$count++;

			$block = $blocks[ $i ];

			if ( 'ct-custom/sidebar-content' !== $block['blockName'] ) {
				continue;
			}

			$block_position = isset( $block['attrs']['position'] )
				? $block['attrs']['position']
				: 'left';

			if ( $block_position === $position ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the post ID for sidebar meta lookup.
	 *
	 * Works for singular pages/posts, static front pages, and the
	 * blog posts page. Returns 0 when no page context is available.
	 *
	 * @return int Post ID or 0.
	 */
	public function resolvePostId() {
		assert( function_exists( 'is_singular' ), 'is_singular must exist' );
		assert( function_exists( 'get_the_ID' ), 'get_the_ID must exist' );

		// Singular pages/posts -- standard case.
		if ( is_singular() ) {
			$post_id = get_the_ID();
			assert( is_int( $post_id ) || $post_id === false, 'get_the_ID must return int or false' );
			return $post_id ? (int) $post_id : 0;
		}

		// Static front page (Settings > Reading > "A static page").
		if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
			return (int) get_option( 'page_on_front' );
		}

		// Blog posts page.
		if ( is_home() && 'page' === get_option( 'show_on_front' ) ) {
			return (int) get_option( 'page_for_posts' );
		}

		return 0;
	}

	/**
	 * Get the sidebar mode for a position on the current post.
	 *
	 * Meta stores 'on' or 'off'. When 'on', auto-detects whether a
	 * ct-custom/sidebar-content block exists for the position to
	 * determine 'custom' vs 'global'. Legacy meta values ('global',
	 * 'custom') are treated as 'on'.
	 *
	 * Works on any template -- singular pages, static front page,
	 * and blog posts page.
	 *
	 * @param string $position 'left' or 'right'.
	 * @return string 'off', 'global', or 'custom'.
	 */
	public function getMode( $position ) {
		assert( is_string( $position ), 'Position must be a string' );
		assert( in_array( $position, array( 'left', 'right' ), true ), 'Position must be left or right' );

		$post_id = $this->resolvePostId();

		if ( ! $post_id || $post_id <= 0 ) {
			return 'off';
		}

		$cache_key = $position . '_' . $post_id;

		if ( isset( $this->mode_cache[ $cache_key ] ) ) {
			return $this->mode_cache[ $cache_key ];
		}

		$meta_key = 'ct_sidebar_' . $position;
		$value    = get_post_meta( $post_id, $meta_key, true );

		$mode = 'off';

		// Normalize: 'on', 'global', 'custom' all mean sidebar is enabled.
		if ( in_array( $value, array( 'on', 'global', 'custom' ), true ) ) {
			$mode = $this->hasContentBlock( $position ) ? 'custom' : 'global';
		}

		$this->mode_cache[ $cache_key ] = $mode;

		return $mode;
	}

	/**
	 * Check whether a sidebar is active for a given position.
	 *
	 * @param string $position 'left' or 'right'.
	 * @return bool
	 */
	public function has( $position ) {
		assert( is_string( $position ), 'Position must be a string' );
		assert( in_array( $position, array( 'left', 'right' ), true ), 'Position must be left or right' );

		return $this->getMode( $position ) !== 'off';
	}

	/**
	 * Extract rendered content from ct-custom/sidebar-content blocks
	 * matching the given position.
	 *
	 * Parses the current post content, finds the first sidebar-content
	 * block with the matching position, and renders its inner blocks.
	 *
	 * @param string $position 'left' or 'right'.
	 * @return string Rendered HTML or empty string.
	 */
	public function extractContent( $position ) {
		assert( is_string( $position ), 'Position must be a string' );
		assert( in_array( $position, array( 'left', 'right' ), true ), 'Position must be left or right' );

		$post = get_post();
		if ( ! $post ) {
			return '';
		}

		assert( is_object( $post ) && isset( $post->post_content ), 'Post must have content property' );

		$blocks = $this->get_parsed_blocks( $post );

		$max_scan = 200;
		$count    = 0;

		for ( $i = 0, $len = count( $blocks ); $i < $len; $i++ ) {
			if ( $count >= $max_scan ) {
				break;
			}
			$count++;

			$block = $blocks[ $i ];

			if ( 'ct-custom/sidebar-content' !== $block['blockName'] ) {
				continue;
			}

			$block_position = isset( $block['attrs']['position'] )
				? $block['attrs']['position']
				: 'left';

			if ( $block_position !== $position ) {
				continue;
			}

			$output      = '';
			$inner_max   = 100;
			$inner_count = 0;

			for ( $j = 0, $inner_len = count( $block['innerBlocks'] ); $j < $inner_len; $j++ ) {
				if ( $inner_count >= $inner_max ) {
					break;
				}
				$inner_count++;

				$output .= render_block( $block['innerBlocks'][ $j ] );
			}

			return $output;
		}

		return '';
	}

	/**
	 * Render a sidebar for the given position.
	 *
	 * Outputs an <aside> element with either the global widget area
	 * or custom extracted block content.
	 *
	 * @param string $position 'left' or 'right'.
	 * @return void
	 */
	public function render( $position ) {
		assert( is_string( $position ), 'Position must be a string' );
		assert( in_array( $position, array( 'left', 'right' ), true ), 'Position must be left or right' );

		$mode = $this->getMode( $position );

		if ( 'off' === $mode ) {
			return;
		}

		$label = 'left' === $position
			? __( 'Left Sidebar', 'ct-custom' )
			: __( 'Right Sidebar', 'ct-custom' );

		echo '<aside class="ct-sidebar ct-sidebar--' . esc_attr( $position ) . '" role="complementary" aria-label="' . esc_attr( $label ) . '">' . "\n";

		if ( 'global' === $mode ) {
			$iso2       = function_exists( 'ct_get_current_language' ) ? ct_get_current_language() : 'en';
			$sidebar_id = 'sidebar-' . $position . '-' . $iso2;
			if ( is_active_sidebar( $sidebar_id ) ) {
				dynamic_sidebar( $sidebar_id );
			} else {
				echo '<p class="ct-sidebar__empty fs14">' . esc_html__( 'No widgets assigned to this sidebar.', 'ct-custom' ) . '</p>';
			}
		} elseif ( 'custom' === $mode ) {
			$content = $this->extractContent( $position );
			if ( '' !== $content ) {
				echo wp_kses_post( $content );
			} else {
				echo '<p class="ct-sidebar__empty fs14">' . esc_html__( 'Add a Sidebar Content block to this page.', 'ct-custom' ) . '</p>';
			}
		}

		echo '</aside>' . "\n";
	}

	/**
	 * Build CSS classes for the main content layout container.
	 *
	 * Returns 'ct-container ct-layout' plus modifier classes
	 * when sidebars are active.
	 *
	 * @return string Space-separated class names.
	 */
	public function layoutClasses() {
		$classes = array( 'ct-container', 'ct-layout' );

		assert( is_array( $classes ), 'Classes must be an array' );

		if ( $this->has( 'left' ) ) {
			$classes[] = 'ct-layout--with-left';
		}

		if ( $this->has( 'right' ) ) {
			$classes[] = 'ct-layout--with-right';
		}

		assert( count( $classes ) >= 2, 'Must have at least base classes' );

		return implode( ' ', $classes );
	}
}
