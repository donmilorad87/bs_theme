<?php
/**
 * Template functions trait.
 *
 * Contains methods that enhance the theme by hooking into WordPress
 * (body classes, pingback, breadcrumbs, schemas, attachment images).
 * Consumed by BS_Template_Hooks.
 *
 * @package BS_Custom
 */

namespace BSCustom\Template;

trait TemplateFunctions {

	/** @var array|null Cached breadcrumb items for the current request. */
	private $breadcrumb_items_cache = null;

	/**
	 * Add custom classes to the array of body classes.
	 *
	 * @param array $classes Classes for the body element.
	 * @return array
	 */
	public function body_classes( $classes ) {
		assert( is_array( $classes ), 'classes must be an array' );
		assert( function_exists( 'is_singular' ), 'WordPress must be loaded' );

		if ( ! is_singular() ) {
			$classes[] = 'hfeed';
		}

		if ( ! is_active_sidebar( 'sidebar-1' ) ) {
			$classes[] = 'no-sidebar';
		}

		return $classes;
	}

	/**
	 * Add a pingback url auto-discovery header for single posts, pages, or attachments.
	 *
	 * @return void
	 */
	public function pingback_header() {
		assert( function_exists( 'is_singular' ), 'WordPress must be loaded' );
		assert( function_exists( 'pings_open' ), 'pings_open must exist' );

		if ( is_singular() && pings_open() ) {
			printf( '<link rel="pingback" href="%s">', esc_url( get_bloginfo( 'pingback_url' ) ) );
		}
	}

