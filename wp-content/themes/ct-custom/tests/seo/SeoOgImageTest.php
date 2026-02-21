<?php
/**
 * Tests for SeoOgImage.
 *
 * Covers the getImageUrl cascade: custom OG -> featured image -> auto-generated.
 * Tests invalidate() method for cache clearing.
 *
 * @package BSCustom\Tests\Seo
 */

namespace BSCustom\Tests\Seo;

use BSCustom\Seo\SeoOgImage;

class SeoOgImageTest extends SeoTestCase {

	protected function setUp(): void {
		parent::setUp();

		/* Clean up any test OG images from prior runs */
		$dir = '/tmp/wp-stub-uploads/seo-og';
		if ( is_dir( $dir ) ) {
			$files = glob( $dir . '/og-*.png' );
			$max   = 50;
			$count = 0;

			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( $count >= $max ) { break; }
					$count++;
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
			}
		}
	}

	/* ── getImageUrl: invalid post ID ─────────────────────── */

	public function test_get_image_url_returns_empty_for_zero(): void {
		$result = SeoOgImage::getImageUrl( 0 );
		$this->assertSame( '', $result );
	}

	public function test_get_image_url_returns_empty_for_negative(): void {
		$result = SeoOgImage::getImageUrl( -1 );
		$this->assertSame( '', $result );
	}

	/* ── getImageUrl: custom OG image has highest priority ── */

	public function test_get_image_url_returns_custom_og_image(): void {
		$this->createPost( 1, 'Test Page' );
		$this->setPostMeta( 1, 'bs_seo_og_image', 'https://example.com/custom-og.png' );

		$result = SeoOgImage::getImageUrl( 1 );

		$this->assertEquals( 'https://example.com/custom-og.png', $result );
	}

	/* ── getImageUrl: featured image is second priority ───── */

	public function test_get_image_url_returns_featured_image(): void {
		$this->createPost( 2, 'Featured Post' );
		$this->setPostMeta( 2, '_thumbnail_id', 100 );

		$result = SeoOgImage::getImageUrl( 2 );

		$this->assertStringContainsString( 'thumb_2', $result );
	}

	/* ── getImageUrl: custom OG takes precedence over featured ── */

	public function test_custom_og_takes_priority_over_featured(): void {
		$this->createPost( 3, 'Both Set' );
		$this->setPostMeta( 3, 'bs_seo_og_image', 'https://example.com/custom-priority.png' );
		$this->setPostMeta( 3, '_thumbnail_id', 200 );

		$result = SeoOgImage::getImageUrl( 3 );

		$this->assertEquals( 'https://example.com/custom-priority.png', $result );
	}

	/* ── getImageUrl: auto-generates when GD available ────── */

	public function test_get_image_url_auto_generates_with_gd(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'GD extension not available' );
		}

		$this->createPost( 4, 'Auto Generated' );

		$result = SeoOgImage::getImageUrl( 4 );

		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'seo-og', $result );
		$this->assertStringContainsString( 'og-4-', $result );
	}

	/* ── generateImage: creates file on disk ──────────────── */

	public function test_generate_image_creates_file(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'GD extension not available' );
		}

		$this->createPost( 5, 'Disk File Test' );

		$url = SeoOgImage::generateImage( 5 );

		$this->assertNotEmpty( $url );

		/* Verify file was actually created on disk */
		$expected_dir = '/tmp/wp-stub-uploads/seo-og';
		$files = glob( $expected_dir . '/og-5-*.png' );
		$this->assertNotEmpty( $files, 'PNG file should be created on disk' );
	}

	/* ── generateImage: returns cached URL ────────────────── */

	public function test_generate_image_returns_cached(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'GD extension not available' );
		}

		$this->createPost( 6, 'Cached Image' );

		$first  = SeoOgImage::generateImage( 6 );
		$second = SeoOgImage::generateImage( 6 );

		$this->assertEquals( $first, $second, 'Second call should return cached URL' );
	}

	/* ── generateImage: null post returns empty ───────────── */

	public function test_generate_image_returns_empty_for_missing_post(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'GD extension not available' );
		}

		$result = SeoOgImage::generateImage( 999 );

		$this->assertSame( '', $result );
	}

	/* ── invalidate: removes cached files ─────────────────── */

	public function test_invalidate_removes_cached_files(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'GD extension not available' );
		}

		$this->createPost( 7, 'Invalidation Test' );

		/* Generate image first */
		SeoOgImage::generateImage( 7 );

		$dir = '/tmp/wp-stub-uploads/seo-og';
		$files_before = glob( $dir . '/og-7-*.png' );
		$this->assertNotEmpty( $files_before, 'File should exist before invalidation' );

		/* Invalidate */
		SeoOgImage::invalidate( 7 );

		$files_after = glob( $dir . '/og-7-*.png' );
		$this->assertEmpty( $files_after, 'File should be removed after invalidation' );
	}

	/* ── invalidate: safe when directory missing ──────────── */

	public function test_invalidate_safe_when_dir_missing(): void {
		/* Should not throw even if directory doesn't exist */
		SeoOgImage::invalidate( 9999 );
		$this->assertTrue( true );
	}

	/* ── Constants ────────────────────────────────────────── */

	public function test_dimensions_constants(): void {
		$this->assertEquals( 1200, SeoOgImage::WIDTH );
		$this->assertEquals( 630, SeoOgImage::HEIGHT );
	}

	public function test_upload_dir_constant(): void {
		$this->assertEquals( 'seo-og', SeoOgImage::UPLOAD_DIR );
	}
}
