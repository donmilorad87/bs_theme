<?php
/**
 * Tests for the PageAccessControl redirect logic.
 *
 * Covers: unprotected pages (guests vs logged-in), protected pages,
 * admin pages (guests, non-admins, admins), and non-singular bypass.
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

use BSCustom\Blocks\PageAccessControl;

class PageAccessControlTest extends AuthTestCase {

    private PageAccessControl $pac;

    protected function setUp(): void {
        parent::setUp();
        $this->pac = new PageAccessControl();

        /* Enable the throw-on-redirect flag so exit is never reached. */
        $GLOBALS['bs_test_options']['_throw_on_redirect'] = true;

        /* Default to singular page context. */
        $GLOBALS['bs_test_options']['_is_singular'] = true;
    }

    protected function tearDown(): void {
        unset( $GLOBALS['bs_test_options']['_throw_on_redirect'] );
        parent::tearDown();
    }

    /* ── helpers ──────────────────────────────────────────────────── */

    /**
     * Create a WP_Post stub with the given block marker.
     *
     * @param string $block_name Full block name (e.g. 'ct-custom/unprotected-page').
     * @return \WP_Post
     */
    private function makePageWithBlock( string $block_name ): \WP_Post {
        return new \WP_Post( array(
            'ID'           => 1,
            'post_content' => '<!-- wp:' . $block_name . ' -->',
            'post_title'   => 'Test Page',
            'post_type'    => 'page',
        ) );
    }

    /**
     * Set the queried object used by get_queried_object().
     *
     * @param \WP_Post $post Post object.
     */
    private function setQueriedObject( \WP_Post $post ): void {
        $GLOBALS['bs_test_options']['_queried_object'] = $post;
    }

    /**
     * Get the last redirect URL, if any.
     *
     * @return string|null
     */
    private function getLastRedirect(): ?string {
        return $GLOBALS['bs_test_options']['_last_redirect'] ?? null;
    }

    /**
     * Call handle_redirect and capture a possible redirect exception.
     * Returns the redirect URL or null when no redirect occurred.
     *
     * @return string|null
     */
    private function callHandleRedirect(): ?string {
        try {
            $this->pac->handle_redirect();
        } catch ( \RuntimeException $e ) {
            /* The stub threw because a redirect happened. */
        }

        return $this->getLastRedirect();
    }

    /* ── tests ───────────────────────────────────────────────────── */

    public function test_unprotected_page_redirects_logged_in_to_profile(): void {
        $user = $this->registerUser( 'logged', 'logged@example.com', 'Test1234!' );
        $this->loginAs( $user->ID );

        $post = $this->makePageWithBlock( 'ct-custom/unprotected-page' );
        $this->setQueriedObject( $post );

        $redirect = $this->callHandleRedirect();

        $this->assertNotNull( $redirect );
        $this->assertSame( 'https://example.com/profile/', $redirect );
    }

    public function test_unprotected_page_allows_guests(): void {
        /* No user logged in (default state). */
        $post = $this->makePageWithBlock( 'ct-custom/unprotected-page' );
        $this->setQueriedObject( $post );

        $redirect = $this->callHandleRedirect();

        $this->assertNull( $redirect );
    }

    public function test_protected_page_redirects_guests_to_login(): void {
        $post = $this->makePageWithBlock( 'ct-custom/protected-page' );
        $this->setQueriedObject( $post );

        $redirect = $this->callHandleRedirect();

        $this->assertNotNull( $redirect );
        $this->assertSame( 'https://example.com/login-register/', $redirect );
    }

    public function test_protected_page_allows_logged_in(): void {
        $user = $this->registerUser( 'protuser', 'prot@example.com', 'Test1234!' );
        $this->loginAs( $user->ID );

        $post = $this->makePageWithBlock( 'ct-custom/protected-page' );
        $this->setQueriedObject( $post );

        $redirect = $this->callHandleRedirect();

        $this->assertNull( $redirect );
    }

    public function test_admin_page_redirects_guests(): void {
        $post = $this->makePageWithBlock( 'ct-custom/admin-page' );
        $this->setQueriedObject( $post );

        $redirect = $this->callHandleRedirect();

        $this->assertNotNull( $redirect );
        $this->assertSame( 'https://example.com/login-register/', $redirect );
    }

    public function test_admin_page_redirects_non_admin(): void {
        $user = $this->registerUser( 'subscriber', 'sub@example.com', 'Test1234!', true, 'subscriber' );
        $this->loginAs( $user->ID );

        $post = $this->makePageWithBlock( 'ct-custom/admin-page' );
        $this->setQueriedObject( $post );

        $redirect = $this->callHandleRedirect();

        $this->assertNotNull( $redirect );
        $this->assertSame( 'https://example.com/', $redirect );
    }

    public function test_admin_page_allows_admin(): void {
        $admin = $this->registerUser( 'adminuser', 'admin@example.com', 'Test1234!', true, 'administrator' );
        $this->loginAs( $admin->ID );

        $post = $this->makePageWithBlock( 'ct-custom/admin-page' );
        $this->setQueriedObject( $post );

        $redirect = $this->callHandleRedirect();

        $this->assertNull( $redirect );
    }

    public function test_non_singular_skips(): void {
        $GLOBALS['bs_test_options']['_is_singular'] = false;

        $post = $this->makePageWithBlock( 'ct-custom/protected-page' );
        $this->setQueriedObject( $post );

        $redirect = $this->callHandleRedirect();

        $this->assertNull( $redirect );
    }
}
