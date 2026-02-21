<?php
/**
 * The header for our theme
 *
 * @package BS_Custom
 */

extract( bs_custom_get_header_data() );
$bs_lang_switcher_position = get_theme_mod( 'bs_lang_switcher_position', 'top_header' );
$bs_lang_switcher_position = is_string( $bs_lang_switcher_position ) ? $bs_lang_switcher_position : 'top_header';
$bs_auth_links_position = get_theme_mod( 'bs_auth_links_position', 'top_header' );
$bs_auth_links_position = is_string( $bs_auth_links_position ) ? $bs_auth_links_position : 'top_header';
$bs_auth_links_enabled  = function_exists( 'bs_user_management_enabled' ) && bs_user_management_enabled();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    /* Meta description now output by SeoService via wp_head */
    ?><?php
    /* Preload main stylesheet — browser starts download before wp_head parsing */
    $bs_css_path = get_template_directory() . '/assets/frontend/css/app.css';
    $bs_css_ver  = bs_get_asset_version( $bs_css_path );
    ?>
    <link rel="preload" href="<?php echo esc_url( get_template_directory_uri() . '/assets/frontend/css/app.css?ver=' . $bs_css_ver ); ?>" as="style">
    <?php
    $bs_js_path = get_template_directory() . '/assets/frontend/js/app.js';
    $bs_js_ver  = bs_get_asset_version( $bs_js_path );
    ?>
    <link rel="preload" href="<?php echo esc_url( get_template_directory_uri() . '/assets/frontend/js/app.js?ver=' . $bs_js_ver ); ?>">

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?> data-theme="<?php echo esc_attr( $bs_data_theme ); ?>" data-theme-toggle="<?php echo ! empty( $bs_theme_toggle_enabled ) ? 'on' : 'off'; ?>">
<?php wp_body_open(); ?>
<div id="page" class="site">

    <div class="ct-site-header-wrap">

    <?php $bs_topbar_enabled = get_theme_mod( 'bs_topbar_enabled', true ); ?>
    <?php if ( ! empty( $bs_topbar_enabled ) ) : ?>
    <!-- Top Bar -->
    <div class="topbar" role="banner">
        <div class="topbar__container df jcsb aic">
            <div class="topbar__left df aic">
                <?php if ( ! empty( $topbar_text1 ) ) : ?>
                    <span class="topbar__text1"><?php echo esc_html( $topbar_text1 ); ?></span>
                <?php endif; ?>
                <a href="<?php echo ! empty( $topbar_phone ) ? esc_attr( 'tel:' . preg_replace( '/[^0-9+.]/', '', $topbar_phone ) ) : ''; ?>"
                   class="topbar__phone-link topbar__text2"
                   <?php if ( empty( $topbar_phone ) ) : ?>style="display:none"<?php endif; ?>>
                    <?php echo esc_html( $topbar_phone ); ?>
                </a>
            </div>
            <div class="topbar__right df aic">
                <?php
                wp_nav_menu( array(
                    'theme_location' => bs_get_menu_location( 'top-bar-menu' ),
                    'depth'          => 1,
                    'container'      => false,
                    'menu_class'     => 'menu df aic m0 p0',
                    'fallback_cb'    => false,
                ) );
                ?>
                <?php if ( $bs_auth_links_enabled && 'top_header' === $bs_auth_links_position ) : ?>
                    <?php bs_custom_render_auth_links(); ?>
                <?php endif; ?>
                <?php if ( function_exists( 'bs_is_multilingual' ) && bs_is_multilingual() && 'top_header' === $bs_lang_switcher_position ) : ?>
                    <?php bs_custom_render_language_switcher(); ?>
                <?php endif; ?>
                <?php if ( ! empty( $bs_theme_toggle_enabled ) && 'top_header' === $bs_theme_toggle_position ) : ?>
                    <?php bs_custom_render_theme_toggle(); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <header id="masthead" class="site-header" role="banner">
        <div class="site-header__container df jcsb aic">
            <div class="site-header__logo">
                <a href="<?php echo esc_url( bs_get_language_home_url() ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
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
                    'theme_location' => bs_get_menu_location( 'main-menu' ),
                    'menu_id'        => 'primary-menu',
                    'container'      => false,
                    'menu_class'     => 'menu df aic m0 p0',
                    'fallback_cb'    => false,
                ) );
                ?>
                <?php if ( $bs_auth_links_enabled && 'header' === $bs_auth_links_position ) : ?>
                    <?php bs_custom_render_auth_links(); ?>
                <?php endif; ?>
                <?php if ( function_exists( 'bs_is_multilingual' ) && bs_is_multilingual() && 'header' === $bs_lang_switcher_position ) : ?>
                    <?php bs_custom_render_language_switcher(); ?>
                <?php endif; ?>
                <?php if ( ! empty( $bs_theme_toggle_enabled ) && 'header' === $bs_theme_toggle_position ) : ?>
                    <?php bs_custom_render_theme_toggle(); ?>
                <?php endif; ?>
            </nav>
        </div>
    </header>


    </div><!-- .ct-site-header-wrap -->

    <!-- Breadcrumbs (hidden on front page) -->
    <?php
    $bs_breadcrumbs_enabled = get_option( 'bs_seo_global_breadcrumb_enabled', '' );
    $bs_breadcrumbs_enabled = ( ! empty( $bs_breadcrumbs_enabled ) && 'off' !== $bs_breadcrumbs_enabled && '0' !== $bs_breadcrumbs_enabled );
    ?>
    <?php if ( $bs_breadcrumbs_enabled && ! is_front_page() ) : ?>
        <div class="breadcrumbs" role="navigation" aria-label="<?php esc_attr_e( 'Breadcrumbs', 'ct-custom' ); ?>">
            <?php bs_seo_breadcrumbs(); ?>
        </div>
    <?php endif; ?>

    <div id="content" class="site-content">
        <div class="<?php echo esc_attr( bs_sidebar_layout_classes() ); ?>">
            <?php bs_sidebar_render( 'left' ); ?>
            <div class="ct-layout__content">
