<?php
/**
 * Translator.
 *
 * Loads translation JSON files and resolves keys to translated strings
 * with placeholder replacement, CLDR plural support, and escaping.
 *
 * @package CT_Custom
 */

namespace CTCustom\Multilang;

class Translator {

    const MAX_ARGS             = 50;
    const MAX_PATTERN_MATCHES  = 200;
    const MAX_PLACEHOLDER_ITER = 100;

    /** @var string Two-letter language code. */
    private $iso2;

    /** @var string|null Locale code (e.g. en_GB). */
    private $locale;

    /** @var string Base directory for translation files. */
    private $base_dir;

    /** @var array<string, mixed> Merged translations (locale over language). */
    private $translations;

    /** @var array<string, array> Static file cache keyed by absolute path. */
    private static $file_cache = array();

    /**
     * @param string      $iso2     Two-letter language code.
     * @param string|null $locale   Optional locale code.
     * @param string      $base_dir Optional base directory override.
     */
    public function __construct( string $iso2, ?string $locale = null, string $base_dir = '' ) {
        assert( is_string( $iso2 ), 'iso2 must be a string' );

        $this->iso2     = $iso2;
        $this->locale   = $locale;
        $this->base_dir = '' !== $base_dir ? $base_dir : $this->default_base_dir();

        $this->translations = $this->load_translations();
    }

    /**
     * Translate a key (HTML-escaped by default).
     *
     * @param string          $key   Translation key.
     * @param array           $args  Placeholder replacements.
     * @param string|int|null $count Count for CLDR resolution, or form name (e.g. "one", "few").
     * @return string
     */
    public function translate( string $key, array $args = array(), string|int|null $count = null ): string {
        assert( is_string( $key ), 'Key must be a string' );
        assert( is_array( $args ), 'Args must be an array' );

        $raw = $this->resolve_key( $key, $args, $count );

        return $this->escape_html( $raw );
    }

    /**
     * Translate a key without escaping (for trusted HTML content).
     *
     * @param string          $key   Translation key.
     * @param array           $args  Placeholder replacements.
     * @param string|int|null $count Count for CLDR resolution, or form name (e.g. "one", "few").
     * @return string
     */
    public function translate_raw( string $key, array $args = array(), string|int|null $count = null ): string {
        return $this->resolve_key( $key, $args, $count );
    }

    /**
     * Check if a translation key exists.
     *
     * @param string $key Translation key.
     * @return bool
     */
    public function has( string $key ): bool {
        return array_key_exists( $key, $this->translations );
    }

    /**
     * Parse ct_translate() patterns with HTML escaping.
     *
     * @param string $text Input text with ct_translate() patterns.
     * @return string
     */
    public function parse_ct_translate_patterns( string $text ): string {
        return $this->parse_ct_translate_internal( $text, true );
    }

    /**
     * Parse ct_translate() patterns without HTML escaping (raw output).
     *
     * Use when the caller will apply its own escaping (e.g. esc_html()).
     *
     * @param string $text Input text with ct_translate() patterns.
     * @return string
     */
    public function parse_ct_translate_patterns_raw( string $text ): string {
        return $this->parse_ct_translate_internal( $text, false );
    }

