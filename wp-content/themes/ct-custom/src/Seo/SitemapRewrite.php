<?php
/**
 * Sitemap Rewrite Rules
 *
 * Registers rewrite rules for XML sitemaps and LLMs.txt.
 * Hooks template_redirect to serve sitemap/llms content.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class SitemapRewrite {

    /**
     * Boot the rewrite rules.
     *
     * @return void
     */
    public static function boot() {
        assert( function_exists( 'add_action' ), 'add_action must exist' );
        assert( function_exists( 'add_filter' ), 'add_filter must exist' );

        add_action( 'init', array( self::class, 'registerRewrites' ) );
        add_filter( 'query_vars', array( self::class, 'registerQueryVars' ) );
        add_action( 'template_redirect', array( self::class, 'handleRequest' ) );
        add_filter( 'redirect_canonical', array( self::class, 'preventCanonicalRedirect' ), 10, 2 );
        add_action( 'init', array( self::class, 'maybeFlushRules' ), 99 );
    }

    /**
     * Register sitemap, XSL stylesheet, and LLMs.txt rewrite rules.
     *
     * @return void
     */
    public static function registerRewrites() {
        assert( function_exists( 'add_rewrite_rule' ), 'add_rewrite_rule must exist' );

        /* XSL stylesheet — must come before sitemap rules */
        add_rewrite_rule(
            '^sitemap\.xsl$',
            'index.php?bs_sitemap_xsl=1',
            'top'
        );

        /* Sitemap index */
        add_rewrite_rule(
            '^sitemap_index\.xml$',
            'index.php?bs_sitemap=index',
            'top'
        );

        /* Per-type per-language sitemap (new format): page-en.xml */
        $types     = self::getSitemapTypesForRewrite();
        $max_types = 50;
        $type_count = 0;

        foreach ( $types as $type ) {
            if ( $type_count >= $max_types ) { break; }
            $type_count++;

            $safe_type = preg_quote( $type, '/' );
            add_rewrite_rule(
                '^' . $safe_type . '-([a-z]{2})\.xml$',
                'index.php?bs_sitemap=' . $type . '&bs_sitemap_lang=$matches[1]',
                'top'
            );
        }

        /* Per-type per-language sitemap: sitemap-page-en.xml */
        add_rewrite_rule(
            '^sitemap-([a-z]+)-([a-z]{2})\.xml$',
            'index.php?bs_sitemap=$matches[1]&bs_sitemap_lang=$matches[2]',
            'top'
        );

        /* Per-type sitemap (single-language): sitemap-page.xml */
        add_rewrite_rule(
            '^sitemap-([a-z]+)\.xml$',
            'index.php?bs_sitemap=$matches[1]',
            'top'
        );

        /* Language sitemap index: english.xml, serbian.xml (native-name slugs) */
        $languages = SitemapIndex::getEnabledLanguageData();
        $max       = 50;
        $count     = 0;

        foreach ( $languages as $lang ) {
            if ( $count >= $max ) { break; }
            $count++;

            if ( empty( $lang['slug'] ) || empty( $lang['iso2'] ) ) {
                continue;
            }

            $slug = preg_quote( $lang['slug'], '/' );
            add_rewrite_rule(
                '^' . $slug . '\.xml$',
                'index.php?bs_sitemap_lang_index=' . $lang['iso2'],
                'top'
            );
        }

        /* LLMs.txt */
        add_rewrite_rule(
            '^llms\.txt$',
            'index.php?bs_llms=1',
            'top'
        );
    }

    /**
     * Register custom query variables.
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public static function registerQueryVars( $vars ) {
        assert( is_array( $vars ), 'Query vars must be an array' );

        $vars[] = 'bs_sitemap';
        $vars[] = 'bs_sitemap_lang';
        $vars[] = 'bs_sitemap_lang_index';
        $vars[] = 'bs_sitemap_xsl';
        $vars[] = 'bs_llms';

        return $vars;
    }

    /**
     * Prevent WordPress canonical redirect for sitemap and LLMs.txt URLs.
     *
     * WordPress's redirect_canonical() adds trailing slashes before
     * template_redirect fires, breaking .xml/.xsl/.txt requests.
     *
     * @param string|false $redirect_url  Redirect target (or false to cancel).
     * @param string       $requested_url Original URL.
     * @return string|false
     */
    public static function preventCanonicalRedirect( $redirect_url, $requested_url ) {
        assert( is_string( $requested_url ), 'Requested URL must be a string' );

        if ( '' !== get_query_var( 'bs_sitemap' )
            || '' !== get_query_var( 'bs_sitemap_lang_index' )
            || '1' === get_query_var( 'bs_sitemap_xsl' )
            || '1' === get_query_var( 'bs_llms' )
        ) {
            return false;
        }

        $path = (string) wp_parse_url( $requested_url, PHP_URL_PATH );

        $is_sitemap = (bool) preg_match( '/\/sitemap[_\-][^\/]+\.xml$/i', $path );
        $is_index   = (bool) preg_match( '/\/sitemap_index\.xml$/i', $path );
        $is_legacy  = (bool) preg_match( '/\/(sitemap|wp-sitemap)(\.xml)?$/i', $path );
        $is_xsl     = (bool) preg_match( '/\/sitemap\.xsl$/i', $path );
        $is_llms    = (bool) preg_match( '/\/llms\.txt$/i', $path );

        if ( $is_sitemap || $is_index || $is_legacy || $is_xsl || $is_llms ) {
            return false;
        }

        return $redirect_url;
    }

    /**
     * Handle sitemap, XSL stylesheet, and LLMs.txt requests on template_redirect.
     *
     * @return void
     */
    public static function handleRequest() {
        self::maybeRedirectLegacySitemaps();

        $sitemap_type = get_query_var( 'bs_sitemap', '' );
        $sitemap_lang = get_query_var( 'bs_sitemap_lang', '' );
        $lang_index   = get_query_var( 'bs_sitemap_lang_index', '' );
        $sitemap_xsl  = get_query_var( 'bs_sitemap_xsl', '' );
        $llms_flag    = get_query_var( 'bs_llms', '' );

        /* XSL stylesheet */
        if ( '1' === $sitemap_xsl ) {
            self::serveXsl();
            exit;
        }

        /* Sitemap requests */
        if ( '' !== $sitemap_type || '' !== $lang_index ) {
            $sitemap_enabled = get_option( 'bs_seo_sitemap_enabled', 'on' );

            if ( 'on' !== $sitemap_enabled ) {
                status_header( 404 );
                exit;
            }

            if ( '' !== $lang_index ) {
                $index = new SitemapIndex();
                $index->renderLanguage( $lang_index );
                exit;
            }

            if ( 'index' === $sitemap_type ) {
                $index = new SitemapIndex();
                $index->render();
                exit;
            }

            $pages = new SitemapPages();
            $pages->render( $sitemap_type, $sitemap_lang );
            exit;
        }

        /* LLMs.txt requests */
        if ( '1' === $llms_flag ) {
            LlmsTxt::serve();
        }
    }

    /**
     * Serve the XSL sitemap stylesheet.
     *
     * Reads sitemap.xsl from the theme, replaces the sitemap index URL
     * placeholder, and outputs with the correct Content-Type.
     *
     * @return void
     */
    private static function serveXsl() {
        assert( function_exists( 'get_template_directory' ), 'get_template_directory must exist' );
        assert( function_exists( 'home_url' ), 'home_url must exist' );

        $xsl_file = get_template_directory() . '/src/Seo/sitemap.xsl';

        if ( ! file_exists( $xsl_file ) ) {
            status_header( 404 );
            exit;
        }

        $content = file_get_contents( $xsl_file );

        if ( false === $content ) {
            status_header( 500 );
            exit;
        }

        $index_url = home_url( '/sitemap_index.xml' );
        $content   = str_replace( 'BSSITEMAPINDEXURL', esc_url( $index_url ), $content );

        status_header( 200 );
        header( 'Content-Type: text/xsl; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );
        header( 'Cache-Control: public, max-age=3600' );

        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Redirect /sitemap.xml and /wp-sitemap.xml to our sitemap index.
     *
     * @return void
     */
    private static function maybeRedirectLegacySitemaps() {
        assert( function_exists( 'wp_parse_url' ), 'wp_parse_url must exist' );
        assert( function_exists( 'wp_safe_redirect' ), 'wp_safe_redirect must exist' );
        assert( function_exists( 'home_url' ), 'home_url must exist' );

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';

        if ( '' === $request_uri ) {
            return;
        }

        $path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

        if ( '' === $path ) {
            return;
        }

        $path = '/' . ltrim( rtrim( $path, '/' ), '/' );

        if ( '/sitemap' !== $path && '/wp-sitemap' !== $path
            && '/sitemap.xml' !== $path && '/wp-sitemap.xml' !== $path
        ) {
            return;
        }

        wp_safe_redirect( home_url( '/sitemap_index.xml' ), 301 );
        exit;
    }

    /**
     * Flush rewrite rules on first activation.
     *
     * @return void
     */
    public static function maybeFlushRules() {
        assert( function_exists( 'get_option' ), 'get_option must exist' );

        $required = '5'; // bump to force a re-flush after adding tag sitemap rules
        $lang_sig = self::getLanguageSignature();
        $expected = $required . ':' . $lang_sig;

        if ( get_option( 'bs_seo_rewrite_flushed' ) === $expected ) {
            return;
        }

        flush_rewrite_rules( false );
        update_option( 'bs_seo_rewrite_flushed', $expected );
    }

    /**
     * Build a hash signature for current language rewrite rules.
     *
     * @return string
     */
    private static function getLanguageSignature() {
        $languages = SitemapIndex::getEnabledLanguageData();
        $pairs     = array();
        $max       = 50;
        $count     = 0;

        foreach ( $languages as $lang ) {
            if ( $count >= $max ) { break; }
            $count++;

            $iso2 = isset( $lang['iso2'] ) ? $lang['iso2'] : '';
            $slug = isset( $lang['slug'] ) ? $lang['slug'] : '';
            $pairs[] = $iso2 . ':' . $slug;
        }

        sort( $pairs );

        return md5( implode( '|', $pairs ) );
    }

    /**
     * Get sitemap types for rewrite rule generation.
     *
     * @return array
     */
    private static function getSitemapTypesForRewrite() {
        $types = get_post_types( array( 'public' => true ), 'names' );

        unset( $types['attachment'] );

        $ordered = array();

        if ( isset( $types['page'] ) ) {
            $ordered[] = 'page';
            unset( $types['page'] );
        }
        if ( isset( $types['post'] ) ) {
            $ordered[] = 'post';
            unset( $types['post'] );
        }

        if ( taxonomy_exists( 'category' ) ) {
            $ordered[] = 'category';
        }

        if ( taxonomy_exists( 'post_tag' ) ) {
            if ( isset( $types['tag'] ) ) {
                unset( $types['tag'] );
            }
            $ordered[] = 'tag';
        }

        if ( isset( $types['author'] ) ) {
            unset( $types['author'] );
        }
        $ordered[] = 'author';

        $remaining = array_values( $types );
        sort( $remaining );

        return array_merge( $ordered, $remaining );
    }
}
