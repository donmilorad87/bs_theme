<?php
/**
 * Homepage singleton.
 *
 * Provides data-preparation and rendering for the homepage template.
 *
 * @package BS_Custom
 */

namespace BSCustom\Template;

class Homepage {

	/** @var Homepage|null Singleton instance. */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		assert( true === true, 'instance() called' );

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		assert( self::$instance instanceof self, 'Instance must be Homepage' );

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Gather hero section data for the homepage.
	 *
	 * @return array{hero_title: string, hero_description: string, section2_title: string, section2_desc: string}
	 */
	public function get_homepage_hero_data() {
		$hero_title       = get_theme_mod( 'bs_hero_title', '' );
		$hero_description = get_theme_mod( 'bs_hero_description', '' );
		$section2_title   = get_theme_mod( 'bs_section2_title', '' );
		$section2_desc    = get_theme_mod( 'bs_section2_description', '' );

		assert( is_string( $hero_title ), 'hero_title must be a string' );
		assert( is_string( $hero_description ), 'hero_description must be a string' );

		return array(
			'hero_title'       => $hero_title,
			'hero_description' => $hero_description,
			'section2_title'   => $section2_title,
			'section2_desc'    => $section2_desc,
		);
	}

	/**
	 * Gather image grid items from customizer settings.
	 *
	 * @return array List of image grid item arrays.
	 */
	public function get_image_grid_items() {
		$items      = array();
		$max_images = 4;

		for ( $i = 1; $i <= $max_images; $i++ ) {
			$items[] = array(
				'index'       => $i,
				'image_id'    => (int) get_theme_mod( 'bs_hero_image_' . $i, 0 ),
				'image_alt'   => get_theme_mod( 'bs_hero_image_' . $i . '_alt', '' ),
				'image_title' => get_theme_mod( 'bs_hero_image_' . $i . '_title', '' ),
				'image_url'   => get_theme_mod( 'bs_hero_image_' . $i . '_url', '' ),
			);
		}

		assert( is_array( $items ), 'items must be an array' );
		assert( count( $items ) === $max_images, 'items count must match max_images' );

		return $items;
	}

	/**
	 * Render image grid items HTML.
	 *
	 * @return void
	 */
	public function render_image_grid_items() {
		$items      = $this->get_image_grid_items();
		$max_images = 4;
		$is_first   = true;

		assert( is_array( $items ), 'items must be an array' );
		assert( count( $items ) <= $max_images, 'items count must not exceed max_images' );

		foreach ( $items as $item ) :
			$tag = ! empty( $item['image_url'] ) ? 'a' : 'div';

			$img_attr = array(
				'alt'   => esc_attr( $item['image_alt'] ),
				'title' => esc_attr( $item['image_title'] ),
				'class' => 'db',
			);

			/* Prioritize the first visible image for LCP */
			if ( $is_first && $item['image_id'] ) {
				$img_attr['fetchpriority'] = 'high';
				$is_first = false;
			} else {
				$img_attr['loading'] = 'lazy';
			}
			?>
			<figure class="image-grid__item m0<?php echo ! $item['image_id'] ? ' dn' : ''; ?>" data-image-index="<?php echo (int) $item['index']; ?>">
				<<?php echo $tag; ?> class="image-grid__card db"<?php if ( ! empty( $item['image_url'] ) ) : ?> href="<?php echo esc_url( $item['image_url'] ); ?>" target="_blank" rel="noopener noreferrer"<?php endif; ?>>
					<?php if ( $item['image_id'] ) : ?>
						<?php echo bs_custom_get_attachment_image( $item['image_id'], 'large', $img_attr ); ?>
					<?php endif; ?>
					<figcaption class="image-grid__caption tac"><?php echo esc_html( $item['image_title'] ); ?></figcaption>
				</<?php echo $tag; ?>>
			</figure>
			<?php
		endforeach;
	}
}
