<?php
/**
 * Tests for LanguageManager class.
 *
 * Runs without WordPress via vendor/autoload.php.
 * Uses temp directory for isolated file operations.
 *
 * @package BS_Custom
 */

namespace BSCustom\Tests\Multilang;

use PHPUnit\Framework\TestCase;
use BSCustom\Multilang\LanguageManager;

class LanguageManagerTest extends TestCase {

    /** @var string */
    private $tmp_dir;

    /** @var string */
    private $tmp_file;

    protected function setUp(): void {
        $this->tmp_dir  = sys_get_temp_dir() . '/ct_langmgr_test_' . getmypid() . '_' . mt_rand();
        $this->tmp_file = $this->tmp_dir . '/languages.json';

        if ( ! is_dir( $this->tmp_dir ) ) {
            mkdir( $this->tmp_dir, 0755, true );
        }
    }

    protected function tearDown(): void {
        if ( file_exists( $this->tmp_file ) ) {
            unlink( $this->tmp_file );
        }
        if ( is_dir( $this->tmp_dir ) ) {
            rmdir( $this->tmp_dir );
        }
    }

    /**
     * Helper: write seed data to the temp file and return a fresh manager.
     */
    private function seed( array $data ): LanguageManager {
        file_put_contents( $this->tmp_file, json_encode( $data, JSON_PRETTY_PRINT ) );
        return new LanguageManager( $this->tmp_file );
    }

    /**
     * Helper: create a standard two-language seed.
     */
    private function seed_default(): LanguageManager {
        return $this->seed( array(
            array(
                'id'          => 'lang_en',
                'iso2'        => 'en',
                'iso3'        => 'eng',
                'native_name' => 'English',
                'flag'        => '',
                'locales'     => array( 'en_US' ),
                'enabled'     => true,
                'is_default'  => true,
            ),
            array(
                'id'          => 'lang_sr',
                'iso2'        => 'sr',
                'iso3'        => 'srb',
                'native_name' => 'Srpski',
                'flag'        => '',
                'locales'     => array( 'sr_RS' ),
                'enabled'     => true,
                'is_default'  => false,
            ),
        ) );
    }

    /* ── get_all() ───────────────────────────────────────────────────── */

    public function test_get_all_reads_file(): void {
        $mgr = $this->seed_default();

        $all = $mgr->get_all();

        $this->assertCount( 2, $all );
        $this->assertSame( 'en', $all[0]['iso2'] );
        $this->assertSame( 'sr', $all[1]['iso2'] );
    }

    public function test_get_all_empty_file(): void {
        $mgr = $this->seed( array() );

        $this->assertSame( array(), $mgr->get_all() );
    }

    /* ── get_enabled() ───────────────────────────────────────────────── */

    public function test_get_enabled_returns_only_enabled(): void {
        $mgr = $this->seed( array(
            array( 'id' => '1', 'iso2' => 'en', 'native_name' => 'English', 'enabled' => true, 'is_default' => true ),
            array( 'id' => '2', 'iso2' => 'fr', 'native_name' => 'French', 'enabled' => false, 'is_default' => false ),
            array( 'id' => '3', 'iso2' => 'de', 'native_name' => 'German', 'enabled' => true, 'is_default' => false ),
        ) );

        $enabled = $mgr->get_enabled();

        $this->assertCount( 2, $enabled );
        $this->assertSame( 'en', $enabled[0]['iso2'] );
        $this->assertSame( 'de', $enabled[1]['iso2'] );
    }

    /* ── get_default() ───────────────────────────────────────────────── */

    public function test_get_default_returns_default_language(): void {
        $mgr = $this->seed_default();

        $default = $mgr->get_default();

        $this->assertNotNull( $default );
        $this->assertSame( 'en', $default['iso2'] );
        $this->assertTrue( $default['is_default'] );
    }

    public function test_get_default_null_when_no_default(): void {
        $mgr = $this->seed( array(
            array( 'id' => '1', 'iso2' => 'en', 'native_name' => 'English', 'enabled' => true, 'is_default' => false ),
        ) );

        $this->assertNull( $mgr->get_default() );
    }

    /* ── get_by_iso2() ───────────────────────────────────────────────── */

    public function test_get_by_iso2_found(): void {
        $mgr = $this->seed_default();

        $lang = $mgr->get_by_iso2( 'sr' );

        $this->assertNotNull( $lang );
        $this->assertSame( 'Srpski', $lang['native_name'] );
    }

    public function test_get_by_iso2_not_found(): void {
        $mgr = $this->seed_default();

        $this->assertNull( $mgr->get_by_iso2( 'zz' ) );
    }

    /* ── add() ───────────────────────────────────────────────────────── */

    public function test_add_creates_language(): void {
        $mgr = $this->seed_default();

        $result = $mgr->add( array(
            'iso2'        => 'de',
            'iso3'        => 'deu',
            'native_name' => 'Deutsch',
        ) );

        $this->assertTrue( $result );

        /* Re-read from file to verify persistence */
        $mgr2 = new LanguageManager( $this->tmp_file );
        $this->assertCount( 3, $mgr2->get_all() );

        $de = $mgr2->get_by_iso2( 'de' );
        $this->assertNotNull( $de );
        $this->assertSame( 'Deutsch', $de['native_name'] );
        $this->assertTrue( $de['enabled'] );
        $this->assertFalse( $de['is_default'] );
    }

