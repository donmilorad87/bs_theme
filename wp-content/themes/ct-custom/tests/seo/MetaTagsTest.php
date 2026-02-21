<?php
/**
 * Tests for MetaTags trait output.
 *
 * @package BSCustom\Tests\Seo
 */

namespace BSCustom\Tests\Seo;

use BSCustom\Seo\SeoService;

class MetaTagsTest extends SeoTestCase {

	/** @var SeoService */
	private $service;

	protected function setUp(): void {
		parent::setUp();

		/* Reset singleton for clean test */
		$ref = new \ReflectionClass( SeoService::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setValue( null, null );

		$this->service = SeoService::instance();

		/* Simulate singular context */
		$GLOBALS['bs_test_options']['_is_singular'] = true;
		$GLOBALS['bs_test_options']['_current_post_id'] = 1;
	}

	public function test_filter_title_parts_with_custom_title(): void {
		$this->createPost( 1, 'Original Title' );
		$this->setPostMeta( 1, 'bs_seo_title', 'Custom SEO Title' );

		$parts = array( 'title' => 'Original Title', 'site' => 'My Site' );
		$result = $this->service->filter_title_parts( $parts );

		$this->assertSame( 'Custom SEO Title', $result['title'] );
		$this->assertArrayNotHasKey( 'site', $result );
	}

	public function test_filter_title_parts_with_template_placeholders(): void {
		$this->createPost( 1, 'About Us' );
		$this->setPostMeta( 1, 'bs_seo_title', '%%title%% %%sep%% %%sitename%%' );
		$GLOBALS['bs_test_options']['blogname'] = 'My Site';

		$parts = array( 'title' => 'About Us', 'site' => 'My Site' );
		$result = $this->service->filter_title_parts( $parts );

		$this->assertSame( 'About Us - My Site', $result['title'] );
	}

	public function test_filter_title_parts_no_change_when_no_meta(): void {
		$this->createPost( 1, 'Original Title' );

		$parts = array( 'title' => 'Original Title', 'site' => 'My Site' );
		$result = $this->service->filter_title_parts( $parts );

		$this->assertSame( 'Original Title', $result['title'] );
		$this->assertSame( 'My Site', $result['site'] );
	}

	public function test_filter_title_separator_with_custom(): void {
		update_option( 'bs_seo_global_title_separator', '|' );

		$result = $this->service->filter_title_separator( '-' );
		$this->assertSame( '|', $result );
	}

	public function test_filter_title_separator_defaults(): void {
		$result = $this->service->filter_title_separator( '-' );
		$this->assertSame( '-', $result );
	}

	public function test_output_meta_tags_with_description(): void {
		$this->createPost( 1, 'Test Page' );
		$this->setPostMeta( 1, 'bs_seo_description', 'A custom meta description for testing.' );

		ob_start();
		$this->service->output_meta_tags();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="description"', $output );
		$this->assertStringContainsString( 'A custom meta description for testing.', $output );
	}

	public function test_output_meta_tags_with_keywords(): void {
		$this->createPost( 1, 'Test Page' );
		$this->setPostMeta( 1, 'bs_seo_keywords', 'seo, testing, wordpress' );

		ob_start();
		$this->service->output_meta_tags();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="keywords"', $output );
		$this->assertStringContainsString( 'seo, testing, wordpress', $output );
	}

	public function test_output_robots_meta_noindex(): void {
		$this->createPost( 1, 'Test Page' );
		$this->setPostMeta( 1, 'bs_seo_robots_index', 'noindex' );

		ob_start();
		$this->service->output_robots_meta();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="robots"', $output );
		$this->assertStringContainsString( 'noindex', $output );
	}

	public function test_output_robots_meta_noindex_nofollow(): void {
		$this->createPost( 1, 'Test Page' );
		$this->setPostMeta( 1, 'bs_seo_robots_index', 'noindex' );
		$this->setPostMeta( 1, 'bs_seo_robots_follow', 'nofollow' );

		ob_start();
		$this->service->output_robots_meta();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'noindex, nofollow', $output );
	}

	public function test_output_robots_meta_no_output_when_empty(): void {
		$this->createPost( 1, 'Test Page' );

		ob_start();
		$this->service->output_robots_meta();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_output_open_graph_tags(): void {
		$this->createPost( 1, 'OG Test Page' );
		$this->setPostMeta( 1, 'bs_seo_og_title', 'Custom OG Title' );
		$this->setPostMeta( 1, 'bs_seo_og_description', 'Custom OG Description' );
		$GLOBALS['bs_test_options']['blogname'] = 'Test Site';

		ob_start();
		$this->service->output_open_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'og:locale', $output );
		$this->assertStringContainsString( 'og:type', $output );
		$this->assertStringContainsString( 'og:title', $output );
		$this->assertStringContainsString( 'Custom OG Title', $output );
		$this->assertStringContainsString( 'og:description', $output );
		$this->assertStringContainsString( 'Custom OG Description', $output );
		$this->assertStringContainsString( 'og:site_name', $output );
	}

