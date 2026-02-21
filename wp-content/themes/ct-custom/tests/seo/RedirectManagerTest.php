<?php
/**
 * Tests for RedirectManager.
 *
 * Covers redirect matching (exact, regex), slug change detection,
 * hit tracking, and admin bypass.
 *
 * @package BSCustom\Tests\Seo
 */

namespace BSCustom\Tests\Seo;

use BSCustom\Seo\RedirectManager;

class RedirectManagerTest extends SeoTestCase {

	protected function setUp(): void {
		parent::setUp();

		/* Reset redirect-specific globals */
		$GLOBALS['bs_test_options']['_is_admin']                = false;
		$GLOBALS['bs_test_options']['_throw_on_redirect']       = true;
		$GLOBALS['bs_test_options']['_last_wp_redirect']        = '';
		$GLOBALS['bs_test_options']['_last_wp_redirect_status'] = 0;
	}

	/* ── boot() registration ──────────────────────────────── */

	public function test_boot_registers_hooks(): void {
		/* Simply ensure boot() can be called without error */
		RedirectManager::boot();
		$this->assertTrue( true, 'boot() should not throw' );
	}

	/* ── handle_redirect: admin bypass ────────────────────── */

	public function test_handle_redirect_skips_admin(): void {
		$_SERVER['REQUEST_URI'] = '/old-page/';
		$GLOBALS['bs_test_options']['_is_admin'] = true;

		/* Store a redirect that would match */
		$redirects = array(
			array( 'from' => '/old-page/', 'to' => '/new-page/', 'type' => 301, 'hits' => 0 ),
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		/* Should NOT redirect in admin context */
		RedirectManager::handle_redirect();

		$this->assertEmpty(
			$GLOBALS['bs_test_options']['_last_wp_redirect'],
			'Admin requests should skip redirects'
		);
	}

	/* ── handle_redirect: empty REQUEST_URI ───────────────── */

	public function test_handle_redirect_returns_on_empty_uri(): void {
		$_SERVER['REQUEST_URI'] = '';

		$redirects = array(
			array( 'from' => '/page/', 'to' => '/other/', 'type' => 301, 'hits' => 0 ),
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		RedirectManager::handle_redirect();

		$this->assertEmpty(
			$GLOBALS['bs_test_options']['_last_wp_redirect'],
			'Empty URI should not trigger redirect'
		);
	}

	/* ── handle_redirect: exact match 301 ─────────────────── */

	public function test_handle_redirect_exact_match_301(): void {
		$_SERVER['REQUEST_URI'] = '/old-page/';

		$redirects = array(
			array( 'from' => '/old-page/', 'to' => '/new-page/', 'type' => 301, 'hits' => 0 ),
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		$caught = false;

		try {
			RedirectManager::handle_redirect();
		} catch ( \RuntimeException $e ) {
			$caught = true;
			$this->assertStringContainsString( '301', $e->getMessage() );
			$this->assertStringContainsString( '/new-page/', $e->getMessage() );
		}

		$this->assertTrue( $caught, 'Exact match should trigger redirect' );
	}

	/* ── handle_redirect: exact match 302 ─────────────────── */

	public function test_handle_redirect_exact_match_302(): void {
		$_SERVER['REQUEST_URI'] = '/temp-page';

		$redirects = array(
			array( 'from' => '/temp-page', 'to' => '/replacement/', 'type' => 302, 'hits' => 0 ),
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		$caught = false;

		try {
			RedirectManager::handle_redirect();
		} catch ( \RuntimeException $e ) {
			$caught = true;
			$this->assertStringContainsString( '302', $e->getMessage() );
			$this->assertStringContainsString( '/replacement/', $e->getMessage() );
		}

		$this->assertTrue( $caught, '302 redirect should trigger' );
	}

	/* ── handle_redirect: trailing slash normalization ─────── */

	public function test_handle_redirect_normalizes_trailing_slash(): void {
		$_SERVER['REQUEST_URI'] = '/old-page';

		/* Stored with trailing slash */
		$redirects = array(
			array( 'from' => '/old-page/', 'to' => '/new-page/', 'type' => 301, 'hits' => 0 ),
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		$caught = false;

		try {
			RedirectManager::handle_redirect();
		} catch ( \RuntimeException $e ) {
			$caught = true;
			$this->assertStringContainsString( '/new-page/', $e->getMessage() );
		}

		$this->assertTrue( $caught, 'Trailing slash difference should still match' );
	}

	/* ── handle_redirect: no match ────────────────────────── */

	public function test_handle_redirect_no_match(): void {
		$_SERVER['REQUEST_URI'] = '/unrelated-page/';

		$redirects = array(
			array( 'from' => '/old-page/', 'to' => '/new-page/', 'type' => 301, 'hits' => 0 ),
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		RedirectManager::handle_redirect();

		$this->assertEmpty(
			$GLOBALS['bs_test_options']['_last_wp_redirect'],
			'Non-matching URLs should not redirect'
		);
	}

	/* ── handle_redirect: empty redirects ─────────────────── */

	public function test_handle_redirect_empty_redirects(): void {
		$_SERVER['REQUEST_URI'] = '/any-page/';
		update_option( 'bs_seo_redirects', '[]' );

		RedirectManager::handle_redirect();

		$this->assertEmpty(
			$GLOBALS['bs_test_options']['_last_wp_redirect'],
			'Empty redirect list should not redirect'
		);
	}

	/* ── handle_redirect: missing from/to fields ──────────── */

	public function test_handle_redirect_skips_malformed_entries(): void {
		$_SERVER['REQUEST_URI'] = '/broken/';

		$redirects = array(
			array( 'from' => '/broken/' ), /* Missing 'to' */
			array( 'to' => '/somewhere/' ), /* Missing 'from' */
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		RedirectManager::handle_redirect();

		$this->assertEmpty(
			$GLOBALS['bs_test_options']['_last_wp_redirect'],
			'Malformed entries should be skipped'
		);
	}

	/* ── handle_redirect: hit tracking ────────────────────── */

	public function test_handle_redirect_increments_hit_counter(): void {
		$_SERVER['REQUEST_URI'] = '/tracked/';

		$redirects = array(
			array( 'from' => '/tracked/', 'to' => '/destination/', 'type' => 301, 'hits' => 5 ),
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		try {
			RedirectManager::handle_redirect();
		} catch ( \RuntimeException $e ) {
			/* Expected: wp_redirect throws */
		}

		/* Verify hit count incremented */
		$updated = json_decode( get_option( 'bs_seo_redirects', '[]' ), true );
		$this->assertIsArray( $updated );
		$this->assertArrayHasKey( 0, $updated );
		$this->assertEquals( 6, $updated[0]['hits'], 'Hit count should increment from 5 to 6' );
	}

	/* ── handle_redirect: default redirect type ───────────── */

	public function test_handle_redirect_defaults_to_301(): void {
		$_SERVER['REQUEST_URI'] = '/no-type/';

		$redirects = array(
			array( 'from' => '/no-type/', 'to' => '/dest/' ), /* No 'type' field */
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $redirects ) );

		$caught = false;

		try {
			RedirectManager::handle_redirect();
		} catch ( \RuntimeException $e ) {
			$caught = true;
			$this->assertStringContainsString( '301', $e->getMessage(), 'Default type should be 301' );
		}

		$this->assertTrue( $caught );
	}

	/* ── detect_slug_change: creates redirect ─────────────── */

	public function test_detect_slug_change_creates_redirect(): void {
		$post_before = new \WP_Post( array(
			'ID'          => 10,
			'post_name'   => 'old-slug',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );

		$post_after = new \WP_Post( array(
			'ID'          => 10,
			'post_name'   => 'new-slug',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );

		$GLOBALS['bs_test_posts'][10] = $post_after;
		update_option( 'bs_seo_redirects', '[]' );

		RedirectManager::detect_slug_change( 10, $post_after, $post_before );

		$redirects = json_decode( get_option( 'bs_seo_redirects', '[]' ), true );
		$this->assertCount( 1, $redirects, 'Should create one redirect' );
		$this->assertStringContainsString( 'old-slug', $redirects[0]['from'] );
		$this->assertEquals( 301, $redirects[0]['type'] );
		$this->assertEquals( 0, $redirects[0]['hits'] );
	}

	/* ── detect_slug_change: skips non-publish ────────────── */

	public function test_detect_slug_change_skips_draft(): void {
		$post_before = new \WP_Post( array(
			'ID'          => 11,
			'post_name'   => 'old-draft',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );

		$post_after = new \WP_Post( array(
			'ID'          => 11,
			'post_name'   => 'new-draft',
			'post_type'   => 'page',
			'post_status' => 'draft',
		) );

		update_option( 'bs_seo_redirects', '[]' );

		RedirectManager::detect_slug_change( 11, $post_after, $post_before );

		$redirects = json_decode( get_option( 'bs_seo_redirects', '[]' ), true );
		$this->assertCount( 0, $redirects, 'Draft should not create redirect' );
	}

	/* ── detect_slug_change: skips custom post types ──────── */

	public function test_detect_slug_change_skips_custom_post_type(): void {
		$post_before = new \WP_Post( array(
			'ID'          => 12,
			'post_name'   => 'old-cpt',
			'post_type'   => 'product',
			'post_status' => 'publish',
		) );

		$post_after = new \WP_Post( array(
			'ID'          => 12,
			'post_name'   => 'new-cpt',
			'post_type'   => 'product',
			'post_status' => 'publish',
		) );

		update_option( 'bs_seo_redirects', '[]' );

		RedirectManager::detect_slug_change( 12, $post_after, $post_before );

		$redirects = json_decode( get_option( 'bs_seo_redirects', '[]' ), true );
		$this->assertCount( 0, $redirects, 'Custom post types should not create redirect' );
	}

	/* ── detect_slug_change: skips same slug ──────────────── */

	public function test_detect_slug_change_skips_identical_slugs(): void {
		$post_before = new \WP_Post( array(
			'ID'          => 13,
			'post_name'   => 'same-slug',
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );

		$post_after = new \WP_Post( array(
			'ID'          => 13,
			'post_name'   => 'same-slug',
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );

		update_option( 'bs_seo_redirects', '[]' );

		RedirectManager::detect_slug_change( 13, $post_after, $post_before );

		$redirects = json_decode( get_option( 'bs_seo_redirects', '[]' ), true );
		$this->assertCount( 0, $redirects, 'Same slugs should not create redirect' );
	}

	/* ── detect_slug_change: skips duplicate ──────────────── */

	public function test_detect_slug_change_skips_existing_redirect(): void {
		$post_before = new \WP_Post( array(
			'ID'          => 14,
			'post_name'   => 'old-page',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );

		$post_after = new \WP_Post( array(
			'ID'          => 14,
			'post_name'   => 'new-page',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );

		$GLOBALS['bs_test_posts'][14] = $post_after;

		/* Pre-existing redirect for the old path */
		$old_url  = get_permalink( 14 );
		$old_path = wp_parse_url(
			str_replace( 'new-page', 'old-page', $old_url ),
			PHP_URL_PATH
		);

		$existing = array(
			array( 'from' => $old_path, 'to' => $old_url, 'type' => 301, 'hits' => 3 ),
		);
		update_option( 'bs_seo_redirects', wp_json_encode( $existing ) );

		RedirectManager::detect_slug_change( 14, $post_after, $post_before );

		$redirects = json_decode( get_option( 'bs_seo_redirects', '[]' ), true );
		$this->assertCount( 1, $redirects, 'Duplicate redirect should not be added' );
	}

	/* ── detect_slug_change: respects MAX_REDIRECTS ───────── */

	public function test_detect_slug_change_respects_max_limit(): void {
		$post_before = new \WP_Post( array(
			'ID'          => 15,
			'post_name'   => 'old-limit',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );

		$post_after = new \WP_Post( array(
			'ID'          => 15,
			'post_name'   => 'new-limit',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );

		$GLOBALS['bs_test_posts'][15] = $post_after;

		/* Fill up to MAX_REDIRECTS */
		$filled = array();
		$max    = 500;

		for ( $i = 0; $i < $max; $i++ ) {
			$filled[] = array(
				'from' => '/fill-' . $i . '/',
				'to'   => '/dest-' . $i . '/',
				'type' => 301,
				'hits' => 0,
			);
		}

		update_option( 'bs_seo_redirects', wp_json_encode( $filled ) );

		RedirectManager::detect_slug_change( 15, $post_after, $post_before );

		$redirects = json_decode( get_option( 'bs_seo_redirects', '[]' ), true );
		$this->assertCount( $max, $redirects, 'Should not exceed MAX_REDIRECTS' );
	}
}
