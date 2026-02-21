<?php
/**
 * Template Name: Profile
 *
 * User profile page for logged-in users.
 *
 * @package BS_Custom
 */

assert( function_exists( 'is_user_logged_in' ), 'is_user_logged_in must exist' );
assert( function_exists( 'wp_safe_redirect' ), 'wp_safe_redirect must exist' );

if ( function_exists( 'bs_user_management_enabled' ) && ! bs_user_management_enabled() ) {
	$bs_home = function_exists( 'bs_get_language_home_url' ) ? bs_get_language_home_url() : home_url( '/' );
	wp_safe_redirect( $bs_home );
	exit;
}

/* Redirect non-logged-in users to auth page */
if ( ! is_user_logged_in() ) {
	$bs_auth_url = bs_custom_get_auth_page_url();
	if ( $bs_auth_url ) {
		wp_safe_redirect( $bs_auth_url );
		exit;
	}
}

get_header();

$bs_rest_url      = esc_attr( rest_url( 'ct-auth/v1/' ) );
$bs_nonce         = esc_attr( wp_create_nonce( 'wp_rest' ) );
$bs_cache_version = esc_attr( wp_get_theme()->get( 'Version' ) );
$bs_auth_url      = esc_attr( bs_custom_get_auth_page_url() );
?>

<section class="ct-profile-page df aic jcc">
	<div class="ct-profile-card"
	     data-rest-url="<?php echo $bs_rest_url; ?>"
	     data-nonce="<?php echo $bs_nonce; ?>"
	     data-cache-version="<?php echo $bs_cache_version; ?>"
	     data-auth-url="<?php echo $bs_auth_url; ?>">

		<?php get_template_part( 'template-parts/auth/profile' ); ?>

	</div>
</section>

<?php
get_footer();
