<?php
/**
 * Auth Forms — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to BS_Auth_Forms.
 * All hook registrations have moved to BS_Template_Hooks::register_hooks().
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
 * Check if user management (login/register/profile) is enabled.
 *
 * @return bool
 */
function bs_user_management_enabled(): bool {
	$enabled = get_theme_mod( 'bs_user_management_enabled', true );

	if ( is_string( $enabled ) ) {
		$normalized = strtolower( $enabled );
		if ( '0' === $enabled || 'off' === $normalized || 'false' === $normalized ) {
			return false;
		}
	}

	return ! empty( $enabled );
}

/**
 * Check if email features are enabled.
 *
 * @return bool
 */
function bs_email_enabled(): bool {
	$enabled = get_option( 'bs_email_enabled', 'on' );

	if ( is_string( $enabled ) ) {
		$normalized = strtolower( $enabled );
		if ( '0' === $enabled || 'off' === $normalized || 'false' === $normalized || 'no' === $normalized ) {
			return false;
		}
	}

	return ! empty( $enabled );
}

/**
 * Render the auth links (login/register or profile/logout).
 *
 * @param string $extra_classes Extra classes for the wrapper.
 * @return void
 */
function bs_custom_render_auth_links( $extra_classes = '' ) {
	if ( ! bs_user_management_enabled() ) {
		return;
	}

	$auth_data   = bs_custom_get_auth_data();
	$is_logged_in = ! empty( $auth_data['is_logged_in'] );
	$display_name = isset( $auth_data['display_name'] ) ? $auth_data['display_name'] : '';

	$class = 'ct-auth-links df aic';
	if ( is_string( $extra_classes ) && '' !== trim( $extra_classes ) ) {
		$class .= ' ' . trim( $extra_classes );
	}
	?>
	<div class="<?php echo esc_attr( $class ); ?>"
	     data-rest-url="<?php echo esc_attr( rest_url( 'ct-auth/v1/' ) ); ?>"
	     data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
	     data-cache-version="<?php echo esc_attr( wp_get_theme()->get( 'Version' ) ); ?>">
		<?php if ( $is_logged_in ) : ?>
			<span class="ct-auth-links__greeting"><?php echo esc_html( $display_name ); ?></span>
			<a href="<?php echo esc_url( bs_custom_get_profile_page_url() ); ?>" class="ct-auth-links__link ct-auth-links__link--profile"><?php esc_html_e( 'Profile', 'ct-custom' ); ?></a>
			<span class="ct-auth-links__separator">/</span>
			<a href="#" class="ct-auth-links__link ct-auth-links__link--logout"
			   data-ct-auth-action="logout"><?php esc_html_e( 'Log Out', 'ct-custom' ); ?></a>
		<?php else : ?>
			<a href="<?php echo esc_url( bs_custom_get_auth_page_url() ); ?>" class="ct-auth-links__link ct-auth-links__link--login"><?php esc_html_e( 'Login', 'ct-custom' ); ?></a>
			<span class="ct-auth-links__separator">/</span>
			<a href="<?php echo esc_url( bs_custom_get_auth_page_url() . '#register' ); ?>" class="ct-auth-links__link ct-auth-links__link--register"><?php esc_html_e( 'Sign Up', 'ct-custom' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * REST API permission callback: accepts JWT or cookie auth.
 *
 * @param WP_REST_Request $request The REST request.
 * @return bool|WP_Error
 */
function bs_jwt_or_cookie_permission_check( WP_REST_Request $request ) {
	return \BSCustom\Services\JwtAuth::jwt_or_cookie_permission_check( $request );
}

/**
 * REST API permission callback: JWT auth only.
 *
 * @param WP_REST_Request $request The REST request.
 * @return bool|WP_Error
 */
function bs_jwt_permission_check( WP_REST_Request $request ) {
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
		$current_lang = get_post_meta( $queried->ID, 'bs_language', true );
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
			'key'   => 'bs_language',
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
