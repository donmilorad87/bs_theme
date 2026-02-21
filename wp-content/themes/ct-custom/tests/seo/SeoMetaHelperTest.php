<?php
/**
 * Tests for SeoMetaHelper priority cascade.
 *
 * @package BSCustom\Tests\Seo
 */

namespace BSCustom\Tests\Seo;

use BSCustom\Seo\SeoMetaHelper;

class SeoMetaHelperTest extends SeoTestCase {

	public function test_get_returns_page_meta_when_set(): void {
		$this->createPost( 1, 'My Page' );
		$this->setPostMeta( 1, 'bs_seo_title', 'Custom SEO Title' );

		$result = SeoMetaHelper::get( 1, 'title' );
		$this->assertSame( 'Custom SEO Title', $result );
	}

	public function test_get_falls_back_to_global_option(): void {
		$this->createPost( 1, 'My Page' );
		/* No page meta set */

		update_option( 'bs_seo_global_title', 'Global Default Title' );

		$result = SeoMetaHelper::get( 1, 'title' );
		$this->assertSame( 'Global Default Title', $result );
	}

	public function test_get_returns_empty_string_when_nothing_set(): void {
		$this->createPost( 1, 'My Page' );

		$result = SeoMetaHelper::get( 1, 'title' );
		$this->assertSame( '', $result );
	}

	public function test_get_page_meta_takes_priority_over_global(): void {
		$this->createPost( 1, 'My Page' );
		$this->setPostMeta( 1, 'bs_seo_description', 'Page Description' );
		update_option( 'bs_seo_global_description', 'Global Description' );

		$result = SeoMetaHelper::get( 1, 'description' );
		$this->assertSame( 'Page Description', $result );
	}

	public function test_get_with_zero_post_id_skips_meta(): void {
		update_option( 'bs_seo_global_keywords', 'global, keywords' );

		$result = SeoMetaHelper::get( 0, 'keywords' );
		$this->assertSame( 'global, keywords', $result );
	}

	public function test_get_int_returns_page_meta_when_set(): void {
		$this->createPost( 1, 'My Page' );
		$this->setPostMeta( 1, 'bs_seo_score', 85 );

		$result = SeoMetaHelper::getInt( 1, 'score' );
		$this->assertSame( 85, $result );
	}

	public function test_get_int_falls_back_to_global(): void {
		$this->createPost( 1, 'My Page' );
		update_option( 'bs_seo_global_score', 50 );

		$result = SeoMetaHelper::getInt( 1, 'score' );
		$this->assertSame( 50, $result );
	}

	public function test_get_int_returns_zero_when_nothing_set(): void {
		$this->createPost( 1, 'My Page' );

		$result = SeoMetaHelper::getInt( 1, 'score' );
		$this->assertSame( 0, $result );
	}

	public function test_resolve_title_replaces_placeholders(): void {
		$this->createPost( 1, 'About Us' );
		$GLOBALS['bs_test_options']['blogname'] = 'My Site';

		$template = '%%title%% %%sep%% %%sitename%%';
		$result   = SeoMetaHelper::resolveTitle( $template, 1 );

		$this->assertSame( 'About Us - My Site', $result );
	}

	public function test_resolve_title_with_custom_separator(): void {
		$this->createPost( 1, 'Contact' );
		$GLOBALS['bs_test_options']['blogname'] = 'My Site';
		update_option( 'bs_seo_global_title_separator', '|' );

		$template = '%%title%% %%sep%% %%sitename%%';
		$result   = SeoMetaHelper::resolveTitle( $template, 1 );

		$this->assertSame( 'Contact | My Site', $result );
	}

	public function test_resolve_title_returns_empty_for_empty_template(): void {
		$result = SeoMetaHelper::resolveTitle( '', 1 );
		$this->assertSame( '', $result );
	}

	public function test_resolve_title_with_page_number(): void {
		$this->createPost( 1, 'Blog' );
		$GLOBALS['bs_test_options']['blogname'] = 'My Site';
		$GLOBALS['bs_test_options']['_query_vars'] = array( 'paged' => 3 );

		$template = '%%title%% %%sep%% %%page%% %%sep%% %%sitename%%';
		$result   = SeoMetaHelper::resolveTitle( $template, 1 );

		$this->assertSame( 'Blog - Page 3 - My Site', $result );
	}
}
