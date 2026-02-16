<?php
/**
 * CT Font Manager
 *
 * Handles Google Font downloading, file management,
 * @font-face CSS generation, and cleanup.
 *
 * Font files are stored in the theme's assets/fonts/ directory.
 * Docker entrypoint ensures write permissions on container start.
 *
 * Migrated from inc/customizer/class-ct-font-manager.php.
 * Old class: CT_Font_Manager -> New: FontManager
 *
 * @package CT_Custom
 */

namespace CTCustom\Customizer;

class FontManager {

    /** @var string Absolute path to the fonts directory. */
    private $fonts_dir = '';

    /** @var string Public URL to the fonts directory. */
    private $fonts_url = '';

    /** @var bool Whether paths have been resolved. */
    private $paths_resolved = false;

    /** @var int Maximum number of weight variants to process. */
    const MAX_WEIGHTS = 18;

    /** @var int Maximum number of woff2 URLs to download. */
    const MAX_DOWNLOADS = 18;

    public function __construct() {
        assert( true, 'FontManager constructed with deferred path resolution' );
        assert( function_exists( 'get_template_directory' ), 'get_template_directory must exist' );
    }

    /**
     * Log a debug message when WP_DEBUG is enabled.
     *
     * @param string $message The message to log.
     */
    private function debug_log( $message ) {
        assert( is_string( $message ), 'Log message must be a string' );
        assert( strlen( $message ) > 0, 'Log message must not be empty' );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[CT_FontManager] ' . $message );
        }
    }

    /**
     * Resolve fonts_dir and fonts_url on first access.
     */
    private function ensure_paths_resolved() {
        if ( $this->paths_resolved ) {
            return;
        }

        $this->fonts_dir = get_template_directory() . '/assets/fonts';

        /* Build fonts URL, ensuring it uses the same scheme as the site URL */
        $template_url   = get_template_directory_uri() . '/assets/fonts';
        $site_url       = home_url();
        $site_is_https  = ( 0 === strpos( $site_url, 'https://' ) );

        if ( $site_is_https && 0 === strpos( $template_url, 'http://' ) ) {
            $template_url = 'https://' . substr( $template_url, 7 );
        }

        $this->fonts_url      = $template_url;
        $this->paths_resolved = true;

        assert( is_string( $this->fonts_dir ), 'Fonts dir must be a string' );
        assert( is_string( $this->fonts_url ), 'Fonts url must be a string' );
    }

    /**
     * Initialize hooks.
     *
     * Only the cleanup hook remains. Font downloads are now
     * triggered via the REST API FontDownload endpoint.
     */
    public function init() {
        assert( is_string( $this->fonts_dir ), 'Fonts dir must be a string' );
        assert( is_string( $this->fonts_url ), 'Fonts url must be a string' );

        add_action( 'customize_save_after', array( $this, 'on_customizer_save' ) );
    }

    /**
     * Hook: after customizer save/publish.
     *
     * When fonts are enabled:
     *   1. Remove ALL woff2 files from assets/fonts/
     *   2. Re-download only the currently checked family + weights
     *   3. Store the resulting @font-face CSS in ct_font_face_css
     *
     * When fonts are disabled:
     *   Clean up all font files and remove ct_font_face_css.
     *
     * @param \WP_Customize_Manager $wp_customize Customizer manager.
     */
    public function on_customizer_save( $wp_customize ) {
        assert( $wp_customize instanceof \WP_Customize_Manager, 'Must receive WP_Customize_Manager' );

        $this->ensure_paths_resolved();

        $enabled = get_theme_mod( 'ct_font_enabled', false );
        $family  = get_theme_mod( 'ct_font_family', '' );
        $weights = get_theme_mod( 'ct_font_weights', '' );

        assert( is_string( $family ), 'Font family must be a string' );
        assert( is_string( $weights ), 'Font weights must be a string' );

        /* If fonts disabled or no family selected, clean up everything */
        if ( ! $enabled || '' === $family ) {
            $this->cleanup_all();
            remove_theme_mod( 'ct_font_face_css' );
            remove_theme_mod( 'ct_font_prev_family' );
            return;
        }

        /* Clean ALL font files — start fresh on every publish */
        $this->cleanup_all();

        /* Resolve display name to API family name for Google API calls */
        $api_family = self::resolve_api_family( $family );

        /* Re-download only the currently checked weights */
        if ( '' !== $weights ) {
            $valid_weights = self::validate_weights_for_font( $family, $weights );

            if ( ! empty( $valid_weights ) ) {
                $weights_string = implode( ',', $valid_weights );
                $face_css       = $this->download_font( $api_family, $weights_string, $family );

                if ( '' !== $face_css ) {
                    set_theme_mod( 'ct_font_face_css', $face_css );
                }
            }
        }

        set_theme_mod( 'ct_font_prev_family', $family );
    }

    /**
     * Validate that a font family exists in google-fonts.json.
     *
     * @param string $family Font family name.
     * @return bool True if font exists in catalog.
     */
    public static function validate_font_in_catalog( $family ) {
        assert( is_string( $family ), 'Family must be a string' );

        if ( '' === $family ) {
            return false;
        }

        $variants = self::get_font_variants( $family );

        return ! empty( $variants );
    }

    /**
     * Validate that requested weights are available for a font.
     *
     * @param string $family  Font family name.
     * @param string $weights Comma-separated weights (e.g. "400,400i,700").
     * @return array Valid weights that exist for this font.
     */
    public static function validate_weights_for_font( $family, $weights ) {
        assert( is_string( $family ), 'Family must be a string' );
        assert( is_string( $weights ), 'Weights must be a string' );

        $variants    = self::get_font_variants( $family );
        $requested   = array_filter( array_map( 'trim', explode( ',', $weights ) ) );
        $valid       = array();
        $count       = 0;

        foreach ( $requested as $w ) {
            if ( $count >= self::MAX_WEIGHTS ) {
                break;
            }
            $count++;

            $google_variant = self::weight_to_variant( $w );

            if ( in_array( $google_variant, $variants, true ) ) {
                $valid[] = $w;
            }
        }

        return $valid;
    }

    /**
     * Map our weight format to Google Fonts variant name.
     *
     * @param string $weight Weight like "400", "400i", "700".
     * @return string Google variant name like "regular", "italic", "700".
     */
    public static function weight_to_variant( $weight ) {
        assert( is_string( $weight ), 'Weight must be a string' );

        $map = array(
            '100'  => '100',
            '200'  => '200',
            '300'  => '300',
            '400'  => 'regular',
            '500'  => '500',
            '600'  => '600',
            '700'  => '700',
            '800'  => '800',
            '900'  => '900',
            '100i' => '100italic',
            '200i' => '200italic',
            '300i' => '300italic',
            '400i' => 'italic',
            '500i' => '500italic',
            '600i' => '600italic',
            '700i' => '700italic',
            '800i' => '800italic',
            '900i' => '900italic',
        );

        return isset( $map[ $weight ] ) ? $map[ $weight ] : $weight;
    }

    /**
     * Download woff2 files for a font family and return @font-face CSS.
     *
     * @param string $family       Font API family name for Google Fonts URL (e.g. "Roboto").
     * @param string $weights      Comma-separated weights (e.g. "400,400i,700,700i").
     * @param string $display_name Optional display name for @font-face CSS. Defaults to $family.
     * @return string Generated @font-face CSS block, or empty string on failure.
     */
    public function download_font( $family, $weights, $display_name = '' ) {
        assert( is_string( $family ) && '' !== $family, 'Family must be a non-empty string' );
        assert( is_string( $weights ), 'Weights must be a string' );

        if ( '' === $display_name ) {
            $display_name = $family;
        }

        $this->ensure_paths_resolved();
        $this->ensure_fonts_dir();

        /* Parse weight list */
        $weight_list = array_filter( array_map( 'trim', explode( ',', $weights ) ) );

        if ( empty( $weight_list ) ) {
            $weight_list = array( '400' );
        }

        /* Build Google Fonts CSS2 URL using the API family name */
        $url = $this->build_google_fonts_url( $family, $weight_list );

        if ( '' === $url ) {
            $this->debug_log( 'build_google_fonts_url returned empty for family=' . $family );
            return '';
        }

        $this->debug_log( 'Google Fonts URL: ' . $url );

        /* Fetch CSS with woff2 user-agent */
        $css_response = wp_remote_get( $url, array(
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ) );

        if ( is_wp_error( $css_response ) ) {
            $this->debug_log( 'CSS fetch WP_Error: ' . $css_response->get_error_message() );
            return '';
        }

        $css_status = (int) wp_remote_retrieve_response_code( $css_response );
        $css_body   = wp_remote_retrieve_body( $css_response );

        $this->debug_log( 'CSS response: status=' . $css_status . ', body_length=' . strlen( $css_body ) );

        if ( '' === $css_body ) {
            $this->debug_log( 'CSS body is empty, status=' . $css_status );
            return '';
        }

        /* Parse and download woff2 URLs, generate local @font-face rules.
         * Use display_name for @font-face CSS font-family, API name for filenames. */
        return $this->process_google_css( $display_name, $css_body );
    }

    /**
     * Build a Google Fonts CSS2 API URL.
     *
     * @param string $family      Font family name.
     * @param array  $weight_list Array of weight strings like "400", "700", "400i".
     * @return string URL.
     */
    private function build_google_fonts_url( $family, $weight_list ) {
        assert( is_string( $family ), 'Family must be a string' );
        assert( is_array( $weight_list ), 'Weight list must be an array' );

        $has_italic = false;
        $tuples     = array();
        $wght_only  = array();
        $count      = 0;

        foreach ( $weight_list as $w ) {
            if ( $count >= self::MAX_WEIGHTS ) {
                break;
            }
            $count++;

            $w = trim( $w );

            if ( '' === $w ) {
                continue;
            }

            /* Check for italic suffix */
            $is_italic = false;

            if ( substr( $w, -1 ) === 'i' || substr( $w, -6 ) === 'italic' ) {
                $is_italic = true;
                $has_italic = true;
                $w = rtrim( $w, 'i' );
                $w = str_replace( 'italic', '', $w );

                if ( '' === $w ) {
                    $w = '400';
                }
            }

            $wght = absint( $w );

            if ( $wght < 100 || $wght > 900 ) {
                continue;
            }

            $tuples[]    = ( $is_italic ? '1' : '0' ) . ',' . $wght;
            $wght_only[] = $wght;
        }

        if ( empty( $tuples ) ) {
            return '';
        }

        $encoded_family = rawurlencode( $family );

        /*
         * Use ital,wght@ axis only when italic weights are requested.
         * Fonts without italic support reject the ital axis entirely.
         */
        if ( $has_italic ) {
            sort( $tuples );
            $tuples      = array_unique( $tuples );
            $axis_string = implode( ';', $tuples );

            return 'https://fonts.googleapis.com/css2?family=' . $encoded_family . ':ital,wght@' . $axis_string . '&display=swap';
        }

        sort( $wght_only, SORT_NUMERIC );
        $wght_only   = array_unique( $wght_only );
        $axis_string = implode( ';', $wght_only );

        return 'https://fonts.googleapis.com/css2?family=' . $encoded_family . ':wght@' . $axis_string . '&display=swap';
    }

    /**
     * Parse Google CSS response, download woff2 files, build local @font-face CSS.
     *
     * @param string $family   Font family name.
     * @param string $css_body Raw CSS from Google Fonts API.
     * @return string Local @font-face CSS.
     */
    private function process_google_css( $family, $css_body ) {
        assert( is_string( $family ), 'Family must be a string' );
        assert( is_string( $css_body ), 'CSS body must be a string' );

        $face_blocks = array();

        /* Match each @font-face block */
        $match_count = preg_match_all(
            '/\/\*\s*([^*]*)\s*\*\/\s*@font-face\s*\{([^}]+)\}/s',
            $css_body,
            $matches,
            PREG_SET_ORDER
        );

        $this->debug_log( 'process_google_css: regex match_count=' . ( false === $match_count ? 'false' : $match_count ) );

        if ( false === $match_count || 0 === $match_count ) {
            /* Fallback: try without subset comment */
            $match_count = preg_match_all(
                '/@font-face\s*\{([^}]+)\}/s',
                $css_body,
                $matches_simple,
                PREG_SET_ORDER
            );

            $this->debug_log( 'process_google_css fallback: match_count=' . ( false === $match_count ? 'false' : $match_count ) );

            if ( false === $match_count || 0 === $match_count ) {
                $this->debug_log( 'process_google_css: no @font-face blocks found in CSS' );
                return '';
            }

            /* Restructure to match the comment+block pattern */
            $matches = array();
            $max_simple = min( count( $matches_simple ), self::MAX_DOWNLOADS );

            for ( $i = 0; $i < $max_simple; $i++ ) {
                $matches[] = array( $matches_simple[ $i ][0], 'latin', $matches_simple[ $i ][1] );
            }
        }

        /* Only process latin subset blocks to keep file count reasonable */
        $download_count = 0;
        $safe_family    = sanitize_file_name( $family );

        $max_blocks  = min( count( $matches ), 50 );
        $block_count = 0;

        foreach ( $matches as $m ) {
            if ( $block_count >= $max_blocks ) {
                break;
            }
            $block_count++;

            if ( $download_count >= self::MAX_DOWNLOADS ) {
                break;
            }

            $comment = isset( $m[1] ) ? trim( $m[1] ) : '';
            $block   = isset( $m[2] ) ? $m[2] : '';

            /* Only download latin subset or fallback (some fonts use "fallback" instead of "latin") */
            if ( '' !== $comment
                && false === stripos( $comment, 'latin' )
                && false === stripos( $comment, 'fallback' )
            ) {
                continue;
            }

            /* Skip latin-ext if pure latin exists */
            if ( false !== stripos( $comment, 'latin-ext' ) ) {
                continue;
            }

            /* Extract font-style */
            $style = 'normal';
            if ( preg_match( '/font-style:\s*(italic|normal)/', $block, $sm ) ) {
                $style = $sm[1];
            }

            /* Extract font-weight */
            $weight = '400';
            if ( preg_match( '/font-weight:\s*(\d+)/', $block, $wm ) ) {
                $weight = $wm[1];
            }

            /* Extract woff2 URL */
            if ( ! preg_match( '/url\(([^)]+\.woff2[^)]*)\)/', $block, $um ) ) {
                continue;
            }

            $remote_url = trim( $um[1], "' \"" );

            /* Build local filename */
            $italic_suffix = ( 'italic' === $style ) ? 'i' : '';
            $local_name    = $safe_family . '-' . $weight . $italic_suffix . '.woff2';
            $local_path    = $this->fonts_dir . '/' . $local_name;

            /* Download if not already present */
            if ( ! file_exists( $local_path ) ) {
                $dl_result = $this->download_file( $remote_url, $local_path );

                if ( ! $dl_result ) {
                    continue;
                }
            }

            $download_count++;

            /* Build @font-face rule */
            $local_url     = $this->fonts_url . '/' . $local_name;
            $face_blocks[] = "@font-face {\n"
                . "    font-family: '{$family}';\n"
                . "    font-style: {$style};\n"
                . "    font-weight: {$weight};\n"
                . "    font-display: swap;\n"
                . "    src: url('{$local_url}') format('woff2');\n"
                . '}';
        }

        if ( empty( $face_blocks ) ) {
            $this->debug_log( 'process_google_css: no latin @font-face blocks after filtering, family=' . $family );
            return '';
        }

        $this->debug_log( 'process_google_css: generated ' . count( $face_blocks ) . ' @font-face blocks for family=' . $family );

        return implode( "\n", $face_blocks );
    }

    /**
     * Download a remote file to a local path.
     *
     * @param string $url  Remote URL.
     * @param string $path Local file path.
     * @return bool True on success.
     */
    private function download_file( $url, $path ) {
        assert( is_string( $url ), 'URL must be a string' );
        assert( is_string( $path ), 'Path must be a string' );

        $response = wp_remote_get( $url, array(
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->debug_log( 'download_file WP_Error: ' . $response->get_error_message() . ', url=' . $url );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( 200 !== $code || '' === $body ) {
            $this->debug_log( 'download_file failed: status=' . $code . ', body_empty=' . ( '' === $body ? 'yes' : 'no' ) . ', url=' . $url );
            return false;
        }

        $written = file_put_contents( $path, $body );

        if ( false === $written ) {
            $this->debug_log( 'download_file write failed: path=' . $path );
            return false;
        }

        chmod( $path, 0644 );

        return true;
    }

    /**
     * Ensure the fonts directory exists and is writable.
     */
    private function ensure_fonts_dir() {
        if ( ! is_dir( $this->fonts_dir ) ) {
            wp_mkdir_p( $this->fonts_dir );
        }

        assert( is_dir( $this->fonts_dir ), 'Fonts directory must exist after creation' );
    }

    /**
     * Remove all woff2 files for a specific font family.
     *
     * @param string $family Font family name.
     */
    public function cleanup_family( $family ) {
        assert( is_string( $family ), 'Family must be a string' );

        $this->ensure_paths_resolved();

        if ( ! is_dir( $this->fonts_dir ) ) {
            return;
        }

        $safe_family = sanitize_file_name( $family );
        $pattern     = $this->fonts_dir . '/' . $safe_family . '-*.woff2';
        $files       = glob( $pattern );

        if ( ! is_array( $files ) ) {
            return;
        }

        $max_files  = 50;
        $file_count = 0;

        foreach ( $files as $file ) {
            if ( $file_count >= $max_files ) {
                break;
            }
            $file_count++;

            if ( is_file( $file ) ) {
                wp_delete_file( $file );
            }
        }
    }

    /**
     * Remove all woff2 files from the fonts directory.
     */
    public function cleanup_all() {
        $this->ensure_paths_resolved();

        if ( ! is_dir( $this->fonts_dir ) ) {
            return;
        }

        $pattern = $this->fonts_dir . '/*.woff2';
        $files   = glob( $pattern );

        assert( is_array( $files ) || false === $files, 'Glob must return array or false' );

        if ( ! is_array( $files ) ) {
            return;
        }

        $max_files  = 100;
        $file_count = 0;

        foreach ( $files as $file ) {
            if ( $file_count >= $max_files ) {
                break;
            }
            $file_count++;

            if ( is_file( $file ) ) {
                wp_delete_file( $file );
            }
        }
    }

    /**
     * Get list of available Google Fonts from the JSON catalog.
     *
     * @return array Array of font objects with family, variants, category.
     */
    public static function get_font_catalog() {
        $json_path = get_template_directory() . '/inc/customizer/google-fonts.json';

        assert( is_string( $json_path ), 'JSON path must be a string' );

        if ( ! is_readable( $json_path ) ) {
            return array();
        }

        $contents = file_get_contents( $json_path );

        if ( false === $contents || '' === $contents ) {
            return array();
        }

        $fonts = json_decode( $contents, true );

        if ( ! is_array( $fonts ) ) {
            return array();
        }

        assert( count( $fonts ) > 0, 'Font catalog should have entries' );

        return $fonts;
    }

    /**
     * Get variants for a specific font family.
     *
     * @param string $family Font family name.
     * @return array Array of variant strings.
     */
    public static function get_font_variants( $family ) {
        assert( is_string( $family ), 'Family must be a string' );

        $catalog   = self::get_font_catalog();
        $max_fonts = 2000;
        $count     = 0;

        foreach ( $catalog as $font ) {
            if ( $count >= $max_fonts ) {
                break;
            }
            $count++;

            $match = ( isset( $font['family'] ) && $font['family'] === $family )
                || ( isset( $font['displayName'] ) && $font['displayName'] === $family );

            if ( $match ) {
                return isset( $font['variants'] ) ? $font['variants'] : array();
            }
        }

        return array();
    }

    /**
     * Resolve a font name (family or displayName) to the API family name.
     *
     * @param string $name Font name (could be family or displayName).
     * @return string API family name, or original name if not found.
     */
    public static function resolve_api_family( $name ) {
        assert( is_string( $name ), 'Name must be a string' );

        if ( '' === $name ) {
            return '';
        }

        $catalog   = self::get_font_catalog();
        $max_fonts = 2000;
        $count     = 0;

        foreach ( $catalog as $font ) {
            if ( $count >= $max_fonts ) {
                break;
            }
            $count++;

            if ( isset( $font['family'] ) && $font['family'] === $name ) {
                return $font['family'];
            }

            if ( isset( $font['displayName'] ) && $font['displayName'] === $name ) {
                return $font['family'];
            }
        }

        return $name;
    }
}
