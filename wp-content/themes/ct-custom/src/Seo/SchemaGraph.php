<?php
/**
 * SchemaGraph Trait
 *
 * Outputs unified JSON-LD @graph containing Organization, WebSite,
 * BreadcrumbList, and per-page schema (WebPage, Article, FAQ, etc.).
 *
 * Replaces the separate breadcrumb_schema + contact_point_schema output.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

trait SchemaGraph {

	/**
	 * Output unified JSON-LD @graph.
	 *
	 * Hooked on `wp_head` priority 5.
	 *
	 * @return void
	 */
	public function output_schema_graph() {
		assert( function_exists( 'wp_json_encode' ), 'wp_json_encode must exist' );
		assert( function_exists( 'home_url' ), 'home_url must exist' );

		$graph = array();

		$org = $this->build_organization_schema();
		if ( ! empty( $org ) ) {
			$graph[] = $org;
		}

		$website = $this->build_website_schema();
		if ( ! empty( $website ) ) {
			$graph[] = $website;
		}

		$breadcrumb_list = $this->build_breadcrumb_list_schema();
		if ( ! empty( $breadcrumb_list ) ) {
			$graph[] = $breadcrumb_list;
		}

		$page_schema = $this->build_page_schema();
		if ( ! empty( $page_schema ) ) {
			$graph[] = $page_schema;
		}

		if ( empty( $graph ) ) {
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		if ( $json ) {
			echo '<script type="application/ld+json">' . "\n";
			echo $json . "\n";
			echo '</script>' . "\n";
		}
	}

	/**
	 * Build Organization schema node.
	 *
	 * Migrated from TemplateFunctions::contact_point_schema().
	 *
	 * @return array Organization schema or empty array.
	 */
	public function build_organization_schema() {
		assert( function_exists( 'get_bloginfo' ), 'WordPress must be loaded' );

		$cached = get_transient( 'bs_seo_org_schema' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$kg_type = get_option( 'bs_seo_global_knowledge_graph_type', 'Organization' );
		$org_name = get_option( 'bs_seo_global_org_name', '' );

		if ( '' === $org_name ) {
			$org_name = get_bloginfo( 'name' );
		}

		$org_url = get_option( 'bs_seo_global_org_url', '' );
		if ( '' === $org_url ) {
			$org_url = home_url( '/' );
		}

		$schema = array(
			'@type' => $kg_type,
			'@id'   => home_url( '/#organization' ),
			'name'  => $org_name,
			'url'   => $org_url,
		);

		$description = get_theme_mod( 'bs_site_description', '' );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		/* Logo */
		$org_logo = get_option( 'bs_seo_global_org_logo', '' );
		if ( '' === $org_logo ) {
			$logo_id = get_theme_mod( 'custom_logo', 0 );
			if ( $logo_id ) {
				$org_logo = wp_get_attachment_url( $logo_id );
			}
		}

		if ( '' !== $org_logo && false !== $org_logo ) {
			$schema['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $org_logo,
			);
		}

		/* sameAs from social networks */
		$same_as = $this->build_schema_same_as();
		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = $same_as;
		}

		/* Contact point from theme option */
		$cp_raw = get_option( 'bs_custom_contact_point', '' );
		$cp     = json_decode( stripslashes( $cp_raw ) );

		if ( $cp ) {
			$contact_type = isset( $cp->contact_type ) ? $cp->contact_type : 'customer service';

			$contact_point = array(
				'@type'       => 'ContactPoint',
				'contactType' => $contact_type,
			);

			if ( ! empty( $cp->telephone ) ) {
				$contact_point['telephone'] = $cp->telephone;
			}
			if ( ! empty( $cp->email ) ) {
				$contact_point['email'] = $cp->email;
			}

			$schema['contactPoint'] = array( $contact_point );

			$address = isset( $cp->address ) && is_object( $cp->address ) ? $cp->address : null;
			if ( $address ) {
				$postal = array( '@type' => 'PostalAddress' );
				$street_parts = array();

				if ( ! empty( $address->street_number ) ) {
					$street_parts[] = $address->street_number;
				}
				if ( ! empty( $address->street_address ) ) {
					$street_parts[] = $address->street_address;
				}
				if ( ! empty( $street_parts ) ) {
					$postal['streetAddress'] = implode( ' ', $street_parts );
				}
				if ( ! empty( $address->city ) ) {
					$postal['addressLocality'] = $address->city;
				}
				if ( ! empty( $address->state ) ) {
					$postal['addressRegion'] = $address->state;
				}
				if ( ! empty( $address->postal_code ) ) {
					$postal['postalCode'] = $address->postal_code;
				}
				if ( ! empty( $address->country ) ) {
					$postal['addressCountry'] = $address->country;
				}

				$schema['address'] = $postal;
			}
		}

		set_transient( 'bs_seo_org_schema', $schema, 3600 );

		return $schema;
	}

	/**
	 * Build WebSite schema node with SearchAction.
	 *
	 * @return array WebSite schema.
	 */
	public function build_website_schema() {
		assert( function_exists( 'get_bloginfo' ), 'WordPress must be loaded' );

		return array(
			'@type'         => 'WebSite',
			'@id'           => home_url( '/#website' ),
			'url'           => home_url( '/' ),
			'name'          => get_bloginfo( 'name' ),
			'description'   => get_bloginfo( 'description' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'        => 'EntryPoint',
					'urlTemplate'  => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Build BreadcrumbList schema node.
	 *
	 * @return array BreadcrumbList schema or empty array.
	 */
	public function build_breadcrumb_list_schema() {
		$breadcrumbs = Breadcrumbs::instance();
		$items       = $breadcrumbs->get_items();
		$count       = count( $items );
		$max_items   = 20;

		if ( 0 === $count ) {
			return array();
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

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => home_url( '/#breadcrumb' ),
			'itemListElement' => $list_elements,
		);
	}

	/**
	 * Build per-page schema node based on bs_seo_schema_type meta.
	 *
	 * @return array Page schema or empty array.
	 */
	public function build_page_schema() {
		if ( ! is_singular() ) {
			return array();
		}

		$post_id = (int) get_the_ID();
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		$schema_type = SeoMetaHelper::get( $post_id, 'schema_type' );

		/* Auto-detect schema type: Article for posts, WebPage for pages */
		if ( '' === $schema_type || 'none' === $schema_type ) {
			$schema_type = ( 'post' === $post->post_type ) ? 'Article' : 'WebPage';
		}

		$schema = array(
			'@type'       => $schema_type,
			'@id'         => get_permalink( $post_id ) . '#' . strtolower( $schema_type ),
			'url'         => get_permalink( $post_id ),
			'name'        => get_the_title( $post_id ),
			'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
			'datePublished'  => isset( $post->post_date ) ? $post->post_date : '',
			'dateModified'   => isset( $post->post_modified ) ? $post->post_modified : '',
		);

		$description = SeoMetaHelper::get( $post_id, 'description' );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		/* Featured image */
		if ( has_post_thumbnail( $post_id ) ) {
			$thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
			if ( $thumb_url ) {
				$schema['image'] = $thumb_url;
			}
		}

		/* Article-specific fields */
		if ( in_array( $schema_type, array( 'Article', 'BlogPosting', 'NewsArticle' ), true ) ) {
			$schema['author'] = array(
				'@type' => 'Organization',
				'@id'   => home_url( '/#organization' ),
			);

			/* Word count */
			$content    = isset( $post->post_content ) ? $post->post_content : '';
			$word_count = str_word_count( wp_strip_all_tags( $content ) );

			if ( $word_count > 0 ) {
				$schema['wordCount'] = $word_count;
			}

			/* Headline */
			$schema['headline'] = get_the_title( $post_id );
		}

		/* Custom schema data from JSON meta */
		$custom_data = SeoMetaHelper::get( $post_id, 'schema_data' );
		if ( '' !== $custom_data ) {
			$decoded = json_decode( $custom_data, true );
			if ( is_array( $decoded ) ) {
				$max_fields = 30;
				$field_count = 0;

				foreach ( $decoded as $key => $value ) {
					if ( $field_count >= $max_fields ) {
						break;
					}
					$field_count++;

					if ( '' !== $value && ! isset( $schema[ $key ] ) ) {
						$schema[ $key ] = $value;
					}
				}
			}
		}

		return $schema;
	}

	/**
	 * Build sameAs array from social networks option.
	 *
	 * @return array List of social profile URLs.
	 */
	private function build_schema_same_as() {
		$networks_raw = get_option( 'bs_custom_social_networks', '[]' );
		$networks     = json_decode( stripslashes( $networks_raw ), true );

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

		/* Also include global social profiles setting */
		$profiles = get_option( 'bs_seo_global_social_profiles', '' );
		if ( '' !== $profiles ) {
			$urls = explode( "\n", $profiles );
			$url_count = 0;

			foreach ( $urls as $url ) {
				if ( $url_count >= 20 ) {
					break;
				}
				$url_count++;

				$trimmed = trim( $url );
				if ( '' !== $trimmed && ! in_array( $trimmed, $same_as, true ) ) {
					$same_as[] = $trimmed;
				}
			}
		}

		return $same_as;
	}
}
