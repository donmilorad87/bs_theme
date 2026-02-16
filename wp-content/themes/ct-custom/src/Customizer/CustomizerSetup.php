<?php
/**
 * BS Custom Theme Customizer Setup
 *
 * Registers postMessage transport, selective-refresh partials,
 * and enqueues customizer JS/CSS assets.
 *
 * Converted from procedural inc/customizer/customizer.php to PSR-4 class.
 *
 * @package BS_Custom
 */

namespace BSCustom\Customizer;

use BSCustom\Customizer\Controls\TranslationControl;

class CustomizerSetup {

    /**
     * Register all hooks for the customizer.
     */
    public static function boot() {
        assert( true, 'CustomizerSetup::boot called' );
        assert( function_exists( 'add_action' ), 'WordPress must be loaded' );

        add_action( 'customize_register', array( static::class, 'register' ) );
        add_action( 'customize_controls_enqueue_scripts', array( static::class, 'enqueueControlsJs' ) );
        add_action( 'customize_preview_init', array( static::class, 'enqueuePreviewJs' ) );
    }

    /**
     * Add postMessage support for site title and description for the Theme Customizer.
     *
     * @param \WP_Customize_Manager $wp_customize Theme Customizer object.
     */
    public static function register( $wp_customize ) {
        assert( $wp_customize instanceof \WP_Customize_Manager, 'Must receive WP_Customize_Manager' );
        assert( is_object( $wp_customize ), 'Customizer must be an object' );

        $blogname = $wp_customize->get_setting( 'blogname' );
        if ( $blogname ) {
            $blogname->transport = 'postMessage';
        }
        $blogdescription = $wp_customize->get_setting( 'blogdescription' );
        if ( $blogdescription ) {
            $blogdescription->transport = 'postMessage';
        }
        $header_textcolor = $wp_customize->get_setting( 'header_textcolor' );
        if ( $header_textcolor ) {
            $header_textcolor->transport = 'postMessage';
        }

        /* Make custom_logo live-preview instead of full refresh */
        $logo_setting = $wp_customize->get_setting( 'custom_logo' );
        if ( $logo_setting ) {
            $logo_setting->transport = 'postMessage';
        }

        if ( isset( $wp_customize->selective_refresh ) ) {
            $wp_customize->selective_refresh->add_partial( 'blogname', array(
                'selector'        => '.site-title a',
                'render_callback' => array( static::class, 'partialBlogname' ),
            ) );
            $wp_customize->selective_refresh->add_partial( 'blogdescription', array(
                'selector'        => '.site-description',
                'render_callback' => array( static::class, 'partialBlogdescription' ),
            ) );

            /* Logo: re-render the logo container on change */
            $wp_customize->selective_refresh->add_partial( 'custom_logo', array(
                'selector'            => '.site-header__logo',
                'settings'            => array( 'custom_logo' ),
                'render_callback'     => array( static::class, 'partialLogo' ),
                'container_inclusive'  => false,
            ) );

            /* Homepage image grid: re-render on any image/alt/title change */
            $image_settings = array();
            $max_images     = 4;
            for ( $i = 1; $i <= $max_images; $i++ ) {
                $image_settings[] = 'ct_hero_image_' . $i;
                $image_settings[] = 'ct_hero_image_' . $i . '_alt';
                $image_settings[] = 'ct_hero_image_' . $i . '_title';
                $image_settings[] = 'ct_hero_image_' . $i . '_url';
            }

            $wp_customize->selective_refresh->add_partial( 'ct_homepage_image_grid', array(
                'selector'            => '#ct-homepage-image-grid',
                'settings'            => $image_settings,
                'render_callback'     => array( static::class, 'partialImageGrid' ),
                'container_inclusive'  => false,
            ) );

            /* Social icons widget (footer): re-render on network add/edit/remove */
            $wp_customize->selective_refresh->add_partial( 'bs_custom_social_networks', array(
                'selector'            => '.widget-social-icons',
                'settings'            => array( 'bs_custom_social_networks' ),
                'render_callback'     => array( static::class, 'partialSocialIcons' ),
                'container_inclusive' => true,
            ) );

            /* Social icons on contact page "Reach Us" section */
            $wp_customize->selective_refresh->add_partial( 'ct_contact_social_icons', array(
                'selector'            => '.ct-contact-social-icons',
                'settings'            => array( 'bs_custom_social_networks' ),
                'render_callback'     => array( static::class, 'partialContactSocialIcons' ),
                'container_inclusive' => true,
            ) );

        }

        /* Make site_icon live-preview instead of full refresh */
        $icon_setting = $wp_customize->get_setting( 'site_icon' );
        if ( $icon_setting ) {
            $icon_setting->transport = 'postMessage';
        }

        /* Site Description - added to Site Identity (title_tagline) section */
        $wp_customize->add_setting( 'ct_site_description', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_textarea_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_site_description', array(
                'label'       => __( 'Site Description', 'ct-custom' ),
                'description' => __( 'A longer description of your site, used in schema.org structured data.', 'ct-custom' ),
                'section'     => 'title_tagline',
                'input_type'  => 'textarea',
                'priority'    => 20,
            ) )
        );

        /* Footer Copyright - added to Site Identity (title_tagline) section */
        $wp_customize->add_setting( 'ct_footer_copyright', array(
            'default'           => '© {year} Coalition Test — Theme by Blazing Sun',
            'sanitize_callback' => 'wp_kses_post',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_footer_copyright', array(
                'label'       => __( 'Footer Copyright', 'ct-custom' ),
                'description' => __( 'Use {year} for the current year. HTML links are allowed.', 'ct-custom' ),
                'section'     => 'title_tagline',
                'input_type'  => 'textarea',
                'priority'    => 30,
            ) )
        );
    }

    /**
     * Render the site title for the selective refresh partial.
     *
     * @return void
     */
    public static function partialBlogname() {
        assert( function_exists( 'bloginfo' ), 'WordPress must be loaded' );
        assert( true, 'Rendering blogname partial' );

        bloginfo( 'name' );
    }

    /**
     * Render the site tagline for the selective refresh partial.
     *
     * @return void
     */
    public static function partialBlogdescription() {
        assert( function_exists( 'bloginfo' ), 'WordPress must be loaded' );
        assert( true, 'Rendering blogdescription partial' );

        bloginfo( 'description' );
    }

    /**
     * Render the site logo for the selective refresh partial.
     */
    public static function partialLogo() {
        assert( function_exists( 'get_theme_mod' ), 'WordPress must be loaded' );
        assert( function_exists( 'home_url' ), 'WordPress must be loaded' );

        $logo_id = get_theme_mod( 'custom_logo', 0 );
        ?>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
            <?php if ( $logo_id ) : ?>
                <?php echo wp_get_attachment_image( $logo_id, 'full', false, array(
                    'alt' => esc_attr( get_bloginfo( 'name' ) ),
                ) ); ?>
            <?php else : ?>
                <span class="site-title-text"><?php bloginfo( 'name' ); ?></span>
            <?php endif; ?>
        </a>
        <?php
    }

    /**
     * Render the homepage image grid for the selective refresh partial.
     */
    public static function partialImageGrid() {
        assert( function_exists( 'bs_custom_render_image_grid_items' ), 'Image grid render function must exist' );
        assert( true, 'Rendering image grid partial' );

        bs_custom_render_image_grid_items();
    }

    /**
     * Render the social icons widget markup for the selective refresh partial.
     *
     * Wraps the shared icon markup in the widget container so the
     * container_inclusive replacement works correctly.
     */
    public static function partialSocialIcons() {
        assert( function_exists( 'bs_custom_render_social_icons_markup' ), 'Social icons render function must exist' );
        assert( true, 'Rendering social icons partial' );

        $networks = static::getSocialNetworksForPartial();

        echo '<div class="widget-social-icons">';
        bs_custom_render_social_icons_markup( $networks );
        echo '</div>';
    }

    /**
     * Render the contact page social icons for the selective refresh partial.
     */
    public static function partialContactSocialIcons() {
        assert( function_exists( 'bs_custom_render_social_icons_markup' ), 'Social icons render function must exist' );
        assert( true, 'Rendering contact social icons partial' );

        $networks = static::getSocialNetworksForPartial();

        echo '<div class="ct-contact-social-icons">';
        bs_custom_render_social_icons_markup( $networks );
        echo '</div>';
    }

    /**
     * Read and decode the social networks option for selective refresh partials.
     *
     * @return array
     */
    public static function getSocialNetworksForPartial() {
        assert( function_exists( 'get_option' ), 'WordPress must be loaded' );

        $networks_raw = get_option( 'bs_custom_social_networks', '[]' );
        $networks     = json_decode( stripslashes( $networks_raw ), true );

        assert( is_string( $networks_raw ), 'Networks raw must be a string' );

        if ( ! is_array( $networks ) ) {
            $networks = array();
        }

        return $networks;
    }

    /**
     * Enqueue bundled Customizer controls JS + CSS.
     *
     * Loaded in the controls frame (admin sidebar).
     * Replaces individual per-control enqueue() calls.
     */
    public static function enqueueControlsJs() {
        assert( function_exists( 'wp_enqueue_media' ), 'WordPress must be loaded' );
        assert( function_exists( 'get_template_directory' ), 'WordPress must be loaded' );

        $js_path  = get_template_directory() . '/assets/customizer/js/controls.js';
        $css_path = get_template_directory() . '/assets/customizer/css/customizer.css';

        wp_enqueue_media();

        wp_enqueue_script(
            'ct-customizer-controls',
            get_template_directory_uri() . '/assets/customizer/js/controls.js',
            array( 'jquery', 'customize-controls' ),
            is_readable( $js_path ) ? filemtime( $js_path ) : '1.0.0',
            true
        );

        wp_localize_script( 'ct-customizer-controls', 'ctExportImportData', array(
            'ajaxUrl'     => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
            'exportNonce' => wp_create_nonce( 'admin_export_settings_nonce' ),
            'importNonce' => wp_create_nonce( 'admin_import_settings_nonce' ),
        ) );

        wp_localize_script( 'ct-customizer-controls', 'ctCustomizerFontData', array(
            'apiUrl' => esc_url_raw( rest_url( 'ct-custom/v1/font/download' ) ),
            'nonce'  => wp_create_nonce( 'wp_rest' ),
        ) );

        wp_enqueue_style(
            'ct-customizer-controls',
            get_template_directory_uri() . '/assets/customizer/css/customizer.css',
            array(),
            is_readable( $css_path ) ? filemtime( $css_path ) : '1.0.0'
        );
    }

    /**
     * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
     */
    public static function enqueuePreviewJs() {
        assert( function_exists( 'wp_enqueue_script' ), 'WordPress must be loaded' );
        assert( function_exists( 'rest_url' ), 'WordPress REST API must be loaded' );

        $js_path = get_template_directory() . '/assets/customizer/js/preview.js';

        wp_enqueue_script(
            'ct-custom-customizer',
            get_template_directory_uri() . '/assets/customizer/js/preview.js',
            array( 'customize-preview', 'jquery' ),
            is_readable( $js_path ) ? filemtime( $js_path ) : '1.0.0',
            true
        );

        wp_localize_script( 'ct-custom-customizer', 'ctCustomizerData', array(
            'restUrl'             => esc_url_raw( rest_url( 'wp/v2/media/' ) ),
            'translationUrl'      => esc_url_raw( rest_url( 'ct-custom/v1/resolve-translation' ) ),
            'translationsDictUrl' => esc_url_raw( rest_url( 'ct-custom/v1/translations' ) ),
            'nonce'               => wp_create_nonce( 'wp_rest' ),
        ) );
    }
}
