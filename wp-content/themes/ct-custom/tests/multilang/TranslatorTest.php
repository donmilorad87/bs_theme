<?php
/**
 * Tests for Translator class.
 *
 * Runs without WordPress via vendor/autoload.php.
 * Uses real translations/en.json and translations/sr.json files.
 *
 * @package BS_Custom
 */

namespace BSCustom\Tests\Multilang;

use PHPUnit\Framework\TestCase;
use BSCustom\Multilang\Translator;

class TranslatorTest extends TestCase {

    /** @var string */
    private static $base_dir;

    /** @var string */
    private static $tmp_dir;

    public static function setUpBeforeClass(): void {
        self::$base_dir = dirname( __DIR__, 2 ) . '/translations';
        self::$tmp_dir  = sys_get_temp_dir() . '/ct_translator_test_' . getmypid();

        if ( ! is_dir( self::$tmp_dir ) ) {
            mkdir( self::$tmp_dir, 0755, true );
        }
    }

    protected function setUp(): void {
        Translator::clear_cache();
    }

    public static function tearDownAfterClass(): void {
        /* Clean up temp files */
        $files = glob( self::$tmp_dir . '/*' );
        if ( is_array( $files ) ) {
            $count = 0;
            foreach ( $files as $file ) {
                if ( $count >= 100 ) { break; }
                $count++;
                if ( is_file( $file ) ) {
                    unlink( $file );
                }
            }
        }
        if ( is_dir( self::$tmp_dir ) ) {
            rmdir( self::$tmp_dir );
        }
    }

    /* ── translate() ─────────────────────────────────────────────────── */

    public function test_translate_simple_key(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $result = $t->translate( 'SITE_NAME' );

        $this->assertSame( 'BS Custom', $result );
    }

    public function test_translate_html_escaping(): void {
        /* Create a temp file with HTML in a value */
        $data = array( 'HTML_KEY' => '<b>Bold & "Quoted"</b>' );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        $t = new Translator( 'en', null, self::$tmp_dir );

        $result = $t->translate( 'HTML_KEY' );

        $this->assertSame( '&lt;b&gt;Bold &amp; &quot;Quoted&quot;&lt;/b&gt;', $result );
    }

    public function test_translate_unknown_key_returns_key_name(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $result = $t->translate( 'NONEXISTENT_KEY_XYZ' );

        $this->assertSame( 'NONEXISTENT_KEY_XYZ', $result );
    }

    /* ── translate_raw() ─────────────────────────────────────────────── */

    public function test_translate_raw_no_escaping(): void {
        $data = array( 'RAW_KEY' => '<em>Emphasis</em>' );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        $t = new Translator( 'en', null, self::$tmp_dir );

        $result = $t->translate_raw( 'RAW_KEY' );

        $this->assertSame( '<em>Emphasis</em>', $result );
    }

    /* ── has() ───────────────────────────────────────────────────────── */

    public function test_has_existing_key(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $this->assertTrue( $t->has( 'SITE_NAME' ) );
    }

    public function test_has_missing_key(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $this->assertFalse( $t->has( 'DOES_NOT_EXIST' ) );
    }

    /* ── parse_ct_translate_patterns() ───────────────────────────────── */

    public function test_parse_single_pattern(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $result = $t->parse_ct_translate_patterns( "Hello ct_translate('SITE_NAME') world" );

        $this->assertSame( 'Hello BS Custom world', $result );
    }

    public function test_parse_multiple_patterns(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $result = $t->parse_ct_translate_patterns( "ct_translate('SITE_NAME') - ct_translate('LOGIN')" );

        $this->assertSame( 'BS Custom - Login', $result );
    }

    public function test_parse_no_patterns_passthrough(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $text = 'No patterns here';
        $result = $t->parse_ct_translate_patterns( $text );

        $this->assertSame( $text, $result );
    }

