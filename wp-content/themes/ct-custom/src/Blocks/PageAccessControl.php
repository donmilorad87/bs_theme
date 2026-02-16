<?php
/**
 * Page Access Control — redirect logic driven by Gutenberg blocks.
 *
 * When one of the access-control blocks is placed on a page, this
 * class intercepts the frontend request on template_redirect and
 * sends the visitor to the appropriate destination.
 *
 * @package BS_Custom
 */

namespace BSCustom\Blocks;

class PageAccessControl {

	/** Block names checked against post content. */
	private const BLOCK_UNPROTECTED = 'ct-custom/unprotected-page';
	private const BLOCK_PROTECTED   = 'ct-custom/protected-page';
	private const BLOCK_ADMIN       = 'ct-custom/admin-page';

	/**
	 * Register the template_redirect hook.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'template_redirect', array( new self(), 'handle_redirect' ) );
	}

	/**
	 * Check the current singular page for access-control blocks
	 * and redirect when the visitor does not meet the requirement.
	 *
	 * @return void
	 */
	public function handle_redirect(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		assert( null === $post || $post instanceof \WP_Post, 'Queried object must be WP_Post or null' );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( empty( $post->post_content ) ) {
			return;
		}

		assert( is_string( $post->post_content ), 'Post content must be a string' );

		/* Unprotected page — guests only, logged-in users go to profile */
		if ( has_block( self::BLOCK_UNPROTECTED, $post ) ) {
			if ( is_user_logged_in() ) {
				wp_safe_redirect( bs_custom_get_profile_page_url() );
				exit;
			}
		}

		/* Protected page — logged-in only, guests go to login */
		if ( has_block( self::BLOCK_PROTECTED, $post ) ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( bs_custom_get_auth_page_url() );
				exit;
			}
		}

		/* Admin page — admins only */
		if ( has_block( self::BLOCK_ADMIN, $post ) ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( bs_custom_get_auth_page_url() );
				exit;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_safe_redirect( ct_get_language_home_url() );
				exit;
			}
		}
	}
}
