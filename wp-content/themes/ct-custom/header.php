<?php
/**
 * The header for our theme
 *
 * @package BS_Custom
 */

extract( bs_custom_get_header_data() );
extract( bs_custom_get_auth_data() );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    $ct_meta_description = get_bloginfo( 'description', 'display' );
    if ( ! empty( $ct_meta_description ) ) :
    ?>
    <meta name="description" content="<?php echo esc_attr( $ct_meta_description ); ?>">
    <?php endif; ?>
    
    <script>
        (function(){
            var b=document.body;
            if(b.getAttribute('data-theme'))return;
            var c=document.cookie.match(/(?:^|;\s*)ct_theme=(light|dark)/);
            var t=c?c[1]:(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
            b.setAttribute('data-theme',t);
            if(!c){document.cookie='ct_theme='+t+';path=/;max-age=31536000;SameSite=Lax';}
        })();
    </script>

    <?php
    /* Preload main stylesheet — browser starts download before wp_head parsing */
    $ct_css_path = get_template_directory() . '/assets/frontend/css/app.css';
    $ct_css_ver  = ct_get_asset_version( $ct_css_path );
    ?>
    <link rel="preload" href="<?php echo esc_url( get_template_directory_uri() . '/assets/frontend/css/app.css?ver=' . $ct_css_ver ); ?>" as="style">
    <?php
    $ct_js_path = get_template_directory() . '/assets/frontend/js/app.js';
    $ct_js_ver  = ct_get_asset_version( $ct_js_path );
    ?>
    <link rel="preload" href="<?php echo esc_url( get_template_directory_uri() . '/assets/frontend/js/app.js?ver=' . $ct_js_ver ); ?>">

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?><?php if ( $ct_data_theme ) { echo ' data-theme="' . esc_attr( $ct_data_theme ) . '"'; } ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">

    <div class="ct-site-header-wrap">

    <!-- Top Bar -->
    <div class="topbar" role="banner">
        <div class="topbar__container df jcsb aic">
            <div class="topbar__left df aic">
                <?php if ( ! empty( $topbar_text1 ) ) : ?>
                    <span class="topbar__text1"><?php echo esc_html( $topbar_text1 ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $topbar_phone ) ) : ?>
                    <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+.]/', '', $topbar_phone ) ); ?>" class="topbar__phone-link topbar__text2">
                        <?php echo esc_html( $topbar_phone ); ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="topbar__right df aic">
                <?php
                wp_nav_menu( array(
                    'theme_location' => ct_get_menu_location( 'top-bar-menu' ),
                    'depth'          => 1,
                    'container'      => false,
                    'fallback_cb'    => false,
                ) );
                ?>
                <div class="ct-auth-links df aic"
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
                if ( function_exists( 'ct_is_multilingual' ) && ct_is_multilingual() ) {
                    $ct_switcher_data = bs_custom_get_language_switcher_data();
                    include get_template_directory() . '/template-parts/language-switcher.php';
                }
                ?>
                <button type="button" class="theme-toggle dif aic cp p0" role="switch" aria-checked="false" aria-label="<?php esc_attr_e( 'Toggle dark/light mode', 'ct-custom' ); ?>">
                    <span class="theme-toggle__track df aic jcsb">
                        <svg class="theme-toggle__icon theme-toggle__icon--sun" xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><circle cx="12" cy="12" r="5"/><g stroke="currentColor" stroke-width="2" fill="none"><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></g></svg>
                        <svg class="theme-toggle__icon theme-toggle__icon--moon" xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        <span class="theme-toggle__thumb"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header id="masthead" class="site-header" role="banner">
        <div class="site-header__container df jcsb aic">
            <div class="site-header__logo">
                <a href="<?php echo esc_url( ct_get_language_home_url() ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                    <?php if ( $logo_id ) : ?>
                        <?php echo bs_custom_get_attachment_image( $logo_id, 'full', array(
                            'alt'     => esc_attr( get_bloginfo( 'name' ) ),
                            'loading' => 'lazy',
                        ) ); ?>
                    <?php else : ?>
                        <span class="site-title-text"><?php bloginfo( 'name' ); ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <nav id="site-navigation" class="site-header__nav main-navigation df aic" role="navigation" aria-label="<?php esc_attr_e( 'Main Menu', 'ct-custom' ); ?>">
                <button class="menu-toggle cp fs14" aria-controls="primary-menu" aria-expanded="false">
                    <span class="screen-reader-text"><?php esc_html_e( 'Menu', 'ct-custom' ); ?></span>
                    &#9776;
                </button>
                <?php
                wp_nav_menu( array(
                    'theme_location' => ct_get_menu_location( 'main-menu' ),
                    'menu_id'        => 'primary-menu',
                    'container'      => false,
                    'fallback_cb'    => false,
                ) );
                ?>
            </nav>
        </div>
    </header>


    </div><!-- .ct-site-header-wrap -->

    <!-- Breadcrumbs (hidden on front page) -->
    <?php if ( ! is_front_page() ) : ?>
        <div class="breadcrumbs" role="navigation" aria-label="<?php esc_attr_e( 'Breadcrumbs', 'ct-custom' ); ?>">
            <?php bs_custom_breadcrumbs(); ?>
        </div>
    <?php endif; ?>

    <div id="content" class="site-content">
        <div class="<?php echo esc_attr( ct_sidebar_layout_classes() ); ?>">
            <?php ct_sidebar_render( 'left' ); ?>
            <div class="ct-layout__content">
