<?php
/**
 * SEO Global Wrapper Functions
 *
 * These functions cannot be autoloaded (not in a class).
 * Required from functions.php after the autoloader.
 *
 * @package BS_Custom
 */

use BSCustom\Seo\Breadcrumbs;
use BSCustom\Seo\SeoMetaHelper;

/**
 * Render breadcrumbs with microdata HTML.
 *
 * Drop-in replacement for bs_custom_breadcrumbs().
 *
 * @return void
 */
function bs_seo_breadcrumbs() {
	assert( class_exists( Breadcrumbs::class ), 'Breadcrumbs class must exist' );

	Breadcrumbs::instance()->render();
}

/**
 * Get breadcrumb items array.
 *
 * @return array List of breadcrumb items.
 */
function bs_seo_get_breadcrumb_items() {
	return Breadcrumbs::instance()->get_items();
}

/**
 * Get an SEO meta value with priority cascade.
 *
 * @param int    $post_id Post ID (0 for global only).
 * @param string $field   Field name without prefix.
 * @return string Resolved value.
 */
function bs_seo_get( $post_id, $field ) {
	return SeoMetaHelper::get( $post_id, $field );
}
