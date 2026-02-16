<?php
/**
 * Tests for TranslationService::resolve_block_content().
 *
 * Runs without WordPress via vendor/autoload.php.
 * Uses Translator('en') with the en.json translations file directly.
 *
 * @package BS_Custom
 */

namespace BSCustom\Tests\Multilang;

use PHPUnit\Framework\TestCase;
use BSCustom\Multilang\TranslationService;
use BSCustom\Multilang\Translator;

class TranslationServiceBlockContentTest extends TestCase {

    protected function setUp(): void {
        Translator::clear_cache();
    }

    /* ── Passthrough ─────────────────────────────────────────────────── */

    public function test_passthrough_no_patterns(): void {
        $html = '<p>Hello world</p>';

        $result = TranslationService::resolve_block_content( $html, 'en' );

        $this->assertSame( $html, $result );
    }

    public function test_empty_content_passthrough(): void {
        $result = TranslationService::resolve_block_content( '', 'en' );

        $this->assertSame( '', $result );
    }

    /* ── Simple key ──────────────────────────────────────────────────── */

    public function test_simple_key_resolves(): void {
        $content = "ct_translate('SITE_NAME')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( 'BS Custom', $result );
    }

    /* ── Args with PHP arrow syntax ──────────────────────────────────── */

    public function test_args_php_syntax(): void {
        $content = "ct_translate('ITEM_COUNT',['count'=>'5'],'other')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '5 items', $result );
    }

    /* ── Args with JSON syntax ───────────────────────────────────────── */

    public function test_args_json_syntax(): void {
        $content = "ct_translate('ITEM_COUNT',{\"count\":\"5\"},'other')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '5 items', $result );
    }

    /* ── Named plural form ───────────────────────────────────────────── */

    public function test_named_plural_form_one(): void {
        $content = "ct_translate('ITEM_COUNT',['count'=>'1'],'one')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '1 item', $result );
    }

    public function test_named_plural_form_other(): void {
        $content = "ct_translate('ITEM_COUNT',['count'=>'3'],'other')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '3 items', $result );
    }

    /* ── Numeric plural form ─────────────────────────────────────────── */

    public function test_numeric_plural_form_one(): void {
        /* English CLDR: count=1 resolves to 'one' */
        $content = "ct_translate('ITEM_COUNT',['count'=>'1'],1)";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '1 item', $result );
    }

    public function test_numeric_plural_form_other(): void {
        /* English CLDR: count=5 resolves to 'other' */
        $content = "ct_translate('ITEM_COUNT',['count'=>'5'],5)";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '5 items', $result );
    }

    /* ── Singular fallback (plural key, no count) ────────────────────── */

    public function test_singular_fallback_no_count(): void {
        $content = "ct_translate('ITEM_COUNT')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( 'Count items', $result );
    }

    /* ── Multiple patterns in one content block ──────────────────────── */

    public function test_multiple_patterns(): void {
        $content = "<h1>ct_translate('SITE_NAME')</h1><p>ct_translate('CONTACT_US')</p>";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '<h1>BS Custom</h1><p>Contact Us</p>', $result );
    }

    /* ── Unknown key returns key name ────────────────────────────────── */

    public function test_unknown_key_returns_key(): void {
        $content = "ct_translate('NONEXISTENT_KEY')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( 'NONEXISTENT_KEY', $result );
    }

    /* ── HTML in surrounding content is preserved ────────────────────── */

    public function test_html_preserved(): void {
        $content = '<div class="hero"><h1>ct_translate(\'SITE_NAME\')</h1><span>&amp; more</span></div>';

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( '<div class="hero"><h1>BS Custom</h1><span>&amp; more</span></div>', $result );
    }

    /* ── Duplicate patterns resolved once, replaced everywhere ───────── */

    public function test_duplicate_patterns_deduplication(): void {
        $content = "ct_translate('SITE_NAME') - ct_translate('SITE_NAME')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( 'BS Custom - BS Custom', $result );
    }

    /* ── Singular form on plural key via named form ──────────────────── */

    public function test_singular_named_form(): void {
        $content = "ct_translate('CLOSE',[],'singular')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( 'Close', $result );
    }

    /* ── Page count with no singular key falls back ──────────────────── */

    public function test_plural_key_no_singular_fallback(): void {
        /* PAGE_COUNT has no 'singular' key — fallback is the key name */
        $content = "ct_translate('PAGE_COUNT')";

        $result = TranslationService::resolve_block_content( $content, 'en' );

        $this->assertSame( 'PAGE_COUNT', $result );
    }
}
