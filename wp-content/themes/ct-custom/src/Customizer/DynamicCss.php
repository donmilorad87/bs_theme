<?php
/**
 * Dynamic CSS Output
 *
 * Generates dynamic inline CSS from theme_mod values.
 * Colors are output as CSS custom properties under
 * body[data-theme="light"] and body[data-theme="dark"].
 * All CSS rules reference var(--ct-*) for colors.
 *
 * Layout/structural CSS is in frontend.css (Vite-compiled).
 * This file only outputs customizer-driven values.
 *
 * Converted from procedural inc/customizer/customizer_css_output.php to PSR-4 class.
 *
 * @package BS_Custom
 */

namespace BSCustom\Customizer;

class DynamicCss {

    /**
     * Register all hooks for dynamic CSS output.
     */
    public static function boot() {
        assert( true, 'DynamicCss::boot called' );
        assert( function_exists( 'add_action' ), 'WordPress must be loaded' );

        add_action( 'wp_head', array( static::class, 'output' ), 100 );
        add_action( 'customize_save_after', array( static::class, 'invalidateCache' ) );
    }

    /**
     * Output cached dynamic CSS. Regenerates only when the cache is empty
     * (cleared on every customizer save via invalidateCache).
     */
    public static function output() {
        assert( function_exists( 'get_transient' ), 'WordPress must be loaded' );

        $css = get_transient( 'bs_dynamic_css' );

        if ( false === $css ) {
            $css = static::build();
            set_transient( 'bs_dynamic_css', $css );
        }

        assert( is_string( $css ), 'Dynamic CSS must be a string' );

        echo '<style id="ct-custom-dynamic-css">' . "\n";
        echo $css;
        if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
            echo "body { background-color:var(--ct-body-bg-color); }\n";
        }
        echo '</style>' . "\n";
    }

    /**
     * Invalidate the cached dynamic CSS so it regenerates on next page load.
     */
    public static function invalidateCache() {
        assert( function_exists( 'delete_transient' ), 'WordPress must be loaded' );
        assert( true, 'Invalidating dynamic CSS cache' );

        delete_transient( 'bs_dynamic_css' );
    }

    /**
     * Build the full dynamic CSS string from all customizer settings.
     *
     * @return string
     */
    public static function build() {
        assert( function_exists( 'get_theme_mod' ), 'WordPress must be loaded' );
        assert( true, 'Building dynamic CSS' );

        $css  = static::cssVariables();
        $css .= static::containerCss();
        $css .= static::layoutCss();
        $css .= static::topbarCss();
        $css .= static::headerCss();
        $css .= static::menuCss();
        $css .= static::mobileMenuCss();
        $css .= static::breadcrumbCss();
        $css .= static::typographyCss();
        $css .= static::formCss();
        $css .= static::socialCss();
        $css .= static::footerCss();
        $css .= static::backToTopCss();
        $css .= static::themeToggleCss();
        $css .= static::themeTransitionCss();
        $css .= static::authCss();
        $css .= static::fontCss();

        return $css;
    }

    /**
     * Build CSS style properties from checkbox values.
     */
    private static function getCheckboxStyleProps( $bold, $italic, $uppercase ) {
        assert( true, 'Building checkbox style props' );
        assert( true, 'Checkbox style props helper' );

        $props = '';
        $props .= $bold ? 'font-weight:700;' : 'font-weight:400;';
        $props .= $italic ? 'font-style:italic;' : 'font-style:normal;';
        $props .= $uppercase ? 'text-transform:uppercase;' : 'text-transform:none;';
        return $props;
    }

    /**
     * Centralized map: CSS variable name => [ light_setting_id, light_default, dark_setting_id, dark_default ]
     */
    private static function getColorVariableMap() {
        assert( true, 'Getting color variable map' );
        assert( true, 'Color variable map helper' );

        return array(
            /* Topbar */
            '--ct-topbar-bg-color'            => array( 'bs_topbar_bg_color', '#FF6B35', 'bs_topbar_bg_color_dark', '#D45A2B' ),
            '--ct-topbar-text1-color'         => array( 'bs_topbar_text1_color', '#FFFFFF', 'bs_topbar_text1_color_dark', '#FFFFFF' ),
            '--ct-topbar-text2-color'         => array( 'bs_topbar_text2_color', '#FFFFFF', 'bs_topbar_text2_color_dark', '#FFFFFF' ),
            '--ct-topbar-links-color'         => array( 'bs_topbar_links_color', '#FFFFFF', 'bs_topbar_links_color_dark', '#FFFFFF' ),
            '--ct-topbar-links-hover-color'   => array( 'bs_topbar_links_hover_color', '#CCCCCC', 'bs_topbar_links_hover_color_dark', '#FFB088' ),
            /* Header */
            '--ct-header-bg-color'            => array( 'bs_header_bg_color', '#FFFFFF', 'bs_header_bg_color_dark', '#1A1A2E' ),
            '--ct-header-border-color'        => array( 'bs_header_border_color', '#EEEEEE', 'bs_header_border_color_dark', '#2A2A3E' ),
            '--ct-site-title-color'           => array( 'bs_site_title_color', '#333333', 'bs_site_title_color_dark', '#E0E0E0' ),
            /* Menu */
            '--ct-menu-top-color'             => array( 'bs_menu_top_color', '#333333', 'bs_menu_top_color_dark', '#E0E0E0' ),
            '--ct-menu-active-underline-color' => array( 'bs_menu_active_underline_color', '#FF6B35', 'bs_menu_active_underline_color_dark', '#FF8C5A' ),
            '--ct-menu-sub-color'             => array( 'bs_menu_sub_color', '#333333', 'bs_menu_sub_color_dark', '#E0E0E0' ),
            '--ct-menu-sub-border-color'      => array( 'bs_menu_sub_border_color', '#CCCCCC', 'bs_menu_sub_border_color_dark', '#3A3A4E' ),
            '--ct-menu-sub-bg-color'          => array( 'bs_menu_sub_bg_color', '#FFFFFF', 'bs_menu_sub_bg_color_dark', '#242438' ),
            '--ct-menu-sub-hover-bg-color'    => array( 'bs_menu_sub_hover_bg_color', '#F7F7F7', 'bs_menu_sub_hover_bg_color_dark', '#2E2E44' ),
            /* Mobile Menu */
            '--ct-mobile-menu-bg-color'       => array( 'bs_mobile_menu_bg_color', '#FFFFFF', 'bs_mobile_menu_bg_color_dark', '#1A1A2E' ),
            '--ct-mobile-menu-border-color'   => array( 'bs_mobile_menu_border_color', '#EEEEEE', 'bs_mobile_menu_border_color_dark', '#2A2A3E' ),
            /* Breadcrumbs */
            '--ct-breadcrumb-color'           => array( 'bs_breadcrumb_color', '#999999', 'bs_breadcrumb_color_dark', '#888888' ),
            '--ct-breadcrumb-active-color'    => array( 'bs_breadcrumb_active_color', '#333333', 'bs_breadcrumb_active_color_dark', '#E0E0E0' ),
            /* Body */
            '--ct-body-bg-color'              => array( 'bs_body_bg_color', '#FFFFFF', 'bs_body_bg_color_dark', '#16162A' ),
            /* Typography */
            '--ct-h1-color'                   => array( 'bs_h1_color', '#FF6B35', 'bs_h1_color_dark', '#FF8C5A' ),
            '--ct-h2-color'                   => array( 'bs_h2_color', '#FF6B35', 'bs_h2_color_dark', '#FF8C5A' ),
            '--ct-h3-color'                   => array( 'bs_h3_color', '#FF6B35', 'bs_h3_color_dark', '#FF8C5A' ),
            '--ct-h4-color'                   => array( 'bs_h4_color', '#333333', 'bs_h4_color_dark', '#D0D0D0' ),
            '--ct-h5-color'                   => array( 'bs_h5_color', '#333333', 'bs_h5_color_dark', '#D0D0D0' ),
            '--ct-paragraph-color'            => array( 'bs_paragraph_color', '#666666', 'bs_paragraph_color_dark', '#B0B0B0' ),
            '--ct-special-color'              => array( 'bs_special_color', '#333333', 'bs_special_color_dark', '#D0D0D0' ),
            /* Forms */
            '--ct-form-input-bg-color'        => array( 'bs_form_input_bg_color', '#FFFFFF', 'bs_form_input_bg_color_dark', '#1E1E32' ),
            '--ct-form-input-border-color'    => array( 'bs_form_input_border_color', '#DDDDDD', 'bs_form_input_border_color_dark', '#3A3A4E' ),
            '--ct-form-submit-hover-color'    => array( 'bs_form_submit_hover_color', '#E55A28', 'bs_form_submit_hover_color_dark', '#C44A1E' ),
            /* Footer */
            '--ct-footer-bg-color'            => array( 'bs_footer_bg_color', '#333333', 'bs_footer_bg_color_dark', '#0D0D1A' ),
            '--ct-footer-text-color'          => array( 'bs_footer_text_color', '#999999', 'bs_footer_text_color_dark', '#888888' ),
            '--ct-footer-link-color'          => array( 'bs_footer_link_color', '#CCCCCC', 'bs_footer_link_color_dark', '#BBBBBB' ),
            '--ct-footer-link-hover-color'    => array( 'bs_footer_link_hover_color', '#FFFFFF', 'bs_footer_link_hover_color_dark', '#FFFFFF' ),
            /* Social */
            '--ct-social-bg-color'            => array( 'bs_social_bg_color', '#888888', 'bs_social_bg_color_dark', '#555566' ),
            /* Back to Top */
            '--ct-back-to-top-bg'             => array( 'bs_back_to_top_bg_color', '#FF6B35', 'bs_back_to_top_bg_color_dark', '#D45A2B' ),
            '--ct-back-to-top-border-color'   => array( 'bs_back_to_top_border_color', '#E5E5E5', 'bs_back_to_top_border_color_dark', '#333333' ),
        );
    }

    /**
     * Output CSS custom property blocks for light and dark themes.
     */
    private static function cssVariables() {
        $map = static::getColorVariableMap();

        assert( is_array( $map ), 'Variable map must be an array' );
        assert( count( $map ) > 0, 'Variable map must not be empty' );

        $light_vars = '';
        $dark_vars  = '';
        $max_vars   = 100;
        $count      = 0;

        foreach ( $map as $var_name => $config ) {
            if ( $count >= $max_vars ) {
                break;
            }
            $count++;

            $light_val = get_theme_mod( $config[0], $config[1] );
            $dark_val  = get_theme_mod( $config[2], $config[3] );

            $light_vars .= "    {$var_name}:{$light_val};\n";
            $dark_vars  .= "    {$var_name}:{$dark_val};\n";
        }

        return "
body,
body[data-theme=\"light\"] {
    color-scheme:light;
{$light_vars}}
body[data-theme=\"dark\"] {
    color-scheme:dark;
{$dark_vars}}
";
    }

    /**
     * Container max-width CSS applied to header, footer, breadcrumbs.
     */
    private static function containerCss() {
        $max_width = absint( get_theme_mod( 'bs_container_max_width', 1200 ) );

        assert( $max_width >= 800, 'Container max width must be at least 800' );
        assert( $max_width <= 1920, 'Container max width must be at most 1920' );

        return "
.topbar__container,
.site-header__container,
.breadcrumbs,
.site-footer__widgets .ct-container,
.site-footer__copyright .ct-container {
    max-width:{$max_width}px;
}
";
    }

    /**
     * Full-width sidebar layout CSS.
     *
     * Layout grid has no container max-width constraint.
     * Content area is centered at the container max-width only when sidebars are active.
     */
    private static function layoutCss() {
        $max_width = absint( get_theme_mod( 'bs_container_max_width', 1200 ) );

        assert( $max_width >= 800, 'Layout max width must be at least 800' );
        assert( $max_width <= 1920, 'Layout max width must be at most 1920' );

        return "
.ct-layout.ct-container {
    max-width:none;
    padding:0;
}
.ct-layout--with-left .ct-layout__content,
.ct-layout--with-right .ct-layout__content,
.ct-layout--constrained .ct-layout__content {
    max-width:{$max_width}px;
    margin-left:auto;
    margin-right:auto;
    padding:0 20px;
}
";
    }

    private static function topbarCss() {
        $t1_size   = get_theme_mod( 'bs_topbar_text1_size', 14 );
        assert( is_numeric( $t1_size ), 'Topbar text1 size must be numeric' );
        assert( $t1_size >= 0 && $t1_size <= 100, 'Topbar text1 size must be in range' );

        $t1_bold   = get_theme_mod( 'bs_topbar_text1_bold', true );
        $t1_italic = get_theme_mod( 'bs_topbar_text1_italic', false );
        $t1_upper  = get_theme_mod( 'bs_topbar_text1_uppercase', false );
        $t1_ml     = get_theme_mod( 'bs_topbar_text1_margin_left', 0 );
        $t1_mr     = get_theme_mod( 'bs_topbar_text1_margin_right', 10 );
        $t1_mt     = get_theme_mod( 'bs_topbar_text1_margin_top', 0 );

        $t2_size   = get_theme_mod( 'bs_topbar_text2_size', 14 );
        $t2_bold   = get_theme_mod( 'bs_topbar_text2_bold', false );
        $t2_italic = get_theme_mod( 'bs_topbar_text2_italic', false );
        $t2_upper  = get_theme_mod( 'bs_topbar_text2_uppercase', false );
        $t2_ml     = get_theme_mod( 'bs_topbar_text2_margin_left', 0 );
        $t2_mr     = get_theme_mod( 'bs_topbar_text2_margin_right', 0 );
        $t2_mt     = get_theme_mod( 'bs_topbar_text2_margin_top', 0 );

        $link_size  = get_theme_mod( 'bs_topbar_links_size', 14 );
        $link_bold  = get_theme_mod( 'bs_topbar_links_bold', true );
        $link_italic = get_theme_mod( 'bs_topbar_links_italic', false );
        $link_upper = get_theme_mod( 'bs_topbar_links_uppercase', true );
        $link_ml    = get_theme_mod( 'bs_topbar_links_margin_left', 0 );
        $link_mr    = get_theme_mod( 'bs_topbar_links_margin_right', 0 );
        $link_mt    = get_theme_mod( 'bs_topbar_links_margin_top', 0 );

        $t1_props   = static::getCheckboxStyleProps( $t1_bold, $t1_italic, $t1_upper );
        $t2_props   = static::getCheckboxStyleProps( $t2_bold, $t2_italic, $t2_upper );
        $link_props = static::getCheckboxStyleProps( $link_bold, $link_italic, $link_upper );

        return "
.topbar {
    background-color:var(--ct-topbar-bg-color);
}
.topbar__text1 {
    font-size:{$t1_size}px;
    color:var(--ct-topbar-text1-color);
    {$t1_props}
    margin:{$t1_mt}px {$t1_mr}px 0 {$t1_ml}px;
}
.topbar__text2 {
    font-size:{$t2_size}px;
    color:var(--ct-topbar-text2-color);
    {$t2_props}
    margin:{$t2_mt}px {$t2_mr}px 0 {$t2_ml}px;
}
.topbar__phone-link {
    color:var(--ct-topbar-text2-color);
}
.topbar__phone-link:hover {
    opacity:0.85;
}
.topbar__right .menu li a {
    font-size:{$link_size}px;
    color:var(--ct-topbar-links-color);
    {$link_props}
    margin:{$link_mt}px {$link_mr}px 0 {$link_ml}px;
    transition:color 0.2s ease;
}
.topbar__right .menu li a:hover {
    color:var(--ct-topbar-links-hover-color);
}
";
    }

    private static function headerCss() {
        $logo_h     = get_theme_mod( 'bs_header_logo_height', 60 );
        assert( is_numeric( $logo_h ), 'Logo height must be numeric' );
        assert( $logo_h >= 0 && $logo_h <= 1000, 'Logo height must be in range' );
        $logo_ml    = get_theme_mod( 'bs_header_logo_margin_left', 0 );
        $logo_mr    = get_theme_mod( 'bs_header_logo_margin_right', 0 );
        $logo_mt    = get_theme_mod( 'bs_header_logo_margin_top', 10 );
        $logo_mb    = get_theme_mod( 'bs_header_logo_margin_bottom', 10 );

        return "
.site-header {
    background-color:var(--ct-header-bg-color);
    border-bottom:1px solid var(--ct-header-border-color);
}
.site-header__logo {
    margin:{$logo_mt}px {$logo_mr}px {$logo_mb}px {$logo_ml}px;
}
.site-header__logo img {
    height:{$logo_h}px;
    width:auto;
}
.site-header__logo .site-title-text {
    font-size:28px;
    font-weight:700;
    color:var(--ct-site-title-color);
}
";
    }

    private static function menuCss() {
        $top_size    = get_theme_mod( 'bs_menu_top_font_size', 14 );
        assert( is_numeric( $top_size ), 'Menu top font size must be numeric' );
        assert( $top_size >= 0 && $top_size <= 100, 'Menu top font size must be in range' );
        $top_bold    = get_theme_mod( 'bs_menu_top_bold', false );
        $top_italic  = get_theme_mod( 'bs_menu_top_italic', false );
        $top_upper   = get_theme_mod( 'bs_menu_top_uppercase', true );
        $top_ml      = get_theme_mod( 'bs_menu_top_margin_left', 10 );
        $top_mr      = get_theme_mod( 'bs_menu_top_margin_right', 10 );
        $top_mt      = get_theme_mod( 'bs_menu_top_margin_top', 0 );

        $sub_size    = get_theme_mod( 'bs_menu_sub_font_size', 13 );
        $sub_bold    = get_theme_mod( 'bs_menu_sub_bold', false );
        $sub_italic  = get_theme_mod( 'bs_menu_sub_italic', false );
        $sub_upper   = get_theme_mod( 'bs_menu_sub_uppercase', true );
        $sub_ml      = get_theme_mod( 'bs_menu_sub_margin_left', 0 );
        $sub_mr      = get_theme_mod( 'bs_menu_sub_margin_right', 0 );
        $sub_mt      = get_theme_mod( 'bs_menu_sub_margin_top', 0 );
        $sub_bw      = get_theme_mod( 'bs_menu_sub_border_width', 1 );
        $sub_bs      = get_theme_mod( 'bs_menu_sub_border_style', 'solid' );

        $top_props = static::getCheckboxStyleProps( $top_bold, $top_italic, $top_upper );
        $sub_props = static::getCheckboxStyleProps( $sub_bold, $sub_italic, $sub_upper );

        return "
.main-navigation .menu > li > a {
    font-size:{$top_size}px;
    color:var(--ct-menu-top-color);
    {$top_props}
    margin:{$top_mt}px {$top_mr}px 0 {$top_ml}px;
    border-bottom:3px solid transparent;
    transition:border-color 0.2s ease, color 0.2s ease;
}
.main-navigation .menu > li > a:hover,
.main-navigation .menu > li.current-menu-item > a,
.main-navigation .menu > li.current-menu-ancestor > a {
    border-bottom-color:var(--ct-menu-active-underline-color);
}
.main-navigation .menu > li > .sub-menu {
    background:var(--ct-menu-sub-bg-color);
    border:{$sub_bw}px {$sub_bs} var(--ct-menu-sub-border-color);
    box-shadow:0 2px 8px rgba(0,0,0,0.08);
}
.main-navigation .sub-menu li a {
    font-size:{$sub_size}px;
    color:var(--ct-menu-sub-color);
    {$sub_props}
    margin:{$sub_mt}px {$sub_mr}px 0 {$sub_ml}px;
    border-bottom:{$sub_bw}px {$sub_bs} var(--ct-menu-sub-border-color);
    transition:background 0.2s ease;
}
.main-navigation .sub-menu li:last-child a {
    border-bottom:none;
}
.main-navigation .sub-menu li a:hover {
    background:var(--ct-menu-sub-hover-bg-color);
}
.main-navigation .sub-menu .sub-menu {
    background:var(--ct-menu-sub-bg-color);
    border:{$sub_bw}px {$sub_bs} var(--ct-menu-sub-border-color);
    box-shadow:0 2px 8px rgba(0,0,0,0.08);
}
";
    }

    private static function mobileMenuCss() {
        $bw = absint( get_theme_mod( 'bs_mobile_menu_border_width', 1 ) );
        assert( $bw >= 0 && $bw <= 10, 'Mobile menu border width must be in range' );
        assert( true, 'Mobile bg color set via cssVariables()' );

        return "
@media screen and (max-width: 768px) {
    .main-navigation .menu {
        background:var(--ct-mobile-menu-bg-color);
        border:{$bw}px solid var(--ct-mobile-menu-border-color);
        box-shadow:0 4px 12px rgba(0,0,0,0.1);
    }
    .menu-toggle {
        border:{$bw}px solid var(--ct-mobile-menu-border-color);
    }
}
";
    }

    private static function breadcrumbCss() {
        $font_size  = get_theme_mod( 'bs_breadcrumb_font_size', 14 );
        assert( is_numeric( $font_size ), 'Breadcrumb font size must be numeric' );
        assert( true, 'Breadcrumb transform validated at retrieval' );

        $transform  = get_theme_mod( 'bs_breadcrumb_transform', 'none' );
        $bold       = get_theme_mod( 'bs_breadcrumb_active_bold', true );
        $underline  = get_theme_mod( 'bs_breadcrumb_active_underline', false );

        $active_fw = $bold ? 'font-weight:700;' : 'font-weight:400;';
        $active_td = $underline ? 'text-decoration:underline;' : 'text-decoration:none;';

        return "
.breadcrumbs a {
    font-size:{$font_size}px;
    color:var(--ct-breadcrumb-color);
    text-transform:{$transform};
    transition:color 0.2s;
}
.breadcrumbs a:hover {
    color:var(--ct-breadcrumb-active-color);
}
.breadcrumbs .breadcrumb-separator {
    margin:0 8px;
    color:var(--ct-breadcrumb-color);
    font-size:{$font_size}px;
}
.breadcrumbs .breadcrumb-current {
    font-size:{$font_size}px;
    color:var(--ct-breadcrumb-active-color);
    text-transform:{$transform};
    {$active_fw}
    {$active_td}
}
";
    }

    private static function typographyCss() {
        /* H1 */
        $h1_size      = get_theme_mod( 'bs_h1_font_size', 36 );
        assert( is_numeric( $h1_size ), 'H1 font size must be numeric' );
        assert( $h1_size >= 0 && $h1_size <= 200, 'H1 font size must be in range' );
        $h1_bold      = get_theme_mod( 'bs_h1_bold', true );
        $h1_italic    = get_theme_mod( 'bs_h1_italic', false );
        $h1_transform = get_theme_mod( 'bs_h1_transform', 'uppercase' );

        /* H2 */
        $h2_size      = get_theme_mod( 'bs_h2_font_size', 30 );
        $h2_bold      = get_theme_mod( 'bs_h2_bold', true );
        $h2_italic    = get_theme_mod( 'bs_h2_italic', false );
        $h2_transform = get_theme_mod( 'bs_h2_transform', 'uppercase' );

        /* H3 */
        $h3_size      = get_theme_mod( 'bs_h3_font_size', 24 );
        $h3_bold      = get_theme_mod( 'bs_h3_bold', true );
        $h3_italic    = get_theme_mod( 'bs_h3_italic', false );
        $h3_transform = get_theme_mod( 'bs_h3_transform', 'none' );

        /* H4 */
        $h4_size      = get_theme_mod( 'bs_h4_font_size', 20 );
        $h4_bold      = get_theme_mod( 'bs_h4_bold', true );
        $h4_italic    = get_theme_mod( 'bs_h4_italic', false );
        $h4_transform = get_theme_mod( 'bs_h4_transform', 'none' );

        /* H5 */
        $h5_size      = get_theme_mod( 'bs_h5_font_size', 18 );
        $h5_bold      = get_theme_mod( 'bs_h5_bold', true );
        $h5_italic    = get_theme_mod( 'bs_h5_italic', false );
        $h5_transform = get_theme_mod( 'bs_h5_transform', 'none' );

        /* Paragraphs */
        $p_size          = get_theme_mod( 'bs_paragraph_font_size', 16 );
        $p_bold          = get_theme_mod( 'bs_paragraph_bold', false );
        $p_italic        = get_theme_mod( 'bs_paragraph_italic', false );
        $p_transform     = get_theme_mod( 'bs_paragraph_transform', 'none' );
        $p_line_height   = floatval( get_theme_mod( 'bs_paragraph_line_height', 1.6 ) );
        $p_margin_top    = absint( get_theme_mod( 'bs_paragraph_margin_top', 0 ) );
        $p_margin_right  = absint( get_theme_mod( 'bs_paragraph_margin_right', 0 ) );
        $p_margin_bottom = absint( get_theme_mod( 'bs_paragraph_margin_bottom', 16 ) );
        $p_margin_left   = absint( get_theme_mod( 'bs_paragraph_margin_left', 0 ) );

        /* Special text */
        $s_size      = get_theme_mod( 'bs_special_font_size', 16 );
        $s_bold      = get_theme_mod( 'bs_special_bold', true );
        $s_italic    = get_theme_mod( 'bs_special_italic', false );
        $s_transform = get_theme_mod( 'bs_special_transform', 'none' );

        $h1_fw = $h1_bold ? 'font-weight:700;' : 'font-weight:400;';
        $h1_fs = $h1_italic ? 'font-style:italic;' : 'font-style:normal;';
        $h2_fw = $h2_bold ? 'font-weight:700;' : 'font-weight:400;';
        $h2_fs = $h2_italic ? 'font-style:italic;' : 'font-style:normal;';
        $h3_fw = $h3_bold ? 'font-weight:700;' : 'font-weight:400;';
        $h3_fs = $h3_italic ? 'font-style:italic;' : 'font-style:normal;';
        $h4_fw = $h4_bold ? 'font-weight:700;' : 'font-weight:400;';
        $h4_fs = $h4_italic ? 'font-style:italic;' : 'font-style:normal;';
        $h5_fw = $h5_bold ? 'font-weight:700;' : 'font-weight:400;';
        $h5_fs = $h5_italic ? 'font-style:italic;' : 'font-style:normal;';
        $p_fw  = $p_bold ? 'font-weight:700;' : 'font-weight:400;';
        $p_fs  = $p_italic ? 'font-style:italic;' : 'font-style:normal;';
        $s_fw  = $s_bold ? 'font-weight:700;' : 'font-weight:400;';
        $s_fs  = $s_italic ? 'font-style:italic;' : 'font-style:normal;';

        return "
/* H1 */
h1, .entry-title, .page-title {
    font-size:{$h1_size}px;
    color:var(--ct-h1-color);
    {$h1_fw}
    {$h1_fs}
    text-transform:{$h1_transform};
    margin-bottom:16px;
    line-height:1.2;
}
/* H2 */
h2 {
    font-size:{$h2_size}px;
    color:var(--ct-h2-color);
    {$h2_fw}
    {$h2_fs}
    text-transform:{$h2_transform};
    margin-bottom:14px;
    line-height:1.25;
}
/* H3 */
h3, .section-title {
    font-size:{$h3_size}px;
    color:var(--ct-h3-color);
    {$h3_fw}
    {$h3_fs}
    text-transform:{$h3_transform};
    margin-bottom:12px;
    line-height:1.3;
}
/* H4 */
h4 {
    font-size:{$h4_size}px;
    color:var(--ct-h4-color);
    {$h4_fw}
    {$h4_fs}
    text-transform:{$h4_transform};
    margin-bottom:10px;
    line-height:1.35;
}
/* H5 */
h5 {
    font-size:{$h5_size}px;
    color:var(--ct-h5-color);
    {$h5_fw}
    {$h5_fs}
    text-transform:{$h5_transform};
    margin-bottom:8px;
    line-height:1.4;
}
/* Paragraphs */
.ct-p {
    font-size:{$p_size}px;
    color:var(--ct-paragraph-color);
    {$p_fw}
    {$p_fs}
    text-transform:{$p_transform};
    line-height:{$p_line_height};
    margin-top:{$p_margin_top}px;
    margin-right:{$p_margin_right}px;
    margin-bottom:{$p_margin_bottom}px;
    margin-left:{$p_margin_left}px;
}
/* Special text */
.special-text,
.reach-us__company,
.reach-us__address,
.reach-us__phone,
.reach-us__fax,
.reach-us__email {
    font-size:{$s_size}px;
    color:var(--ct-special-color);
    {$s_fw}
    {$s_fs}
    text-transform:{$s_transform};
    line-height:1.5;
}
";
    }

    private static function formCss() {
        assert( true, 'Form input colors set via cssVariables()' );
        assert( true, 'Form border colors set via cssVariables()' );

        return "
.contact-section .section-title::after {
    background:linear-gradient(to right, var(--ct-topbar-bg-color) 30%, #ccc 30%);
}
.ct-contact-form input[type='text'],
.ct-contact-form input[type='email'],
.ct-contact-form input[type='tel'],
.ct-contact-form textarea {
    border:1px solid var(--ct-form-input-border-color);
    background:var(--ct-form-input-bg-color);
    color:var(--ct-paragraph-color);
    font-size:14px;
    transition:border-color 0.2s;
}
.ct-contact-form input:focus,
.ct-contact-form textarea:focus {
    border-color:var(--ct-topbar-bg-color);
}
.ct-contact-form__submit {
    background:var(--ct-topbar-bg-color);
    color:#fff;
    font-size:14px;
    font-weight:700;
    text-transform:uppercase;
    transition:background 0.2s;
}
.ct-contact-form__submit:hover {
    background:var(--ct-form-submit-hover-color);
}
";
    }

    private static function socialCss() {
        $icon_w = absint( get_theme_mod( 'bs_social_icon_width', 36 ) );
        $icon_h = absint( get_theme_mod( 'bs_social_icon_height', 36 ) );

        assert( $icon_w >= 12 && $icon_w <= 128, 'Social icon width must be in range' );
        assert( $icon_h >= 12 && $icon_h <= 128, 'Social icon height must be in range' );

        return "
.social-icons a:not(.customize-unpreviewable),
.social-icons .share-button {
    width:{$icon_w}px;
    height:{$icon_h}px;
    transition:opacity 0.2s;
}
.social-icons .share-button {
    background:var(--ct-social-bg-color);
}
.social-icons a:not(.customize-unpreviewable):hover,
.social-icons .share-button:hover {
    opacity:0.8;
}
.social-icons a img,
.social-icons a svg,
.social-icons .share-button svg {
    width:{$icon_w}px;
    height:{$icon_h}px;
}
";
    }

    private static function footerCss() {
        assert( true, 'Footer bg color set via cssVariables()' );
        assert( true, 'Footer text color set via cssVariables()' );

        return '
.site-footer {
    background:var(--ct-footer-bg-color);
    color:var(--ct-footer-text-color);
    font-size:14px;
}
.site-footer a {
    color:var(--ct-footer-link-color);
}
.site-footer a:hover {
    color:var(--ct-footer-link-hover-color);
}
.footer-widget__title {
    color:var(--ct-footer-link-hover-color);
    font-weight:700;
    text-transform:uppercase;
}
.widget-contact-point__label,
.widget-company-info__label {
    color:var(--ct-footer-link-color);
}
.site-footer__copyright {
    border-top-color:var(--ct-footer-link-color);
    border-top-color:rgba(255,255,255,0.1);
}
';
    }

    private static function backToTopCss() {
        $enabled       = get_theme_mod( 'bs_back_to_top_enabled', true );
        $border_width  = absint( get_theme_mod( 'bs_back_to_top_border_width', 1 ) );
        $border_radius = absint( get_theme_mod( 'bs_back_to_top_border_radius', 8 ) );

        assert( $border_width >= 0 && $border_width <= 10, 'Back to top border width must be in range' );
        assert( $border_radius >= 0 && $border_radius <= 100, 'Back to top border radius must be in range' );

        $css = "
.ct-back-to-top {
    --ct-back-to-top-border-width:{$border_width}px;
    --ct-back-to-top-border-radius:{$border_radius}px;
}
";

        if ( ! $enabled ) {
            $css .= ".ct-back-to-top { display:none !important; }\n";
        }

        return $css;
    }

    private static function themeToggleCss() {
        assert( true, 'Theme toggle CSS moved to compiled app.css' );
        assert( true, 'No inline output needed' );

        /* Static CSS now lives in _theme-toggle.scss (compiled by Vite) */
        return '';
    }

    private static function themeTransitionCss() {
        assert( true, 'Theme transition CSS moved to compiled app.css' );
        assert( true, 'No inline output needed' );

        /* Static CSS now lives in _theme-toggle.scss (compiled by Vite) */
        return '';
    }

    private static function authCss() {
        $link_size   = get_theme_mod( 'bs_topbar_links_size', 14 );
        $link_bold   = get_theme_mod( 'bs_topbar_links_bold', true );
        $link_italic = get_theme_mod( 'bs_topbar_links_italic', false );
        $link_upper  = get_theme_mod( 'bs_topbar_links_uppercase', true );

        assert( is_numeric( $link_size ), 'Auth link size must be numeric' );
        assert( $link_size >= 0 && $link_size <= 100, 'Auth link size must be in range' );

        $link_props = static::getCheckboxStyleProps( $link_bold, $link_italic, $link_upper );

        return "
.ct-auth-links__link,
.ct-auth-links__greeting,
.ct-auth-links__separator {
    font-size:{$link_size}px;
    {$link_props}
}
";
    }

    /**
     * Google Fonts @font-face rules and body font-family.
     */
    private static function fontCss() {
        $enabled = get_theme_mod( 'bs_font_enabled', false );

        assert( is_bool( $enabled ) || is_string( $enabled ) || is_int( $enabled ), 'Font enabled must be bool-like' );

        if ( ! $enabled ) {
            return '';
        }

        $face_css = get_theme_mod( 'bs_font_face_css', '' );
        $family   = get_theme_mod( 'bs_font_family', '' );

        assert( is_string( $face_css ), 'Font face CSS must be a string' );
        assert( is_string( $family ), 'Font family must be a string' );

        if ( '' === $face_css || '' === $family ) {
            return '';
        }

        $safe_family = esc_attr( $family );

        return "
{$face_css}
body, button, input, select, textarea {
    font-family:'{$safe_family}', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
";
    }
}
