<?php
/**
 * Translation Service.
 *
 * Static utility class that resolves `ct_translate()` patterns
 * in customizer and block editor values.
 *
 * @package BS_Custom
 */

namespace BSCustom\Multilang;

class TranslationService {

    /**
     * Resolve a value that may contain ct_translate() patterns.
     *
     * Plain strings pass through unchanged.
     * Strings containing ct_translate('KEY', [...], count) get resolved.
     *
     * @param string $value Input value.
     * @param string $iso2  Optional language override (defaults to current).
     * @return string
     */
    public static function resolve( string $value, string $iso2 = '' ): string {
        assert( is_string( $value ), 'Value must be a string' );

        if ( false === strpos( $value, 'ct_translate(' ) ) {
            return $value;
        }

        if ( '' === $iso2 ) {
            $iso2 = function_exists( 'ct_get_current_language' ) ? ct_get_current_language() : 'en';
        }

        $locale     = function_exists( 'ct_get_current_locale' ) ? ct_get_current_locale() : null;
        $translator = new Translator( $iso2, $locale );

        return $translator->parse_ct_translate_patterns( $value );
    }

    /**
     * Resolve a value that may contain ct_translate() patterns (no escaping).
     *
     * Use when the caller will apply its own escaping (e.g. esc_html()).
     * Pattern: esc_html( TranslationService::resolve_raw( $value ) )
     *
     * @param string $value Input value.
     * @param string $iso2  Optional language override (defaults to current).
     * @return string
     */
    public static function resolve_raw( string $value, string $iso2 = '' ): string {
        assert( is_string( $value ), 'Value must be a string' );

        if ( false === strpos( $value, 'ct_translate(' ) ) {
            return $value;
        }

        if ( '' === $iso2 ) {
            $iso2 = function_exists( 'ct_get_current_language' ) ? ct_get_current_language() : 'en';
        }

        $locale     = function_exists( 'ct_get_current_locale' ) ? ct_get_current_locale() : null;
        $translator = new Translator( $iso2, $locale );

        return $translator->parse_ct_translate_patterns_raw( $value );
    }

    /**
     * Resolve ct_translate() patterns in block editor content.
     *
     * Three-phase approach:
     *   1. COLLECT  — find all ct_translate() patterns via regex.
     *   2. TRANSLATE — resolve each unique pattern once via Translator.
     *   3. REPLACE  — substitute all patterns in a single str_replace pass.
     *
     * HTML-escaped output (prevents XSS from translation JSON values).
     *
     * @param string $content Post content from the_content filter.
     * @param string $iso2    Optional language override (defaults to current).
     * @return string
     */
    public static function resolve_block_content( string $content, string $iso2 = '' ): string {
        assert( is_string( $content ), 'Content must be a string' );

        if ( '' === $content || false === strpos( $content, 'ct_translate(' ) ) {
            return $content;
        }

        if ( '' === $iso2 ) {
            $iso2 = function_exists( 'ct_get_current_language' ) ? ct_get_current_language() : 'en';
        }

        $locale     = function_exists( 'ct_get_current_locale' ) ? ct_get_current_locale() : null;
        $translator = new Translator( $iso2, $locale );

        /* ── Phase 1: COLLECT ────────────────────────────────────────── */
        $pattern = '/ct_translate\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]'
            . '\s*(?:,\s*(?:\[(.*?)\]|\{(.*?)\})\s*(?:,\s*(?:[\'"]([a-z]+)[\'"]|(\d+))\s*)?)?\)/';

        $match_count = preg_match_all( $pattern, $content, $all_matches, PREG_SET_ORDER );

        if ( false === $match_count || 0 === $match_count ) {
            return $content;
        }

        $max_matches = Translator::MAX_PATTERN_MATCHES;
        $search      = array();
        $replace     = array();
        $seen        = array();
        $processed   = 0;

        /* ── Phase 2: TRANSLATE (deduplicated) ───────────────────────── */
        for ( $i = 0; $i < $match_count; $i++ ) {
            if ( $processed >= $max_matches ) {
                break;
            }

            $full_match = $all_matches[ $i ][0];

            /* Skip duplicates — resolve once, replace all */
            if ( isset( $seen[ $full_match ] ) ) {
                continue;
            }

            $processed++;

            $key      = $all_matches[ $i ][1];
            $args_str = '';

            if ( isset( $all_matches[ $i ][2] ) && '' !== $all_matches[ $i ][2] ) {
                $args_str = $all_matches[ $i ][2];
            } elseif ( isset( $all_matches[ $i ][3] ) && '' !== $all_matches[ $i ][3] ) {
                $args_str = $all_matches[ $i ][3];
            }

            $args  = Translator::parse_inline_args( $args_str );
            $count = null;

            if ( ! empty( $all_matches[ $i ][4] ) ) {
                $count = $all_matches[ $i ][4];
            } elseif ( isset( $all_matches[ $i ][5] ) && '' !== $all_matches[ $i ][5] ) {
                $count = (int) $all_matches[ $i ][5];
            }

            $resolved = $translator->translate( $key, $args, $count );

            $search[]              = $full_match;
            $replace[]             = $resolved;
            $seen[ $full_match ]   = true;
        }

        /* ── Phase 3: REPLACE (single C-level pass) ──────────────────── */
        assert( count( $search ) === count( $replace ), 'Search and replace arrays must match' );

        return str_replace( $search, $replace, $content );
    }

    /**
     * Get all available translation keys (for picker dropdowns).
     *
     * @return array<string>
     */
    public static function get_all_keys(): array {
        $trans_dir = '';

        if ( function_exists( 'get_template_directory' ) ) {
            $trans_dir = get_template_directory() . '/translations';
        } else {
            $trans_dir = dirname( __DIR__, 2 ) . '/translations';
        }

        if ( ! function_exists( 'ct_get_language_manager' ) ) {
            return array();
        }

        $mgr       = ct_get_language_manager();
        $languages = $mgr->get_all();
        $all_keys  = array();
        $max_langs = 50;
        $count     = 0;

        foreach ( $languages as $lang ) {
            if ( $count >= $max_langs ) {
                break;
            }
            $count++;

            $file = $trans_dir . '/' . $lang['iso2'] . '.json';

            if ( ! file_exists( $file ) ) {
                continue;
            }

            $data = json_decode( file_get_contents( $file ), true );

            if ( ! is_array( $data ) ) {
                continue;
            }

            $key_count = 0;
            $max_keys  = 500;
            foreach ( array_keys( $data ) as $key ) {
                if ( $key_count >= $max_keys ) {
                    break;
                }
                $key_count++;
                $all_keys[ $key ] = true;
            }
        }

        ksort( $all_keys );

        return array_keys( $all_keys );
    }
}