	/**
	 * Build breadcrumb trail as structured data.
	 *
	 * Returns an ordered array of breadcrumb items. Each item is an
	 * associative array with 'label' (string) and optional 'url' (string).
	 * Max 10 ancestor levels to keep loops bounded.
	 *
	 * @return array[] List of breadcrumb items, empty on front page.
	 */
	public function get_breadcrumb_items() {
		assert( function_exists( 'home_url' ), 'WordPress must be loaded' );
		assert( function_exists( 'is_front_page' ), 'is_front_page must exist' );

		if ( null !== $this->breadcrumb_items_cache ) {
			return $this->breadcrumb_items_cache;
		}

		if ( is_front_page() ) {
			$this->breadcrumb_items_cache = array();
			return array();
		}

		$max_depth = 10;
		$items     = array();

		$items[] = array(
			'label' => __( 'Home', 'ct-custom' ),
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

		$this->breadcrumb_items_cache = $items;

		return $items;
	}

	/**
	 * Output breadcrumbs navigation HTML.
	 *
	 * @return void
	 */
	public function breadcrumbs() {
		$items     = $this->get_breadcrumb_items();
		$count     = count( $items );
		$max_items = 20;

		assert( is_array( $items ), 'Breadcrumb items must be an array' );
		assert( $count <= $max_items, 'Breadcrumb depth exceeds safe limit' );

		if ( 0 === $count ) {
			return;
		}

		for ( $i = 0; $i < $count && $i < $max_items; $i++ ) {
			if ( $i > 0 ) {
				echo '<span class="breadcrumb-separator">/</span>';
			}

			if ( isset( $items[ $i ]['url'] ) ) {
				echo '<a href="' . esc_url( $items[ $i ]['url'] ) . '">' . esc_html( $items[ $i ]['label'] ) . '</a>';
			} else {
				echo '<span class="breadcrumb-current">' . esc_html( $items[ $i ]['label'] ) . '</span>';
			}
		}
	}

	/**
	 * Output schema.org BreadcrumbList JSON-LD structured data.
	 *
	 * @return void
	 */
	public function breadcrumb_schema() {
		$items     = $this->get_breadcrumb_items();
		$count     = count( $items );
		$max_items = 20;

		assert( is_array( $items ), 'Breadcrumb items must be an array' );
		assert( $count <= $max_items, 'Breadcrumb depth exceeds safe limit' );

		if ( 0 === $count ) {
			return;
		}

		$list_elements = array();

		for ( $i = 0; $i < $count && $i < $max_items; $i++ ) {
			$element = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $items[ $i ]['label'],
			);

			if ( isset( $items[ $i ]['url'] ) ) {
				$element['item'] = $items[ $i ]['url'];
			}

			$list_elements[] = $element;
		}

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list_elements,
		);

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		if ( $json ) {
			echo '<script type="application/ld+json">' . "\n";
			echo $json . "\n";
			echo '</script>' . "\n";
		}
	}

	/**
	 * Render an attachment image by ID, inlining SVG content when applicable.
	 *
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $size          Image size (used for non-SVG). Default 'thumbnail'.
	 * @param array  $attr          Extra attributes (alt, class, etc.).
	 * @return string HTML markup.
	 */
	public function get_attachment_image( $attachment_id, $size = 'thumbnail', $attr = array() ) {
		assert( is_int( $attachment_id ), 'Attachment ID must be an integer' );
		assert( $attachment_id > 0, 'Attachment ID must be positive' );

		$mime_type = get_post_mime_type( $attachment_id );

		if ( 'image/svg+xml' !== $mime_type ) {
			return wp_get_attachment_image( $attachment_id, $size, false, $attr );
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return wp_get_attachment_image( $attachment_id, $size, false, $attr );
		}

		$svg_content = file_get_contents( $file_path );

		if ( empty( $svg_content ) ) {
			return wp_get_attachment_image( $attachment_id, $size, false, $attr );
		}

		$alt   = isset( $attr['alt'] ) ? $attr['alt'] : '';
		$class = isset( $attr['class'] ) ? $attr['class'] : '';

		/* Add aria-label and role to the SVG element for accessibility */
		if ( ! empty( $alt ) ) {
			$svg_content = preg_replace(
				'/<svg\b/i',
				'<svg aria-label="' . esc_attr( $alt ) . '" role="img"',
				$svg_content,
				1
			);
		} else {
			$svg_content = preg_replace(
				'/<svg\b/i',
				'<svg aria-hidden="true"',
				$svg_content,
				1
			);
		}

		/* Add class if provided */
		if ( ! empty( $class ) ) {
			$svg_content = preg_replace(
				'/<svg\b/i',
				'<svg class="' . esc_attr( $class ) . '"',
				$svg_content,
				1
			);
		}

		return $svg_content;
	}

	/**
	 * Output schema.org Organization JSON-LD structured data.
	 *
	 * @return void
	 */
	public function contact_point_schema() {
		assert( function_exists( 'get_bloginfo' ), 'WordPress must be loaded' );
		assert( function_exists( 'home_url' ), 'home_url must exist' );

		$cached_json = get_transient( 'bs_contact_point_schema' );

		if ( false !== $cached_json ) {
			echo '<script type="application/ld+json">' . "\n";
			echo $cached_json . "\n";
			echo '</script>' . "\n";
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		);

		$description = get_theme_mod( 'bs_site_description', '' );
		if ( ! empty( $description ) ) {
			$schema['description'] = $description;
		}

		$logo_id = get_theme_mod( 'custom_logo', 0 );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_url( $logo_id );
			if ( $logo_url ) {
				$schema['logo'] = $logo_url;
			}
		}

		$icon_id = get_option( 'site_icon', 0 );
		if ( $icon_id ) {
			$icon_url = wp_get_attachment_url( $icon_id );
			if ( $icon_url ) {
				$schema['image'] = $icon_url;
			}
		}

		$same_as = $this->build_same_as();
		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = $same_as;
		}

		$cp_raw = get_option( 'bs_custom_contact_point', '' );
		$cp     = json_decode( stripslashes( $cp_raw ) );

		if ( $cp ) {
			$contact_point         = $this->build_contact_point( $cp );
			$schema['contactPoint'] = array( $contact_point );

			$address = isset( $cp->address ) && is_object( $cp->address ) ? $cp->address : null;
			if ( $address ) {
				$postal_address    = $this->build_postal_address( $address );
				$schema['address'] = $postal_address;
			}
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		if ( $json ) {
			set_transient( 'bs_contact_point_schema', $json );
			echo '<script type="application/ld+json">' . "\n";
			echo $json . "\n";
			echo '</script>' . "\n";
		}
	}

	/**
	 * Invalidate the cached Organization schema transient.
	 */
	public function invalidate_schema_cache() {
		assert( function_exists( 'delete_transient' ), 'WordPress must be loaded' );
		assert( true, 'Invalidating schema cache' );

		delete_transient( 'bs_contact_point_schema' );
	}

	/**
	 * Build sameAs array from social networks option.
	 *
	 * @return array
	 */
	public function build_same_as() {
		assert( function_exists( 'get_option' ), 'get_option must exist' );

		$networks_raw = get_option( 'bs_custom_social_networks', '[]' );
		$networks     = json_decode( stripslashes( $networks_raw ), true );

		assert( is_array( $networks ) || ! is_array( $networks ), 'Networks decoded' );

		if ( ! is_array( $networks ) ) {
			return array();
		}

		$same_as   = array();
		$max_items = 50;
		$count     = 0;

		foreach ( $networks as $network ) {
			if ( $count >= $max_items ) {
				break;
			}
			$count++;

			if ( ! empty( $network['url'] ) ) {
				$same_as[] = $network['url'];
			}
		}

		return $same_as;
	}

	/**
	 * Build a ContactPoint array from the contact point object.
	 *
	 * @param object $cp Contact point data.
	 * @return array
	 */
	public function build_contact_point( $cp ) {
		assert( is_object( $cp ), 'Contact point must be an object' );

		$contact_type = isset( $cp->contact_type ) ? $cp->contact_type : 'customer service';

		assert( is_string( $contact_type ), 'contact_type must be a string' );

		$contact_point = array(
			'@type'       => 'ContactPoint',
			'contactType' => $contact_type,
		);

		if ( ! empty( $cp->telephone ) ) {
			$contact_point['telephone'] = $cp->telephone;
		}

		if ( ! empty( $cp->fax_number ) ) {
			$contact_point['faxNumber'] = $cp->fax_number;
		}

		if ( ! empty( $cp->email ) ) {
			$contact_point['email'] = $cp->email;
		}

		return $contact_point;
	}

	/**
	 * Build a PostalAddress array from the address object.
	 *
	 * @param object $address Address data.
	 * @return array
	 */
	public function build_postal_address( $address ) {
		assert( is_object( $address ), 'Address must be an object' );

		$street_parts = array();
		if ( ! empty( $address->street_number ) ) {
			$street_parts[] = $address->street_number;
		}
		if ( ! empty( $address->street_address ) ) {
			$street_parts[] = $address->street_address;
		}

		assert( is_array( $street_parts ), 'street_parts must be an array' );

		$postal_address = array(
			'@type' => 'PostalAddress',
		);

		if ( ! empty( $street_parts ) ) {
			$postal_address['streetAddress'] = implode( ' ', $street_parts );
		}
		if ( ! empty( $address->city ) ) {
			$postal_address['addressLocality'] = $address->city;
		}
		if ( ! empty( $address->state ) ) {
			$postal_address['addressRegion'] = $address->state;
		}
		if ( ! empty( $address->postal_code ) ) {
			$postal_address['postalCode'] = $address->postal_code;
		}
		if ( ! empty( $address->country ) ) {
			$postal_address['addressCountry'] = $address->country;
		}

		return $postal_address;
	}
}
