<?php
/**
 * Base test case for SEO tests.
 *
 * @package BSCustom\Tests\Seo
 */

namespace BSCustom\Tests\Seo;

use PHPUnit\Framework\TestCase;

abstract class SeoTestCase extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__ ) . '/bootstrap-wp-stubs.php';
	}

	protected function setUp(): void {
		$GLOBALS['bs_test_transients']      = array();
		$GLOBALS['bs_test_transient_ttl']   = array();
		$GLOBALS['bs_test_options']         = array();
		$GLOBALS['bs_test_post_meta']       = array();
		$GLOBALS['bs_test_registered_meta'] = array();
		$GLOBALS['bs_test_posts']           = array();
		$GLOBALS['bs_test_current_user']    = 0;
		$GLOBALS['bs_test_users']           = array();
		$GLOBALS['bs_test_user_meta']       = array();
		$GLOBALS['bs_test_next_user_id']    = 1;
	}

	/**
	 * Create a mock WP_Post object.
	 *
	 * @param int    $id      Post ID.
	 * @param string $title   Post title.
	 * @param string $content Post content.
	 * @param string $type    Post type.
	 * @return \WP_Post
	 */
	protected function createPost( $id, $title = 'Test Post', $content = '', $type = 'page' ) {
		$post = new \WP_Post( array(
			'ID'           => $id,
			'post_title'   => $title,
			'post_content' => $content,
			'post_type'    => $type,
		) );

		$GLOBALS['bs_test_posts'][ $id ] = $post;
		$GLOBALS['bs_test_options']['_post_titles'][ $id ] = $title;

		return $post;
	}

	/**
	 * Set post meta for a given post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 */
	protected function setPostMeta( $post_id, $key, $value ) {
		update_post_meta( $post_id, $key, $value );
	}
}
