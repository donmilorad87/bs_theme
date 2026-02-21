<?php
/**
 * Post Language Filter — admin list-table dropdown.
 *
 * Adds a language select box and Filter button to the Posts
 * list table so editors can filter by language taxonomy.
 *
 * Language post rules:
 * - Match posts with the language category (native name slug) OR
 *   the language tag (iso2 slug).
 * - "No Language" shows posts that have none of the language categories/tags.
 *
 * @package BSCustom\Admin
 */

namespace BSCustom\Admin;

class PostLanguageFilter {

    /** Maximum languages we will iterate over. */
    private const MAX_LANGUAGES = 50;

    /** URL query parameter for language filtering. */
    private const QUERY_PARAM = 'bs_lang';

    /** Special value for posts without language taxonomy. */
    private const NO_LANGUAGE = 'none';

    /** Post type this filter applies to. */
    private const POST_TYPE = 'post';

    /** Post statuses to include in counts. */
    private const COUNT_STATUSES = array( 'publish', 'draft', 'pending', 'private' );

    /**
     * Register WordPress hooks.
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'restrict_manage_posts', array( $this, 'render_language_dropdown' ) );
        add_action( 'pre_get_posts', array( $this, 'filter_posts_query' ) );
    }

    /**
     * Enqueue the filter CSS on the Posts list screen.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_assets( string $hook ): void {
        assert( is_string( $hook ), 'Hook must be a string' );
        assert( is_admin(), 'Must be in admin context' );

        if ( 'edit.php' !== $hook ) {
            return;
        }

        $screen = get_current_screen();
        if ( null === $screen || self::POST_TYPE !== $screen->post_type ) {
            return;
        }

        $css_path = get_template_directory() . '/assets/admin/css/page-language-filter.css';
        $css_ver  = is_readable( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0';

        wp_enqueue_style(
            'ct-post-language-filter',
            get_template_directory_uri() . '/assets/admin/css/page-language-filter.css',
            array(),
            $css_ver
        );
    }

    /**
     * Render the language select box alongside the native filters.
     *
     * @param string $post_type Current post type.
     */
    public function render_language_dropdown( string $post_type ): void {
        assert( is_string( $post_type ), 'Post type must be a string' );

        if ( self::POST_TYPE !== $post_type ) {
            return;
        }

        $enabled = $this->get_enabled_languages();

        if ( empty( $enabled ) ) {
            return;
        }

        $counts       = $this->count_posts_by_language( $enabled );
        $has_param    = array_key_exists( self::QUERY_PARAM, $_GET );
        $active_iso   = $has_param
            ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PARAM ] ) )
            : $this->get_default_iso2( $enabled );

        $options = sprintf(
            '<option value="">%s</option>',
            esc_html__( 'All Languages', 'flavor' )
        );

        $lang_count = 0;

        for ( $i = 0, $len = count( $enabled ); $i < $len && $lang_count < self::MAX_LANGUAGES; $i++ ) {
            $lang       = $enabled[ $i ];
            $iso2       = isset( $lang['iso2'] ) ? (string) $lang['iso2'] : '';
            $name       = isset( $lang['native_name'] ) ? (string) $lang['native_name'] : '';
            $post_count = isset( $counts[ $iso2 ] ) ? (int) $counts[ $iso2 ] : 0;
            $selected   = selected( $active_iso, $iso2, false );
            $lang_count++;

            if ( '' === $iso2 || '' === $name ) {
                continue;
            }

            $options .= sprintf(
                '<option value="%s"%s>%s (%d)</option>',
                esc_attr( $iso2 ),
                $selected,
                esc_html( $name ),
                $post_count
            );
        }

        $no_lang_count = isset( $counts[ self::NO_LANGUAGE ] ) ? (int) $counts[ self::NO_LANGUAGE ] : 0;
        $options      .= sprintf(
            '<option value="%s"%s>%s (%d)</option>',
            esc_attr( self::NO_LANGUAGE ),
            selected( $active_iso, self::NO_LANGUAGE, false ),
            esc_html__( 'No Language', 'flavor' ),
            $no_lang_count
        );

        printf(
            '<label for="ct-lang-filter" class="screen-reader-text">%s</label>'
            . '<select name="%s" id="ct-lang-filter">%s</select>',
            esc_html__( 'Filter by language', 'flavor' ),
            esc_attr( self::QUERY_PARAM ),
            $options
        );
    }

    /**
     * Filter the main admin query by language when bs_lang is set.
     *
     * @param \WP_Query $query The query being modified.
     */
    public function filter_posts_query( $query ): void {
        assert( $query instanceof \WP_Query, 'Query must be a WP_Query instance' );

        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( null === $screen || 'edit' !== $screen->base || self::POST_TYPE !== $screen->post_type ) {
            return;
        }

        $has_param = array_key_exists( self::QUERY_PARAM, $_GET );
        $iso2      = $has_param
            ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PARAM ] ) )
            : $this->get_default_iso2( $this->get_enabled_languages() );

        if ( '' === $iso2 ) {
            return;
        }

        if ( self::NO_LANGUAGE === $iso2 ) {
            $tax_query = $this->build_no_language_tax_query( $this->get_enabled_languages() );
            if ( empty( $tax_query ) ) {
                return;
            }
            $query->set( 'tax_query', $tax_query );
            return;
        }

        if ( ! $this->is_valid_iso2( $iso2 ) ) {
            return;
        }

        $lang = $this->get_language_by_iso2( $iso2 );
        if ( null === $lang ) {
            return;
        }

        $tax_query = $this->build_language_tax_query( $iso2, (string) $lang['native_name'] );
        if ( empty( $tax_query ) ) {
            return;
        }

        $query->set( 'tax_query', $tax_query );
    }

    /**
     * Count posts per language + no-language bucket.
     *
     * @param array<int, array> $languages Enabled languages.
     * @return array<string, int>
     */
    private function count_posts_by_language( array $languages ): array {
        $counts = array();
        $limit  = self::MAX_LANGUAGES;
        $idx    = 0;

        foreach ( $languages as $lang ) {
            if ( $idx >= $limit ) {
                break;
            }
            $idx++;

            $iso2       = isset( $lang['iso2'] ) ? (string) $lang['iso2'] : '';
            $native     = isset( $lang['native_name'] ) ? (string) $lang['native_name'] : '';
            $counts[ $iso2 ] = $this->count_posts_for_language( $iso2, $native );
        }

        $counts[ self::NO_LANGUAGE ] = $this->count_posts_without_language( $languages );

        return $counts;
    }

    /**
     * Count posts that belong to a specific language.
     *
     * @param string $iso2 Language ISO2 code.
     * @param string $native_name Native name for category lookup.
     * @return int
     */
    private function count_posts_for_language( string $iso2, string $native_name ): int {
        if ( '' === $iso2 || '' === $native_name ) {
            return 0;
        }

        $tax_query = $this->build_language_tax_query( $iso2, $native_name );
        if ( empty( $tax_query ) ) {
            return 0;
        }

        $query = new \WP_Query(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => self::COUNT_STATUSES,
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'no_found_rows'  => false,
                'tax_query'      => $tax_query,
            )
        );

        return (int) $query->found_posts;
    }

    /**
     * Count posts that have no language category/tag.
     *
     * @param array<int, array> $languages Enabled languages.
     * @return int
     */
    private function count_posts_without_language( array $languages ): int {
        $tax_query = $this->build_no_language_tax_query( $languages );
        if ( empty( $tax_query ) ) {
            return $this->count_all_posts();
        }

        $query = new \WP_Query(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => self::COUNT_STATUSES,
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'no_found_rows'  => false,
                'tax_query'      => $tax_query,
            )
        );

        return (int) $query->found_posts;
    }

    /**
     * Count all posts for the post type.
     *
     * @return int
     */
    private function count_all_posts(): int {
        $query = new \WP_Query(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => self::COUNT_STATUSES,
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'no_found_rows'  => false,
            )
        );

        return (int) $query->found_posts;
    }

    /**
     * Build a tax_query that matches a single language.
     *
     * @param string $iso2 Language ISO2 code.
     * @param string $native_name Native name for category lookup.
     * @return array<int|string, array>|array<string, string>
     */
    private function build_language_tax_query( string $iso2, string $native_name ): array {
        $clauses = array();

        $category_ids = $this->get_language_category_ids( $native_name );
        if ( ! empty( $category_ids ) ) {
            $clauses[] = array(
                'taxonomy'         => 'category',
                'field'            => 'term_id',
                'terms'            => $category_ids,
                'include_children' => false,
            );
        }

        $tag_id = $this->get_language_tag_id( $iso2 );
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

        if ( 1 === count( $clauses ) ) {
            return array( $clauses[0] );
        }

        return array_merge( array( 'relation' => 'OR' ), $clauses );
    }

    /**
     * Build a tax_query that excludes all language categories/tags.
     *
     * @param array<int, array> $languages Enabled languages.
     * @return array<int|string, array>|array<string, string>
     */
    private function build_no_language_tax_query( array $languages ): array {
        $category_ids = $this->collect_language_category_ids( $languages );
        $tag_ids      = $this->collect_language_tag_ids( $languages );

        $clauses = array();

        if ( ! empty( $category_ids ) ) {
            $clauses[] = array(
                'taxonomy'         => 'category',
                'field'            => 'term_id',
                'terms'            => $category_ids,
                'operator'         => 'NOT IN',
                'include_children' => false,
            );
        }

        if ( ! empty( $tag_ids ) ) {
            $clauses[] = array(
                'taxonomy'         => 'post_tag',
                'field'            => 'term_id',
                'terms'            => $tag_ids,
                'operator'         => 'NOT IN',
                'include_children' => false,
            );
        }

        if ( empty( $clauses ) ) {
            return array();
        }

        if ( 1 === count( $clauses ) ) {
            return array( $clauses[0] );
        }

        return array_merge( array( 'relation' => 'AND' ), $clauses );
    }

    /**
     * Get language category IDs (including children) for a native name.
     *
     * @param string $native_name Native language name.
     * @return array<int>
     */
    private function get_language_category_ids( string $native_name ): array {
        if ( '' === $native_name ) {
            return array();
        }

        $slug = sanitize_title( $native_name );
        if ( '' === $slug ) {
            return array();
        }

        $term = get_term_by( 'slug', $slug, 'category' );
        if ( ! $term instanceof \WP_Term ) {
            return array();
        }

        $ids = array( (int) $term->term_id );
        $children = get_term_children( $term->term_id, 'category' );

        if ( is_array( $children ) ) {
            foreach ( $children as $child_id ) {
                $ids[] = (int) $child_id;
            }
        }

        $ids = array_values( array_unique( array_filter( $ids ) ) );

        return $ids;
    }

    /**
     * Collect all language category IDs for enabled languages.
     *
     * @param array<int, array> $languages Enabled languages.
     * @return array<int>
     */
    private function collect_language_category_ids( array $languages ): array {
        $ids   = array();
        $limit = self::MAX_LANGUAGES;
        $idx   = 0;

        foreach ( $languages as $lang ) {
            if ( $idx >= $limit ) {
                break;
            }
            $idx++;

            $native = isset( $lang['native_name'] ) ? (string) $lang['native_name'] : '';
            if ( '' === $native ) {
                continue;
            }

            $ids = array_merge( $ids, $this->get_language_category_ids( $native ) );
        }

        $ids = array_values( array_unique( array_filter( $ids ) ) );

        return $ids;
    }

    /**
     * Get language tag ID for an ISO2 code.
     *
     * @param string $iso2 Language ISO2 code.
     * @return int
     */
    private function get_language_tag_id( string $iso2 ): int {
        if ( '' === $iso2 ) {
            return 0;
        }

        $slug = sanitize_key( $iso2 );
        if ( '' === $slug ) {
            return 0;
        }

        $term = get_term_by( 'slug', $slug, 'post_tag' );
        if ( ! $term instanceof \WP_Term ) {
            return 0;
        }

        return (int) $term->term_id;
    }

    /**
     * Collect all language tag IDs for enabled languages.
     *
     * @param array<int, array> $languages Enabled languages.
     * @return array<int>
     */
    private function collect_language_tag_ids( array $languages ): array {
        $ids   = array();
        $limit = self::MAX_LANGUAGES;
        $idx   = 0;

        foreach ( $languages as $lang ) {
            if ( $idx >= $limit ) {
                break;
            }
            $idx++;

            $iso2 = isset( $lang['iso2'] ) ? (string) $lang['iso2'] : '';
            $tag_id = $this->get_language_tag_id( $iso2 );
            if ( $tag_id > 0 ) {
                $ids[] = $tag_id;
            }
        }

        $ids = array_values( array_unique( array_filter( $ids ) ) );

        return $ids;
    }

    /**
     * Get enabled languages (limited).
     *
     * @return array<int, array>
     */
    private function get_enabled_languages(): array {
        $mgr = bs_get_language_manager();
        $enabled = $mgr->get_enabled();

        assert( is_array( $enabled ), 'Enabled languages must be an array' );

        return $enabled;
    }

    /**
     * Get default language ISO2 code.
     *
     * @param array<int, array> $enabled Enabled languages.
     * @return string
     */
    private function get_default_iso2( array $enabled ): string {
        $mgr     = bs_get_language_manager();
        $default = $mgr->get_default();
        $iso2    = is_array( $default ) && ! empty( $default['iso2'] )
            ? sanitize_key( (string) $default['iso2'] )
            : '';

        if ( '' !== $iso2 ) {
            foreach ( $enabled as $lang ) {
                if ( isset( $lang['iso2'] ) && $lang['iso2'] === $iso2 ) {
                    return $iso2;
                }
            }
        }

        if ( ! empty( $enabled ) && isset( $enabled[0]['iso2'] ) ) {
            return sanitize_key( (string) $enabled[0]['iso2'] );
        }

        return '';
    }

    /**
     * Check whether the given iso2 code belongs to an enabled language.
     *
     * @param string $iso2 Two-letter language code.
     * @return bool
     */
    private function is_valid_iso2( string $iso2 ): bool {
        assert( is_string( $iso2 ), 'ISO2 must be a string' );

        $lang = $this->get_language_by_iso2( $iso2 );

        return null !== $lang;
    }

    /**
     * Get language by ISO2.
     *
     * @param string $iso2 Language code.
     * @return array|null
     */
    private function get_language_by_iso2( string $iso2 ): ?array {
        $mgr  = bs_get_language_manager();
        $lang = $mgr->get_by_iso2( $iso2 );

        return is_array( $lang ) ? $lang : null;
    }
}
