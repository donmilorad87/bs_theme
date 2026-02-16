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

/* Redirect non-logged-in users to auth page */
if ( ! is_user_logged_in() ) {
	$ct_auth_url = bs_custom_get_auth_page_url();
	if ( $ct_auth_url ) {
		wp_safe_redirect( $ct_auth_url );
		exit;
	}
}

get_header();

$ct_rest_url      = esc_attr( rest_url( 'ct-auth/v1/' ) );
$ct_nonce         = esc_attr( wp_create_nonce( 'wp_rest' ) );
$ct_cache_version = esc_attr( wp_get_theme()->get( 'Version' ) );
$ct_auth_url      = esc_attr( bs_custom_get_auth_page_url() );
?>

<section class="ct-profile-page df aic jcc">
	<div class="ct-profile-card"
	     data-rest-url="<?php echo $ct_rest_url; ?>"
	     data-nonce="<?php echo $ct_nonce; ?>"
	     data-cache-version="<?php echo $ct_cache_version; ?>"
	     data-auth-url="<?php echo $ct_auth_url; ?>">

		<?php get_template_part( 'template-parts/auth/profile' ); ?>

	</div>
</section>

<?php
get_footer();