	public function test_output_twitter_cards(): void {
		$this->createPost( 1, 'Twitter Test' );
		$this->setPostMeta( 1, 'bs_seo_twitter_title', 'Twitter Title' );
		$this->setPostMeta( 1, 'bs_seo_twitter_description', 'Twitter Desc' );
		update_option( 'bs_seo_global_twitter_username', 'testuser' );

		ob_start();
		$this->service->output_twitter_cards();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'twitter:card', $output );
		$this->assertStringContainsString( 'twitter:site', $output );
		$this->assertStringContainsString( '@testuser', $output );
		$this->assertStringContainsString( 'Twitter Title', $output );
		$this->assertStringContainsString( 'Twitter Desc', $output );
	}

	public function test_output_pinterest_with_verification(): void {
		update_option( 'bs_seo_global_pinterest_verify', 'abc123def' );

		ob_start();
		$this->service->output_pinterest();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'p:domain_verify', $output );
		$this->assertStringContainsString( 'abc123def', $output );
	}

	public function test_output_pinterest_empty_when_not_set(): void {
		ob_start();
		$this->service->output_pinterest();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/* ── OG type auto-detection ──────────────────────────── */

	public function test_og_type_auto_detects_article_for_posts(): void {
		$this->createPost( 1, 'Blog Post', 'Content here.', 'post' );

		ob_start();
		$this->service->output_open_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'og:type', $output );
		$this->assertStringContainsString( 'content="article"', $output );
	}

	public function test_og_type_auto_detects_website_for_pages(): void {
		$this->createPost( 1, 'About Page', 'Content here.', 'page' );

		ob_start();
		$this->service->output_open_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'og:type', $output );
		$this->assertStringContainsString( 'content="website"', $output );
	}

	/* ── article:author, article:section, article:tag ──── */

	public function test_og_outputs_article_author_for_posts(): void {
		$this->createPost( 1, 'Author Post', 'Content here.', 'post' );
		$GLOBALS['bs_test_options']['_author_name'] = 'Jane Smith';

		ob_start();
		$this->service->output_open_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'article:author', $output );
		$this->assertStringContainsString( 'Jane Smith', $output );
	}

	public function test_og_outputs_article_section_for_posts(): void {
		$this->createPost( 1, 'Categorized Post', 'Content here.', 'post' );

		$cat       = new \stdClass();
		$cat->name = 'Technology';
		$GLOBALS['bs_test_options']['_post_categories'][1] = array( $cat );

		ob_start();
		$this->service->output_open_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'article:section', $output );
		$this->assertStringContainsString( 'Technology', $output );
	}

	public function test_og_outputs_article_tags_for_posts(): void {
		$this->createPost( 1, 'Tagged Post', 'Content here.', 'post' );

		$tag1       = new \stdClass();
		$tag1->name = 'PHP';
		$tag2       = new \stdClass();
		$tag2->name = 'WordPress';
		$GLOBALS['bs_test_options']['_post_tags'][1] = array( $tag1, $tag2 );

		ob_start();
		$this->service->output_open_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'article:tag', $output );
		$this->assertStringContainsString( 'PHP', $output );
		$this->assertStringContainsString( 'WordPress', $output );
	}

	public function test_og_no_article_meta_for_pages(): void {
		$this->createPost( 1, 'Regular Page', 'Content here.', 'page' );

		ob_start();
		$this->service->output_open_graph();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'article:published_time', $output );
		$this->assertStringNotContainsString( 'article:author', $output );
	}

	/* ── Schema auto-detection ───────────────────────────── */

	public function test_schema_auto_detects_article_for_posts(): void {
		$this->createPost( 1, 'Blog Post', 'Word count content for testing.', 'post' );

		ob_start();
		$this->service->output_schema_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( '"@type":"Article"', str_replace( ' ', '', $output ) );
	}

	public function test_schema_auto_detects_webpage_for_pages(): void {
		$this->createPost( 1, 'About Us', '', 'page' );

		ob_start();
		$this->service->output_schema_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( '"@type":"WebPage"', str_replace( ' ', '', $output ) );
	}

	public function test_schema_article_includes_headline(): void {
		$this->createPost( 1, 'My Headline', 'Some content.', 'post' );

		ob_start();
		$this->service->output_schema_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'headline', $output );
		$this->assertStringContainsString( 'My Headline', $output );
	}

	public function test_schema_respects_manual_override(): void {
		$this->createPost( 1, 'FAQ Page', 'Content.', 'page' );
		$this->setPostMeta( 1, 'bs_seo_schema_type', 'FAQPage' );

		ob_start();
		$this->service->output_schema_graph();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'FAQPage', $output );
		$this->assertStringNotContainsString( '"WebPage"', $output );
	}
}