    public function test_add_prevents_duplicate_iso2(): void {
        $mgr = $this->seed_default();

        $result = $mgr->add( array(
            'iso2'        => 'en',
            'native_name' => 'English duplicate',
        ) );

        $this->assertFalse( $result );
    }

    public function test_add_validates_required_fields(): void {
        $mgr = $this->seed_default();

        /* Missing native_name */
        $this->assertFalse( $mgr->add( array( 'iso2' => 'de' ) ) );

        /* Missing iso2 */
        $this->assertFalse( $mgr->add( array( 'native_name' => 'Deutsch' ) ) );

        /* Both empty */
        $this->assertFalse( $mgr->add( array( 'iso2' => '', 'native_name' => '' ) ) );
    }

    public function test_add_enforces_max_languages(): void {
        /* Seed with MAX_LANGUAGES entries */
        $langs = array();
        for ( $i = 0; $i < LanguageManager::MAX_LANGUAGES; $i++ ) {
            $code = sprintf( '%02d', $i );
            $langs[] = array(
                'id'          => 'lang_' . $code,
                'iso2'        => $code,
                'native_name' => 'Lang ' . $code,
                'enabled'     => true,
                'is_default'  => ( 0 === $i ),
            );
        }

        $mgr = $this->seed( $langs );

        $result = $mgr->add( array(
            'iso2'        => 'xx',
            'native_name' => 'Extra',
        ) );

        $this->assertFalse( $result );
    }

    /* ── update() ────────────────────────────────────────────────────── */

    public function test_update_allowed_fields(): void {
        $mgr = $this->seed_default();

        $result = $mgr->update( 'en', array(
            'native_name' => 'American English',
            'iso3'        => 'ame',
        ) );

        $this->assertTrue( $result );

        $mgr2 = new LanguageManager( $this->tmp_file );
        $lang  = $mgr2->get_by_iso2( 'en' );

        $this->assertSame( 'American English', $lang['native_name'] );
        $this->assertSame( 'ame', $lang['iso3'] );
    }

    public function test_update_ignores_unknown_fields(): void {
        $mgr = $this->seed_default();

        /* 'bogus_field' is not in ALLOWED_FIELDS */
        $result = $mgr->update( 'en', array(
            'native_name' => 'Updated',
            'bogus_field' => 'should be ignored',
        ) );

        $this->assertTrue( $result );

        $mgr2 = new LanguageManager( $this->tmp_file );
        $lang  = $mgr2->get_by_iso2( 'en' );

        $this->assertSame( 'Updated', $lang['native_name'] );
        $this->assertArrayNotHasKey( 'bogus_field', $lang );
    }

    public function test_update_not_found_returns_false(): void {
        $mgr = $this->seed_default();

        $this->assertFalse( $mgr->update( 'zz', array( 'native_name' => 'Nope' ) ) );
    }

    /* ── remove() ────────────────────────────────────────────────────── */

    public function test_remove_deletes_language(): void {
        $mgr = $this->seed_default();

        $result = $mgr->remove( 'sr' );

        $this->assertTrue( $result );

        $mgr2 = new LanguageManager( $this->tmp_file );
        $this->assertCount( 1, $mgr2->get_all() );
        $this->assertNull( $mgr2->get_by_iso2( 'sr' ) );
    }

    public function test_remove_prevents_removing_default(): void {
        $mgr = $this->seed_default();

        $result = $mgr->remove( 'en' );

        $this->assertFalse( $result );
        $this->assertCount( 2, ( new LanguageManager( $this->tmp_file ) )->get_all() );
    }

    public function test_remove_not_found_returns_false(): void {
        $mgr = $this->seed_default();

        $this->assertFalse( $mgr->remove( 'zz' ) );
    }

    /* ── set_default() ───────────────────────────────────────────────── */

    public function test_set_default_changes_default(): void {
        $mgr = $this->seed_default();

        $result = $mgr->set_default( 'sr' );

        $this->assertTrue( $result );

        $mgr2    = new LanguageManager( $this->tmp_file );
        $default = $mgr2->get_default();

        $this->assertNotNull( $default );
        $this->assertSame( 'sr', $default['iso2'] );
    }

    public function test_set_default_clears_previous(): void {
        $mgr = $this->seed_default();

        $mgr->set_default( 'sr' );

        $mgr2 = new LanguageManager( $this->tmp_file );
        $en    = $mgr2->get_by_iso2( 'en' );

        $this->assertFalse( $en['is_default'] );
    }

    public function test_set_default_not_found_returns_false(): void {
        $mgr = $this->seed_default();

        $this->assertFalse( $mgr->set_default( 'zz' ) );
    }

    /* ── set_enabled() ───────────────────────────────────────────────── */

    public function test_set_enabled_toggles_state(): void {
        $mgr = $this->seed_default();

        $mgr->set_enabled( 'sr', false );

        $mgr2 = new LanguageManager( $this->tmp_file );
        $sr    = $mgr2->get_by_iso2( 'sr' );

        $this->assertFalse( $sr['enabled'] );
    }

