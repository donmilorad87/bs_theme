<?php
/**
 * Template Name: Login Register
 *
 * Auth page with Sign In / Register tabs and password reset flows.
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

/* Redirect logged-in users to profile page */
if ( is_user_logged_in() ) {
	$bs_profile_url = bs_custom_get_profile_page_url();
	if ( $bs_profile_url ) {
		wp_safe_redirect( $bs_profile_url );
		exit;
	}
}

get_header();

$bs_rest_url      = esc_attr( rest_url( 'ct-auth/v1/' ) );
$bs_nonce         = esc_attr( wp_create_nonce( 'wp_rest' ) );
$bs_cache_version = esc_attr( wp_get_theme()->get( 'Version' ) );
$bs_home_url      = esc_attr( bs_get_language_home_url() );
$bs_email_enabled = function_exists( 'bs_email_enabled' ) ? bs_email_enabled() : true;
?>

<section class="ct-auth-page df aic jcc">
	<div class="ct-auth-card"
	     data-rest-url="<?php echo $bs_rest_url; ?>"
	     data-nonce="<?php echo $bs_nonce; ?>"
	     data-cache-version="<?php echo $bs_cache_version; ?>"
	     data-home-url="<?php echo $bs_home_url; ?>"
	     data-email-enabled="<?php echo esc_attr( $bs_email_enabled ? '1' : '0' ); ?>">

		<!-- Tab navigation -->
		<div class="ct-auth-card__tabs df mb24" role="tablist">
			<button type="button" class="ct-auth-card__tab ct-auth-card__tab--active fs14" data-ct-auth-tab="login" role="tab" aria-selected="true">
				<?php esc_html_e( 'Sign In', 'ct-custom' ); ?>
			</button>
			<button type="button" class="ct-auth-card__tab fs14" data-ct-auth-tab="register" role="tab" aria-selected="false">
				<?php esc_html_e( 'Register', 'ct-custom' ); ?>
			</button>
		</div>

		<!-- Back bar (hidden by default) -->
		<div class="ct-auth-card__back-bar dn mb16" data-ct-auth-back-bar>
			<button type="button" class="ct-auth-card__back-btn df aic" data-ct-auth-action="back-to-login">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
				<span><?php esc_html_e( 'Return to Sign In', 'ct-custom' ); ?></span>
			</button>
		</div>

		<!-- Form panels -->
		<div class="ct-auth-card__panel ct-auth-card__panel--active" data-ct-auth-form="login">
			<?php get_template_part( 'template-parts/auth/login' ); ?>
		</div>

		<div class="ct-auth-card__panel dn" data-ct-auth-form="register">
			<?php get_template_part( 'template-parts/auth/register' ); ?>
		</div>

		<?php if ( $bs_email_enabled ) : ?>
			<div class="ct-auth-card__panel dn" data-ct-auth-form="forgot-password">
				<?php get_template_part( 'template-parts/auth/forgot-password' ); ?>
			</div>

			<div class="ct-auth-card__panel dn" data-ct-auth-form="activation-code">
				<?php get_template_part( 'template-parts/auth/activation-code' ); ?>
			</div>

			<div class="ct-auth-card__panel dn" data-ct-auth-form="reset-code">
				<?php get_template_part( 'template-parts/auth/reset-code' ); ?>
			</div>

			<div class="ct-auth-card__panel dn" data-ct-auth-form="reset-password">
				<?php get_template_part( 'template-parts/auth/reset-password' ); ?>
			</div>
		<?php endif; ?>

	</div>
</section>

<?php
get_footer();
