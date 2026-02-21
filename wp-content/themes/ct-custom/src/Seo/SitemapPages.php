<?php
/**
 * Sitemap Pages
 *
 * Renders <urlset> XML for a specific post type (and optionally language).
 * Includes image entries, hreflang alternates, and cache invalidation.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class SitemapPages {

    /** @var int Cache TTL in seconds (1 hour). */
    const CACHE_TTL = 3600;

    /** @var int Maximum URLs per sitemap. */
    const MAX_URLS = 2000;

    /** @var int Maximum images per URL entry. */
    const MAX_IMAGES = 10;

    /** @var int Maximum hreflang alternates per URL. */
    const MAX_ALTERNATES = 50;

    /**
     * Boot cache invalidation hooks.
     *
     * @return void
     */
    public static function boot() {
        assert( function_exists( 'add_action' ), 'add_action must exist' );

        add_action( 'save_post', array( self::class, 'invalidateCache' ), 10, 1 );
        add_action( 'delete_post', array( self::class, 'invalidateCache' ), 10, 1 );
        add_action( 'transition_post_status', array( self::class, 'invalidateCacheOnTransition' ), 10, 3 );
    }

    /**
     * Render the urlset XML for a given post type and language.
     *
     * @param string $post_type Post type slug (e.g. 'page', 'post').
     * @param string $lang      Optional ISO2 language code.
     * @return void
     */
    public function render( $post_type, $lang = '' ) {
        assert( is_string( $post_type ), 'Post type must be a string' );
        assert( is_string( $lang ), 'Language must be a string' );

        $post_type = sanitize_key( $post_type );
        $lang      = sanitize_key( $lang );

        if ( ! $this->isTypeEnabled( $post_type ) ) {
            status_header( 404 );
            exit;
        }

        /* Category sitemap (taxonomy) */
        if ( 'category' === $post_type ) {
            $this->renderCategorySitemap( $lang );
            return;
        }

        /* Tag sitemap (taxonomy) */
        if ( 'tag' === $post_type ) {
            $this->renderTagSitemap( $lang );
            return;
        }

        /* Author archive sitemap */
        if ( 'author' === $post_type ) {
            $this->renderAuthorSitemap( $lang );
            return;
        }

        /* Validate post type */
        $valid_types = get_post_types( array( 'public' => true ), 'names' );
        unset( $valid_types['attachment'] );

        if ( ! isset( $valid_types[ $post_type ] ) ) {
            status_header( 404 );
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: text/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        $cache_key = 'bs_sitemap_' . $post_type . ( '' !== $lang ? '_' . $lang : '' );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_string( $cached ) ) {
            echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $xml = $this->build( $post_type, $lang );

        set_transient( $cache_key, $xml, self::CACHE_TTL );

        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Build the urlset XML string.
     *
     * @param string $post_type Post type slug.
     * @param string $lang      ISO2 language code.
     * @return string
     */
    private function build( $post_type, $lang ) {
        $exclude_ids = $this->getExcludedIds();

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => self::MAX_URLS,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'has_password'   => false,
        );

        $use_query = true;

        if ( '' !== $lang && 'post' === $post_type ) {
            $tax_query = $this->buildLanguageTaxQuery( $lang );

            if ( empty( $tax_query ) ) {
                $use_query = false;
            } else {
                $args['tax_query'] = $tax_query;
            }
        } elseif ( '' !== $lang ) {
            $args['meta_key']   = 'bs_language';
            $args['meta_value'] = sanitize_text_field( $lang );
        }

        /* Exclude noindex pages via meta query */
        $args['meta_query'] = $this->getNoindexMetaQuery();

        $posts = $use_query ? get_posts( $args ) : array();

        $xsl_url = home_url( '/sitemap.xsl' );
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
        $xml .= '<urlset';
        $xml .= ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        $xml .= '>' . "\n";

        $url_count = 0;

        foreach ( $posts as $post ) {
            if ( $url_count >= self::MAX_URLS ) { break; }

            /* Skip excluded post IDs */
            if ( in_array( $post->ID, $exclude_ids, true ) ) {
                continue;
            }

            $url_count++;

            $permalink = get_permalink( $post->ID );
            $modified  = get_post_modified_time( 'c', true, $post->ID );
            $priority  = $this->getPriority( $post );
            $changefreq = $this->getChangeFreq( $post );

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . esc_url( $permalink ) . "</loc>\n";

            if ( $modified ) {
                $xml .= "    <lastmod>{$modified}</lastmod>\n";
            }

            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";

            /* Image entries */
            $xml .= $this->buildImageEntries( $post );

            /* Hreflang alternates */
            $xml .= $this->buildHreflangEntries( $post );

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    /**
     * Render category sitemap (taxonomy) for a given language.
     *
     * @param string $lang ISO2 language code.
     * @return void
     */
    private function renderCategorySitemap( $lang ) {
        if ( ! taxonomy_exists( 'category' ) ) {
            status_header( 404 );
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: text/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        $cache_key = 'bs_sitemap_category' . ( '' !== $lang ? '_' . $lang : '' );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_string( $cached ) ) {
            echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $xml = $this->buildCategorySitemap( $lang );

        set_transient( $cache_key, $xml, self::CACHE_TTL );

        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Build category sitemap XML.
     *
     * @param string $lang ISO2 language code.
     * @return string
     */
    private function buildCategorySitemap( $lang ) {
        $terms = $this->getCategoryTermsForLanguage( $lang );

        $xsl_url = home_url( '/sitemap.xsl' );
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
        $xml .= '<urlset';
        $xml .= ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= '>' . "\n";

        $count = 0;

        if ( is_array( $terms ) ) {
            foreach ( $terms as $term ) {
                if ( $count >= self::MAX_URLS ) { break; }
                $count++;

                $term_link = get_term_link( $term );
                if ( is_wp_error( $term_link ) || empty( $term_link ) ) {
                    continue;
                }

                $xml .= "  <url>\n";
                $xml .= "    <loc>" . esc_url( $term_link ) . "</loc>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "    <priority>0.3</priority>\n";
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    /**
     * Render tag sitemap for a given language.
     *
     * @param string $lang ISO2 language code.
     * @return void
     */
    private function renderTagSitemap( $lang ) {
        if ( ! taxonomy_exists( 'post_tag' ) ) {
            status_header( 404 );
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: text/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        $cache_key = 'bs_sitemap_tag' . ( '' !== $lang ? '_' . $lang : '' );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_string( $cached ) ) {
            echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $xml = $this->buildTagSitemap( $lang );

        set_transient( $cache_key, $xml, self::CACHE_TTL );

        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Build tag sitemap XML.
     *
     * @param string $lang ISO2 language code.
     * @return string
     */
    private function buildTagSitemap( $lang ) {
        $terms = $this->getTagTermsForLanguage( $lang );

        $xsl_url = home_url( '/sitemap.xsl' );
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
        $xml .= '<urlset';
        $xml .= ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= '>' . "\n";

        $count = 0;

        if ( is_array( $terms ) ) {
            foreach ( $terms as $term ) {
                if ( $count >= self::MAX_URLS ) { break; }
                $count++;

                $term_link = get_term_link( $term, 'post_tag' );
                if ( is_wp_error( $term_link ) || empty( $term_link ) ) {
                    continue;
                }

                $xml .= "  <url>\n";
                $xml .= "    <loc>" . esc_url( $term_link ) . "</loc>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "    <priority>0.3</priority>\n";
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    /**
     * Render author sitemap for a given language.
     *
     * @param string $lang ISO2 language code.
     * @return void
     */
    private function renderAuthorSitemap( $lang ) {
        status_header( 200 );
        header( 'Content-Type: text/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        $cache_key = 'bs_sitemap_author' . ( '' !== $lang ? '_' . $lang : '' );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_string( $cached ) ) {
            echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $xml = $this->buildAuthorSitemap( $lang );

        set_transient( $cache_key, $xml, self::CACHE_TTL );

        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Build author sitemap XML.
     *
     * @param string $lang ISO2 language code.
     * @return string
     */
    private function buildAuthorSitemap( $lang ) {
        $author_ids = $this->getAuthorIdsForLanguage( $lang, self::MAX_URLS );

        $xsl_url = home_url( '/sitemap.xsl' );
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
        $xml .= '<urlset';
        $xml .= ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= '>' . "\n";

        $count = 0;

        foreach ( $author_ids as $author_id ) {
            if ( $count >= self::MAX_URLS ) { break; }
            $count++;

            $author_url = $this->getAuthorUrlForLanguage( $author_id, $lang );
            if ( empty( $author_url ) ) {
                continue;
            }

            $lastmod = $this->getAuthorLastMod( $author_id, $lang );

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . esc_url( $author_url ) . "</loc>\n";

            if ( $lastmod ) {
                $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            }

            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= "    <priority>0.4</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    /**
     * Build an author URL for a specific language (prefixing /{lang}/ when provided).
     *
     * @param int    $author_id Author user ID.
     * @param string $lang      ISO2 language code.
     * @return string
     */
    private function getAuthorUrlForLanguage( $author_id, $lang ) {
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
     * Get author IDs with published posts (optionally filtered by language).
     *
     * @param string $lang  ISO2 language code.
     * @param int    $limit Max author IDs to return.
     * @return array<int, int>
     */
    private function getAuthorIdsForLanguage( $lang, $limit ) {
        global $wpdb;

        $limit = max( 1, min( self::MAX_URLS, (int) $limit ) );

        if ( ! isset( $wpdb ) ) {
            return array();
        }

        $post_ids = $this->getLanguagePostIds( $lang, $limit );

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
     * Get latest modified date for an author's posts.
     *
     * @param int    $author_id Author user ID.
     * @param string $lang      ISO2 language code.
     * @return string
     */
    private function getAuthorLastMod( $author_id, $lang ) {
        $tax_query = $this->buildLanguageTaxQuery( $lang );

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
            'has_password'   => false,
            'tax_query'      => $tax_query,
            'meta_query'     => $this->getNoindexMetaQuery(),
        );

        $posts = get_posts( $args );

        if ( empty( $posts ) ) {
            return '';
        }

        $lastmod = get_post_modified_time( 'c', true, $posts[0] );

        return $lastmod ? $lastmod : '';
    }

    /**
     * Get category terms for a language (parent term = ISO2).
     *
     * @param string $lang ISO2 language code.
     * @return array<int, \WP_Term>
     */
    private function getCategoryTermsForLanguage( $lang ) {
        $args = array(
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'number'     => self::MAX_URLS,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $uncategorized_id = 0;
        $uncat_term = get_term_by( 'slug', 'uncategorized', 'category' );
        if ( $uncat_term && ! is_wp_error( $uncat_term ) ) {
            $uncategorized_id = (int) $uncat_term->term_id;
        }

        if ( '' !== $lang ) {
            $term_ids = $this->getLanguageCategoryIds( $lang );

            if ( $uncategorized_id > 0 && ! empty( $term_ids ) ) {
                $term_ids = array_values( array_diff( $term_ids, array( $uncategorized_id ) ) );
            }

            if ( empty( $term_ids ) ) {
                return array();
            }

            $args['include'] = $term_ids;
        } else {
            $args['parent'] = 0;
        }

        $terms = get_terms( $args );

        return is_array( $terms ) ? $terms : array();
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
            'number'     => self::MAX_URLS,
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
     * Get post IDs for a language based on category/tag markers.
     *
     * @param string $lang  ISO2 language code.
     * @param int    $limit Max IDs to return.
     * @return array<int, int>
     */
    private function getLanguagePostIds( $lang, $limit ) {
        $limit = max( 1, min( self::MAX_URLS, (int) $limit ) );

        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'has_password'   => false,
            'meta_query'     => $this->getNoindexMetaQuery(),
        );

        if ( '' !== $lang ) {
            $tax_query = $this->buildLanguageTaxQuery( $lang );

            if ( empty( $tax_query ) ) {
                return array();
            }

            $args['tax_query'] = $tax_query;
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
     * @param string $lang ISO2 language code.
     * @return array<int, \WP_Term>
     */
    private function getTagTermsForLanguage( $lang ) {
        if ( ! taxonomy_exists( 'post_tag' ) ) {
            return array();
        }

        $ids = $this->getTagIdsForLanguage( $lang, self::MAX_URLS );

        if ( empty( $ids ) ) {
            return array();
        }

        $terms = get_terms( array(
            'taxonomy'   => 'post_tag',
            'include'    => $ids,
            'hide_empty' => false,
            'number'     => self::MAX_URLS,
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
    private function getTagIdsForLanguage( $lang, $limit ) {
        $limit = max( 1, min( self::MAX_URLS, (int) $limit ) );

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

        $language_tag_id = $this->getLanguageTagId( $lang );

        if ( $language_tag_id <= 0 ) {
            return array();
        }

        $post_ids = $this->getPostIdsWithLanguageTag( $language_tag_id, self::MAX_URLS );

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
    private function getPostIdsWithLanguageTag( $tag_id, $limit ) {
        $limit = max( 1, min( self::MAX_URLS, (int) $limit ) );

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
            'meta_query'     => $this->getNoindexMetaQuery(),
        );

        $ids = get_posts( $args );

        if ( ! is_array( $ids ) ) {
            return array();
        }

        return array_map( 'intval', $ids );
    }

    /**
     * Check if a sitemap type is enabled in settings.
     *
     * Empty list means "all types enabled".
     *
     * @param string $type Post type slug or 'category'.
     * @return bool
     */
    private function isTypeEnabled( $type ) {
        $enabled = $this->getEnabledTypeFilter();

        if ( empty( $enabled ) ) {
            return true;
        }

        return in_array( $type, $enabled, true );
    }

    /**
     * Get enabled sitemap type slugs from settings.
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

        return array_values( array_unique( array_filter( $result ) ) );
    }

    /**
     * Build image sitemap entries from featured image + content images.
     *
     * @param \WP_Post $post Post object.
     * @return string XML fragment.
     */
    private function buildImageEntries( $post ) {
        $xml    = '';
        $images = array();
        $count  = 0;

        /* Featured image */
        $thumbnail_id = get_post_thumbnail_id( $post->ID );

        if ( $thumbnail_id ) {
            $thumb_url = wp_get_attachment_image_url( $thumbnail_id, 'full' );

            if ( $thumb_url ) {
                $images[] = array(
                    'url'   => $thumb_url,
                    'title' => get_the_title( $thumbnail_id ),
                );
            }
        }

        /* Content images (extract <img> src attributes) */
        if ( ! empty( $post->post_content ) ) {
            $pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';
            $matches = array();

            if ( preg_match_all( $pattern, $post->post_content, $matches ) ) {
                $max_content_imgs = self::MAX_IMAGES - count( $images );
                $img_count = 0;

                for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
                    if ( $img_count >= $max_content_imgs ) { break; }
                    $img_count++;

                    $img_url = $matches[1][ $i ];

                    /* Skip data URIs and external tracking pixels */
                    if ( 0 === strpos( $img_url, 'data:' ) ) {
                        continue;
                    }

                    /* Extract alt text if available */
                    $alt = '';
                    $alt_pattern = '/alt=["\']([^"\']*)["\']/' ;

                    if ( preg_match( $alt_pattern, $matches[0][ $i ], $alt_match ) ) {
                        $alt = $alt_match[1];
                    }

                    $images[] = array(
                        'url'   => $img_url,
                        'title' => $alt,
                    );
                }
            }
        }

        /* Render image entries */
        foreach ( $images as $image ) {
            if ( $count >= self::MAX_IMAGES ) { break; }
            $count++;

            $xml .= "    <image:image>\n";
            $xml .= "      <image:loc>" . esc_url( $image['url'] ) . "</image:loc>\n";

            if ( ! empty( $image['title'] ) ) {
                $xml .= '      <image:title>' . $this->xmlEscape( $image['title'] ) . "</image:title>\n";
            }

            $xml .= "    </image:image>\n";
        }

        return $xml;
    }

    /**
     * Build hreflang alternate entries using bs_translation_group meta.
     *
     * @param \WP_Post $post Post object.
     * @return string XML fragment.
     */
    private function buildHreflangEntries( $post ) {
        $xml = '';
        $group = get_post_meta( $post->ID, 'bs_translation_group', true );

        if ( empty( $group ) ) {
            return '';
        }

        /* Find all posts in the same translation group */
        $related = get_posts( array(
            'post_type'      => $post->post_type,
            'post_status'    => 'publish',
            'posts_per_page' => self::MAX_ALTERNATES,
            'no_found_rows'  => true,
            'meta_key'       => 'bs_translation_group',
            'meta_value'     => $group,
        ) );

        $alt_count = 0;

        foreach ( $related as $rel_post ) {
            if ( $alt_count >= self::MAX_ALTERNATES ) { break; }
            $alt_count++;

            $rel_lang = get_post_meta( $rel_post->ID, 'bs_language', true );

            if ( empty( $rel_lang ) ) {
                continue;
            }

            $rel_url = get_permalink( $rel_post->ID );

            $xml .= '    <xhtml:link rel="alternate" hreflang="' . esc_attr( $rel_lang ) . '"';
            $xml .= ' href="' . esc_url( $rel_url ) . '" />' . "\n";
        }

        return $xml;
    }

    /**
     * Get the sitemap priority for a post.
     *
     * @param \WP_Post $post Post object.
     * @return string Priority value (0.0 to 1.0).
     */
    private function getPriority( $post ) {
        assert( $post instanceof \WP_Post, 'post must be WP_Post' );

        /* Per-page override stored via the admin priorities panel */
        $meta = get_post_meta( $post->ID, 'bs_seo_sitemap_priority', true );

        if ( '' !== $meta ) {
            return $meta;
        }

        /* Auto-compute fallback */
        $front_page_id = (int) get_option( 'page_on_front' );

        if ( $post->ID === $front_page_id ) {
            return '1.0';
        }

        if ( 'page' === $post->post_type && 0 === (int) $post->post_parent ) {
            return '0.8';
        }

        if ( 'post' === $post->post_type ) {
            return '0.6';
        }

        return '0.5';
    }

    /**
     * Get the changefreq value for a post.
     *
     * @param \WP_Post $post Post object.
     * @return string
     */
    private function getChangeFreq( $post ) {
        assert( $post instanceof \WP_Post, 'post must be WP_Post' );

        /* Per-page override */
        $meta = get_post_meta( $post->ID, 'bs_seo_sitemap_changefreq', true );

        if ( '' !== $meta ) {
            return $meta;
        }

        /* Auto-compute fallback */
        $modified = strtotime( $post->post_modified_gmt );
        $now      = time();
        $diff     = $now - $modified;

        /* Modified within last week */
        if ( $diff < 604800 ) {
            return 'daily';
        }

        /* Modified within last month */
        if ( $diff < 2592000 ) {
            return 'weekly';
        }

        return 'monthly';
    }

    /**
     * Get excluded post IDs from settings.
     *
     * @return array Array of integer IDs.
     */
    private function getExcludedIds() {
        $raw = get_option( 'bs_seo_sitemap_excluded', '' );

        if ( empty( $raw ) ) {
            return array();
        }

        $parts = explode( ',', $raw );
        $ids   = array();
        $max   = 200;
        $count = 0;

        foreach ( $parts as $part ) {
            if ( $count >= $max ) { break; }
            $count++;

            $id = (int) trim( $part );

            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Escape a string for use in XML content.
     *
     * @param string $str Input string.
     * @return string Escaped string.
     */
    private function xmlEscape( $str ) {
        return htmlspecialchars( $str, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }

    /**
     * Invalidate sitemap caches when a post is saved or deleted.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public static function invalidateCache( $post_id ) {
        assert( is_int( $post_id ) || is_numeric( $post_id ), 'Post ID must be numeric' );

        $post = get_post( $post_id );

        if ( ! $post ) {
            return;
        }

        /* Delete index cache */
        SitemapIndex::clearCache();

        /* Delete type-specific caches */
        $post_type = $post->post_type;
        $lang      = get_post_meta( $post_id, 'bs_language', true );

        delete_transient( 'bs_sitemap_' . $post_type );

        if ( ! empty( $lang ) ) {
            delete_transient( 'bs_sitemap_' . $post_type . '_' . $lang );
        }

        /* Author sitemap depends on post changes */
        delete_transient( 'bs_sitemap_author' );

        if ( ! empty( $lang ) ) {
            delete_transient( 'bs_sitemap_author_' . $lang );
        }

        /* Tag sitemap depends on post changes */
        delete_transient( 'bs_sitemap_tag' );

        if ( ! empty( $lang ) ) {
            delete_transient( 'bs_sitemap_tag_' . $lang );
        }

        /* Also clear all language variants for this type */
        if ( function_exists( 'bs_get_language_manager' ) ) {
            $mgr       = bs_get_language_manager();
            $languages = $mgr->get_enabled();
            $max       = 50;
            $count     = 0;

            foreach ( $languages as $l ) {
                if ( $count >= $max ) { break; }
                $count++;

                delete_transient( 'bs_sitemap_' . $post_type . '_' . $l['iso2'] );
                delete_transient( 'bs_sitemap_author_' . $l['iso2'] );
                delete_transient( 'bs_sitemap_tag_' . $l['iso2'] );
            }
        }
    }

    /**
     * Invalidate cache on post status transitions.
     *
     * @param string   $new_status New post status.
     * @param string   $old_status Old post status.
     * @param \WP_Post $post       Post object.
     * @return void
     */
    public static function invalidateCacheOnTransition( $new_status, $old_status, $post ) {
        assert( is_string( $new_status ), 'New status must be a string' );
        assert( is_string( $old_status ), 'Old status must be a string' );

        /* Only invalidate when transitioning to/from publish */
        if ( 'publish' === $new_status || 'publish' === $old_status ) {
            self::invalidateCache( $post->ID );
        }
    }
}
