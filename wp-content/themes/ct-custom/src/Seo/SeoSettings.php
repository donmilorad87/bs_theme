<?php
/**
 * SEO Settings AJAX Handlers
 *
 * Handles save/load for each SEO admin sub-section.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class SeoSettings {

	/** @var int Maximum number of redirects allowed. */
	const MAX_REDIRECTS = 500;

	/** @var int Maximum length of option values. */
	const MAX_OPTION_LENGTH = 5000;

	/**
	 * Verify an AJAX request with nonce check and capability.
	 *
	 * @return void Sends JSON error and dies on failure.
	 */
	private function verify_ajax_request() {
		assert( function_exists( 'check_ajax_referer' ), 'check_ajax_referer must exist' );

		check_ajax_referer( 'bs_seo_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'ct-custom' ) ) );
		}
	}

	/**
	 * Parse JSON input from the AJAX request body.
	 *
	 * JS sends: formData.append('input', JSON.stringify(data))
	 *
	 * @return array Decoded input or empty array.
	 */
	private function parse_input() {
		$raw = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '{}';

		if ( ! is_string( $raw ) ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Get a sanitized string from parsed input.
	 *
	 * @param array  $input Parsed input array.
	 * @param string $key   Field key.
	 * @return string Sanitized value.
	 */
	private function input_string( $input, $key ) {
		assert( is_array( $input ), 'input must be an array' );
		assert( is_string( $key ), 'key must be a string' );

		$value = isset( $input[ $key ] ) ? $input[ $key ] : '';

		if ( ! is_string( $value ) ) {
			$value = (string) $value;
		}

		$value = sanitize_text_field( $value );

		if ( mb_strlen( $value ) > self::MAX_OPTION_LENGTH ) {
			$value = mb_substr( $value, 0, self::MAX_OPTION_LENGTH );
		}

		return $value;
	}

	/* ═══ Global Settings ═══════════════════════════════ */

	/**
	 * Save global SEO settings.
	 *
	 * JS sends: title_template, separator, default_description, default_keywords,
	 *           kg_type, kg_name, kg_logo, kg_url
	 *
	 * @return void
	 */
	public function admin_save_seo_global() {
		$this->verify_ajax_request();

		$input = $this->parse_input();

		/* Map JS field names → WP option suffixes */
		$field_map = array(
			'title_template'      => 'title_template',
			'separator'           => 'title_separator',
			'default_description' => 'default_description',
			'default_keywords'    => 'default_keywords',
			'kg_type'             => 'knowledge_graph_type',
			'kg_name'             => 'org_name',
			'kg_logo'             => 'org_logo',
			'kg_url'              => 'org_url',
		);

		$max_fields = 20;
		$count      = 0;

		foreach ( $field_map as $js_key => $option_suffix ) {
			if ( $count >= $max_fields ) {
				break;
			}
			$count++;

			$value = $this->input_string( $input, $js_key );
			update_option( 'bs_seo_global_' . $option_suffix, $value );
		}

		wp_send_json_success( array( 'message' => __( 'SEO settings saved.', 'ct-custom' ) ) );
	}

	/**
	 * Load ALL SEO settings (used by Global, Social, Breadcrumbs tabs).
	 *
	 * Returns unified data using JS field names.
	 *
	 * @return void
	 */
	public function admin_load_seo_global() {
		$this->verify_ajax_request();

		$data = array(
			/* Global tab */
			'title_template'      => get_option( 'bs_seo_global_title_template', '' ),
			'separator'           => get_option( 'bs_seo_global_title_separator', '' ),
			'default_description' => get_option( 'bs_seo_global_default_description', '' ),
			'default_keywords'    => get_option( 'bs_seo_global_default_keywords', '' ),
			'kg_type'             => get_option( 'bs_seo_global_knowledge_graph_type', '' ),
			'kg_name'             => get_option( 'bs_seo_global_org_name', '' ),
			'kg_logo'             => get_option( 'bs_seo_global_org_logo', '' ),
			'kg_url'              => get_option( 'bs_seo_global_org_url', '' ),

			/* Social tab */
			'og_image'            => get_option( 'bs_seo_global_default_og_image', '' ),
			'og_sitename'         => get_option( 'bs_seo_global_og_site_name', '' ),
			'twitter_username'    => get_option( 'bs_seo_global_twitter_username', '' ),
			'twitter_card_type'   => get_option( 'bs_seo_global_default_twitter_card', '' ),
			'pinterest_verify'    => get_option( 'bs_seo_global_pinterest_verify', '' ),
			'social_profiles'     => get_option( 'bs_seo_global_social_profiles', '' ),

			/* Breadcrumbs tab */
			'breadcrumbs_enabled'    => get_option( 'bs_seo_global_breadcrumb_enabled', '' ),
			'breadcrumbs_separator'  => get_option( 'bs_seo_global_breadcrumb_separator', '' ),
			'breadcrumbs_home_label' => get_option( 'bs_seo_global_breadcrumb_home_label', '' ),
			'breadcrumbs_pages'      => get_option( 'bs_seo_global_breadcrumb_show_on_pages', '' ),
			'breadcrumbs_posts'      => get_option( 'bs_seo_global_breadcrumb_show_on_posts', '' ),

			/* Sitemap tab */
			'sitemap_enabled'    => get_option( 'bs_seo_sitemap_enabled', 'on' ),
			'sitemap_excluded'   => get_option( 'bs_seo_sitemap_excluded', '' ),
			'sitemap_post_types' => get_option( 'bs_seo_sitemap_post_types', '' ),

			/* LLMs tab */
			'llms_enabled'  => get_option( 'bs_seo_llms_enabled', 'on' ),
			'llms_custom'   => get_option( 'bs_seo_llms_custom', '' ),
			'llms_preview'  => mb_substr( LlmsTxt::generate(), 0, 500 ),
		);

		wp_send_json_success( $data );
	}

	/* ═══ Social Settings ═══════════════════════════════ */

	/**
	 * Save social defaults.
	 *
	 * JS sends: og_image, og_sitename, twitter_username, twitter_card_type,
	 *           pinterest_verify, social_profiles
	 *
	 * @return void
	 */
	public function admin_save_seo_social() {
		$this->verify_ajax_request();

		$input = $this->parse_input();

		$field_map = array(
			'og_image'          => 'default_og_image',
			'og_sitename'       => 'og_site_name',
			'twitter_username'  => 'twitter_username',
			'twitter_card_type' => 'default_twitter_card',
			'pinterest_verify'  => 'pinterest_verify',
			'social_profiles'   => 'social_profiles',
		);

		$max_fields = 20;
		$count      = 0;

		foreach ( $field_map as $js_key => $option_suffix ) {
			if ( $count >= $max_fields ) {
				break;
			}
			$count++;

			$value = $this->input_string( $input, $js_key );
			update_option( 'bs_seo_global_' . $option_suffix, $value );
		}

		wp_send_json_success( array( 'message' => __( 'Social settings saved.', 'ct-custom' ) ) );
	}

	/**
	 * Save social icons (theme-level) settings.
	 *
	 * JS sends: social_icons_enabled, share_enabled, networks[]
	 *
	 * @return void
	 */
	public function admin_save_seo_social_icons() {
		$this->verify_ajax_request();

		$input = $this->parse_input();

		$enabled = $this->input_string( $input, 'social_icons_enabled' );
		$enabled = ( 'off' === $enabled ) ? 'off' : 'on';
		update_option( 'bs_social_icons_enabled', $enabled );

		$share_raw = isset( $input['share_enabled'] ) ? $input['share_enabled'] : false;
		$share_val = filter_var( $share_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		$share_val = ( null === $share_val ) ? false : $share_val;
		set_theme_mod( 'bs_social_share_enabled', $share_val );

		$networks = isset( $input['networks'] ) && is_array( $input['networks'] ) ? $input['networks'] : array();
		$sanitized   = array();
		$max_entries = 50;
		$count       = 0;

		foreach ( $networks as $entry ) {
			if ( $count >= $max_entries ) {
				break;
			}

			if ( ! is_array( $entry ) ) {
				continue;
			}

			$name = isset( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : '';
			$url  = isset( $entry['url'] ) ? esc_url_raw( $entry['url'] ) : '';

			if ( '' === $name || '' === $url ) {
				continue;
			}

			$sanitized[] = array(
				'name'     => $name,
				'url'      => $url,
				'icon_id'  => isset( $entry['icon_id'] ) ? absint( $entry['icon_id'] ) : 0,
				'icon_url' => isset( $entry['icon_url'] ) ? esc_url_raw( $entry['icon_url'] ) : '',
			);

			$count++;
		}

		update_option( 'bs_custom_social_networks', wp_json_encode( $sanitized ) );

		wp_send_json_success( array( 'message' => __( 'Social icons saved.', 'ct-custom' ) ) );
	}

	/**
	 * Save contact point settings.
	 *
	 * JS sends: company, telephone, fax_number, email, contact_type, address{}
	 *
	 * @return void
	 */
	public function admin_save_seo_contact_point() {
		$this->verify_ajax_request();

		$input = $this->parse_input();

		$address = isset( $input['address'] ) && is_array( $input['address'] ) ? $input['address'] : array();

		$sanitized = array(
			'company'      => isset( $input['company'] ) ? sanitize_text_field( $input['company'] ) : '',
			'telephone'    => isset( $input['telephone'] ) ? sanitize_text_field( $input['telephone'] ) : '',
			'fax_number'   => isset( $input['fax_number'] ) ? sanitize_text_field( $input['fax_number'] ) : '',
			'email'        => isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '',
			'contact_type' => isset( $input['contact_type'] ) ? sanitize_text_field( $input['contact_type'] ) : '',
			'address'      => array(
				'street_number'  => isset( $address['street_number'] ) ? sanitize_text_field( $address['street_number'] ) : '',
				'street_address' => isset( $address['street_address'] ) ? sanitize_text_field( $address['street_address'] ) : '',
				'city'           => isset( $address['city'] ) ? sanitize_text_field( $address['city'] ) : '',
				'state'          => isset( $address['state'] ) ? sanitize_text_field( $address['state'] ) : '',
				'postal_code'    => isset( $address['postal_code'] ) ? sanitize_text_field( $address['postal_code'] ) : '',
				'country'        => isset( $address['country'] ) ? sanitize_text_field( $address['country'] ) : '',
			),
		);

		update_option( 'bs_custom_contact_point', wp_json_encode( $sanitized ) );

		wp_send_json_success( array( 'message' => __( 'Contact point saved.', 'ct-custom' ) ) );
	}

	/* ═══ Sitemap Settings ══════════════════════════════ */

	/**
	 * Save sitemap settings.
	 *
	 * @return void
	 */
	public function admin_save_seo_sitemap() {
		$this->verify_ajax_request();

		$input = $this->parse_input();

		$enabled    = $this->input_string( $input, 'sitemap_enabled' );
		$excluded   = $this->input_string( $input, 'sitemap_excluded' );
		$post_types = isset( $input['sitemap_post_types'] ) ? $input['sitemap_post_types'] : null;

		if ( '' === $enabled ) {
			$enabled = 'on';
		}

		update_option( 'bs_seo_sitemap_enabled', $enabled );
		update_option( 'bs_seo_sitemap_excluded', $excluded );

		if ( is_array( $post_types ) ) {
			$clean_types = array();
			$max_types   = 20;
			$type_count  = 0;

			foreach ( $post_types as $slug ) {
				if ( $type_count >= $max_types ) {
					break;
				}
				$type_count++;

				if ( is_string( $slug ) ) {
					$clean_types[] = sanitize_key( $slug );
				}
			}

			update_option( 'bs_seo_sitemap_post_types', wp_json_encode( $clean_types ) );
		}

		flush_rewrite_rules();
		SitemapIndex::clearCache();

		wp_send_json_success( array( 'message' => __( 'Sitemap settings saved.', 'ct-custom' ) ) );
	}

	/* ═══ Sitemap Tree ═══════════════════════════════════ */

	/**
	 * Return language + post-type header data for the sitemap tree.
	 *
	 * @return void
	 */
	public function admin_get_sitemap_tree_types() {
		assert( function_exists( 'get_option' ), 'get_option must exist' );
		assert( function_exists( 'get_post_types' ), 'get_post_types must exist' );

		$this->verify_ajax_request();

		$enabled_raw  = get_option( 'bs_seo_sitemap_post_types', '' );
		$enabled_list = array();

		if ( '' !== $enabled_raw ) {
			$decoded = json_decode( $enabled_raw, true );
			if ( is_array( $decoded ) ) {
				$enabled_list = $decoded;
			}
		}

		/* Build languages list — always start with "All", then each enabled language */
		$langs    = array();
		$max_lang = 50;

		/* "All" is always the first node (iso2 = '' means no language filter) */
		$langs[] = array( 'iso2' => '', 'label' => __( 'All', 'ct-custom' ) );

		if ( function_exists( 'bs_get_language_manager' ) ) {
			$lang_manager = bs_get_language_manager();
			if ( $lang_manager && method_exists( $lang_manager, 'get_all' ) ) {
				$all_langs = $lang_manager->get_all();
				$lcount    = 0;

				foreach ( $all_langs as $lang ) {
					if ( $lcount >= $max_lang ) {
						break;
					}

					/* Skip disabled languages */
					if ( isset( $lang['enabled'] ) && ! $lang['enabled'] ) {
						continue;
					}

					$lcount++;

					$langs[] = array(
						'iso2'  => isset( $lang['iso2'] ) ? sanitize_key( $lang['iso2'] ) : '',
						'label' => isset( $lang['native_name'] ) ? sanitize_text_field( $lang['native_name'] ) : '',
					);
				}
			}
		}

		/* Build post-types list — exclude attachment, add category */
		$all_types  = get_post_types( array( 'public' => true ), 'objects' );
		$types_data = array();
		$max_types  = 20;
		$tcount     = 0;

		foreach ( $all_types as $type_obj ) {
			if ( $tcount >= $max_types ) {
				break;
			}

			/* Skip media attachments — not useful in sitemap index */
			if ( 'attachment' === $type_obj->name ) {
				continue;
			}

			$tcount++;

			$slug    = $type_obj->name;
			$enabled = empty( $enabled_list ) || in_array( $slug, $enabled_list, true );

			$count_query = new \WP_Query( array(
				'post_type'      => $slug,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );

			$types_data[] = array(
				'slug'    => $slug,
				'label'   => $type_obj->labels->name,
				'count'   => (int) $count_query->found_posts,
				'enabled' => $enabled,
			);
		}

		/* Add categories as a type */
		$cat_count    = wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
		$types_data[] = array(
			'slug'    => 'category',
			'label'   => __( 'Categories', 'ct-custom' ),
			'count'   => is_wp_error( $cat_count ) ? 0 : (int) $cat_count,
			'enabled' => empty( $enabled_list ) || in_array( 'category', $enabled_list, true ),
		);

		/* Add tags as a type */
		if ( taxonomy_exists( 'post_tag' ) ) {
			$tag_count    = $this->get_tag_count_for_language( '' );
			$types_data[] = array(
				'slug'    => 'tag',
				'label'   => __( 'Tags', 'ct-custom' ),
				'count'   => (int) $tag_count,
				'enabled' => empty( $enabled_list ) || in_array( 'tag', $enabled_list, true ),
			);
		}

		/* Add authors as a type */
		$author_count = $this->get_author_count_for_language( '' );
		$types_data[] = array(
			'slug'    => 'author',
			'label'   => __( 'Authors', 'ct-custom' ),
			'count'   => (int) $author_count,
			'enabled' => empty( $enabled_list ) || in_array( 'author', $enabled_list, true ),
		);

		wp_send_json_success( array(
			'langs' => $langs,
			'types' => $types_data,
		) );
	}

	/**
	 * Return paginated items for one post type + one language.
	 *
	 * POST params: type, lang.
	 * All matching items are returned in one response — no pagination.
	 *
	 * @return void
	 */
	public function admin_get_sitemap_tree_items() {
		assert( function_exists( 'sanitize_text_field' ), 'sanitize_text_field must exist' );
		assert( function_exists( 'get_post_meta' ), 'get_post_meta must exist' );

		$this->verify_ajax_request();

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'page';
		$lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';

		/* Route category requests to term handler */
		if ( 'category' === $type ) {
			$this->get_category_tree_items( $lang );
			return;
		}

		/* Route author requests to author handler */
		if ( 'author' === $type ) {
			$this->get_author_tree_items( $lang );
			return;
		}

		/* Route tag requests to tag handler */
		if ( 'tag' === $type && taxonomy_exists( 'post_tag' ) ) {
			$this->get_tag_tree_items( $lang );
			return;
		}

		$excluded_raw = get_option( 'bs_seo_sitemap_excluded', '' );
		$excluded_ids = array_filter( array_map( 'intval', explode( ',', $excluded_raw ) ) );

		/* Load drag-and-drop order saved by JS (IDs only — does NOT limit results) */
		$order_key   = 'bs_seo_sitemap_order_' . $type . '_' . $lang;
		$saved_order = get_option( $order_key, '' );
		$order_ids   = array();

		if ( '' !== $saved_order ) {
			$decoded_order = json_decode( $saved_order, true );
			if ( is_array( $decoded_order ) ) {
				$order_ids = array_map( 'intval', $decoded_order );
			}
		}

		/* Fetch ALL published posts for this type+language — -1 means no WP limit */
		$query_args = array(
			'post_type'           => $type,
			'post_status'         => 'publish',
			'posts_per_page'      => -1,
			'orderby'             => 'menu_order',
			'order'               => 'ASC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		/* Language filtering: for posts use category/tag markers, others use bs_language meta. */
		if ( '' !== $lang && 'post' === $type ) {
			$tax_query = $this->build_language_tax_query( $lang );

			if ( empty( $tax_query ) ) {
				$query_args['post__in'] = array( 0 );
			} else {
				$query_args['tax_query'] = $tax_query;
			}
		} elseif ( '' !== $lang ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => 'bs_language',
					'value' => $lang,
				),
			);
		}

		$query     = new \WP_Query( $query_args );
		$all_posts = $query->posts;

		/* Apply saved drag-and-drop order in PHP (avoids post__in limiting results).
		 * Posts in the saved order come first; any new posts not yet in the order
		 * are appended at the end.
		 */
		if ( ! empty( $order_ids ) ) {
			$pos_map   = array();
			$max_pos   = 500;
			$pos_count = 0;

			foreach ( $order_ids as $oid ) {
				if ( $pos_count >= $max_pos ) {
					break;
				}
				$pos_map[ (int) $oid ] = $pos_count;
				$pos_count++;
			}

			$ordered   = array();
			$remaining = array();
			$max_iter  = 500;
			$iter      = 0;

			foreach ( $all_posts as $post ) {
				if ( $iter >= $max_iter ) {
					break;
				}
				$iter++;

				if ( isset( $pos_map[ $post->ID ] ) ) {
					$ordered[ $pos_map[ $post->ID ] ] = $post;
				} else {
					$remaining[] = $post;
				}
			}

			ksort( $ordered );
			$all_posts = array_merge( array_values( $ordered ), $remaining );
		}

		$items = array();
		$max   = 500;
		$count = 0;

		foreach ( $all_posts as $post ) {
			if ( $count >= $max ) {
				break;
			}
			$count++;

			$priority   = get_post_meta( $post->ID, 'bs_seo_sitemap_priority', true );
			$changefreq = get_post_meta( $post->ID, 'bs_seo_sitemap_changefreq', true );
			$lastmod    = get_post_modified_time( 'Y-m-d H:i', false, $post->ID );

			$items[] = array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'url'        => get_permalink( $post->ID ),
				'edit_url'   => get_edit_post_link( $post->ID, 'raw' ),
				'priority'   => '' !== $priority ? $priority : 'auto',
				'changefreq' => '' !== $changefreq ? $changefreq : 'auto',
				'excluded'   => in_array( $post->ID, $excluded_ids, true ),
				'lastmod'    => $lastmod ? $lastmod : '',
			);
		}

		wp_send_json_success( array(
			'items'       => $items,
			'total'       => count( $items ),
			'total_pages' => 1,
			'page'        => 1,
		) );
	}

	/**
	 * Return all category terms for a language node (no pagination).
	 *
	 * @param string $lang ISO2 language code or '' for all.
	 * @return void
	 */
	private function get_category_tree_items( $lang ) {
		assert( is_string( $lang ), 'lang must be a string' );

		$args = array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'number'     => 500,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		$uncategorized_id = 0;
		$uncat_term = get_term_by( 'slug', 'uncategorized', 'category' );
		if ( $uncat_term && ! is_wp_error( $uncat_term ) ) {
			$uncategorized_id = (int) $uncat_term->term_id;
		}

		if ( '' !== $lang ) {
			$term_ids = $this->get_language_category_ids( $lang );

			if ( $uncategorized_id > 0 && ! empty( $term_ids ) ) {
				$term_ids = array_values( array_diff( $term_ids, array( $uncategorized_id ) ) );
			}

			if ( empty( $term_ids ) ) {
				$terms = array();
			} else {
				$args['include'] = $term_ids;
				$terms = get_terms( $args );
			}
		} else {
			$args['parent'] = 0;
			$terms = get_terms( $args );
		}

		$items = array();
		$max   = 500;
		$ti    = 0;

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $ti >= $max ) {
					break;
				}
				$ti++;

				$term_link = get_term_link( $term );
				$priority  = get_term_meta( $term->term_id, 'bs_seo_sitemap_priority', true );
				$freq      = get_term_meta( $term->term_id, 'bs_seo_sitemap_changefreq', true );

				$items[] = array(
					'id'         => $term->term_id,
					'title'      => $term->name,
					'url'        => is_wp_error( $term_link ) ? '' : $term_link,
					'edit_url'   => get_edit_term_link( $term->term_id, 'category' ),
					'priority'   => '' !== $priority ? $priority : 'auto',
					'changefreq' => '' !== $freq ? $freq : 'auto',
					'excluded'   => false,
					'lastmod'    => '',
				);
			}
		}

		wp_send_json_success( array(
			'items'       => $items,
			'total'       => count( $items ),
			'total_pages' => 1,
			'page'        => 1,
		) );
	}

	/**
	 * Return all tag terms for a language node (no pagination).
	 *
	 * @param string $lang ISO2 language code or '' for all.
	 * @return void
	 */
	private function get_tag_tree_items( $lang ) {
		assert( is_string( $lang ), 'lang must be a string' );

		$terms = $this->get_tag_terms_for_language( $lang, 500 );

		$items = array();
		$max   = 500;
		$ti    = 0;

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $ti >= $max ) {
					break;
				}
				$ti++;

				$term_link = get_term_link( $term, 'post_tag' );
				$priority  = get_term_meta( $term->term_id, 'bs_seo_sitemap_priority', true );
				$freq      = get_term_meta( $term->term_id, 'bs_seo_sitemap_changefreq', true );

				$items[] = array(
					'id'         => $term->term_id,
					'title'      => $term->name,
					'url'        => is_wp_error( $term_link ) ? '' : $term_link,
					'edit_url'   => get_edit_term_link( $term->term_id, 'post_tag' ),
					'priority'   => '' !== $priority ? $priority : 'auto',
					'changefreq' => '' !== $freq ? $freq : 'auto',
					'excluded'   => false,
					'lastmod'    => '',
				);
			}
		}

		wp_send_json_success( array(
			'items'       => $items,
			'total'       => count( $items ),
			'total_pages' => 1,
			'page'        => 1,
		) );
	}

	/**
	 * Return all author archive entries for a language node (no pagination).
	 *
	 * @param string $lang ISO2 language code or '' for all.
	 * @return void
	 */
	private function get_author_tree_items( $lang ) {
		assert( is_string( $lang ), 'lang must be a string' );
		assert( function_exists( 'get_userdata' ), 'get_userdata must exist' );

		$author_ids = $this->get_author_ids_for_language( $lang, 500 );

		$items = array();
		$max   = 500;
		$count = 0;

		foreach ( $author_ids as $author_id ) {
			if ( $count >= $max ) {
				break;
			}
			$count++;

			$user = get_userdata( $author_id );
			if ( ! $user ) {
				continue;
			}

			$lastmod = $this->get_author_lastmod( $author_id, $lang );

			$items[] = array(
				'id'         => $author_id,
				'title'      => $user->display_name,
				'url'        => $this->get_author_url_for_language( $author_id, $lang ),
				'edit_url'   => get_edit_user_link( $author_id ),
				'priority'   => 'auto',
				'changefreq' => 'auto',
				'excluded'   => false,
				'lastmod'    => $lastmod ? $lastmod : '',
			);
		}

		wp_send_json_success( array(
			'items'       => $items,
			'total'       => count( $items ),
			'total_pages' => 1,
			'page'        => 1,
		) );
	}

	/**
	 * Get author IDs that have published posts (optionally filtered by language).
	 *
	 * @param string $lang  ISO2 language code.
	 * @param int    $limit Max author IDs to return.
	 * @return array<int, int>
	 */
	private function get_author_ids_for_language( $lang, $limit ) {
		global $wpdb;

		$limit = max( 1, min( 500, (int) $limit ) );

		if ( ! isset( $wpdb ) ) {
			return array();
		}

		$post_ids = $this->get_language_post_ids( $lang, 5000, true );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$posts_table  = $wpdb->posts;
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$sql = $wpdb->prepare(
			"SELECT DISTINCT post_author
			 FROM {$posts_table}
			 WHERE ID IN ({$placeholders})
			   AND post_author > 0",
			$post_ids
		);

		$ids = $wpdb->get_col( $sql );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		$ids = array_map( 'intval', $ids );

		return array_slice( $ids, 0, $limit );
	}

	/**
	 * Build an author URL for a specific language (prefixing /{lang}/ when provided).
	 *
	 * @param int    $author_id Author user ID.
	 * @param string $lang      ISO2 language code.
	 * @return string
	 */
	private function get_author_url_for_language( $author_id, $lang ) {
		$author_url = get_author_posts_url( $author_id );

		if ( '' === $author_url || '' === $lang ) {
			return $author_url;
		}

		$lang = sanitize_key( $lang );

		if ( '' === $lang ) {
			return $author_url;
		}

		$home = home_url( '/' );

		if ( '' === $home ) {
			return $author_url;
		}

		$home = trailingslashit( $home );

		if ( 0 !== strpos( $author_url, $home ) ) {
			return $author_url;
		}

		$relative = ltrim( substr( $author_url, strlen( $home ) ), '/' );

		if ( '' === $relative ) {
			return $author_url;
		}

		$prefix = $lang . '/';

		if ( 0 === strpos( $relative, $prefix ) ) {
			return $author_url;
		}

		return $home . $prefix . $relative;
	}

	/**
	 * Get author count for a language.
	 *
	 * @param string $lang ISO2 language code.
	 * @return int
	 */
	private function get_author_count_for_language( $lang ) {
		$ids = $this->get_author_ids_for_language( $lang, 5000 );

		return count( $ids );
	}

	/**
	 * Get latest modified date for a specific author.
	 *
	 * @param int    $author_id Author user ID.
	 * @param string $lang      ISO2 language code.
	 * @return string
	 */
	private function get_author_lastmod( $author_id, $lang ) {
		$tax_query = $this->build_language_tax_query( $lang );

		if ( empty( $tax_query ) ) {
			return '';
		}

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'author'         => (int) $author_id,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'tax_query'      => $tax_query,
			'meta_query'     => $this->get_noindex_meta_query(),
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return '';
		}

		$lastmod = get_post_modified_time( 'Y-m-d H:i', false, $posts[0] );

		return $lastmod ? $lastmod : '';
	}

	/**
	 * Build the noindex meta query used by sitemaps.
	 *
	 * @return array
	 */
	private function get_noindex_meta_query() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => 'bs_seo_robots_index',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'bs_seo_robots_index',
				'value'   => 'noindex',
				'compare' => '!=',
			),
		);
	}

	/**
	 * Get language data (native name) for a given ISO2 code.
	 *
	 * @param string $lang ISO2 language code.
	 * @return array{iso2:string,native_name:string}
	 */
	private function get_language_data( $lang ) {
		$languages = SitemapIndex::getEnabledLanguageData();

		foreach ( $languages as $lang_data ) {
			if ( isset( $lang_data['iso2'] ) && $lang_data['iso2'] === $lang ) {
				$native = isset( $lang_data['native_name'] ) ? (string) $lang_data['native_name'] : $lang;
				return array(
					'iso2'        => $lang,
					'native_name' => $native,
				);
			}
		}

		return array(
			'iso2'        => $lang,
			'native_name' => $lang,
		);
	}

	/**
	 * Get category term IDs representing a language (including children).
	 *
	 * @param string $lang ISO2 language code.
	 * @return array<int, int>
	 */
	private function get_language_category_ids( $lang ) {
		if ( '' === $lang || ! taxonomy_exists( 'category' ) ) {
			return array();
		}

		$lang      = sanitize_key( $lang );
		$lang_data = $this->get_language_data( $lang );
		$native    = isset( $lang_data['native_name'] ) ? (string) $lang_data['native_name'] : '';

		$slugs = array();
		if ( '' !== $lang ) {
			$slugs[] = $lang;
		}
		if ( '' !== $native ) {
			$slugs[] = sanitize_title( $native );
		}
		$slugs = array_values( array_unique( array_filter( $slugs ) ) );

		$term = null;
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				break;
			}
		}

		if ( ! $term ) {
			$names = array();
			if ( '' !== $native ) {
				$names[] = $native;
			}
			if ( '' !== $lang ) {
				$names[] = strtoupper( $lang );
				$names[] = ucfirst( $lang );
			}
			$names = array_values( array_unique( array_filter( $names ) ) );

			foreach ( $names as $name ) {
				$term = get_term_by( 'name', $name, 'category' );
				if ( $term && ! is_wp_error( $term ) ) {
					break;
				}
			}
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		$parent_id = (int) $term->term_id;
		$ids       = array( $parent_id );

		$children = get_terms( array(
			'taxonomy'   => 'category',
			'child_of'   => $parent_id,
			'hide_empty' => false,
			'fields'     => 'ids',
			'number'     => 500,
		) );

		if ( is_array( $children ) ) {
			foreach ( $children as $cid ) {
				$ids[] = (int) $cid;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Get tag term ID representing a language.
	 *
	 * @param string $lang ISO2 language code.
	 * @return int
	 */
	private function get_language_tag_id( $lang ) {
		if ( '' === $lang || ! taxonomy_exists( 'post_tag' ) ) {
			return 0;
		}

		$lang      = sanitize_key( $lang );
		$lang_data = $this->get_language_data( $lang );
		$native    = isset( $lang_data['native_name'] ) ? (string) $lang_data['native_name'] : '';

		$slugs = array();
		if ( '' !== $lang ) {
			$slugs[] = $lang;
		}
		if ( '' !== $native ) {
			$slugs[] = sanitize_title( $native );
		}
		$slugs = array_values( array_unique( array_filter( $slugs ) ) );

		$term = null;
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'post_tag' );
			if ( $term && ! is_wp_error( $term ) ) {
				break;
			}
		}

		if ( ! $term ) {
			$names = array();
			if ( '' !== $lang ) {
				$names[] = strtoupper( $lang );
				$names[] = ucfirst( $lang );
				$names[] = $lang;
			}
			if ( '' !== $native ) {
				$names[] = $native;
			}
			$names = array_values( array_unique( array_filter( $names ) ) );

			foreach ( $names as $name ) {
				$term = get_term_by( 'name', $name, 'post_tag' );
				if ( $term && ! is_wp_error( $term ) ) {
					break;
				}
			}
		}

		return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
	}

	/**
	 * Build a tax_query for language markers (category/tag).
	 *
	 * @param string $lang ISO2 language code.
	 * @return array
	 */
	private function build_language_tax_query( $lang ) {
		$clauses = array();
		$cat_ids = $this->get_language_category_ids( $lang );
		$tag_id  = $this->get_language_tag_id( $lang );

		if ( ! empty( $cat_ids ) ) {
			$clauses[] = array(
				'taxonomy'         => 'category',
				'field'            => 'term_id',
				'terms'            => $cat_ids,
				'include_children' => false,
			);
		}

		if ( $tag_id > 0 ) {
			$clauses[] = array(
				'taxonomy'         => 'post_tag',
				'field'            => 'term_id',
				'terms'            => array( $tag_id ),
				'include_children' => false,
			);
		}

		if ( empty( $clauses ) ) {
			return array();
		}

		if ( count( $clauses ) === 1 ) {
			return array( $clauses[0] );
		}

		$tax_query = array( 'relation' => 'OR' );
		foreach ( $clauses as $clause ) {
			$tax_query[] = $clause;
		}

		return $tax_query;
	}

	/**
	 * Get post IDs for a language based on category/tag markers.
	 *
	 * @param string $lang           ISO2 language code.
	 * @param int    $limit          Max IDs to return.
	 * @param bool   $exclude_noindex Whether to exclude noindex posts.
	 * @return array<int, int>
	 */
	private function get_language_post_ids( $lang, $limit, $exclude_noindex ) {
		$limit = max( 1, min( 5000, (int) $limit ) );

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'has_password'   => false,
		);

		if ( '' !== $lang ) {
			$tax_query = $this->build_language_tax_query( $lang );

			if ( empty( $tax_query ) ) {
				return array();
			}

			$args['tax_query'] = $tax_query;
		}

		if ( $exclude_noindex ) {
			$args['meta_query'] = $this->get_noindex_meta_query();
		}

		$ids = get_posts( $args );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * Get tag terms for a language (based on the language tag).
	 *
	 * @param string $lang  ISO2 language code.
	 * @param int    $limit Max terms to return.
	 * @return array<int, \WP_Term>
	 */
	private function get_tag_terms_for_language( $lang, $limit ) {
		$ids = $this->get_tag_ids_for_language( $lang, $limit );

		if ( empty( $ids ) ) {
			return array();
		}

		$terms = get_terms( array(
			'taxonomy'   => 'post_tag',
			'include'    => $ids,
			'hide_empty' => false,
			'number'     => max( 1, min( 500, (int) $limit ) ),
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Get tag IDs used by published posts (filtered by language tag).
	 *
	 * @param string $lang  ISO2 language code.
	 * @param int    $limit Max tag IDs to return.
	 * @return array<int, int>
	 */
	private function get_tag_ids_for_language( $lang, $limit ) {
		$limit = max( 1, min( 500, (int) $limit ) );

		if ( ! taxonomy_exists( 'post_tag' ) ) {
			return array();
		}

		if ( '' === $lang ) {
			$ids = get_terms( array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => $limit,
			) );

			if ( ! is_array( $ids ) ) {
				return array();
			}

			return array_slice( array_map( 'intval', $ids ), 0, $limit );
		}

		$language_tag_id = $this->get_language_tag_id( $lang );

		if ( $language_tag_id <= 0 ) {
			return array();
		}

		$post_ids = $this->get_post_ids_with_language_tag( $language_tag_id, 5000 );

		if ( empty( $post_ids ) ) {
			return array( $language_tag_id );
		}

		$ids = get_terms( array(
			'taxonomy'   => 'post_tag',
			'object_ids' => $post_ids,
			'hide_empty' => false,
			'fields'     => 'ids',
			'number'     => $limit,
		) );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		$ids = array_map( 'intval', $ids );

		if ( ! in_array( $language_tag_id, $ids, true ) ) {
			$ids[] = $language_tag_id;
		}

		return array_slice( $ids, 0, $limit );
	}

	/**
	 * Get post IDs that have the language tag.
	 *
	 * @param int $tag_id Language tag term ID.
	 * @param int $limit  Max IDs to return.
	 * @return array<int, int>
	 */
	private function get_post_ids_with_language_tag( $tag_id, $limit ) {
		$limit = max( 1, min( 5000, (int) $limit ) );

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'has_password'   => false,
			'tax_query'      => array(
				array(
					'taxonomy'         => 'post_tag',
					'field'            => 'term_id',
					'terms'            => array( (int) $tag_id ),
					'include_children' => false,
				),
			),
			'meta_query'     => $this->get_noindex_meta_query(),
		);

		$ids = get_posts( $args );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * Get tag count for a language.
	 *
	 * @param string $lang ISO2 language code.
	 * @return int
	 */
	private function get_tag_count_for_language( $lang ) {
		$ids = $this->get_tag_ids_for_language( $lang, 5000 );

		return count( $ids );
	}

	/**
	 * Return per-type item counts for a given language.
	 *
	 * Mirrors the filtering used by SitemapPages::build() so counts shown in the
	 * sitemap index tree match the actual XML output.
	 *
	 * POST param: lang (ISO2 code, or '' for all).
	 *
	 * @return void
	 */
	public function admin_get_sitemap_lang_counts() {
		assert( function_exists( 'get_post_types' ), 'get_post_types must exist' );
		assert( function_exists( 'wp_count_terms' ), 'wp_count_terms must exist' );

		$this->verify_ajax_request();

		$lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';

		$excluded_raw = get_option( 'bs_seo_sitemap_excluded', '' );
		$excluded_ids = array_filter( array_map( 'intval', explode( ',', $excluded_raw ) ) );

		$noindex_clause = array(
			'relation' => 'OR',
			array(
				'key'     => 'bs_seo_robots_index',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'bs_seo_robots_index',
				'value'   => 'noindex',
				'compare' => '!=',
			),
		);

		$all_types = get_post_types( array( 'public' => true ), 'objects' );
		$counts    = array();
		$max_types = 20;
		$tcount    = 0;

		foreach ( $all_types as $type_obj ) {
			if ( $tcount >= $max_types ) {
				break;
			}

			if ( 'attachment' === $type_obj->name ) {
				continue;
			}

			$tcount++;

			$meta_query = array( 'relation' => 'AND' );

			if ( '' !== $lang && 'post' !== $type_obj->name ) {
				$meta_query[] = array(
					'key'   => 'bs_language',
					'value' => $lang,
				);
			}

			$meta_query[] = $noindex_clause;

			$query_args = array(
				'post_type'      => $type_obj->name,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'has_password'   => false,
				'meta_query'     => $meta_query,
			);

			if ( '' !== $lang && 'post' === $type_obj->name ) {
				$tax_query = $this->build_language_tax_query( $lang );

				if ( empty( $tax_query ) ) {
					$counts[ $type_obj->name ] = 0;
					continue;
				}

				$query_args['tax_query'] = $tax_query;
			}

			if ( ! empty( $excluded_ids ) ) {
				$query_args['post__not_in'] = $excluded_ids;
			}

			$q = new \WP_Query( $query_args );

			$counts[ $type_obj->name ] = (int) $q->found_posts;
		}

		/* Categories: for language nodes include language category + children (exclude uncategorized) */
		if ( '' !== $lang ) {
			$cat_ids = $this->get_language_category_ids( $lang );

			$uncategorized_id = 0;
			$uncat_term = get_term_by( 'slug', 'uncategorized', 'category' );
			if ( $uncat_term && ! is_wp_error( $uncat_term ) ) {
				$uncategorized_id = (int) $uncat_term->term_id;
			}

			if ( $uncategorized_id > 0 && ! empty( $cat_ids ) ) {
				$cat_ids = array_values( array_diff( $cat_ids, array( $uncategorized_id ) ) );
			}

			$counts['category'] = count( $cat_ids );
		} else {
			$cat_args = array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			);

			$cat_count          = wp_count_terms( $cat_args );
			$counts['category'] = is_wp_error( $cat_count ) ? 0 : (int) $cat_count;
		}

		/* Tags: count terms used by posts in this language */
		if ( taxonomy_exists( 'post_tag' ) ) {
			$counts['tag'] = $this->get_tag_count_for_language( $lang );
		}

		/* Authors: count distinct authors with published posts */
		$counts['author'] = $this->get_author_count_for_language( $lang );

		wp_send_json_success( array( 'counts' => $counts ) );
	}

	/**
	 * Save custom item order for one type + language group.
	 *
	 * JS sends: input = JSON({type, lang, ids: [1,2,3,...]})
	 *
	 * @return void
	 */
	public function admin_save_sitemap_order() {
		assert( function_exists( 'update_option' ), 'update_option must exist' );
		assert( function_exists( 'delete_transient' ), 'delete_transient must exist' );

		$this->verify_ajax_request();

		$input = $this->parse_input();
		$type  = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : '';
		$lang  = isset( $input['lang'] ) ? sanitize_key( $input['lang'] ) : '';
		$ids   = array();

		if ( '' === $type ) {
			wp_send_json_error( array( 'message' => __( 'Type is required.', 'ct-custom' ) ) );
			return;
		}

		if ( isset( $input['ids'] ) && is_array( $input['ids'] ) ) {
			$max_ids  = 500;
			$id_count = 0;

			foreach ( $input['ids'] as $raw_id ) {
				if ( $id_count >= $max_ids ) {
					break;
				}
				$id_count++;

				$int_id = (int) $raw_id;
				if ( $int_id > 0 ) {
					$ids[] = $int_id;
				}
			}
		}

		$option_key = 'bs_seo_sitemap_order_' . $type . '_' . $lang;
		update_option( $option_key, wp_json_encode( $ids ) );

		delete_transient( 'bs_sitemap_' . $type );
		SitemapIndex::clearCache();

		wp_send_json_success( array( 'message' => __( 'Order saved.', 'ct-custom' ) ) );
	}

	/**
	 * Save one item's sitemap settings (priority, changefreq, excluded).
	 *
	 * JS sends: input = JSON({id, type, priority, changefreq, excluded})
	 *
	 * @return void
	 */
	public function admin_save_sitemap_item() {
		assert( function_exists( 'update_post_meta' ), 'update_post_meta must exist' );
		assert( function_exists( 'delete_post_meta' ), 'delete_post_meta must exist' );

		$this->verify_ajax_request();

		$input      = $this->parse_input();
		$id         = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$item_type  = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : 'post';
		$priority   = isset( $input['priority'] ) ? sanitize_text_field( $input['priority'] ) : 'auto';
		$changefreq = isset( $input['changefreq'] ) ? sanitize_text_field( $input['changefreq'] ) : 'auto';
		$excluded   = isset( $input['excluded'] ) ? (bool) $input['excluded'] : false;

		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid item ID.', 'ct-custom' ) ) );
			return;
		}

		$valid_priorities  = array( 'auto', '0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8', '0.9', '1.0' );
		$valid_changefreqs = array( 'auto', 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' );

		if ( ! in_array( $priority, $valid_priorities, true ) ) {
			$priority = 'auto';
		}

		if ( ! in_array( $changefreq, $valid_changefreqs, true ) ) {
			$changefreq = 'auto';
		}

		/* Save meta */
		if ( 'auto' === $priority ) {
			delete_post_meta( $id, 'bs_seo_sitemap_priority' );
		} else {
			update_post_meta( $id, 'bs_seo_sitemap_priority', $priority );
		}

		if ( 'auto' === $changefreq ) {
			delete_post_meta( $id, 'bs_seo_sitemap_changefreq' );
		} else {
			update_post_meta( $id, 'bs_seo_sitemap_changefreq', $changefreq );
		}

		/* Update excluded list */
		$excluded_raw  = get_option( 'bs_seo_sitemap_excluded', '' );
		$excluded_ids  = array_filter( array_map( 'intval', explode( ',', $excluded_raw ) ) );
		$excluded_ids  = array_values( $excluded_ids );

		if ( $excluded ) {
			if ( ! in_array( $id, $excluded_ids, true ) ) {
				$excluded_ids[] = $id;
			}
		} else {
			$max_ex = 1000;
			$ex_i   = 0;
			$new_ex = array();

			foreach ( $excluded_ids as $eid ) {
				if ( $ex_i >= $max_ex ) {
					break;
				}
				$ex_i++;

				if ( $eid !== $id ) {
					$new_ex[] = $eid;
				}
			}

			$excluded_ids = $new_ex;
		}

		update_option( 'bs_seo_sitemap_excluded', implode( ',', $excluded_ids ) );

		/* Sync to post meta so the block editor toggle reflects this change */
		update_post_meta( $id, 'bs_seo_sitemap_excluded', $excluded ? 'on' : '' );

		/* Bust sitemap caches — clear both the generic and language-specific transients
		 * so the next request to the sitemap URL rebuilds the XML with the new settings.
		 */
		$post = get_post( $id );

		if ( $post ) {
			$post_lang = get_post_meta( $id, 'bs_language', true );

			delete_transient( 'bs_sitemap_' . $post->post_type );

			if ( '' !== $post_lang ) {
				delete_transient( 'bs_sitemap_' . $post->post_type . '_' . $post_lang );
			}
		}

		SitemapIndex::clearCache();

		wp_send_json_success( array( 'message' => __( 'Item saved.', 'ct-custom' ) ) );
	}

	/**
	 * Clear all sitemap transient caches.
	 *
	 * @return void
	 */
	public function admin_regenerate_sitemap() {
		global $wpdb;

		assert( isset( $wpdb ), 'wpdb must be available' );
		assert( function_exists( 'delete_transient' ), 'delete_transient must exist' );

		$this->verify_ajax_request();

		/* Delete all bs_sitemap_* transients via DB query */
		$option_name_pattern = $wpdb->esc_like( '_transient_bs_sitemap_' ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$option_name_pattern
			)
		);

		/* Also delete timeout entries */
		$timeout_pattern = $wpdb->esc_like( '_transient_timeout_bs_sitemap_' ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$timeout_pattern
			)
		);

		SitemapIndex::clearCache();

		wp_send_json_success( array( 'message' => __( 'Sitemap cache cleared. Will regenerate on next visit.', 'ct-custom' ) ) );
	}

	/* ═══ LLMs.txt Settings ═════════════════════════════ */

	/**
	 * Save LLMs.txt settings.
	 *
	 * @return void
	 */
	public function admin_save_seo_llms() {
		$this->verify_ajax_request();

		$input = $this->parse_input();

		$enabled = $this->input_string( $input, 'llms_enabled' );
		$custom  = $this->input_string( $input, 'llms_custom' );

		if ( '' === $enabled ) {
			$enabled = 'on';
		}

		update_option( 'bs_seo_llms_enabled', $enabled );
		update_option( 'bs_seo_llms_custom', $custom );

		delete_transient( 'bs_seo_llms_txt' );

		wp_send_json_success( array( 'message' => __( 'LLMs.txt settings saved.', 'ct-custom' ) ) );
	}

	/* ═══ Redirects ═════════════════════════════════════ */

	/**
	 * Save, delete, or import a redirect rule.
	 *
	 * @return void
	 */
	public function admin_save_seo_redirect() {
		$this->verify_ajax_request();

		$input  = $this->parse_input();
		/* JS sends action_type (not redirect_action) */
		$action = isset( $input['action_type'] ) ? sanitize_text_field( $input['action_type'] ) : 'add';
		$from   = isset( $input['from'] ) ? sanitize_text_field( $input['from'] ) : '';
		$to     = isset( $input['to'] ) ? esc_url_raw( $input['to'] ) : '';
		$type   = isset( $input['type'] ) ? (int) $input['type'] : 301;

		$redirects = self::getRedirects();

		/* Import action */
		if ( 'import' === $action ) {
			$import_data = isset( $input['data'] ) ? $input['data'] : '';

			if ( is_string( $import_data ) ) {
				$decoded = json_decode( $import_data, true );
			} elseif ( is_array( $import_data ) ) {
				$decoded = $import_data;
			} else {
				$decoded = null;
			}

			if ( is_array( $decoded ) ) {
				$imported  = array();
				$max_items = self::MAX_REDIRECTS;
				$count     = 0;

				foreach ( $decoded as $item ) {
					if ( $count >= $max_items ) {
						break;
					}
					$count++;

					if ( ! isset( $item['from'] ) || ! isset( $item['to'] ) ) {
						continue;
					}

					$imported[] = array(
						'from' => sanitize_text_field( $item['from'] ),
						'to'   => esc_url_raw( $item['to'] ),
						'type' => isset( $item['type'] ) ? (int) $item['type'] : 301,
						'hits' => isset( $item['hits'] ) ? (int) $item['hits'] : 0,
					);
				}

				$redirects = $imported;
			}

			update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );
			wp_send_json_success( array(
				'message'   => __( 'Redirects imported.', 'ct-custom' ),
				'redirects' => $redirects,
			) );
			return;
		}

		/* Delete action */
		if ( 'delete' === $action ) {
			if ( empty( $from ) ) {
				wp_send_json_error( array( 'message' => __( 'Source URL is required.', 'ct-custom' ) ) );
			}

			$redirects = self::removeRedirect( $redirects, $from );
			update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

			wp_send_json_success( array(
				'message'   => __( 'Redirect deleted.', 'ct-custom' ),
				'redirects' => $redirects,
			) );
			return;
		}

		/* Add action (default) */
		if ( empty( $from ) ) {
			wp_send_json_error( array( 'message' => __( 'Source URL is required.', 'ct-custom' ) ) );
		}

		if ( empty( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Target URL is required.', 'ct-custom' ) ) );
		}

		if ( ! in_array( $type, array( 301, 302 ), true ) ) {
			$type = 301;
		}

		if ( count( $redirects ) >= self::MAX_REDIRECTS ) {
			wp_send_json_error( array( 'message' => __( 'Maximum redirect limit reached.', 'ct-custom' ) ) );
		}

		$redirects = self::removeRedirect( $redirects, $from );
		$redirects[] = array(
			'from' => $from,
			'to'   => $to,
			'type' => $type,
			'hits' => 0,
		);

		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		wp_send_json_success( array(
			'message'   => __( 'Redirect saved.', 'ct-custom' ),
			'redirects' => $redirects,
		) );
	}

	/**
	 * Load all redirects.
	 *
	 * @return void
	 */
	public function admin_load_seo_redirects() {
		$this->verify_ajax_request();

		wp_send_json_success( array( 'redirects' => self::getRedirects() ) );
	}

	/* ═══ Breadcrumbs ═══════════════════════════════════ */

	/**
	 * Save breadcrumb settings.
	 *
	 * @return void
	 */
	public function admin_save_seo_breadcrumbs() {
		$this->verify_ajax_request();

		$input = $this->parse_input();

		$field_map = array(
			'breadcrumbs_enabled'    => 'breadcrumb_enabled',
			'breadcrumbs_separator'  => 'breadcrumb_separator',
			'breadcrumbs_home_label' => 'breadcrumb_home_label',
			'breadcrumbs_pages'      => 'breadcrumb_show_on_pages',
			'breadcrumbs_posts'      => 'breadcrumb_show_on_posts',
		);

		$max_fields = 10;
		$count      = 0;

		foreach ( $field_map as $js_key => $option_suffix ) {
			if ( $count >= $max_fields ) {
				break;
			}
			$count++;

			$value = $this->input_string( $input, $js_key );
			update_option( 'bs_seo_global_' . $option_suffix, $value );
		}

		wp_send_json_success( array( 'message' => __( 'Breadcrumb settings saved.', 'ct-custom' ) ) );
	}

	/* ═══ Dashboard ═════════════════════════════════════ */

	/**
	 * Get SEO dashboard data (paginated) with overview cards.
	 *
	 * @return void
	 */
	public function admin_get_seo_dashboard() {
		$this->verify_ajax_request();

		$page      = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page  = 20;
		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'any';
		$search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$lang      = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';

		$args = array(
			'post_type'      => 'any' === $post_type ? array( 'page', 'post' ) : $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( '' !== $lang ) {
			$args['meta_query'] = array(
				array(
					'key'   => 'bs_language',
					'value' => $lang,
				),
			);
		}

		$query = new \WP_Query( $args );
		$items = array();
		$max   = 100;
		$count = 0;

		$scored_above_70 = 0;
		$missing_meta    = 0;
		$noindex_count   = 0;

		foreach ( $query->posts as $post ) {
			if ( $count >= $max ) {
				break;
			}
			$count++;

			$meta_title = get_post_meta( $post->ID, 'bs_seo_title', true );
			$meta_desc  = get_post_meta( $post->ID, 'bs_seo_description', true );
			$focus_kw   = get_post_meta( $post->ID, 'bs_seo_focus_keyword', true );
			$score      = (int) get_post_meta( $post->ID, 'bs_seo_score', true );
			$robots     = get_post_meta( $post->ID, 'bs_seo_robots_index', true );

			if ( $score >= 70 ) {
				$scored_above_70++;
			}
			if ( '' === $meta_title && '' === $meta_desc ) {
				$missing_meta++;
			}
			if ( 'noindex' === $robots ) {
				$noindex_count++;
			}

			$items[] = array(
				'id'               => $post->ID,
				'title'            => $post->post_title,
				'post_type'        => $post->post_type,
				'url'              => get_permalink( $post->ID ),
				'edit_url'         => get_edit_post_link( $post->ID, 'raw' ),
				'meta_title'       => $meta_title,
				'meta_description' => $meta_desc,
				'focus_keyword'    => $focus_kw,
				'score'            => $score,
				'robots'           => $robots,
			);
		}

		wp_send_json_success( array(
			'cards' => array(
				'total_pages'     => $query->found_posts,
				'scored_above_70' => $scored_above_70,
				'missing_meta'    => $missing_meta,
				'noindex'         => $noindex_count,
			),
			'items'       => $items,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'page'        => $page,
		) );
	}

	/* ═══ Bulk Analyze ══════════════════════════════════ */

	/**
	 * Bulk analyze SEO scores for multiple posts.
	 *
	 * @return void
	 */
	public function admin_bulk_analyze_seo() {
		$this->verify_ajax_request();

		$post_ids = isset( $_POST['post_ids'] ) ? wp_unslash( $_POST['post_ids'] ) : '';
		$ids      = array_map( 'intval', explode( ',', $post_ids ) );
		$max_ids  = 50;
		$count    = 0;
		$updated  = 0;

		foreach ( $ids as $id ) {
			if ( $count >= $max_ids ) {
				break;
			}
			$count++;

			if ( $id <= 0 ) {
				continue;
			}

			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$score = self::computeScore( $post );
			update_post_meta( $id, 'bs_seo_score', $score );
			$updated++;
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Analyzed %d posts.', 'ct-custom' ), $updated ),
			'updated' => $updated,
		) );
	}

	/* ═══ Ping Search Engines ═══════════════════════════ */

	/**
	 * Ping search engines with the sitemap URL.
	 *
	 * @return void
	 */
	public function admin_ping_search_engines() {
		$this->verify_ajax_request();

		$sitemap_url = home_url( '/sitemap_index.xml' );
		$results     = array();

		$google_url = 'https://www.google.com/ping?sitemap=' . urlencode( $sitemap_url );
		$google     = wp_remote_get( $google_url, array( 'timeout' => 10 ) );
		$results['google'] = ! is_wp_error( $google );

		$bing_url = 'https://www.bing.com/ping?sitemap=' . urlencode( $sitemap_url );
		$bing     = wp_remote_get( $bing_url, array( 'timeout' => 10 ) );
		$results['bing'] = ! is_wp_error( $bing );

		wp_send_json_success( array(
			'message' => __( 'Search engines pinged.', 'ct-custom' ),
			'results' => $results,
		) );
	}

	/* ═══ Sitemap Priorities ════════════════════════════ */

	/**
	 * Return paginated pages/posts with their per-page sitemap priority and changefreq.
	 *
	 * Accepts POST: page, post_type, search.
	 *
	 * @return void
	 */
	public function admin_get_sitemap_priorities() {
		$this->verify_ajax_request();

		$page      = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page  = 20;
		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'any';
		$search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$args = array(
			'post_type'      => ( 'any' === $post_type ) ? array( 'page', 'post' ) : $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$items = array();
		$max   = 100;
		$count = 0;

		foreach ( $query->posts as $post ) {
			if ( $count >= $max ) {
				break;
			}
			$count++;

			$priority   = get_post_meta( $post->ID, 'bs_seo_sitemap_priority', true );
			$changefreq = get_post_meta( $post->ID, 'bs_seo_sitemap_changefreq', true );

			$items[] = array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'post_type'  => $post->post_type,
				'url'        => get_permalink( $post->ID ),
				'priority'   => '' !== $priority ? $priority : 'auto',
				'changefreq' => '' !== $changefreq ? $changefreq : 'auto',
			);
		}

		wp_send_json_success( array(
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
		) );
	}

	/**
	 * Bulk-save per-page sitemap priority and changefreq.
	 *
	 * JS sends: input = JSON({items: [{id, priority, changefreq}, ...]})
	 *
	 * @return void
	 */
	public function admin_save_sitemap_priorities() {
		$this->verify_ajax_request();

		$input = $this->parse_input();
		$items = ( isset( $input['items'] ) && is_array( $input['items'] ) ) ? $input['items'] : array();

		$valid_priorities  = array( 'auto', '0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8', '0.9', '1.0' );
		$valid_changefreqs = array( 'auto', 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' );

		$max            = 200;
		$count          = 0;
		$saved          = 0;
		$affected_types = array();

		foreach ( $items as $item ) {
			if ( $count >= $max ) {
				break;
			}
			$count++;

			$id         = isset( $item['id'] ) ? (int) $item['id'] : 0;
			$priority   = isset( $item['priority'] ) ? sanitize_text_field( $item['priority'] ) : 'auto';
			$changefreq = isset( $item['changefreq'] ) ? sanitize_text_field( $item['changefreq'] ) : 'auto';

			if ( $id <= 0 ) {
				continue;
			}

			if ( ! in_array( $priority, $valid_priorities, true ) ) {
				$priority = 'auto';
			}

			if ( ! in_array( $changefreq, $valid_changefreqs, true ) ) {
				$changefreq = 'auto';
			}

			if ( 'auto' === $priority ) {
				delete_post_meta( $id, 'bs_seo_sitemap_priority' );
			} else {
				update_post_meta( $id, 'bs_seo_sitemap_priority', $priority );
			}

			if ( 'auto' === $changefreq ) {
				delete_post_meta( $id, 'bs_seo_sitemap_changefreq' );
			} else {
				update_post_meta( $id, 'bs_seo_sitemap_changefreq', $changefreq );
			}

			$post = get_post( $id );

			if ( $post ) {
				$affected_types[ $post->post_type ] = true;
			}

			$saved++;
		}

		/* Bust sitemap transient cache for every affected type */
		SitemapIndex::clearCache();

		$type_max   = 20;
		$type_count = 0;

		foreach ( $affected_types as $type => $_ ) {
			if ( $type_count >= $type_max ) {
				break;
			}
			$type_count++;

			delete_transient( 'bs_sitemap_' . $type );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Saved priorities for %d pages.', 'ct-custom' ), $saved ),
			'saved'   => $saved,
		) );
	}

	/* ═══ Helpers ═══════════════════════════════════════ */

	/**
	 * Get stored redirects array.
	 *
	 * @return array List of redirect rule arrays.
	 */
	public static function getRedirects() {
		$raw     = get_option( 'bs_seo_redirects', '[]' );
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return $decoded;
	}

	/**
	 * Remove a redirect by source URL.
	 *
	 * @param array  $redirects Current redirects array.
	 * @param string $from      Source URL to remove.
	 * @return array Filtered redirects.
	 */
	private static function removeRedirect( $redirects, $from ) {
		assert( is_array( $redirects ), 'redirects must be an array' );
		assert( is_string( $from ), 'from must be a string' );

		$filtered  = array();
		$max_items = self::MAX_REDIRECTS + 1;
		$count     = 0;

		foreach ( $redirects as $redirect ) {
			if ( $count >= $max_items ) {
				break;
			}
			$count++;

			if ( isset( $redirect['from'] ) && $redirect['from'] === $from ) {
				continue;
			}

			$filtered[] = $redirect;
		}

		return $filtered;
	}

	/**
	 * Compute a basic SEO score for a post (server-side).
	 *
	 * @param \WP_Post $post Post object.
	 * @return int Score from 0 to 100.
	 */
	public static function computeScore( $post ) {
		assert( $post instanceof \WP_Post, 'post must be a WP_Post' );

		$score = 0;

		$seo_title   = get_post_meta( $post->ID, 'bs_seo_title', true );
		$seo_desc    = get_post_meta( $post->ID, 'bs_seo_description', true );
		$focus_kw    = get_post_meta( $post->ID, 'bs_seo_focus_keyword', true );
		$content     = $post->post_content;
		$title       = $post->post_title;

		/* 1. Has SEO title */
		if ( '' !== $seo_title ) {
			$score += 10;
		}

		/* 2. SEO title length (30-60 chars) */
		$title_len = mb_strlen( $seo_title ?: $title );
		if ( $title_len >= 30 && $title_len <= 60 ) {
			$score += 10;
		}

		/* 3. Has meta description */
		if ( '' !== $seo_desc ) {
			$score += 10;
		}

		/* 4. Description length (120-160 chars) */
		$desc_len = mb_strlen( $seo_desc );
		if ( $desc_len >= 120 && $desc_len <= 160 ) {
			$score += 10;
		}

		/* 5. Has focus keyword */
		if ( '' !== $focus_kw ) {
			$score += 10;
		}

		/* 6. Focus keyword in title */
		if ( '' !== $focus_kw && false !== mb_stripos( $seo_title ?: $title, $focus_kw ) ) {
			$score += 10;
		}

		/* 7. Focus keyword in description */
		if ( '' !== $focus_kw && '' !== $seo_desc && false !== mb_stripos( $seo_desc, $focus_kw ) ) {
			$score += 10;
		}

		/* 8. Content length (300+ words) */
		$word_count = str_word_count( wp_strip_all_tags( $content ) );
		if ( $word_count >= 300 ) {
			$score += 10;
		}

		/* 9. Has featured image */
		if ( has_post_thumbnail( $post->ID ) ) {
			$score += 10;
		}

		/* 10. Focus keyword in content */
		if ( '' !== $focus_kw && false !== mb_stripos( $content, $focus_kw ) ) {
			$score += 10;
		}

		return min( $score, 100 );
	}
}