    public function test_parse_pattern_with_php_arrow_args(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $result = $t->parse_ct_translate_patterns( "ct_translate('ITEM_COUNT',['count'=>'3'],'other')" );

        $this->assertSame( '3 items', $result );
    }

    public function test_parse_pattern_with_json_args(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $result = $t->parse_ct_translate_patterns( "ct_translate('ITEM_COUNT',{\"count\":\"7\"},'other')" );

        $this->assertSame( '7 items', $result );
    }

    public function test_parse_pattern_with_named_form(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $result = $t->parse_ct_translate_patterns( "ct_translate('ITEM_COUNT',['count'=>'1'],'one')" );

        $this->assertSame( '1 item', $result );
    }

    public function test_parse_pattern_with_numeric_count(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        /* English CLDR: 1 = 'one', 5 = 'other' */
        $result = $t->parse_ct_translate_patterns( "ct_translate('ITEM_COUNT',['count'=>'5'],5)" );

        $this->assertSame( '5 items', $result );
    }

    /* ── parse_ct_translate_patterns_raw() ────────────────────────────── */

    public function test_parse_patterns_raw_no_escaping(): void {
        $data = array( 'HTML_VAL' => '<strong>Bold</strong>' );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        $t = new Translator( 'en', null, self::$tmp_dir );

        $result = $t->parse_ct_translate_patterns_raw( "ct_translate('HTML_VAL')" );

        $this->assertSame( '<strong>Bold</strong>', $result );
    }

    /* ── parse_inline_args() (static) ────────────────────────────────── */

    public function test_parse_inline_args_php_arrow_syntax(): void {
        $result = Translator::parse_inline_args( "'name' => 'Alice', 'age' => '30'" );

        $this->assertSame( array( 'name' => 'Alice', 'age' => '30' ), $result );
    }

    public function test_parse_inline_args_json_colon_syntax(): void {
        $result = Translator::parse_inline_args( '"name": "Bob", "count": "5"' );

        $this->assertSame( array( 'name' => 'Bob', 'count' => '5' ), $result );
    }

    public function test_parse_inline_args_empty_string(): void {
        $result = Translator::parse_inline_args( '' );

        $this->assertSame( array(), $result );
    }

    public function test_parse_inline_args_whitespace_only(): void {
        $result = Translator::parse_inline_args( '   ' );

        $this->assertSame( array(), $result );
    }

    public function test_parse_inline_args_numeric_value(): void {
        $result = Translator::parse_inline_args( "'count' => 42" );

        $this->assertSame( array( 'count' => '42' ), $result );
    }

    public function test_parse_inline_args_max_limit(): void {
        /* Build a string with more than MAX_ARGS pairs */
        $pairs = array();
        for ( $i = 0; $i < 55; $i++ ) {
            $pairs[] = "'k{$i}' => 'v{$i}'";
        }
        $args_str = implode( ', ', $pairs );

        $result = Translator::parse_inline_args( $args_str );

        /* Should be bounded by MAX_ARGS (50) */
        $this->assertLessThanOrEqual( Translator::MAX_ARGS, count( $result ) );
    }

    /* ── Plural resolution ───────────────────────────────────────────── */

    public function test_plural_singular_fallback_when_no_count(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        /* ITEM_COUNT has singular: "Count items" */
        $result = $t->translate( 'ITEM_COUNT' );

        $this->assertSame( 'Count items', $result );
    }

    public function test_plural_named_form_one(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $result = $t->translate( 'ITEM_COUNT', array( 'count' => '1' ), 'one' );

        $this->assertSame( '1 item', $result );
    }

    public function test_plural_named_form_other(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $result = $t->translate( 'ITEM_COUNT', array( 'count' => '5' ), 'other' );

        $this->assertSame( '5 items', $result );
    }

