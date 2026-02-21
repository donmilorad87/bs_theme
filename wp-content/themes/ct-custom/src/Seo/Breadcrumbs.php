<?php
/**
 * Breadcrumbs
 *
 * Enhanced breadcrumb class with microdata HTML, configurable separator,
 * and home label. Reuses logic from TemplateFunctions::get_breadcrumb_items().
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class Breadcrumbs {

	/** @var Breadcrumbs|null Singleton instance. */
	private static $instance = null;

	/** @var array|null Cached breadcrumb items for the current request. */
	private $items_cache = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Build breadcrumb trail as structured data.
	 *
	 * Returns an ordered array of breadcrumb items. Each item is an
	 * associative array with 'label' (string) and optional 'url' (string).
	 *
	 * @return array[] List of breadcrumb items, empty on front page.
	 */
	public function get_items() {
		assert( function_exists( 'home_url' ), 'WordPress must be loaded' );
		assert( function_exists( 'is_front_page' ), 'is_front_page must exist' );

		if ( null !== $this->items_cache ) {
			return $this->items_cache;
		}

		if ( is_front_page() ) {
			$this->items_cache = array();
			return array();
		}

		/* Check per-page breadcrumb hide toggle */
		if ( is_singular() ) {
			$post_id = (int) get_the_ID();
			$hide    = SeoMetaHelper::get( $post_id, 'breadcrumb_hide' );

			if ( 'on' === $hide || '1' === $hide ) {
				$this->items_cache = array();
				return array();
			}
		}

		$max_depth  = 10;
		$items      = array();
		$home_label = get_option( 'bs_seo_global_breadcrumb_home_label', '' );

		if ( '' === $home_label ) {
			$home_label = __( 'Home', 'ct-custom' );
		}

		$items[] = array(
			'label' => $home_label,
			'url'   => home_url( '/' ),
		);

		if ( is_page() ) {
			$ancestors    = get_post_ancestors( get_the_ID() );
			$front_page_id = (int) get_option( 'page_on_front' );

			if ( ! empty( $ancestors ) ) {
				$ancestors = array_reverse( $ancestors );
				$depth     = 0;

				foreach ( $ancestors as $ancestor_id ) {
					if ( $depth >= $max_depth ) {
						break;
					}

					if ( $front_page_id && (int) $ancestor_id === $front_page_id ) {
						continue;
					}

					$items[] = array(
						'label' => get_the_title( $ancestor_id ),
						'url'   => get_permalink( $ancestor_id ),
					);
					$depth++;
				}
			}

			$items[] = array( 'label' => get_the_title() );

		} elseif ( is_single() ) {
			$categories = get_the_category();
			if ( ! empty( $categories ) ) {
				$items[] = array(
					'label' => $categories[0]->name,
					'url'   => get_category_link( $categories[0]->term_id ),
				);
			}

			$items[] = array( 'label' => get_the_title() );

		} elseif ( is_category() ) {
			$items[] = array( 'label' => single_cat_title( '', false ) );

		} elseif ( is_search() ) {
			$items[] = array( 'label' => __( 'Search Results', 'ct-custom' ) );

		} elseif ( is_404() ) {
			$items[] = array( 'label' => __( 'Page Not Found', 'ct-custom' ) );

		} elseif ( is_archive() ) {
			$items[] = array( 'label' => get_the_archive_title() );
		}

		assert( is_array( $items ), 'Breadcrumb items must be an array' );

		$this->items_cache = $items;

		return $items;
	}

	/**
	 * Render breadcrumbs as HTML with microdata.
	 *
	 * @return void
	 */
	public function render() {
		$items      = $this->get_items();
		$count      = count( $items );
		$max_items  = 20;
		$separator  = get_option( 'bs_seo_global_breadcrumb_separator', '' );

		if ( '' === $separator ) {
			$separator = '/';
		}

		if ( 0 === $count ) {
			return;
		}

		assert( is_array( $items ), 'Breadcrumb items must be an array' );
		assert( $count <= $max_items, 'Breadcrumb depth exceeds safe limit' );

		echo '<nav class="bs-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumbs', 'ct-custom' ) . '">';
		echo '<ol class="bs-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">';

		for ( $i = 0; $i < $count && $i < $max_items; $i++ ) {
			$is_last = ( $i === $count - 1 );

			echo '<li class="bs-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

			if ( isset( $items[ $i ]['url'] ) && ! $is_last ) {
				echo '<a href="' . esc_url( $items[ $i ]['url'] ) . '" itemprop="item">';
				echo '<span itemprop="name">' . esc_html( $items[ $i ]['label'] ) . '</span>';
				echo '</a>';
			} else {
				echo '<span itemprop="name" class="bs-breadcrumbs__current">' . esc_html( $items[ $i ]['label'] ) . '</span>';
			}

			echo '<meta itemprop="position" content="' . ( $i + 1 ) . '">';
			echo '</li>';

			if ( ! $is_last ) {
				echo '<li class="bs-breadcrumbs__separator" aria-hidden="true">' . esc_html( $separator ) . '</li>';
			}
		}

		echo '</ol>';
		echo '</nav>';
	}

	/**
	 * Reset the cached items (useful for testing).
	 *
	 * @return void
	 */
	public function reset() {
		$this->items_cache = null;
	}
}
