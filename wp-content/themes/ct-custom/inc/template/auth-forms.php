<?php
/**
 * Auth Forms — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to CT_Auth_Forms.
 * All hook registrations have moved to CT_Template_Hooks::register_hooks().
 *
 * @package BS_Custom
 */

use BSCustom\Template\AuthForms;

/**
 * Get the current authentication state data.
 *
 * @return array{is_logged_in: bool, display_name: string}
 */
function bs_custom_get_auth_data() {
	return AuthForms::instance()->get_auth_data();
}

/**
 * REST API permission callback: accepts JWT or cookie auth.
 *
 * @param WP_REST_Request $request The REST request.
 * @return bool|WP_Error
 */
function ct_jwt_or_cookie_permission_check( WP_REST_Request $request ) {
	return \BSCustom\Services\JwtAuth::jwt_or_cookie_permission_check( $request );
}

/**
 * REST API permission callback: JWT auth only.
 *
 * @param WP_REST_Request $request The REST request.
 * @return bool|WP_Error
 */
function ct_jwt_permission_check( WP_REST_Request $request ) {
	return \BSCustom\Services\JwtAuth::jwt_permission_check( $request );
}

/**
 * Find a page by its template, optionally filtered by language.
 *
 * Checks the current page's language first to return the
 * correct translation. Falls back to any page with the template.
 *
 * @param string $template Template filename (e.g. 'login-register.php').
 * @return string Page permalink or empty string if not found.
 */
function bs_custom_get_page_url_by_template( string $template ): string {
	assert( ! empty( $template ), 'template must not be empty' );
	assert( function_exists( 'get_posts' ), 'get_posts must exist' );

	$current_lang = '';
	$queried      = get_queried_object();

	if ( $queried instanceof WP_Post ) {
		$current_lang = get_post_meta( $queried->ID, 'ct_language', true );
	}

	/* Build meta query: template match + optional language filter */
	$meta_query = array(
		array(
			'key'   => '_wp_page_template',
			'value' => $template,
		),
	);

	if ( '' !== $current_lang ) {
		$meta_query[] = array(
			'key'   => 'ct_language',
			'value' => $current_lang,
		);
	}

	$pages = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => $meta_query,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	if ( ! empty( $pages ) ) {
		return get_permalink( $pages[0]->ID );
	}

	/* Fallback: any page with this template regardless of language */
	if ( '' !== $current_lang ) {
		$fallback = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_wp_page_template',
			'meta_value'     => $template,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		if ( ! empty( $fallback ) ) {
			return get_permalink( $fallback[0]->ID );
		}
	}

	return '';
}

/**
 * Get the URL of the page using the "Login Register" template.
 *
 * @return string Page permalink or empty string if not found.
 */
function bs_custom_get_auth_page_url() {
	return bs_custom_get_page_url_by_template( 'login-register.php' );
}

/**
 * Get the URL of the page using the "Profile" template.
 *
 * @return string Page permalink or empty string if not found.
 */
function bs_custom_get_profile_page_url() {
	return bs_custom_get_page_url_by_template( 'profile.php' );
}