    public function test_plural_numeric_cldr_resolution(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        /* count=1 -> CLDR 'one' for English */
        $result_one = $t->translate( 'ITEM_COUNT', array( 'count' => '1' ), 1 );
        /* count=3 -> CLDR 'other' for English */
        $result_other = $t->translate( 'ITEM_COUNT', array( 'count' => '3' ), 3 );

        $this->assertSame( '1 item', $result_one );
        $this->assertSame( '3 items', $result_other );
    }

    public function test_plural_key_without_singular_falls_back_to_key_name(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        /* PAGE_COUNT has no 'singular' key */
        $result = $t->translate( 'PAGE_COUNT' );

        $this->assertSame( 'PAGE_COUNT', $result );
    }

    public function test_plural_missing_form_falls_to_other(): void {
        $data = array(
            'TEST_PLURAL' => array(
                'singular' => 'Default',
                'other'    => 'Other form',
            ),
        );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        $t = new Translator( 'en', null, self::$tmp_dir );

        /* Request 'few' which doesn't exist — should fall to 'other' */
        $result = $t->translate( 'TEST_PLURAL', array(), 'few' );

        $this->assertSame( 'Default', $result );
    }

    public function test_plural_missing_form_and_other_falls_to_singular(): void {
        $data = array(
            'ONLY_SINGULAR' => array(
                'singular' => 'Singular form',
            ),
        );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        $t = new Translator( 'en', null, self::$tmp_dir );

        /* Numeric count 5 resolves CLDR 'other', but no 'other' key exists -> singular fallback */
        $result = $t->translate( 'ONLY_SINGULAR', array(), 5 );

        $this->assertSame( 'Singular form', $result );
    }

    /* ── Placeholder replacement ─────────────────────────────────────── */

    public function test_placeholder_single_replacement(): void {
        $data = array( 'HELLO' => 'Hello, ##name##!' );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        $t = new Translator( 'en', null, self::$tmp_dir );

        $result = $t->translate_raw( 'HELLO', array( 'name' => 'Alice' ) );

        $this->assertSame( 'Hello, Alice!', $result );
    }

    public function test_placeholder_multiple_replacements(): void {
        $data = array( 'MSG' => '##greeting## ##name##, you have ##count## messages.' );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        $t = new Translator( 'en', null, self::$tmp_dir );

        $result = $t->translate_raw( 'MSG', array(
            'greeting' => 'Hi',
            'name'     => 'Bob',
            'count'    => '3',
        ) );

        $this->assertSame( 'Hi Bob, you have 3 messages.', $result );
    }

    public function test_unresolved_placeholders_stripped(): void {
        $data = array( 'PARTIAL' => 'Hello ##name##, your code is ##code##.' );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        $t = new Translator( 'en', null, self::$tmp_dir );

        /* Only provide 'name', not 'code' */
        $result = $t->translate_raw( 'PARTIAL', array( 'name' => 'Eve' ) );

        $this->assertSame( 'Hello Eve, your code is .', $result );
    }

    /* ── Locale overlay ──────────────────────────────────────────────── */

