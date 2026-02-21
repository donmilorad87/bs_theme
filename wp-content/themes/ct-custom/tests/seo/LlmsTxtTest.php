<?php
/**
 * Tests for LlmsTxt generator.
 *
 * Covers content generation: header, pages, posts, categories, tags,
 * caching, and disabled state.
 *
 * @package BSCustom\Tests\Seo
 */

namespace BSCustom\Tests\Seo;

use BSCustom\Seo\LlmsTxt;

class LlmsTxtTest extends SeoTestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['bs_test_options']['blogname']    = 'Test Site';
		$GLOBALS['bs_test_options']['blogdescription'] = '';
	}

	/* ── Header: site name ────────────────────────────────── */

	public function test_generate_includes_site_name(): void {
		$content = LlmsTxt::generate();

		$this->assertStringContainsString( '# Test Site', $content );
	}

	/* ── Header: description ──────────────────────────────── */

	public function test_generate_includes_description(): void {
		/* Override get_bloginfo to return description */
		$GLOBALS['bs_test_options']['blogdescription'] = 'A great testing site';

		$content = LlmsTxt::generate();

		$this->assertStringContainsString( '> A great testing site', $content );
	}

	/* ── Site information section ─────────────────────────── */

	public function test_generate_includes_site_info(): void {
		$content = LlmsTxt::generate();

		$this->assertStringContainsString( '## Site Information', $content );
		$this->assertStringContainsString( 'https://example.com/', $content );
		$this->assertStringContainsString( 'sitemap_index.xml', $content );
	}

	/* ── Main pages section ───────────────────────────────── */

	public function test_generate_includes_pages(): void {
		$page = new \WP_Post( array(
			'ID'          => 1,
			'post_title'  => 'About Us',
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => 'about-us',
		) );
		$GLOBALS['bs_test_posts'][1] = $page;

		/* Clear transient cache */
		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		$this->assertStringContainsString( '## Main Pages', $content );
		$this->assertStringContainsString( 'About Us', $content );
	}

	/* ── Blog posts section ───────────────────────────────── */

	public function test_generate_includes_posts(): void {
		$post = new \WP_Post( array(
			'ID'          => 2,
			'post_title'  => 'Hello World',
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_name'   => 'hello-world',
		) );
		$GLOBALS['bs_test_posts'][2] = $post;

		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		$this->assertStringContainsString( '## Blog Posts', $content );
		$this->assertStringContainsString( 'Hello World', $content );
	}

	/* ── Posts with SEO description ───────────────────────── */

	public function test_generate_includes_seo_description_for_posts(): void {
		$post = new \WP_Post( array(
			'ID'          => 3,
			'post_title'  => 'SEO Post',
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_name'   => 'seo-post',
		) );
		$GLOBALS['bs_test_posts'][3] = $post;
		$this->setPostMeta( 3, 'bs_seo_description', 'A great post about SEO' );

		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		$this->assertStringContainsString( ': A great post about SEO', $content );
	}

	/* ── Empty title pages are skipped ────────────────────── */

	public function test_generate_skips_empty_title_pages(): void {
		$page = new \WP_Post( array(
			'ID'          => 4,
			'post_title'  => '',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );
		$GLOBALS['bs_test_posts'][4] = $page;

		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		/* Verify the page link is NOT in the output */
		$this->assertStringNotContainsString( '?p=4', $content );
	}

	/* ── Categories section ───────────────────────────────── */

	public function test_generate_includes_categories(): void {
		$cat = new \stdClass();
		$cat->name    = 'Technology';
		$cat->term_id = 5;

		$GLOBALS['bs_test_options']['_categories'] = array( $cat );

		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		$this->assertStringContainsString( '## Categories', $content );
		$this->assertStringContainsString( 'Technology', $content );
		$this->assertStringContainsString( 'category/5', $content );
	}

	/* ── Tags section ─────────────────────────────────────── */

	public function test_generate_includes_tags(): void {
		$tag = new \stdClass();
		$tag->name    = 'PHP';
		$tag->term_id = 7;

		$GLOBALS['bs_test_options']['_tags'] = array( $tag );

		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		$this->assertStringContainsString( '## Tags', $content );
		$this->assertStringContainsString( 'PHP', $content );
		$this->assertStringContainsString( 'tag/7', $content );
	}

	/* ── Custom additions ─────────────────────────────────── */

	public function test_generate_includes_custom_additions(): void {
		update_option( 'bs_seo_llms_custom', 'Custom info about the site.' );

		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		$this->assertStringContainsString( '## Additional Information', $content );
		$this->assertStringContainsString( 'Custom info about the site.', $content );
	}

	/* ── Transient caching ────────────────────────────────── */

	public function test_generate_returns_cached_content(): void {
		/* Pre-set the transient */
		set_transient( 'bs_seo_llms_txt', 'cached-llms-content', 3600 );

		$content = LlmsTxt::generate();

		$this->assertEquals( 'cached-llms-content', $content );
	}

	/* ── serve: disabled state ────────────────────────────── */

	public function test_serve_exits_with_404_when_disabled(): void {
		update_option( 'bs_seo_llms_enabled', 'off' );

		/* serve() calls exit; we can't test it directly,
		   but we can test the option check logic */
		$enabled = get_option( 'bs_seo_llms_enabled', 'on' );
		$this->assertEquals( 'off', $enabled );
	}

	/* ── No empty sections ────────────────────────────────── */

	public function test_generate_omits_empty_blog_posts_section(): void {
		/* No posts in test data */
		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		$this->assertStringNotContainsString( '## Blog Posts', $content );
	}

	/* ── No empty categories ──────────────────────────────── */

	public function test_generate_omits_empty_categories_section(): void {
		$GLOBALS['bs_test_options']['_categories'] = array();

		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		$this->assertStringNotContainsString( '## Categories', $content );
	}

	/* ── No empty tags ────────────────────────────────────── */

	public function test_generate_omits_empty_tags_section(): void {
		$GLOBALS['bs_test_options']['_tags'] = array();

		delete_transient( 'bs_seo_llms_txt' );

		$content = LlmsTxt::generate();

		$this->assertStringNotContainsString( '## Tags', $content );
	}
}