    /**
     * Internal: parse ct_translate('KEY', [.../{...}], 'form'|N) patterns.
     *
     * Supports both PHP arrow syntax ['key' => 'val'] and JSON colon syntax {"key": "val"}.
     *
     * @param string $text   Input text with ct_translate() patterns.
     * @param bool   $escape Whether to HTML-escape resolved values.
     * @return string
     */
    private function parse_ct_translate_internal( string $text, bool $escape ): string {
        assert( is_string( $text ), 'Text must be a string' );

        if ( false === strpos( $text, 'ct_translate(' ) ) {
            return $text;
        }

        $match_count = 0;
        $max_matches = self::MAX_PATTERN_MATCHES;
        $translator  = $this;

        $pattern = '/ct_translate\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]' .
            '\s*(?:,\s*(?:\[(.*?)\]|\{(.*?)\})\s*(?:,\s*(?:[\'"]([a-z]+)[\'"]|(\d+))\s*)?)?\)/';

        $result = preg_replace_callback(
            $pattern,
            function ( $matches ) use ( &$match_count, $max_matches, $translator, $escape ) {
                if ( $match_count >= $max_matches ) {
                    return $matches[0];
                }
                $match_count++;

                $key      = $matches[1];
                $args_str = '';

                if ( isset( $matches[2] ) && '' !== $matches[2] ) {
                    $args_str = $matches[2];
                } elseif ( isset( $matches[3] ) && '' !== $matches[3] ) {
                    $args_str = $matches[3];
                }

                $args  = self::parse_inline_args( $args_str );
                $count = null;

                if ( ! empty( $matches[4] ) ) {
                    $count = $matches[4];
                } elseif ( isset( $matches[5] ) && '' !== $matches[5] ) {
                    $count = (int) $matches[5];
                }

                return $escape
                    ? $translator->translate( $key, $args, $count )
                    : $translator->translate_raw( $key, $args, $count );
            },
            $text
        );

        return ( null !== $result ) ? $result : $text;
    }

    /**
     * Parse key-value pairs from inline args string.
     *
     * Supports PHP arrow syntax: 'key' => 'value'
     * Supports JSON colon syntax: "key": "value"
     *
     * @param string $args_str Content between delimiters.
     * @return array
     */
    public static function parse_inline_args( string $args_str ): array {
        if ( '' === trim( $args_str ) ) {
            return array();
        }

        $args      = array();
        $max_args  = self::MAX_ARGS;
        $arg_count = 0;

        /* Try PHP arrow syntax first: 'key' => 'value' or 'key' => 5 */
        if ( preg_match_all( '/[\'"]([a-zA-Z0-9_]+)[\'"]\s*=>\s*(?:[\'"]([^\'"]*)[\'"]|(\d+))/', $args_str, $pairs, PREG_SET_ORDER ) ) {
            foreach ( $pairs as $pair ) {
                if ( $arg_count >= $max_args ) {
                    break;
                }
                $arg_count++;
                $args[ $pair[1] ] = isset( $pair[3] ) && '' !== $pair[3] ? $pair[3] : $pair[2];
            }
        }

        /* If no PHP arrow matches, try JSON colon syntax: "key": "value" or "key": 5 */
        if ( empty( $args ) ) {
            if ( preg_match_all( '/[\'"]([a-zA-Z0-9_]+)[\'"]\s*:\s*(?:[\'"]([^\'"]*)[\'"]|(\d+))/', $args_str, $pairs, PREG_SET_ORDER ) ) {
                foreach ( $pairs as $pair ) {
                    if ( $arg_count >= $max_args ) {
                        break;
                    }
                    $arg_count++;
                    $args[ $pair[1] ] = isset( $pair[3] ) && '' !== $pair[3] ? $pair[3] : $pair[2];
                }
            }
        }

        return $args;
    }

    /**
     * Clear the static file cache.
     *
     * @return void
     */
    public static function clear_cache(): void {
        self::$file_cache = array();
    }

    /**
     * Get the raw translations array (for frontend JS injection).
     *
     * @return array
     */
    public function get_all_translations(): array {
        return $this->translations;
    }

