<?php
/**
 * Tests for CldrPluralRules class.
 *
 * Runs without WordPress via vendor/autoload.php.
 * Tests all CLDR plural rule families and custom rule registration.
 *
 * @package BS_Custom
 */

namespace BSCustom\Tests\Multilang;

use PHPUnit\Framework\TestCase;
use BSCustom\Multilang\CldrPluralRules;

class CldrPluralRulesTest extends TestCase {

    protected function setUp(): void {
        CldrPluralRules::reset_custom_rules();
    }

    protected function tearDown(): void {
        CldrPluralRules::reset_custom_rules();
    }

    /* ── Germanic family (en, de) ────────────────────────────────────── */

    public function test_germanic_one(): void {
        $this->assertSame( 'one', CldrPluralRules::resolve( 'en', 1 ) );
        $this->assertSame( 'one', CldrPluralRules::resolve( 'de', 1 ) );
    }

    public function test_germanic_other(): void {
        $this->assertSame( 'other', CldrPluralRules::resolve( 'en', 0 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'en', 2 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'en', 5 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'en', 100 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'de', 0 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'de', 15 ) );
    }

    /* ── French family (fr) ──────────────────────────────────────────── */

    public function test_french_one(): void {
        $this->assertSame( 'one', CldrPluralRules::resolve( 'fr', 0 ) );
        $this->assertSame( 'one', CldrPluralRules::resolve( 'fr', 1 ) );
    }

    public function test_french_other(): void {
        $this->assertSame( 'other', CldrPluralRules::resolve( 'fr', 2 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'fr', 10 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'fr', 100 ) );
    }

    /* ── East Slavic family (sr, ru) ─────────────────────────────────── */

    public function test_east_slavic_one(): void {
        /* mod10=1 && mod100!=11 */
        $this->assertSame( 'one', CldrPluralRules::resolve( 'sr', 1 ) );
        $this->assertSame( 'one', CldrPluralRules::resolve( 'sr', 21 ) );
        $this->assertSame( 'one', CldrPluralRules::resolve( 'sr', 101 ) );
        $this->assertSame( 'one', CldrPluralRules::resolve( 'ru', 1 ) );
        $this->assertSame( 'one', CldrPluralRules::resolve( 'ru', 31 ) );
    }

    public function test_east_slavic_few(): void {
        /* mod10 in 2-4 && mod100 not in 12-14 */
        $this->assertSame( 'few', CldrPluralRules::resolve( 'sr', 2 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'sr', 3 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'sr', 4 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'sr', 22 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'sr', 34 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'ru', 102 ) );
    }

    public function test_east_slavic_other(): void {
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sr', 0 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sr', 5 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sr', 11 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sr', 12 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sr', 13 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sr', 14 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sr', 20 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sr', 111 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sr', 112 ) );
    }

    /* ── West Slavic family (cs) ─────────────────────────────────────── */

    public function test_west_slavic_one(): void {
        $this->assertSame( 'one', CldrPluralRules::resolve( 'cs', 1 ) );
    }

    public function test_west_slavic_few(): void {
        $this->assertSame( 'few', CldrPluralRules::resolve( 'cs', 2 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'cs', 3 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'cs', 4 ) );
    }

    public function test_west_slavic_other(): void {
        $this->assertSame( 'other', CldrPluralRules::resolve( 'cs', 0 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'cs', 5 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'cs', 10 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'cs', 100 ) );
    }

    /* ── Polish family (pl) ──────────────────────────────────────────── */

    public function test_polish_one(): void {
        $this->assertSame( 'one', CldrPluralRules::resolve( 'pl', 1 ) );
    }

    public function test_polish_few(): void {
        /* mod10 in 2-4 && mod100 not in 12-14 */
        $this->assertSame( 'few', CldrPluralRules::resolve( 'pl', 2 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'pl', 3 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'pl', 4 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'pl', 22 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'pl', 34 ) );
    }

    public function test_polish_many(): void {
        $this->assertSame( 'many', CldrPluralRules::resolve( 'pl', 0 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'pl', 5 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'pl', 10 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'pl', 11 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'pl', 12 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'pl', 13 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'pl', 14 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'pl', 100 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'pl', 112 ) );
    }

    /* ── Arabic family (ar) — all 6 categories ───────────────────────── */

    public function test_arabic_zero(): void {
        $this->assertSame( 'zero', CldrPluralRules::resolve( 'ar', 0 ) );
    }

    public function test_arabic_one(): void {
        $this->assertSame( 'one', CldrPluralRules::resolve( 'ar', 1 ) );
    }

    public function test_arabic_two(): void {
        $this->assertSame( 'two', CldrPluralRules::resolve( 'ar', 2 ) );
    }

    public function test_arabic_few(): void {
        /* mod100 in 3-10 */
        $this->assertSame( 'few', CldrPluralRules::resolve( 'ar', 3 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'ar', 10 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'ar', 103 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'ar', 210 ) );
    }

    public function test_arabic_many(): void {
        /* mod100 in 11-99 */
        $this->assertSame( 'many', CldrPluralRules::resolve( 'ar', 11 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'ar', 50 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'ar', 99 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'ar', 111 ) );
    }

