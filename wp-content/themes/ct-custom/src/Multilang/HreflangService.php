<?php
/**
 * hreflang Service.
 *
 * Outputs <link rel="alternate" hreflang="xx" href="..."> tags
 * in <head> for pages that belong to a translation group.
 *
 * @package BS_Custom
 */

namespace BSCustom\Multilang;

class HreflangService {

    /** @var int Maximum alternate links to output per page. */
    const MAX_ALTERNATES = 20;

    /**
     * Register the wp_head hook.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'wp_head', array( $this, 'output_hreflang_tags' ), 5 );
    }

    /**
     * Output hreflang link tags for the current singular page.
     *
     * Only fires on singular pages that have a translation_group.
     *
     * @return void
     */
    public function output_hreflang_tags(): void {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();

        assert( is_int( $post_id ), 'Post ID must be an integer' );

        if ( $post_id <= 0 ) {
            return;
        }

        $group = get_post_meta( $post_id, 'bs_translation_group', true );

        if ( empty( $group ) ) {
            return;
        }

        if ( ! function_exists( 'bs_get_language_manager' ) ) {
            return;
        }

        $mgr       = bs_get_language_manager();
        $default    = $mgr->get_default();
        $default_iso = $default ? $default['iso2'] : 'en';

        /* Query all pages sharing this translation group */
        $query_args = array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => self::MAX_ALTERNATES,
            'meta_key'       => 'bs_translation_group',
            'meta_value'     => $group,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        );

        $page_ids = get_posts( $query_args );

        assert( is_array( $page_ids ), 'Page IDs must be an array' );

        if ( empty( $page_ids ) ) {
            return;
        }

        $alternates = array();
        $count      = 0;

        foreach ( $page_ids as $pid ) {
            if ( $count >= self::MAX_ALTERNATES ) {
                break;
            }
            $count++;

            $lang = get_post_meta( $pid, 'bs_language', true );

            if ( empty( $lang ) ) {
                continue;
            }

            $url = get_permalink( $pid );

            if ( ! $url ) {
                continue;
            }

            $alternates[ $lang ] = $url;
        }

        if ( count( $alternates ) < 2 ) {
            return;
        }

        /* Output hreflang tags */
        $output_count = 0;

        foreach ( $alternates as $iso2 => $url ) {
            if ( $output_count >= self::MAX_ALTERNATES ) {
                break;
            }
            $output_count++;

            echo '<link rel="alternate" hreflang="' . esc_attr( $iso2 ) . '" href="' . esc_url( $url ) . '" />' . "\n";
        }

        /* x-default points to the default language version */
        if ( isset( $alternates[ $default_iso ] ) ) {
            echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $alternates[ $default_iso ] ) . '" />' . "\n";
        }
    }
}