    public function test_set_enabled_re_enable(): void {
        $mgr = $this->seed_default();

        $mgr->set_enabled( 'sr', false );
        $mgr2 = new LanguageManager( $this->tmp_file );
        $mgr2->set_enabled( 'sr', true );

        $mgr3 = new LanguageManager( $this->tmp_file );
        $sr    = $mgr3->get_by_iso2( 'sr' );

        $this->assertTrue( $sr['enabled'] );
    }

    /* ── add_locale() ────────────────────────────────────────────────── */

    public function test_add_locale_adds_to_list(): void {
        $mgr = $this->seed_default();

        $result = $mgr->add_locale( 'en', 'en_GB' );

        $this->assertTrue( $result );

        $mgr2 = new LanguageManager( $this->tmp_file );
        $en    = $mgr2->get_by_iso2( 'en' );

        $this->assertContains( 'en_US', $en['locales'] );
        $this->assertContains( 'en_GB', $en['locales'] );
    }

    public function test_add_locale_prevents_duplicate(): void {
        $mgr = $this->seed_default();

        $this->assertFalse( $mgr->add_locale( 'en', 'en_US' ) );
    }

    public function test_add_locale_not_found_returns_false(): void {
        $mgr = $this->seed_default();

        $this->assertFalse( $mgr->add_locale( 'zz', 'zz_ZZ' ) );
    }

    public function test_add_locale_enforces_max(): void {
        /* Seed with MAX_LOCALES_PER locales already */
        $locales = array();
        for ( $i = 0; $i < LanguageManager::MAX_LOCALES_PER; $i++ ) {
            $locales[] = sprintf( 'en_%02d', $i );
        }

        $mgr = $this->seed( array(
            array(
                'id'          => 'lang_en',
                'iso2'        => 'en',
                'native_name' => 'English',
                'locales'     => $locales,
                'enabled'     => true,
                'is_default'  => true,
            ),
        ) );

        $this->assertFalse( $mgr->add_locale( 'en', 'en_XX' ) );
    }

    /* ── remove_locale() ─────────────────────────────────────────────── */

    public function test_remove_locale_removes_from_list(): void {
        $mgr = $this->seed_default();

        $result = $mgr->remove_locale( 'en', 'en_US' );

        $this->assertTrue( $result );

        $mgr2 = new LanguageManager( $this->tmp_file );
        $en    = $mgr2->get_by_iso2( 'en' );

        $this->assertNotContains( 'en_US', $en['locales'] );
    }

    public function test_remove_locale_not_present_returns_false(): void {
        $mgr = $this->seed_default();

        $this->assertFalse( $mgr->remove_locale( 'en', 'en_XX' ) );
    }

    public function test_remove_locale_language_not_found(): void {
        $mgr = $this->seed_default();

        $this->assertFalse( $mgr->remove_locale( 'zz', 'zz_ZZ' ) );
    }

    /* ── File handling edge cases ────────────────────────────────────── */

    public function test_missing_file_returns_empty_array(): void {
        $mgr = new LanguageManager( $this->tmp_dir . '/nonexistent.json' );

        $this->assertSame( array(), $mgr->get_all() );
    }

    public function test_empty_file_returns_empty_array(): void {
        file_put_contents( $this->tmp_file, '' );

        $mgr = new LanguageManager( $this->tmp_file );

        $this->assertSame( array(), $mgr->get_all() );
    }

    public function test_corrupt_json_returns_empty_array(): void {
        file_put_contents( $this->tmp_file, 'not valid json}}' );

        $mgr = new LanguageManager( $this->tmp_file );

        $this->assertSame( array(), $mgr->get_all() );
    }

    public function test_write_creates_directory_if_needed(): void {
        $nested_dir  = $this->tmp_dir . '/nested/deep';
        $nested_file = $nested_dir . '/languages.json';

        $mgr = new LanguageManager( $nested_file );
        $result = $mgr->add( array(
            'iso2'        => 'en',
            'native_name' => 'English',
        ) );

        $this->assertTrue( $result );
        $this->assertFileExists( $nested_file );

        /* Cleanup nested dirs */
        unlink( $nested_file );
        rmdir( $nested_dir );
        rmdir( $this->tmp_dir . '/nested' );
    }

    /* ── get_file_path() ─────────────────────────────────────────────── */

    public function test_get_file_path_returns_correct_path(): void {
        $mgr = new LanguageManager( $this->tmp_file );

        $this->assertSame( $this->tmp_file, $mgr->get_file_path() );
    }

    /* ── Real languages.json file ────────────────────────────────────── */

    public function test_real_languages_file(): void {
        $real_path = dirname( __DIR__, 2 ) . '/translations/languages.json';

        $mgr = new LanguageManager( $real_path );
        $all = $mgr->get_all();

        $this->assertGreaterThanOrEqual( 2, count( $all ) );

        $default = $mgr->get_default();
        $this->assertNotNull( $default );
        $this->assertSame( 'en', $default['iso2'] );
    }
}
