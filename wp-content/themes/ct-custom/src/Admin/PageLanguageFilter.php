<?php
/**
 * Page Language Filter — admin list-table dropdown.
 *
 * Adds a language select box and Filter button to the Pages
 * list table so editors can filter by the ct_language post-meta.
 *
 * @package BSCustom\Admin
 */

namespace BSCustom\Admin;

class PageLanguageFilter {

    /** Maximum languages we will iterate over. */
    private const MAX_LANGUAGES = 50;

    /** Post-meta key that stores the language iso2 code. */
    private const META_KEY = 'ct_language';

    /** URL query parameter for language filtering. */
    private const QUERY_PARAM = 'ct_lang';

    /** Post type this filter applies to. */
    private const POST_TYPE = 'page';

    /**
     * Register WordPress hooks.
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'restrict_manage_posts', array( $this, 'render_language_dropdown' ) );
        add_action( 'pre_get_posts', array( $this, 'filter_pages_query' ) );
    }

    /**
     * Enqueue the filter CSS on the Pages list screen.
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
            'ct-page-language-filter',
            get_template_directory_uri() . '/assets/admin/css/page-language-filter.css',
            array(),
            $css_ver
        );
    }

    /**
     * Render the language select box alongside the native filters.
     *
     * Outputs a <select> + Filter button inside the tablenav actions
     * area, matching the bulk-actions pattern used by WordPress core.
     *
     * @param string $post_type Current post type.
     */
    public function render_language_dropdown( string $post_type ): void {
        assert( is_string( $post_type ), 'Post type must be a string' );

        if ( self::POST_TYPE !== $post_type ) {
            return;
        }

        $lang_mgr = ct_get_language_manager();
        $enabled  = $lang_mgr->get_enabled();

        assert( is_array( $enabled ), 'Enabled languages must be an array' );

        if ( empty( $enabled ) ) {
            return;
        }

        $counts     = $this->count_pages_by_language();
        $active_iso = isset( $_GET[ self::QUERY_PARAM ] )
            ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PARAM ] ) )
            : '';

        $options = sprintf(
            '<option value="">%s</option>',
            esc_html__( 'All Languages', 'flavor' )
        );

        $lang_count = 0;

        for ( $i = 0, $len = count( $enabled ); $i < $len && $lang_count < self::MAX_LANGUAGES; $i++ ) {
            $lang       = $enabled[ $i ];
            $iso2       = $lang['iso2'];
            $name       = $lang['native_name'];
            $page_count = isset( $counts[ $iso2 ] ) ? (int) $counts[ $iso2 ] : 0;
            $selected   = selected( $active_iso, $iso2, false );
            $lang_count++;

            $options .= sprintf(
                '<option value="%s"%s>%s (%d)</option>',
                esc_attr( $iso2 ),
                $selected,
                esc_html( $name ),
                $page_count
            );
        }

        printf(
            '<label for="ct-lang-filter" class="screen-reader-text">%s</label>'
            . '<select name="%s" id="ct-lang-filter">%s</select>',
            esc_html__( 'Filter by language', 'flavor' ),
            esc_attr( self::QUERY_PARAM ),
            $options
        );
    }

    /**
     * Filter the main admin query by language when ct_lang is set.
     *
     * @param \WP_Query $query The query being modified.
     */
    public function filter_pages_query( $query ): void {
        assert( $query instanceof \WP_Query, 'Query must be a WP_Query instance' );

        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( null === $screen || 'edit' !== $screen->base || self::POST_TYPE !== $screen->post_type ) {
            return;
        }

        if ( ! isset( $_GET[ self::QUERY_PARAM ] ) || '' === $_GET[ self::QUERY_PARAM ] ) {
            return;
        }

        $iso2 = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PARAM ] ) );

        if ( ! $this->is_valid_iso2( $iso2 ) ) {
            return;
        }

        $existing = $query->get( 'meta_query' );
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }

        $existing[] = array(
            'key'     => self::META_KEY,
            'value'   => $iso2,
            'compare' => '=',
        );

        $query->set( 'meta_query', $existing );
    }

    /**
     * Count pages per language in a single SQL query.
     *
     * @return array<string, int> Associative array of iso2 => count.
     */
    private function count_pages_by_language(): array {
        global $wpdb;

        assert( $wpdb instanceof \wpdb, 'wpdb must be available' );

        $statuses     = array( 'publish', 'draft', 'pending', 'private' );
        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        $sql = $wpdb->prepare(
            "SELECT pm.meta_value AS lang, COUNT(*) AS cnt
               FROM {$wpdb->postmeta} pm
               JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE pm.meta_key = %s
                AND p.post_type = %s
                AND p.post_status IN ({$placeholders})
           GROUP BY pm.meta_value",
            array_merge(
                array( self::META_KEY, self::POST_TYPE ),
                $statuses
            )
        );

        $results = $wpdb->get_results( $sql );

        assert( is_array( $results ) || null === $results, 'DB results must be array or null' );

        $counts = array();
        $limit  = self::MAX_LANGUAGES;
        $idx    = 0;

        if ( is_array( $results ) ) {
            for ( $i = 0, $len = count( $results ); $i < $len && $idx < $limit; $i++ ) {
                $row = $results[ $i ];
                $counts[ $row->lang ] = (int) $row->cnt;
                $idx++;
            }
        }

        return $counts;
    }

    /**
     * Check whether the given iso2 code belongs to an enabled language.
     *
     * @param string $iso2 Two-letter language code.
     * @return bool
     */
    private function is_valid_iso2( string $iso2 ): bool {
        assert( is_string( $iso2 ), 'ISO2 must be a string' );

        $lang_mgr = ct_get_language_manager();
        $lang     = $lang_mgr->get_by_iso2( $iso2 );

        return null !== $lang;
    }
}
