<?php
/**
 * Sitemap Index
 *
 * Renders the <sitemapindex> XML listing sub-sitemaps per post type and language.
 * Uses transient caching (1 hour TTL).
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class SitemapIndex {

    /** @var int Cache TTL in seconds (1 hour). */
    const CACHE_TTL = 3600;

    /** @var int Maximum sub-sitemaps. */
    const MAX_SITEMAPS = 100;

    /** @var string Transient key for cached output. */
    const CACHE_KEY = 'bs_sitemap_index';

    /** @var string Prefix for per-language sitemap index cache. */
    const LANG_CACHE_PREFIX = 'bs_sitemap_lang_index_';

    /**
     * Render the sitemap index XML.
     *
     * @return void
     */
    public function render() {
        assert( function_exists( 'get_transient' ), 'get_transient must exist' );
        assert( function_exists( 'home_url' ), 'home_url must exist' );

        status_header( 200 );
        header( 'Content-Type: text/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        $cached = get_transient( self::CACHE_KEY );

        if ( false !== $cached && is_string( $cached ) ) {
            echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $xml = $this->build();

        set_transient( self::CACHE_KEY, $xml, self::CACHE_TTL );

        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Build the sitemap index XML string.
     *
     * @return string
     */
    private function build() {
        $types     = $this->getSitemapTypes();
        $languages = self::getEnabledLanguageData();

        $xsl_url = home_url( '/sitemap.xsl' );
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $count = 0;

        /* If multilingual: top-level index lists one sitemap per language */
        if ( count( $languages ) > 1 ) {
            $lang_count = 0;

            foreach ( $languages as $lang ) {
                if ( $lang_count >= 50 ) { break; }
                if ( $count >= self::MAX_SITEMAPS ) { break; }

                if ( empty( $lang['iso2'] ) || empty( $lang['slug'] ) ) {
                    $lang_count++;
                    continue;
                }

                $lang_count++;
                $count++;

                $url     = home_url( '/' . $lang['slug'] . '.xml' );
                $lastmod = $this->getLastModifiedForLanguage( $lang['iso2'], $types );

                $xml .= "  <sitemap>\n";
                $xml .= "    <loc>" . esc_url( $url ) . "</loc>\n";

                if ( $lastmod ) {
                    $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
                }

                $xml .= "  </sitemap>\n";
            }
        } else {
            /* Single language: list per-type sitemaps with language suffix */
            $lang_iso2 = isset( $languages[0]['iso2'] ) ? $languages[0]['iso2'] : 'en';
            $type_count = 0;
            $max_types  = 20;

            foreach ( $types as $type ) {
                if ( $type_count >= $max_types ) { break; }
                if ( $count >= self::MAX_SITEMAPS ) { break; }
                $type_count++;
                $count++;

                $url     = home_url( "/{$type}-{$lang_iso2}.xml" );
                $lastmod = $this->getLastModifiedForType( $type, $lang_iso2 );

                $xml .= "  <sitemap>\n";
                $xml .= "    <loc>" . esc_url( $url ) . "</loc>\n";

                if ( $lastmod ) {
                    $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
                }

                $xml .= "  </sitemap>\n";
            }
        }

        $xml .= '</sitemapindex>' . "\n";

        return $xml;
    }

    /**
     * Get public post types eligible for sitemaps.
     *
     * @return array
     */
    private function getSitemapTypes() {
        $types = get_post_types( array( 'public' => true ), 'names' );

        /* Remove attachment type */
        unset( $types['attachment'] );

        assert( is_array( $types ), 'Post types must be an array' );

        $ordered   = array();
        $preferred = array( 'page', 'post' );

        foreach ( $preferred as $pref ) {
            if ( isset( $types[ $pref ] ) ) {
                $ordered[] = $pref;
                unset( $types[ $pref ] );
            }
        }

        if ( taxonomy_exists( 'category' ) ) {
            $ordered[] = 'category';
        }

        /* Tag taxonomy sitemap */
        if ( taxonomy_exists( 'post_tag' ) ) {
            if ( isset( $types['tag'] ) ) {
                unset( $types['tag'] );
            }
            $ordered[] = 'tag';
        }

        /* Author archive sitemap */
        if ( isset( $types['author'] ) ) {
            unset( $types['author'] );
        }
        $ordered[] = 'author';

        $remaining = array_values( $types );
        sort( $remaining );

        $all_types = array_merge( $ordered, $remaining );
        $enabled   = $this->getEnabledTypeFilter();

        if ( ! empty( $enabled ) ) {
            $all_types = array_values( array_filter( $all_types, function ( $type ) use ( $enabled ) {
                return in_array( $type, $enabled, true );
            } ) );
        }

        return $all_types;
    }

    /**
     * Get enabled sitemap type slugs from settings.
     *
     * Empty list means "all types enabled".
     *
     * @return array
     */
    private function getEnabledTypeFilter() {
        $raw = get_option( 'bs_seo_sitemap_post_types', '' );

        if ( '' === $raw ) {
            return array();
        }

        $decoded = json_decode( $raw, true );

        if ( ! is_array( $decoded ) ) {
            return array();
        }

        $result = array();
        $max    = 50;
        $count  = 0;

        foreach ( $decoded as $slug ) {
            if ( $count >= $max ) { break; }
            $count++;

            if ( is_string( $slug ) && '' !== $slug ) {
                $result[] = sanitize_key( $slug );
            }
        }

        $result = array_values( array_unique( array_filter( $result ) ) );

        return $result;
    }

    /**
     * Get enabled language data (iso2, native_name, slug), default first.
     *
     * @return array<int, array{iso2:string,native_name:string,slug:string,is_default:bool}>
     */
    public static function getEnabledLanguageData() {
        if ( ! function_exists( 'bs_get_language_manager' ) ) {
            return array(
                array(
                    'iso2'        => 'en',
                    'native_name' => 'English',
                    'slug'        => 'english',
                    'is_default'  => true,
                ),
            );
        }

        $mgr       = bs_get_language_manager();
        $languages = $mgr->get_enabled();
        $default   = $mgr->get_default();
        $default_iso2 = is_array( $default ) && ! empty( $default['iso2'] ) ? $default['iso2'] : '';

        $result    = array();
        $used_slugs = array();
        $max       = 50;
        $count     = 0;

        foreach ( $languages as $lang ) {
            if ( $count >= $max ) { break; }
            $count++;

            $iso2  = isset( $lang['iso2'] ) ? sanitize_key( $lang['iso2'] ) : '';
            $name  = isset( $lang['native_name'] ) && is_string( $lang['native_name'] ) ? $lang['native_name'] : $iso2;
            $slug  = sanitize_title( $name );

            if ( '' === $slug ) {
                $slug = $iso2;
            }
            if ( '' === $slug ) {
                $slug = 'language-' . $count;
            }

            $base = $slug;
            $i    = 2;
            while ( isset( $used_slugs[ $slug ] ) ) {
                $slug = $base . '-' . $iso2;
                if ( isset( $used_slugs[ $slug ] ) ) {
                    $slug = $base . '-' . $i;
                }
                $i++;
            }
            $used_slugs[ $slug ] = true;

            $is_default = ! empty( $lang['is_default'] ) || ( '' !== $default_iso2 && $iso2 === $default_iso2 );

            $result[] = array(
                'iso2'        => $iso2,
                'native_name' => (string) $name,
                'slug'        => $slug,
                'is_default'  => $is_default,
            );
        }

        if ( empty( $result ) ) {
            return array(
                array(
                    'iso2'        => 'en',
                    'native_name' => 'English',
                    'slug'        => 'english',
                    'is_default'  => true,
                ),
            );
        }

        /* Default language first */
        if ( '' !== $default_iso2 ) {
            $ordered = array();
            foreach ( $result as $lang ) {
                if ( $lang['iso2'] === $default_iso2 ) {
                    $ordered[] = $lang;
                }
            }
            foreach ( $result as $lang ) {
                if ( $lang['iso2'] !== $default_iso2 ) {
                    $ordered[] = $lang;
                }
            }
            return $ordered;
        }

        return $result;
    }

    /**
     * Build the noindex meta query used by sitemaps.
     *
     * @return array
     */
    private function getNoindexMetaQuery() {
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
    private function getLanguageData( $lang ) {
        $languages = self::getEnabledLanguageData();

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
    private function getLanguageCategoryIds( $lang ) {
        if ( '' === $lang || ! taxonomy_exists( 'category' ) ) {
            return array();
        }

        $lang      = sanitize_key( $lang );
        $lang_data = $this->getLanguageData( $lang );
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
            'number'     => self::MAX_SITEMAPS,
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
    private function getLanguageTagId( $lang ) {
        if ( '' === $lang || ! taxonomy_exists( 'post_tag' ) ) {
            return 0;
        }

        $lang      = sanitize_key( $lang );
        $lang_data = $this->getLanguageData( $lang );
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
    private function buildLanguageTaxQuery( $lang ) {
        $clauses = array();
        $cat_ids = $this->getLanguageCategoryIds( $lang );
        $tag_id  = $this->getLanguageTagId( $lang );

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
     * Get the last modified date for a post type + language combination.
     *
     * @param string $post_type Post type name.
     * @param string $lang      ISO2 language code (empty for all).
     * @return string W3C datetime or empty string.
     */
    private function getLastModified( $post_type, $lang ) {
        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'has_password'   => false,
        );

        if ( '' !== $lang && 'post' === $post_type ) {
            $tax_query = $this->buildLanguageTaxQuery( $lang );

            if ( empty( $tax_query ) ) {
                return '';
            }

            $args['tax_query']  = $tax_query;
            $args['meta_query'] = $this->getNoindexMetaQuery();
        } elseif ( '' !== $lang ) {
            $args['meta_key']   = 'bs_language';
            $args['meta_value'] = sanitize_text_field( $lang );
            $args['meta_query'] = $this->getNoindexMetaQuery();
        } else {
            $args['meta_query'] = $this->getNoindexMetaQuery();
        }

        $posts = get_posts( $args );

        if ( empty( $posts ) ) {
            return '';
        }

        $post_id  = $posts[0];
        $modified = get_post_modified_time( 'c', true, $post_id );

        return $modified ? $modified : '';
    }

    /**
     * Render the sitemap index for a single language.
     *
     * @param string $lang_iso2 ISO2 language code.
     * @return void
     */
    public function renderLanguage( $lang_iso2 ) {
        assert( is_string( $lang_iso2 ), 'Language must be a string' );

        $lang_iso2 = sanitize_key( $lang_iso2 );

        $languages = self::getEnabledLanguageData();
        $valid     = false;
        $max       = 50;
        $count     = 0;

        foreach ( $languages as $lang ) {
            if ( $count >= $max ) { break; }
            $count++;
            if ( $lang['iso2'] === $lang_iso2 ) {
                $valid = true;
                break;
            }
        }

        if ( ! $valid ) {
            status_header( 404 );
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: text/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        $cache_key = self::LANG_CACHE_PREFIX . $lang_iso2;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_string( $cached ) ) {
            echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $xml = $this->buildLanguageIndex( $lang_iso2 );

        set_transient( $cache_key, $xml, self::CACHE_TTL );

        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Build sitemap index XML for a single language.
     *
     * @param string $lang_iso2 ISO2 language code.
     * @return string
     */
    private function buildLanguageIndex( $lang_iso2 ) {
        $types = $this->getSitemapTypes();

        $xsl_url = home_url( '/sitemap.xsl' );
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $count     = 0;
        $max_types = 20;
        $type_count = 0;

        foreach ( $types as $type ) {
            if ( $type_count >= $max_types ) { break; }
            if ( $count >= self::MAX_SITEMAPS ) { break; }
            $type_count++;
            $count++;

            $url     = home_url( "/{$type}-{$lang_iso2}.xml" );
            $lastmod = $this->getLastModifiedForType( $type, $lang_iso2 );

            $xml .= "  <sitemap>\n";
            $xml .= "    <loc>" . esc_url( $url ) . "</loc>\n";

            if ( $lastmod ) {
                $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            }

            $xml .= "  </sitemap>\n";
        }

        $xml .= '</sitemapindex>' . "\n";

        return $xml;
    }

    /**
     * Get last modified date for a type + language.
     *
     * @param string $type Post type slug or 'category'.
     * @param string $lang ISO2 language code.
     * @return string W3C datetime or empty string.
     */
    private function getLastModifiedForType( $type, $lang ) {
        if ( 'category' === $type ) {
            return '';
        }

        if ( 'tag' === $type ) {
            return $this->getLastModified( 'post', $lang );
        }

        if ( 'author' === $type ) {
            return $this->getLastModified( 'post', $lang );
        }

        return $this->getLastModified( $type, $lang );
    }

    /**
     * Get the most recent lastmod across all types for a language.
     *
     * @param string $lang  ISO2 language code.
     * @param array  $types Sitemap types.
     * @return string
     */
    private function getLastModifiedForLanguage( $lang, $types ) {
        $latest_ts  = 0;
        $latest_mod = '';
        $max        = 50;
        $count      = 0;

        foreach ( $types as $type ) {
            if ( $count >= $max ) { break; }
            $count++;

            $lastmod = $this->getLastModifiedForType( $type, $lang );
            if ( '' === $lastmod ) {
                continue;
            }
            $ts = strtotime( $lastmod );
            if ( $ts > $latest_ts ) {
                $latest_ts  = $ts;
                $latest_mod = $lastmod;
            }
        }

        return $latest_mod;
    }

    /**
     * Clear sitemap index caches (main + per-language).
     *
     * @return void
     */
    public static function clearCache() {
        delete_transient( self::CACHE_KEY );

        $languages = self::getEnabledLanguageData();
        $max       = 50;
        $count     = 0;

        foreach ( $languages as $lang ) {
            if ( $count >= $max ) { break; }
            $count++;

            delete_transient( self::LANG_CACHE_PREFIX . $lang['iso2'] );
        }
    }
}