    public function test_locale_overlay_merges_over_language(): void {
        $lang_data   = array( 'KEY_A' => 'Language A', 'KEY_B' => 'Language B' );
        $locale_data = array( 'KEY_A' => 'Locale A override' );

        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $lang_data ) );
        file_put_contents( self::$tmp_dir . '/en_GB.json', json_encode( $locale_data ) );

        $t = new Translator( 'en', 'en_GB', self::$tmp_dir );

        $this->assertSame( 'Locale A override', $t->translate_raw( 'KEY_A' ) );
        $this->assertSame( 'Language B', $t->translate_raw( 'KEY_B' ) );
    }

    /* ── File cache ──────────────────────────────────────────────────── */

    public function test_clear_cache_reloads_file(): void {
        $data1 = array( 'CACHED' => 'Value 1' );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data1 ) );

        $t1 = new Translator( 'en', null, self::$tmp_dir );
        $this->assertSame( 'Value 1', $t1->translate_raw( 'CACHED' ) );

        /* Change file */
        $data2 = array( 'CACHED' => 'Value 2' );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data2 ) );

        /* Without clear, old cache persists */
        $t2 = new Translator( 'en', null, self::$tmp_dir );
        $this->assertSame( 'Value 1', $t2->translate_raw( 'CACHED' ) );

        /* After clear, new value loads */
        Translator::clear_cache();
        $t3 = new Translator( 'en', null, self::$tmp_dir );
        $this->assertSame( 'Value 2', $t3->translate_raw( 'CACHED' ) );
    }

    public function test_file_not_found_returns_empty_translations(): void {
        $t = new Translator( 'xx', null, self::$tmp_dir );

        $this->assertSame( 'SOME_KEY', $t->translate_raw( 'SOME_KEY' ) );
    }

    public function test_empty_file_returns_empty_translations(): void {
        file_put_contents( self::$tmp_dir . '/empty.json', '' );

        $t = new Translator( 'empty', null, self::$tmp_dir );

        $this->assertSame( 'ANY_KEY', $t->translate_raw( 'ANY_KEY' ) );
    }

    public function test_invalid_json_returns_empty_translations(): void {
        file_put_contents( self::$tmp_dir . '/bad.json', 'not valid json{{{' );

        $t = new Translator( 'bad', null, self::$tmp_dir );

        $this->assertSame( 'ANY_KEY', $t->translate_raw( 'ANY_KEY' ) );
    }

    public function test_empty_iso2_returns_empty_translations(): void {
        $t = new Translator( '', null, self::$tmp_dir );

        $this->assertSame( 'ANY_KEY', $t->translate_raw( 'ANY_KEY' ) );
    }

    /* ── get_all_translations() ──────────────────────────────────────── */

    public function test_get_all_translations_returns_array(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        $all = $t->get_all_translations();

        $this->assertIsArray( $all );
        $this->assertArrayHasKey( 'SITE_NAME', $all );
        $this->assertSame( 'BS Custom', $all['SITE_NAME'] );
    }

    /* ── Serbian language (east_slavic CLDR) ──────────────────────────── */

    public function test_serbian_singular_fallback(): void {
        $t = new Translator( 'sr', null, self::$base_dir );

        /* ITEM_COUNT singular in sr.json is "Broj jedinke" */
        $result = $t->translate( 'ITEM_COUNT' );

        $this->assertSame( 'Broj jedinke', $result );
    }

    public function test_serbian_cldr_plurals(): void {
        $t = new Translator( 'sr', null, self::$base_dir );

        /* sr is east_slavic: 1=one, 2-4=few, else=other */
        $result_one  = $t->translate( 'ITEM_COUNT', array( 'count' => '1' ), 1 );
        $result_few  = $t->translate( 'ITEM_COUNT', array( 'count' => '2' ), 2 );
        $result_other = $t->translate( 'ITEM_COUNT', array( 'count' => '5' ), 5 );

        $this->assertSame( '1 item', $result_one );
        /* sr ITEM_COUNT has no 'few' key, falls to 'other' */
        $this->assertSame( '2 items', $result_few );
        $this->assertSame( '5 items', $result_other );
    }

    /* ── Non-string value after plural resolve returns key ───────────── */

    public function test_non_string_plural_value_returns_key(): void {
        $data = array(
            'BROKEN' => array(
                'singular' => array( 'nested' => 'array' ),
            ),
        );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        $t = new Translator( 'en', null, self::$tmp_dir );

        $result = $t->translate_raw( 'BROKEN' );

        $this->assertSame( 'BROKEN', $result );
    }

    /* ── Counter key with empty singular ─────────────────────────────── */

    public function test_empty_singular_value_returns_empty_string(): void {
        $t = new Translator( 'en', null, self::$base_dir );

        /* COUNTER has singular: "" */
        $result = $t->translate_raw( 'COUNTER' );

        $this->assertSame( '', $result );
    }
}
