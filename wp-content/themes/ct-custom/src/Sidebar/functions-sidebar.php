<?php
/**
 * Sidebar global wrapper functions.
 *
 * Delegates every call to the namespaced singleton so that
 * existing theme templates continue to work without changes.
 *
 * @package BS_Custom
 */

use BSCustom\Sidebar\SidebarRenderer;

/**
 * Check whether a ct-custom/sidebar-content block exists for the given position.
 *
 * @param string $position 'left' or 'right'.
 * @return bool
 */
function bs_sidebar_has_content_block( $position ) {
	return SidebarRenderer::instance()->hasContentBlock( $position );
}

/**
 * Resolve the post ID for sidebar meta lookup.
 *
 * @return int Post ID or 0.
 */
function bs_sidebar_resolve_post_id() {
	return SidebarRenderer::instance()->resolvePostId();
}

/**
 * Get the sidebar mode for a position on the current post.
 *
 * @param string $position 'left' or 'right'.
 * @return string 'off', 'global', or 'custom'.
 */
function bs_sidebar_get_mode( $position ) {
	return SidebarRenderer::instance()->getMode( $position );
}

/**
 * Check whether a sidebar is active for a given position.
 *
 * @param string $position 'left' or 'right'.
 * @return bool
 */
function bs_sidebar_has( $position ) {
	return SidebarRenderer::instance()->has( $position );
}

/**
 * Extract rendered content from ct-custom/sidebar-content blocks.
 *
 * @param string $position 'left' or 'right'.
 * @return string Rendered HTML or empty string.
 */
function bs_sidebar_extract_content( $position ) {
	return SidebarRenderer::instance()->extractContent( $position );
}

/**
 * Render a sidebar for the given position.
 *
 * @param string $position 'left' or 'right'.
 * @return void
 */
function bs_sidebar_render( $position ) {
	SidebarRenderer::instance()->render( $position );
}

/**
 * Build CSS classes for the main content layout container.
 *
 * @return string Space-separated class names.
 */
function bs_sidebar_layout_classes() {
	return SidebarRenderer::instance()->layoutClasses();
}