    public function test_arabic_other(): void {
        $this->assertSame( 'other', CldrPluralRules::resolve( 'ar', 100 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'ar', 200 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'ar', 1000 ) );
    }

    /* ── No-plural family (ja, zh) ───────────────────────────────────── */

    public function test_no_plural_always_other(): void {
        $this->assertSame( 'other', CldrPluralRules::resolve( 'ja', 0 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'ja', 1 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'ja', 5 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'zh', 1 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'ko', 1 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'tr', 100 ) );
    }

    /* ── Unknown language defaults to germanic ───────────────────────── */

    public function test_unknown_language_uses_germanic(): void {
        $this->assertSame( 'one', CldrPluralRules::resolve( 'zz', 1 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'zz', 0 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'zz', 5 ) );
    }

    /* ── Negative numbers (absolute value) ───────────────────────────── */

    public function test_negative_numbers_use_absolute(): void {
        /* abs(-1) = 1, which is 'one' for germanic */
        $this->assertSame( 'one', CldrPluralRules::resolve( 'en', -1 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'en', -5 ) );

        /* abs(-2) = 2, which is 'few' for east_slavic */
        $this->assertSame( 'few', CldrPluralRules::resolve( 'sr', -2 ) );
    }

    /* ── Empty iso2 returns 'other' ──────────────────────────────────── */

    public function test_empty_iso2_returns_other(): void {
        $this->assertSame( 'other', CldrPluralRules::resolve( '', 1 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( '', 0 ) );
    }

    /* ── register_rule() ─────────────────────────────────────────────── */

    public function test_register_custom_rule(): void {
        $registered = CldrPluralRules::register_rule( 'xx', function ( int $count ): string {
            return ( $count === 42 ) ? 'one' : 'many';
        } );

        $this->assertTrue( $registered );
        $this->assertSame( 'one', CldrPluralRules::resolve( 'xx', 42 ) );
        $this->assertSame( 'many', CldrPluralRules::resolve( 'xx', 1 ) );
    }

    public function test_custom_rule_overrides_builtin(): void {
        /* English is normally germanic, but custom rule overrides */
        CldrPluralRules::register_rule( 'en', function ( int $count ): string {
            return 'zero';
        } );

        $this->assertSame( 'zero', CldrPluralRules::resolve( 'en', 1 ) );
        $this->assertSame( 'zero', CldrPluralRules::resolve( 'en', 5 ) );
    }

    public function test_register_rule_limit_enforcement(): void {
        /* Fill up to the maximum */
        for ( $i = 0; $i < CldrPluralRules::MAX_CUSTOM_RULES; $i++ ) {
            $code = sprintf( 'x%02d', $i );
            $result = CldrPluralRules::register_rule( $code, function ( int $c ): string {
                return 'other';
            } );
            $this->assertTrue( $result, "Rule {$i} should register" );
        }

        /* Next new rule should fail */
        $over_limit = CldrPluralRules::register_rule( 'yy', function ( int $c ): string {
            return 'other';
        } );

        $this->assertFalse( $over_limit );
    }

    public function test_register_rule_can_update_existing_at_limit(): void {
        /* Fill to limit */
        for ( $i = 0; $i < CldrPluralRules::MAX_CUSTOM_RULES; $i++ ) {
            $code = sprintf( 'x%02d', $i );
            CldrPluralRules::register_rule( $code, function ( int $c ): string {
                return 'other';
            } );
        }

        /* Updating an existing code should succeed even at limit */
        $update = CldrPluralRules::register_rule( 'x00', function ( int $c ): string {
            return 'one';
        } );

        $this->assertTrue( $update );
        $this->assertSame( 'one', CldrPluralRules::resolve( 'x00', 999 ) );
    }

    /* ── reset_custom_rules() ────────────────────────────────────────── */

    public function test_reset_custom_rules_clears_all(): void {
        CldrPluralRules::register_rule( 'en', function ( int $c ): string {
            return 'zero';
        } );

        $this->assertSame( 'zero', CldrPluralRules::resolve( 'en', 1 ) );

        CldrPluralRules::reset_custom_rules();

        /* Should now use default germanic rule */
        $this->assertSame( 'one', CldrPluralRules::resolve( 'en', 1 ) );
    }

    /* ── Additional mapped languages ─────────────────────────────────── */

    public function test_hindi_uses_french_rules(): void {
        $this->assertSame( 'one', CldrPluralRules::resolve( 'hi', 0 ) );
        $this->assertSame( 'one', CldrPluralRules::resolve( 'hi', 1 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'hi', 2 ) );
    }

    public function test_ukrainian_uses_east_slavic(): void {
        $this->assertSame( 'one', CldrPluralRules::resolve( 'uk', 1 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'uk', 3 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'uk', 5 ) );
    }

    public function test_slovak_uses_west_slavic(): void {
        $this->assertSame( 'one', CldrPluralRules::resolve( 'sk', 1 ) );
        $this->assertSame( 'few', CldrPluralRules::resolve( 'sk', 4 ) );
        $this->assertSame( 'other', CldrPluralRules::resolve( 'sk', 5 ) );
    }
}