    /**
     * Resolve a key to its translated string (unescaped).
     *
     * @param string          $key   Translation key.
     * @param array           $args  Placeholder replacements.
     * @param string|int|null $count Count for CLDR resolution, or form name (e.g. "one", "few").
     * @return string
     */
    private function resolve_key( string $key, array $args, string|int|null $count ): string {
        $valid_forms = array( 'singular', 'zero', 'one', 'two', 'few', 'many', 'other' );

        if ( ! isset( $this->translations[ $key ] ) ) {
            return $key;
        }

        $value = $this->translations[ $key ];

        /* Plural handling — combined format: { singular, zero, one, two, few, many, other } */
        if ( is_array( $value ) ) {
            $singular_fallback = isset( $value['singular'] ) ? $value['singular'] : $key;

            if ( null === $count ) {
                $value = $singular_fallback;
            } elseif ( is_string( $count ) && in_array( $count, $valid_forms, true ) ) {
                $value = isset( $value[ $count ] ) ? $value[ $count ] : $singular_fallback;
            } else {
                $category = CldrPluralRules::resolve( $this->iso2, (int) $count );

                if ( isset( $value[ $category ] ) ) {
                    $value = $value[ $category ];
                } elseif ( isset( $value['other'] ) ) {
                    $value = $value['other'];
                } else {
                    $value = $singular_fallback;
                }
            }
        }

        if ( ! is_string( $value ) ) {
            return $key;
        }

        /* Placeholder replacement */
        $value = $this->replace_placeholders( $value, $args );

        return $value;
    }

    /**
     * Replace ##placeholder## patterns with arg values.
     *
     * @param string $text Text with placeholders.
     * @param array  $args Replacement values.
     * @return string
     */
    private function replace_placeholders( string $text, array $args ): string {
        if ( empty( $args ) && false === strpos( $text, '##' ) ) {
            return $text;
        }

        /* Apply provided args (bounded) */
        $count = 0;
        $max   = self::MAX_ARGS;

        foreach ( $args as $placeholder => $replacement ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            $text = str_replace( '##' . $placeholder . '##', (string) $replacement, $text );
        }

        /* Strip any unresolved ##...## placeholders */
        $iter     = 0;
        $max_iter = self::MAX_PLACEHOLDER_ITER;

        while ( $iter < $max_iter && false !== strpos( $text, '##' ) ) {
            $text = preg_replace( '/##[a-zA-Z0-9_]+##/', '', $text, 1, $replaced );
            $iter++;

            if ( 0 === $replaced ) {
                break;
            }
        }

        return $text;
    }

    /**
     * Escape HTML entities.
     *
     * @param string $text Raw text.
     * @return string
     */
    private function escape_html( string $text ): string {
        if ( function_exists( 'esc_html' ) ) {
            return esc_html( $text );
        }

        return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }

    /**
     * Load translations with locale-over-language merge.
     *
     * @return array
     */
    private function load_translations(): array {
        if ( '' === $this->iso2 ) {
            return array();
        }

        /* Language base file */
        $lang_path = $this->base_dir . '/' . $this->iso2 . '.json';
        $lang_data = $this->load_json_file( $lang_path );

        /* Optional locale overlay */
        $locale_data = array();
        if ( null !== $this->locale && '' !== $this->locale ) {
            $locale_path = $this->base_dir . '/' . $this->locale . '.json';
            $locale_data = $this->load_json_file( $locale_path );
        }

        /* Merge: locale overrides language */
        if ( ! empty( $locale_data ) ) {
            return array_merge( $lang_data, $locale_data );
        }

        return $lang_data;
    }

    /**
     * Load a JSON file with caching.
     *
     * @param string $path Absolute file path.
     * @return array
     */
    private function load_json_file( string $path ): array {
        if ( isset( self::$file_cache[ $path ] ) ) {
            return self::$file_cache[ $path ];
        }

        if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
            self::$file_cache[ $path ] = array();
            return array();
        }

        $content = file_get_contents( $path );

        if ( false === $content || '' === $content ) {
            self::$file_cache[ $path ] = array();
            return array();
        }

        $data = json_decode( $content, true );

        if ( ! is_array( $data ) ) {
            self::$file_cache[ $path ] = array();
            return array();
        }

        self::$file_cache[ $path ] = $data;

        return $data;
    }

    /**
     * Default base directory for translation files.
     *
     * @return string
     */
    private function default_base_dir(): string {
        if ( function_exists( 'get_template_directory' ) ) {
            return get_template_directory() . '/translations';
        }

        return dirname( __DIR__, 2 ) . '/translations';
    }
}
