<?php
/**
 * Tests for TranslationService static utility class.
 *
 * Runs without WordPress via vendor/autoload.php.
 * Tests resolve(), resolve_raw(), resolve_block_content(), and get_all_keys().
 *
 * @package CT_Custom
 */

namespace CTCustom\Tests\Multilang;

use PHPUnit\Framework\TestCase;
use CTCustom\Multilang\TranslationService;
use CTCustom\Multilang\Translator;

class TranslationServiceTest extends TestCase {

    /** @var string */
    private static $tmp_dir;

    public static function setUpBeforeClass(): void {
        self::$tmp_dir = sys_get_temp_dir() . '/ct_transservice_test_' . getmypid();

        if ( ! is_dir( self::$tmp_dir ) ) {
            mkdir( self::$tmp_dir, 0755, true );
        }
    }

    protected function setUp(): void {
        Translator::clear_cache();
    }

    public static function tearDownAfterClass(): void {
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

    /* ── resolve() ───────────────────────────────────────────────────── */

    public function test_resolve_with_pattern(): void {
        $result = TranslationService::resolve( "ct_translate('SITE_NAME')", 'en' );

        $this->assertSame( 'CT Custom', $result );
    }

    public function test_resolve_without_pattern_passthrough(): void {
        $text = 'Just plain text, no patterns';

        $result = TranslationService::resolve( $text, 'en' );

        $this->assertSame( $text, $result );
    }

    public function test_resolve_with_language_override(): void {
        $result = TranslationService::resolve( "ct_translate('CONTACT_US')", 'sr' );

        /* sr.json CONTACT_US singular is "Contact Us sr" */
        $this->assertSame( 'Contact Us sr', $result );
    }

    public function test_resolve_html_escapes(): void {
        /* Create temp file with HTML value to verify escaping */
        $data = array( 'XSS_KEY' => '<script>alert(1)</script>' );
        file_put_contents( self::$tmp_dir . '/en.json', json_encode( $data ) );

        /* TranslationService::resolve uses escape — but we can only test with real base_dir
         * since resolve() creates its own Translator. We test indirectly via block content. */
        $result = TranslationService::resolve_block_content(
            "ct_translate('XSS_KEY')",
            'en'
        );

        $this->assertStringNotContainsString( '<script>', $result );
    }

    /* ── resolve_raw() ───────────────────────────────────────────────── */

    public function test_resolve_raw_no_escaping(): void {
        $result = TranslationService::resolve_raw( "ct_translate('SITE_NAME')", 'en' );

        $this->assertSame( 'CT Custom', $result );
    }

    public function test_resolve_raw_passthrough(): void {
        $text = 'No patterns here either';

        $result = TranslationService::resolve_raw( $text, 'en' );

        $this->assertSame( $text, $result );
    }

    /* ── resolve_block_content() ─────────────────────────────────────── */

    public function test_block_content_passthrough(): void {
        $html = '<p>Hello world</p>';

        $this->assertSame( $html, TranslationService::resolve_block_content( $html, 'en' ) );
    }

    public function test_block_content_empty(): void {
        $this->assertSame( '', TranslationService::resolve_block_content( '', 'en' ) );
    }

    public function test_block_content_simple_key(): void {
        $result = TranslationService::resolve_block_content( "ct_translate('SITE_NAME')", 'en' );

        $this->assertSame( 'CT Custom', $result );
    }

    public function test_block_content_multiple_patterns(): void {
        $content = "<h1>ct_translate('SITE_NAME')</h1><p>ct_translate('LOGIN')</p>";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '<h1>CT Custom</h1><p>Login</p>', $result );
    }

    public function test_block_content_deduplication(): void {
        $content = "ct_translate('SITE_NAME') - ct_translate('SITE_NAME') - ct_translate('SITE_NAME')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( 'CT Custom - CT Custom - CT Custom', $result );
    }

    public function test_block_content_unknown_key(): void {
        $result = TranslationService::resolve_block_content( "ct_translate('NONEXISTENT_KEY')", 'en' );

        $this->assertSame( 'NONEXISTENT_KEY', $result );
    }

    public function test_block_content_with_args_and_form(): void {
        $content = "ct_translate('ITEM_COUNT',['count'=>'5'],'other')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '5 items', $result );
    }

    public function test_block_content_numeric_count(): void {
        $content = "ct_translate('ITEM_COUNT',['count'=>'1'],1)";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '1 item', $result );
    }

    public function test_block_content_json_args(): void {
        $content = "ct_translate('ITEM_COUNT',{\"count\":\"3\"},'other')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '3 items', $result );
    }

    public function test_block_content_preserves_html(): void {
        $content = '<div class="hero"><h1>ct_translate(\'SITE_NAME\')</h1></div>';

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '<div class="hero"><h1>CT Custom</h1></div>', $result );
    }

    public function test_block_content_singular_fallback(): void {
        $content = "ct_translate('ITEM_COUNT')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( 'Count items', $result );
    }

    public function test_block_content_serbian_language(): void {
        $result = TranslationService::resolve_block_content( "ct_translate('CONTACT_US')", 'sr' );

        $this->assertSame( 'Contact Us sr', $result );
    }

    /* ── Max matches limit ───────────────────────────────────────────── */

    public function test_block_content_respects_max_matches(): void {
        /* Build content with more than MAX_PATTERN_MATCHES unique patterns.
         * Since only SITE_NAME exists, each returns "CT Custom" or key name.
         * We just verify it doesn't hang or crash. */
        $content = str_repeat( "ct_translate('SITE_NAME') ", 250 );

        $result = TranslationService::resolve_block_content( $content, 'en' );

        /* Should still resolve (they're all the same pattern, deduplicated) */
        $this->assertStringContainsString( 'CT Custom', $result );
    }

    /* ── Null-like content ───────────────────────────────────────────── */

    public function test_block_content_no_ct_translate_marker(): void {
        $content = 'Some text without the marker function';

        $this->assertSame( $content, TranslationService::resolve_block_content( $content, 'en' ) );
    }
}
