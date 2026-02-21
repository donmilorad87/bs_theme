<?php
/**
 * Tests for SeoMeta registration.
 *
 * @package BSCustom\Tests\Seo
 */

namespace BSCustom\Tests\Seo;

use BSCustom\Seo\SeoMeta;

class SeoMetaTest extends SeoTestCase {

	public function test_register_meta_creates_all_string_keys_for_page(): void {
		SeoMeta::registerMeta();

		$string_keys = SeoMeta::getStringKeys();
		$max = 30;
		$count = 0;

		foreach ( $string_keys as $key ) {
			if ( $count >= $max ) {
				break;
			}
			$count++;

			$this->assertArrayHasKey(
				'page:' . $key,
				$GLOBALS['bs_test_registered_meta'],
				"Meta key '{$key}' should be registered for page"
			);
		}
	}

	public function test_register_meta_creates_all_string_keys_for_post(): void {
		SeoMeta::registerMeta();

		$string_keys = SeoMeta::getStringKeys();
		$max = 30;
		$count = 0;

		foreach ( $string_keys as $key ) {
			if ( $count >= $max ) {
				break;
			}
			$count++;

			$this->assertArrayHasKey(
				'post:' . $key,
				$GLOBALS['bs_test_registered_meta'],
				"Meta key '{$key}' should be registered for post"
			);
		}
	}

	public function test_register_meta_creates_all_integer_keys(): void {
		SeoMeta::registerMeta();

		$int_keys = SeoMeta::getIntegerKeys();
		$max = 10;
		$count = 0;

		foreach ( $int_keys as $key ) {
			if ( $count >= $max ) {
				break;
			}
			$count++;

			$this->assertArrayHasKey(
				'page:' . $key,
				$GLOBALS['bs_test_registered_meta'],
				"Integer meta key '{$key}' should be registered for page"
			);

			$this->assertArrayHasKey(
				'post:' . $key,
				$GLOBALS['bs_test_registered_meta'],
				"Integer meta key '{$key}' should be registered for post"
			);
		}
	}

	public function test_register_meta_string_type_is_string(): void {
		SeoMeta::registerMeta();

		$entry = $GLOBALS['bs_test_registered_meta']['page:bs_seo_title'];
		$this->assertSame( 'string', $entry['type'] );
	}

	public function test_register_meta_integer_type_is_integer(): void {
		SeoMeta::registerMeta();

		$entry = $GLOBALS['bs_test_registered_meta']['page:bs_seo_score'];
		$this->assertSame( 'integer', $entry['type'] );
	}

	public function test_register_meta_show_in_rest_is_true(): void {
		SeoMeta::registerMeta();

		$entry = $GLOBALS['bs_test_registered_meta']['page:bs_seo_title'];
		$this->assertTrue( $entry['show_in_rest'] );
	}

	public function test_sanitize_string_returns_clean_string(): void {
		$result = SeoMeta::sanitizeString( '<script>alert("xss")</script>Hello' );
		$this->assertSame( 'alert("xss")Hello', $result );
	}

	public function test_sanitize_string_returns_empty_for_null(): void {
		$result = SeoMeta::sanitizeString( null );
		$this->assertSame( '', $result );
	}

	public function test_sanitize_integer_returns_non_negative(): void {
		$this->assertSame( 42, SeoMeta::sanitizeInteger( 42 ) );
		$this->assertSame( 0, SeoMeta::sanitizeInteger( -5 ) );
		$this->assertSame( 0, SeoMeta::sanitizeInteger( '' ) );
	}

	public function test_get_string_keys_returns_array(): void {
		$keys = SeoMeta::getStringKeys();
		$this->assertIsArray( $keys );
		$this->assertContains( 'bs_seo_title', $keys );
		$this->assertContains( 'bs_seo_description', $keys );
		$this->assertContains( 'bs_seo_og_title', $keys );
	}

	public function test_get_integer_keys_returns_array(): void {
		$keys = SeoMeta::getIntegerKeys();
		$this->assertIsArray( $keys );
		$this->assertContains( 'bs_seo_score', $keys );
		$this->assertContains( 'bs_seo_og_image_id', $keys );
	}
}
