<?php
/**
 * CLDR Plural Rules resolver.
 *
 * Static class with zero WordPress dependencies. Resolves a language iso2 code
 * and an integer count to one of the CLDR plural categories:
 * zero, one, two, few, many, other.
 *
 * @package BS_Custom
 */

namespace BSCustom\Multilang;

class CldrPluralRules {

    const MAX_CUSTOM_RULES = 50;

    /** @var array<string, callable> Custom rule overrides keyed by iso2. */
    private static $custom_rules = array();

    /**
     * Language-to-family mapping for built-in rules.
     *
     * @var array<string, string>
     */
    private static $family_map = array(
        /* Germanic (one / other) */
        'en' => 'germanic',
        'de' => 'germanic',
        'nl' => 'germanic',
        'sv' => 'germanic',
        'nb' => 'germanic',
        'nn' => 'germanic',
        'da' => 'germanic',
        'no' => 'germanic',
        'it' => 'germanic',
        'es' => 'germanic',
        'pt' => 'germanic',
        'el' => 'germanic',
        'bg' => 'germanic',
        'he' => 'germanic',
        'hu' => 'germanic',
        'fi' => 'germanic',
        'et' => 'germanic',
        'ca' => 'germanic',
        'gl' => 'germanic',

        /* French family (0 and 1 → one) */
        'fr' => 'french',
        'hi' => 'french',
        'fa' => 'french',

        /* East Slavic (one / few / other based on mod 10/100) */
        'sr' => 'east_slavic',
        'ru' => 'east_slavic',
        'uk' => 'east_slavic',
        'be' => 'east_slavic',
        'hr' => 'east_slavic',
        'bs' => 'east_slavic',

        /* West Slavic (1 / 2-4 / other) */
        'cs' => 'west_slavic',
        'sk' => 'west_slavic',

        /* Polish (1 / 2-4 not 12-14 / everything else is many) */
        'pl' => 'polish',

        /* Arabic (zero / one / two / few 3-10 / many 11-99 / other) */
        'ar' => 'arabic',

        /* No-plural languages (always other) */
        'ja' => 'no_plural',
        'zh' => 'no_plural',
        'ko' => 'no_plural',
        'tr' => 'no_plural',
        'vi' => 'no_plural',
        'th' => 'no_plural',
        'id' => 'no_plural',
        'ms' => 'no_plural',
    );

    /**
     * Resolve the CLDR plural category for a given language and count.
     *
     * @param string $iso2 Two-letter language code.
     * @param int    $count Integer count (negative values use absolute).
     * @return string One of: zero, one, two, few, many, other.
     */
    public static function resolve( string $iso2, int $count ): string {
        assert( is_string( $iso2 ), 'iso2 must be a string' );
        assert( is_int( $count ), 'count must be an int' );

        $count = abs( $count );

        if ( '' === $iso2 ) {
            return 'other';
        }

        /* Custom rules take priority */
        if ( isset( self::$custom_rules[ $iso2 ] ) ) {
            return call_user_func( self::$custom_rules[ $iso2 ], $count );
        }

        $family = isset( self::$family_map[ $iso2 ] ) ? self::$family_map[ $iso2 ] : 'germanic';

        return self::resolve_by_family( $family, $count );
    }

    /**
     * Register a custom plural rule for a language.
     *
     * @param string   $iso2     Two-letter language code.
     * @param callable $resolver Callable accepting int, returning plural category string.
     * @return bool True if registered, false if limit reached.
     */
    public static function register_rule( string $iso2, callable $resolver ): bool {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );
        assert( is_callable( $resolver ), 'Resolver must be callable' );

        if ( count( self::$custom_rules ) >= self::MAX_CUSTOM_RULES && ! isset( self::$custom_rules[ $iso2 ] ) ) {
            return false;
        }

        self::$custom_rules[ $iso2 ] = $resolver;

        return true;
    }

    /**
     * Reset custom rules (for testing).
     *
     * @return void
     */
    public static function reset_custom_rules(): void {
        self::$custom_rules = array();
    }

    /**
     * Resolve plural category by family.
     *
     * @param string $family Family identifier.
     * @param int    $count  Absolute count.
     * @return string Plural category.
     */
    private static function resolve_by_family( string $family, int $count ): string {
        switch ( $family ) {
            case 'germanic':
                return self::rule_germanic( $count );

            case 'french':
                return self::rule_french( $count );

            case 'east_slavic':
                return self::rule_east_slavic( $count );

            case 'west_slavic':
                return self::rule_west_slavic( $count );

            case 'polish':
                return self::rule_polish( $count );

            case 'arabic':
                return self::rule_arabic( $count );

            case 'no_plural':
                return 'other';

            default:
                return self::rule_germanic( $count );
        }
    }

    /**
     * Germanic: 1 → one, everything else → other.
     */
    private static function rule_germanic( int $n ): string {
        return ( 1 === $n ) ? 'one' : 'other';
    }

    /**
     * French: 0 or 1 → one, everything else → other.
     */
    private static function rule_french( int $n ): string {
        return ( $n <= 1 ) ? 'one' : 'other';
    }

    /**
     * East Slavic (Serbian, Russian, Ukrainian, etc.).
     *
     * mod10=1 && mod100!=11 → one
     * mod10 in 2-4 && mod100 not in 12-14 → few
     * everything else → other
     */
    private static function rule_east_slavic( int $n ): string {
        $mod10  = $n % 10;
        $mod100 = $n % 100;

        if ( 1 === $mod10 && 11 !== $mod100 ) {
            return 'one';
        }

        if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 12 || $mod100 > 14 ) ) {
            return 'few';
        }

        return 'other';
    }

    /**
     * West Slavic (Czech, Slovak).
     *
     * 1 → one, 2-4 → few, everything else → other.
     */
    private static function rule_west_slavic( int $n ): string {
        if ( 1 === $n ) {
            return 'one';
        }

        if ( $n >= 2 && $n <= 4 ) {
            return 'few';
        }

        return 'other';
    }

    /**
     * Polish.
     *
     * 1 → one
     * mod10 in 2-4 && mod100 not in 12-14 → few
     * everything else → many
     */
    private static function rule_polish( int $n ): string {
        if ( 1 === $n ) {
            return 'one';
        }

        $mod10  = $n % 10;
        $mod100 = $n % 100;

        if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 12 || $mod100 > 14 ) ) {
            return 'few';
        }

        return 'many';
    }

    /**
     * Arabic (6 categories).
     *
     * 0 → zero, 1 → one, 2 → two, 3-10 → few, 11-99 → many, 100+ → other.
     */
    private static function rule_arabic( int $n ): string {
        if ( 0 === $n ) {
            return 'zero';
        }
        if ( 1 === $n ) {
            return 'one';
        }
        if ( 2 === $n ) {
            return 'two';
        }

        $mod100 = $n % 100;

        if ( $mod100 >= 3 && $mod100 <= 10 ) {
            return 'few';
        }

        if ( $mod100 >= 11 && $mod100 <= 99 ) {
            return 'many';
        }

        return 'other';
    }
}
