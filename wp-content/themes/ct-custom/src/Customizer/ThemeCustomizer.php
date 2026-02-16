<?php
/**
 * CT Theme Customizer - Settings Registration
 *
 * All color settings are registered as light/dark pairs.
 * Non-color values (sizes, margins, font-weight, font-style,
 * text-transform) remain as single settings.
 *
 * Migrated from inc/customizer/customizer_theme.php.
 * Old class: CT_Theme_Customizer -> New: ThemeCustomizer
 *
 * @package CT_Custom
 */

namespace CTCustom\Customizer;

use CTCustom\Customizer\Controls\FontFamilyControl;
use CTCustom\Customizer\Controls\FontWeightsControl;
use CTCustom\Customizer\Controls\ToggleSwitchControl;
use CTCustom\Customizer\Controls\TranslationControl;
use CTCustom\Customizer\Controls\SocialNetworksControl;
use CTCustom\Customizer\Controls\ContactPointControl;
use CTCustom\Customizer\Controls\RangeControl;

class ThemeCustomizer {

    public function __construct() {
        add_filter( 'customize_loaded_components', array( $this, 'remove_unused_components' ) );
        add_action( 'customize_register', array( $this, 'register' ) );
    }

    /**
     * Remove Widgets and Menus panels from the customizer.
     *
     * @param string[] $components Default customizer components.
     * @return string[]
     */
    public function remove_unused_components( $components ) {
        assert( is_array( $components ), 'Components must be an array' );

        return array_diff( $components, array( 'widgets', 'nav_menus' ) );
    }

    public function register( $wp_customize ) {
        assert( $wp_customize instanceof \WP_Customize_Manager, 'Must receive WP_Customize_Manager' );
        assert( is_object( $wp_customize ), 'Customizer must be an object' );

        /* Remove built-in sections we don't use */
        $wp_customize->remove_section( 'static_front_page' );

        $wp_customize->add_panel( 'ct_theme_panel', array(
            'title'    => __( 'CT Theme Settings', 'ct-custom' ),
            'priority' => 1,
        ) );

        $this->register_theme_settings_section( $wp_customize );
        $this->register_topbar_section( $wp_customize );
        $this->register_header_section( $wp_customize );
        $this->register_menu_top_section( $wp_customize );
        $this->register_menu_sub_section( $wp_customize );
        $this->register_mobile_menu_section( $wp_customize );
        $this->register_breadcrumb_section( $wp_customize );
        $this->register_body_section( $wp_customize );
        $this->register_footer_section( $wp_customize );
        $this->register_form_section( $wp_customize );
        $this->register_social_section( $wp_customize );
        $this->register_contact_point_section( $wp_customize );
        $this->register_back_to_top_section( $wp_customize );
        $this->register_email_template_section( $wp_customize );
        $this->register_typography_sections( $wp_customize );
        $this->register_pages_panel( $wp_customize );
        $this->register_language_menus_panel( $wp_customize );
    }

    /**
     * Register a light + dark color control pair.
     *
     * Creates two settings: {$id} (light) and {$id}_dark (dark),
     * each with its own WP_Customize_Color_Control.
     */
    private function add_color_control_pair( $wp_customize, $id, $label, $section, $light_default, $dark_default ) {
        assert( is_string( $id ), 'ID must be a string' );
        assert( is_string( $label ), 'Label must be a string' );

        /* Light */
        $wp_customize->add_setting( $id, array(
            'default'           => $light_default,
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, $id, array(
            'label'   => sprintf( __( '%s (Light)', 'ct-custom' ), $label ),
            'section' => $section,
        ) ) );

        /* Dark */
        $wp_customize->add_setting( $id . '_dark', array(
            'default'           => $dark_default,
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, $id . '_dark', array(
            'label'   => sprintf( __( '%s (Dark)', 'ct-custom' ), $label ),
            'section' => $section,
        ) ) );
    }

    private function register_theme_settings_section( $wp_customize ) {
        assert( $wp_customize instanceof \WP_Customize_Manager, 'Must receive WP_Customize_Manager' );
        assert( is_object( $wp_customize ), 'Customizer must be an object' );

        $wp_customize->add_section( 'ct_theme_settings_section', array(
            'title'    => __( 'Theme Settings', 'ct-custom' ),
            'panel'    => 'ct_theme_panel',
            'priority' => 5,
        ) );

        /* Logo controls (moved from Header section) */
        $this->add_range_control( $wp_customize, 'ct_header_logo_height', 'Logo Height', 'ct_theme_settings_section', 60, 20, 300 );
        $this->add_range_control( $wp_customize, 'ct_header_logo_margin_left', 'Logo Margin Left', 'ct_theme_settings_section', 0, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_header_logo_margin_right', 'Logo Margin Right', 'ct_theme_settings_section', 0, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_header_logo_margin_top', 'Logo Margin Top', 'ct_theme_settings_section', 10, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_header_logo_margin_bottom', 'Logo Margin Bottom', 'ct_theme_settings_section', 10, 0, 100 );

        /* Container Max Width */
        $this->add_range_control( $wp_customize, 'ct_container_max_width', 'Container Max Width', 'ct_theme_settings_section', 1200, 800, 1920 );

    }

    private function register_topbar_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_topbar_section', array(
            'title' => __( 'Top Bar', 'ct-custom' ),
            'panel' => 'ct_theme_panel',
            'priority' => 10,
        ) );

        $this->add_color_control_pair( $wp_customize, 'ct_topbar_bg_color', 'Background Color', 'ct_topbar_section', '#FF6B35', '#D45A2B' );

        /* Call Label */
        $wp_customize->add_setting( 'ct_topbar_text1_content', array(
            'default'           => 'CALL US NOW!',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_topbar_text1_content', array(
                'label'   => __( 'Call Label', 'ct-custom' ),
                'section' => 'ct_topbar_section',
            ) )
        );

        $this->add_range_control( $wp_customize, 'ct_topbar_text1_size', 'Call Label Size', 'ct_topbar_section', 14, 10, 30 );
        $this->add_color_control_pair( $wp_customize, 'ct_topbar_text1_color', 'Call Label Color', 'ct_topbar_section', '#FFFFFF', '#FFFFFF' );
        $this->add_style_checkboxes( $wp_customize, 'ct_topbar_text1', 'Call Label', 'ct_topbar_section', true, false, false );
        $this->add_range_control( $wp_customize, 'ct_topbar_text1_margin_left', 'Call Label Margin Left', 'ct_topbar_section', 0, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_topbar_text1_margin_right', 'Call Label Margin Right', 'ct_topbar_section', 10, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_topbar_text1_margin_top', 'Call Label Margin Top', 'ct_topbar_section', 0, 0, 100 );

        /* Phone Number */
        $wp_customize->add_setting( 'ct_topbar_text2_content', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_topbar_text2_content', array(
                'label'       => __( 'Phone Number', 'ct-custom' ),
                'description' => __( 'Leave empty to use the phone from Admin Settings.', 'ct-custom' ),
                'section'     => 'ct_topbar_section',
            ) )
        );

        $this->add_range_control( $wp_customize, 'ct_topbar_text2_size', 'Phone Number Size', 'ct_topbar_section', 14, 10, 30 );
        $this->add_color_control_pair( $wp_customize, 'ct_topbar_text2_color', 'Phone Number Color', 'ct_topbar_section', '#FFFFFF', '#FFFFFF' );
        $this->add_style_checkboxes( $wp_customize, 'ct_topbar_text2', 'Phone Number', 'ct_topbar_section', false, false, false );
        $this->add_range_control( $wp_customize, 'ct_topbar_text2_margin_left', 'Phone Number Margin Left', 'ct_topbar_section', 0, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_topbar_text2_margin_right', 'Phone Number Margin Right', 'ct_topbar_section', 0, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_topbar_text2_margin_top', 'Phone Number Margin Top', 'ct_topbar_section', 0, 0, 100 );

        /* Top Bar Menu */
        $this->add_color_control_pair( $wp_customize, 'ct_topbar_links_color', 'Top Bar Menu Color', 'ct_topbar_section', '#FFFFFF', '#FFFFFF' );
        $this->add_range_control( $wp_customize, 'ct_topbar_links_size', 'Top Bar Menu Font Size', 'ct_topbar_section', 14, 10, 30 );
        $this->add_color_control_pair( $wp_customize, 'ct_topbar_links_hover_color', 'Top Bar Menu Hover Color', 'ct_topbar_section', '#CCCCCC', '#FFB088' );
        $this->add_style_checkboxes( $wp_customize, 'ct_topbar_links', 'Top Bar Menu', 'ct_topbar_section', true, false, true );
        $this->add_range_control( $wp_customize, 'ct_topbar_links_margin_left', 'Top Bar Menu Margin Left', 'ct_topbar_section', 0, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_topbar_links_margin_right', 'Top Bar Menu Margin Right', 'ct_topbar_section', 0, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_topbar_links_margin_top', 'Top Bar Menu Margin Top', 'ct_topbar_section', 0, 0, 100 );
    }

    private function register_header_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_header_section', array(
            'title' => __( 'Header', 'ct-custom' ),
            'panel' => 'ct_theme_panel',
            'priority' => 20,
        ) );

        $this->add_color_control_pair( $wp_customize, 'ct_header_bg_color', 'Background Color', 'ct_header_section', '#FFFFFF', '#1A1A2E' );
        $this->add_color_control_pair( $wp_customize, 'ct_header_border_color', 'Border Color', 'ct_header_section', '#EEEEEE', '#2A2A3E' );
        $this->add_color_control_pair( $wp_customize, 'ct_site_title_color', 'Site Title Color', 'ct_header_section', '#333333', '#E0E0E0' );
    }

    private function register_menu_top_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_menu_top_section', array(
            'title' => __( 'Main Menu - Top Level', 'ct-custom' ),
            'panel' => 'ct_theme_panel',
            'priority' => 30,
        ) );

        $this->add_range_control( $wp_customize, 'ct_menu_top_font_size', 'Font Size', 'ct_menu_top_section', 14, 10, 30 );
        $this->add_color_control_pair( $wp_customize, 'ct_menu_top_color', 'Text Color', 'ct_menu_top_section', '#333333', '#E0E0E0' );
        $this->add_style_checkboxes( $wp_customize, 'ct_menu_top', 'Menu', 'ct_menu_top_section', false, false, true );
        $this->add_range_control( $wp_customize, 'ct_menu_top_margin_left', 'Margin Left', 'ct_menu_top_section', 10, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_menu_top_margin_right', 'Margin Right', 'ct_menu_top_section', 10, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_menu_top_margin_top', 'Margin Top', 'ct_menu_top_section', 0, 0, 100 );
        $this->add_color_control_pair( $wp_customize, 'ct_menu_active_underline_color', 'Active Underline Color', 'ct_menu_top_section', '#FF6B35', '#FF8C5A' );
    }

    private function register_menu_sub_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_menu_sub_section', array(
            'title' => __( 'Main Menu - Submenus', 'ct-custom' ),
            'panel' => 'ct_theme_panel',
            'priority' => 40,
        ) );

        $this->add_range_control( $wp_customize, 'ct_menu_sub_font_size', 'Font Size', 'ct_menu_sub_section', 13, 10, 30 );
        $this->add_color_control_pair( $wp_customize, 'ct_menu_sub_color', 'Text Color', 'ct_menu_sub_section', '#333333', '#E0E0E0' );
        $this->add_style_checkboxes( $wp_customize, 'ct_menu_sub', 'Submenu', 'ct_menu_sub_section', false, false, true );
        $this->add_range_control( $wp_customize, 'ct_menu_sub_margin_left', 'Margin Left', 'ct_menu_sub_section', 0, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_menu_sub_margin_right', 'Margin Right', 'ct_menu_sub_section', 0, 0, 100 );
        $this->add_range_control( $wp_customize, 'ct_menu_sub_margin_top', 'Margin Top', 'ct_menu_sub_section', 0, 0, 100 );

        $this->add_color_control_pair( $wp_customize, 'ct_menu_sub_border_color', 'Border Color', 'ct_menu_sub_section', '#CCCCCC', '#3A3A4E' );
        $this->add_range_control( $wp_customize, 'ct_menu_sub_border_width', 'Border Width', 'ct_menu_sub_section', 1, 0, 10 );

        $wp_customize->add_setting( 'ct_menu_sub_border_style', array(
            'default'           => 'solid',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_menu_sub_border_style', array(
            'label'   => __( 'Border Style', 'ct-custom' ),
            'section' => 'ct_menu_sub_section',
            'type'    => 'select',
            'choices' => array(
                'solid'  => __( 'Solid', 'ct-custom' ),
                'dashed' => __( 'Dashed', 'ct-custom' ),
                'dotted' => __( 'Dotted', 'ct-custom' ),
                'none'   => __( 'None', 'ct-custom' ),
            ),
        ) );

        $this->add_color_control_pair( $wp_customize, 'ct_menu_sub_bg_color', 'Submenu Background', 'ct_menu_sub_section', '#FFFFFF', '#242438' );
        $this->add_color_control_pair( $wp_customize, 'ct_menu_sub_hover_bg_color', 'Submenu Hover Background', 'ct_menu_sub_section', '#F7F7F7', '#2E2E44' );
    }

    private function register_mobile_menu_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_mobile_menu_section', array(
            'title'    => __( 'Mobile Menu', 'ct-custom' ),
            'panel'    => 'ct_theme_panel',
            'priority' => 45,
        ) );

        $this->add_color_control_pair( $wp_customize, 'ct_mobile_menu_bg_color', 'Background Color', 'ct_mobile_menu_section', '#FFFFFF', '#1A1A2E' );
        $this->add_color_control_pair( $wp_customize, 'ct_mobile_menu_border_color', 'Border Color', 'ct_mobile_menu_section', '#EEEEEE', '#2A2A3E' );
        $this->add_range_control( $wp_customize, 'ct_mobile_menu_border_width', 'Border Width', 'ct_mobile_menu_section', 1, 0, 5 );
    }

    private function register_breadcrumb_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_breadcrumb_section', array(
            'title' => __( 'Breadcrumbs', 'ct-custom' ),
            'panel' => 'ct_theme_panel',
            'priority' => 50,
        ) );

        $this->add_range_control( $wp_customize, 'ct_breadcrumb_font_size', 'Font Size', 'ct_breadcrumb_section', 14, 10, 24 );

        $wp_customize->add_setting( 'ct_breadcrumb_transform', array(
            'default'           => 'none',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_breadcrumb_transform', array(
            'label'   => __( 'Text Transform', 'ct-custom' ),
            'section' => 'ct_breadcrumb_section',
            'type'    => 'select',
            'choices' => array(
                'none'       => __( 'None', 'ct-custom' ),
                'capitalize' => __( 'Capitalize', 'ct-custom' ),
                'uppercase'  => __( 'Uppercase', 'ct-custom' ),
                'lowercase'  => __( 'Lowercase', 'ct-custom' ),
            ),
        ) );

        $this->add_color_control_pair( $wp_customize, 'ct_breadcrumb_color', 'Link Color', 'ct_breadcrumb_section', '#999999', '#888888' );
        $this->add_color_control_pair( $wp_customize, 'ct_breadcrumb_active_color', 'Active Color', 'ct_breadcrumb_section', '#333333', '#E0E0E0' );

        $wp_customize->add_setting( 'ct_breadcrumb_active_bold', array(
            'default'           => true,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_breadcrumb_active_bold', array(
            'label'   => __( 'Active Bold', 'ct-custom' ),
            'section' => 'ct_breadcrumb_section',
            'type'    => 'checkbox',
        ) );

        $wp_customize->add_setting( 'ct_breadcrumb_active_underline', array(
            'default'           => false,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_breadcrumb_active_underline', array(
            'label'   => __( 'Active Underline', 'ct-custom' ),
            'section' => 'ct_breadcrumb_section',
            'type'    => 'checkbox',
        ) );
    }

    private function register_body_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_body_section', array(
            'title'    => __( 'Body / Background', 'ct-custom' ),
            'panel'    => 'ct_theme_panel',
            'priority' => 55,
        ) );

        $this->add_color_control_pair( $wp_customize, 'ct_body_bg_color', 'Background Color', 'ct_body_section', '#FFFFFF', '#16162A' );
    }

    private function register_footer_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_footer_section', array(
            'title'    => __( 'Footer', 'ct-custom' ),
            'panel'    => 'ct_theme_panel',
            'priority' => 60,
        ) );

        $this->add_color_control_pair( $wp_customize, 'ct_footer_bg_color', 'Background Color', 'ct_footer_section', '#333333', '#0D0D1A' );
        $this->add_color_control_pair( $wp_customize, 'ct_footer_text_color', 'Text Color', 'ct_footer_section', '#999999', '#888888' );
        $this->add_color_control_pair( $wp_customize, 'ct_footer_link_color', 'Link Color', 'ct_footer_section', '#CCCCCC', '#BBBBBB' );
        $this->add_color_control_pair( $wp_customize, 'ct_footer_link_hover_color', 'Link Hover Color', 'ct_footer_section', '#FFFFFF', '#FFFFFF' );

        $wp_customize->add_setting( 'ct_footer_columns', array(
            'default'           => 3,
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ) );
        $wp_customize->add_control( 'ct_footer_columns', array(
            'label'   => __( 'Footer Columns', 'ct-custom' ),
            'section' => 'ct_footer_section',
            'type'    => 'select',
            'choices' => array(
                2 => __( '2 Columns', 'ct-custom' ),
                3 => __( '3 Columns', 'ct-custom' ),
                4 => __( '4 Columns', 'ct-custom' ),
                5 => __( '5 Columns', 'ct-custom' ),
            ),
        ) );
    }

    private function register_form_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_form_section', array(
            'title'    => __( 'Forms', 'ct-custom' ),
            'panel'    => 'ct_theme_panel',
            'priority' => 65,
        ) );

        $this->add_color_control_pair( $wp_customize, 'ct_form_input_bg_color', 'Input Background', 'ct_form_section', '#FFFFFF', '#1E1E32' );
        $this->add_color_control_pair( $wp_customize, 'ct_form_input_border_color', 'Input Border Color', 'ct_form_section', '#DDDDDD', '#3A3A4E' );
        $this->add_color_control_pair( $wp_customize, 'ct_form_submit_hover_color', 'Submit Hover Color', 'ct_form_section', '#E55A28', '#C44A1E' );
    }

    private function register_social_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_social_section', array(
            'title'    => __( 'Social Icons', 'ct-custom' ),
            'panel'    => 'ct_theme_panel',
            'priority' => 70,
        ) );

        $this->add_color_control_pair( $wp_customize, 'ct_social_bg_color', 'Icon Background', 'ct_social_section', '#888888', '#555566' );

        $this->add_range_control( $wp_customize, 'ct_social_icon_width', 'Icon Width', 'ct_social_section', 36, 12, 128 );
        $this->add_range_control( $wp_customize, 'ct_social_icon_height', 'Icon Height', 'ct_social_section', 36, 12, 128 );

        /* Share with Friend toggle */
        $wp_customize->add_setting( 'ct_social_share_enabled', array(
            'default'           => true,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'refresh',
        ) );
        $wp_customize->add_control( new ToggleSwitchControl( $wp_customize, 'ct_social_share_enabled', array(
            'label'   => __( 'Share with Friend', 'ct-custom' ),
            'section' => 'ct_social_section',
        ) ) );

        /* Social Networks repeater (stored as option, not theme_mod) */
        $wp_customize->add_setting( 'ct_custom_social_networks', array(
            'type'              => 'option',
            'default'           => '[]',
            'sanitize_callback' => array( $this, 'sanitize_social_networks' ),
            'transport'         => 'postMessage',
        ) );

        $wp_customize->add_control( new SocialNetworksControl( $wp_customize, 'ct_custom_social_networks', array(
            'label'       => __( 'Social Networks', 'ct-custom' ),
            'description' => __( 'Add, edit, or remove social network icons.', 'ct-custom' ),
            'section'     => 'ct_social_section',
            'priority'    => 20,
        ) ) );
    }

    private function register_contact_point_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_contact_point_section', array(
            'title'    => __( 'Contact Point', 'ct-custom' ),
            'panel'    => 'ct_theme_panel',
            'priority' => 71,
        ) );

        $wp_customize->add_setting( 'ct_custom_contact_point', array(
            'type'              => 'option',
            'default'           => '{}',
            'sanitize_callback' => array( $this, 'sanitize_contact_point' ),
            'transport'         => 'postMessage',
        ) );

        $wp_customize->add_control( new ContactPointControl( $wp_customize, 'ct_custom_contact_point', array(
            'label'       => __( 'Contact Point', 'ct-custom' ),
            'description' => __( 'Manage contact details: phone, fax, email, address.', 'ct-custom' ),
            'section'     => 'ct_contact_point_section',
        ) ) );
    }

    private function register_back_to_top_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_back_to_top_section', array(
            'title'    => __( 'Back to Top', 'ct-custom' ),
            'panel'    => 'ct_theme_panel',
            'priority' => 72,
        ) );

        /* Enable / Disable */
        $wp_customize->add_setting( 'ct_back_to_top_enabled', array(
            'default'           => true,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_back_to_top_enabled', array(
            'label'   => __( 'Enable Back to Top', 'ct-custom' ),
            'section' => 'ct_back_to_top_section',
            'type'    => 'checkbox',
        ) );

        /* Button Label */
        $wp_customize->add_setting( 'ct_back_to_top_label', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_back_to_top_label', array(
                'label'   => __( 'Button Label', 'ct-custom' ),
                'section' => 'ct_back_to_top_section',
            ) )
        );

        /* Custom Icon */
        $wp_customize->add_setting( 'ct_back_to_top_icon', array(
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( new \WP_Customize_Media_Control( $wp_customize, 'ct_back_to_top_icon', array(
            'label'     => __( 'Custom Icon', 'ct-custom' ),
            'section'   => 'ct_back_to_top_section',
            'mime_type' => 'image',
        ) ) );

        $this->add_color_control_pair( $wp_customize, 'ct_back_to_top_bg_color', 'Background Color', 'ct_back_to_top_section', '#FF6B35', '#D45A2B' );
        $this->add_color_control_pair( $wp_customize, 'ct_back_to_top_border_color', 'Border Color', 'ct_back_to_top_section', '#E5E5E5', '#333333' );
        $this->add_range_control( $wp_customize, 'ct_back_to_top_border_width', 'Border Width', 'ct_back_to_top_section', 1, 0, 5 );
        $this->add_range_control( $wp_customize, 'ct_back_to_top_border_radius', 'Border Radius', 'ct_back_to_top_section', 8, 0, 50 );

        $wp_customize->add_setting( 'ct_back_to_top_position', array(
            'default'           => 'right',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_back_to_top_position', array(
            'label'   => __( 'Position', 'ct-custom' ),
            'section' => 'ct_back_to_top_section',
            'type'    => 'radio',
            'choices' => array(
                'left'  => __( 'Left', 'ct-custom' ),
                'right' => __( 'Right', 'ct-custom' ),
            ),
        ) );
    }

    private function register_email_template_section( $wp_customize ) {
        assert( $wp_customize instanceof \WP_Customize_Manager, 'Must receive WP_Customize_Manager' );
        assert( is_object( $wp_customize ), 'Customizer must be an object' );

        $section = 'ct_email_template_section';

        $wp_customize->add_section( $section, array(
            'title'    => __( 'Email Template', 'ct-custom' ),
            'panel'    => 'ct_theme_panel',
            'priority' => 73,
        ) );

        /* Title Font Size */
        $this->add_range_control( $wp_customize, 'ct_email_title_font_size', 'Title Font Size', $section, 24, 14, 48 );

        /* Title Color (light/dark) */
        $this->add_color_control_pair( $wp_customize, 'ct_email_title_color', 'Title Color', $section, '#333333', '#E0E0E0' );

        /* Title Bold */
        $wp_customize->add_setting( 'ct_email_title_bold', array(
            'default'           => true,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_email_title_bold', array(
            'label'   => __( 'Title Bold', 'ct-custom' ),
            'section' => $section,
            'type'    => 'checkbox',
        ) );

        /* Title Transform */
        $this->add_transform_select( $wp_customize, 'ct_email_title_transform', 'Title Transform', $section, 'none' );

        /* Text Font Size */
        $this->add_range_control( $wp_customize, 'ct_email_text_font_size', 'Text Font Size', $section, 15, 12, 24 );

        /* Text Color (light/dark) */
        $this->add_color_control_pair( $wp_customize, 'ct_email_text_color', 'Text Color', $section, '#555555', '#B0B0B0' );

        /* Text Line Height */
        $wp_customize->add_setting( 'ct_email_text_line_height', array(
            'default'           => 1.6,
            'sanitize_callback' => array( $this, 'sanitize_float' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( new RangeControl( $wp_customize, 'ct_email_text_line_height', array(
            'label'       => __( 'Text Line Height', 'ct-custom' ),
            'section'     => $section,
            'input_attrs' => array(
                'min'  => 1.0,
                'max'  => 3.0,
                'step' => 0.1,
            ),
        ) ) );

        /* Border Color (light/dark) */
        $this->add_color_control_pair( $wp_customize, 'ct_email_border_color', 'Border Color', $section, '#E5E5E5', '#333333' );

        /* Background Color (light/dark) */
        $this->add_color_control_pair( $wp_customize, 'ct_email_bg_color', 'Background Color', $section, '#FFFFFF', '#1A1A2E' );

        /* Accent Color (light/dark) */
        $this->add_color_control_pair( $wp_customize, 'ct_email_accent_color', 'Accent Color', $section, '#FF6B35', '#FF8C5A' );
    }

    /**
     * Language Menus panel -- one section per enabled language,
     * each with dropdowns to assign menus to that language's locations.
     */
    private function register_language_menus_panel( $wp_customize ) {
        assert( $wp_customize instanceof \WP_Customize_Manager, 'Must receive WP_Customize_Manager' );

        if ( ! function_exists( 'ct_get_language_manager' ) ) {
            return;
        }

        $mgr       = ct_get_language_manager();
        $languages = $mgr->get_enabled();

        if ( count( $languages ) < 2 ) {
            return;
        }

        $wp_customize->add_panel( 'ct_language_menus_panel', array(
            'title'    => __( 'Language Menus', 'ct-custom' ),
            'priority' => 2,
        ) );

        $nav_menus    = wp_get_nav_menus();
        $menu_choices = array( 0 => __( '--- Select ---', 'ct-custom' ) );
        $max_menus    = 200;
        $menu_count   = 0;

        foreach ( $nav_menus as $menu ) {
            if ( $menu_count >= $max_menus ) {
                break;
            }
            $menu_count++;
            $menu_choices[ $menu->term_id ] = $menu->name;
        }

        $base_locations = array(
            'main-menu'             => __( 'Main Menu', 'ct-custom' ),
            'top-bar-menu'          => __( 'Top Bar Menu', 'ct-custom' ),
            'footer-copyright-menu' => __( 'Footer Copyright Menu', 'ct-custom' ),
        );

        $max_langs  = 50;
        $lang_count = 0;

        foreach ( $languages as $lang ) {
            if ( $lang_count >= $max_langs ) {
                break;
            }
            $lang_count++;

            $iso2       = sanitize_key( $lang['iso2'] );
            $section_id = 'ct_lang_menu_' . $iso2;

            $wp_customize->add_section( $section_id, array(
                'title' => $lang['native_name'],
                'panel' => 'ct_language_menus_panel',
            ) );

            $base_count = 0;

            foreach ( $base_locations as $base_slug => $base_label ) {
                if ( $base_count >= 3 ) {
                    break;
                }
                $base_count++;

                $setting_id = 'nav_menu_locations[' . $base_slug . '-' . $iso2 . ']';

                $wp_customize->add_setting( $setting_id, array(
                    'default'           => 0,
                    'type'              => 'theme_mod',
                    'sanitize_callback' => 'absint',
                ) );

                $wp_customize->add_control( $setting_id, array(
                    'label'   => $base_label,
                    'section' => $section_id,
                    'type'    => 'select',
                    'choices' => $menu_choices,
                ) );
            }
        }
    }

    private function register_pages_panel( $wp_customize ) {
        $wp_customize->add_panel( 'ct_pages_panel', array(
            'title'    => __( 'Pages', 'ct-custom' ),
            'priority' => 3,
        ) );

        $this->register_page_homepage_section( $wp_customize );
        $this->register_page_contact_section( $wp_customize );
    }

    private function register_page_homepage_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_page_homepage_section', array(
            'title'    => __( 'Homepage', 'ct-custom' ),
            'panel'    => 'ct_pages_panel',
            'priority' => 10,
        ) );

        /* Hero Title */
        $wp_customize->add_setting( 'ct_hero_title', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_hero_title', array(
                'label'   => __( 'Hero Title', 'ct-custom' ),
                'section' => 'ct_page_homepage_section',
            ) )
        );

        /* Hero Description */
        $wp_customize->add_setting( 'ct_hero_description', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_textarea_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_hero_description', array(
                'label'      => __( 'Hero Description', 'ct-custom' ),
                'section'    => 'ct_page_homepage_section',
                'input_type' => 'textarea',
            ) )
        );

        /* 4 Images with Alt and Title */
        $max_images = 4;
        for ( $i = 1; $i <= $max_images; $i++ ) {
            $wp_customize->add_setting( 'ct_hero_image_' . $i, array(
                'default'           => 0,
                'sanitize_callback' => 'absint',
                'transport'         => 'postMessage',
            ) );
            $wp_customize->add_control( new \WP_Customize_Media_Control( $wp_customize, 'ct_hero_image_' . $i, array(
                'label'     => sprintf( __( 'Image %d', 'ct-custom' ), $i ),
                'section'   => 'ct_page_homepage_section',
                'mime_type' => 'image',
            ) ) );

            $wp_customize->add_setting( 'ct_hero_image_' . $i . '_alt', array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'transport'         => 'postMessage',
            ) );
            $wp_customize->add_control(
                new TranslationControl( $wp_customize, 'ct_hero_image_' . $i . '_alt', array(
                    'label'   => sprintf( __( 'Image %d Alt Text', 'ct-custom' ), $i ),
                    'section' => 'ct_page_homepage_section',
                ) )
            );

            $wp_customize->add_setting( 'ct_hero_image_' . $i . '_title', array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'transport'         => 'postMessage',
            ) );
            $wp_customize->add_control(
                new TranslationControl( $wp_customize, 'ct_hero_image_' . $i . '_title', array(
                    'label'   => sprintf( __( 'Image %d Title', 'ct-custom' ), $i ),
                    'section' => 'ct_page_homepage_section',
                ) )
            );

            $wp_customize->add_setting( 'ct_hero_image_' . $i . '_url', array(
                'default'           => '',
                'sanitize_callback' => 'esc_url_raw',
                'transport'         => 'postMessage',
            ) );
            $wp_customize->add_control( 'ct_hero_image_' . $i . '_url', array(
                'label'   => sprintf( __( 'Image %d URL', 'ct-custom' ), $i ),
                'section' => 'ct_page_homepage_section',
                'type'    => 'url',
            ) );
        }

        /* Section 2 Title */
        $wp_customize->add_setting( 'ct_section2_title', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_section2_title', array(
                'label'   => __( 'Second Title', 'ct-custom' ),
                'section' => 'ct_page_homepage_section',
            ) )
        );

        /* Section 2 Description */
        $wp_customize->add_setting( 'ct_section2_description', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_textarea_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_section2_description', array(
                'label'      => __( 'Second Description', 'ct-custom' ),
                'section'    => 'ct_page_homepage_section',
                'input_type' => 'textarea',
            ) )
        );
    }

    private function register_page_contact_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_page_contact_section', array(
            'title'    => __( 'Contact', 'ct-custom' ),
            'panel'    => 'ct_pages_panel',
            'priority' => 20,
        ) );

        /* Contact Heading */
        $wp_customize->add_setting( 'ct_contact_heading', array(
            'default'           => 'Contact',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_contact_heading', array(
                'label'   => __( 'Heading', 'ct-custom' ),
                'section' => 'ct_page_contact_section',
            ) )
        );

        /* Contact Content */
        $wp_customize->add_setting( 'ct_contact_content', array(
            'default'           => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam posuere ipsum nec velit mattis elementum. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Maecenas eu placerat metus, eget placerat libero.',
            'sanitize_callback' => 'sanitize_textarea_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_contact_content', array(
                'label'      => __( 'Content', 'ct-custom' ),
                'section'    => 'ct_page_contact_section',
                'input_type' => 'textarea',
            ) )
        );

        /* Reach Us Title */
        $wp_customize->add_setting( 'ct_reach_us_title', array(
            'default'           => 'REACH US',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_reach_us_title', array(
                'label'   => __( 'Reach Us Title', 'ct-custom' ),
                'section' => 'ct_page_contact_section',
            ) )
        );

        /* Contact Us Title */
        $wp_customize->add_setting( 'ct_contact_us_title', array(
            'default'           => 'CONTACT US',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control(
            new TranslationControl( $wp_customize, 'ct_contact_us_title', array(
                'label'   => __( 'Contact Us Title', 'ct-custom' ),
                'section' => 'ct_page_contact_section',
            ) )
        );
    }

    /**
     * Typography: one panel with nested sections for H1-H5, Paragraphs, Special.
     */
    private function register_typography_sections( $wp_customize ) {
        $wp_customize->add_panel( 'ct_typography_panel', array(
            'title'    => __( 'Typography', 'ct-custom' ),
            'priority' => 2,
        ) );

        $this->register_web_fonts_section( $wp_customize );
        $this->register_heading_level_section( $wp_customize, 'ct_h1', 'H1', 36, '#FF6B35', '#FF8C5A', true, false, 'uppercase', 10 );
        $this->register_heading_level_section( $wp_customize, 'ct_h2', 'H2', 30, '#FF6B35', '#FF8C5A', true, false, 'uppercase', 20 );
        $this->register_heading_level_section( $wp_customize, 'ct_h3', 'H3', 24, '#FF6B35', '#FF8C5A', true, false, 'none', 30 );
        $this->register_heading_level_section( $wp_customize, 'ct_h4', 'H4', 20, '#333333', '#D0D0D0', true, false, 'none', 40 );
        $this->register_heading_level_section( $wp_customize, 'ct_h5', 'H5', 18, '#333333', '#D0D0D0', true, false, 'none', 50 );
        $this->register_paragraph_section( $wp_customize );
        $this->register_special_section( $wp_customize );
    }

    /**
     * Web Fonts section inside the Typography panel.
     */
    private function register_web_fonts_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_web_fonts_section', array(
            'title'    => __( 'Web Fonts', 'ct-custom' ),
            'panel'    => 'ct_typography_panel',
            'priority' => 1,
        ) );

        /* Enable Web Fonts */
        $wp_customize->add_setting( 'ct_font_enabled', array(
            'default'           => false,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( new ToggleSwitchControl( $wp_customize, 'ct_font_enabled', array(
            'label'   => __( 'Enable Web Fonts', 'ct-custom' ),
            'section' => 'ct_web_fonts_section',
        ) ) );

        /* Font Family */
        $wp_customize->add_setting( 'ct_font_family', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( new FontFamilyControl( $wp_customize, 'ct_font_family', array(
            'label'       => __( 'Font Family', 'ct-custom' ),
            'description' => __( 'Select a Google Font.', 'ct-custom' ),
            'section'     => 'ct_web_fonts_section',
        ) ) );

        /* Font Weights */
        $wp_customize->add_setting( 'ct_font_weights', array(
            'default'           => '400',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( new FontWeightsControl( $wp_customize, 'ct_font_weights', array(
            'label'       => __( 'Font Weights', 'ct-custom' ),
            'description' => __( 'Select which weights to download.', 'ct-custom' ),
            'section'     => 'ct_web_fonts_section',
        ) ) );

        /* Hidden setting to store generated @font-face CSS */
        $wp_customize->add_setting( 'ct_font_face_css', array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post',
            'transport'         => 'postMessage',
        ) );

        /* Hidden setting to track previous font family for cleanup */
        $wp_customize->add_setting( 'ct_font_prev_family', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
    }

    /**
     * Register a heading level section (h1-h5).
     */
    private function register_heading_level_section( $wp_customize, $prefix, $label, $size_default, $color_default, $color_dark_default, $bold_default, $italic_default, $transform_default, $priority ) {
        $section_id = $prefix . '_section';

        $wp_customize->add_section( $section_id, array(
            'title'    => sprintf( __( '%s Heading', 'ct-custom' ), $label ),
            'panel'    => 'ct_typography_panel',
            'priority' => $priority,
        ) );

        $this->add_range_control( $wp_customize, $prefix . '_font_size', 'Font Size', $section_id, $size_default, 10, 80 );
        $this->add_color_control_pair( $wp_customize, $prefix . '_color', 'Color', $section_id, $color_default, $color_dark_default );

        $wp_customize->add_setting( $prefix . '_bold', array(
            'default'           => $bold_default,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( $prefix . '_bold', array(
            'label'   => __( 'Bold', 'ct-custom' ),
            'section' => $section_id,
            'type'    => 'checkbox',
        ) );

        $wp_customize->add_setting( $prefix . '_italic', array(
            'default'           => $italic_default,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( $prefix . '_italic', array(
            'label'   => __( 'Italic', 'ct-custom' ),
            'section' => $section_id,
            'type'    => 'checkbox',
        ) );

        $this->add_transform_select( $wp_customize, $prefix . '_transform', 'Text Transform', $section_id, $transform_default );
    }

    private function register_paragraph_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_paragraph_section', array(
            'title'    => __( 'Paragraphs', 'ct-custom' ),
            'panel'    => 'ct_typography_panel',
            'priority' => 60,
        ) );

        $this->add_range_control( $wp_customize, 'ct_paragraph_font_size', 'Font Size', 'ct_paragraph_section', 16, 12, 24 );
        $this->add_color_control_pair( $wp_customize, 'ct_paragraph_color', 'Color', 'ct_paragraph_section', '#666666', '#B0B0B0' );

        $wp_customize->add_setting( 'ct_paragraph_bold', array(
            'default'           => false,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_paragraph_bold', array(
            'label'   => __( 'Bold', 'ct-custom' ),
            'section' => 'ct_paragraph_section',
            'type'    => 'checkbox',
        ) );

        $wp_customize->add_setting( 'ct_paragraph_italic', array(
            'default'           => false,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_paragraph_italic', array(
            'label'   => __( 'Italic', 'ct-custom' ),
            'section' => 'ct_paragraph_section',
            'type'    => 'checkbox',
        ) );

        $this->add_transform_select( $wp_customize, 'ct_paragraph_transform', 'Text Transform', 'ct_paragraph_section', 'none' );

        /* Line height */
        $wp_customize->add_setting( 'ct_paragraph_line_height', array(
            'default'           => 1.6,
            'sanitize_callback' => array( $this, 'sanitize_float' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( new RangeControl( $wp_customize, 'ct_paragraph_line_height', array(
            'label'       => __( 'Line Height', 'ct-custom' ),
            'section'     => 'ct_paragraph_section',
            'input_attrs' => array(
                'min'  => 1.0,
                'max'  => 3.0,
                'step' => 0.1,
            ),
        ) ) );

        /* Margins */
        $this->add_range_control( $wp_customize, 'ct_paragraph_margin_top', 'Margin Top', 'ct_paragraph_section', 0, 0, 60 );
        $this->add_range_control( $wp_customize, 'ct_paragraph_margin_right', 'Margin Right', 'ct_paragraph_section', 0, 0, 60 );
        $this->add_range_control( $wp_customize, 'ct_paragraph_margin_bottom', 'Margin Bottom', 'ct_paragraph_section', 16, 0, 60 );
        $this->add_range_control( $wp_customize, 'ct_paragraph_margin_left', 'Margin Left', 'ct_paragraph_section', 0, 0, 60 );
    }

    private function register_special_section( $wp_customize ) {
        $wp_customize->add_section( 'ct_special_section', array(
            'title'    => __( 'Special Text', 'ct-custom' ),
            'panel'    => 'ct_typography_panel',
            'priority' => 70,
        ) );

        $this->add_range_control( $wp_customize, 'ct_special_font_size', 'Font Size', 'ct_special_section', 16, 12, 24 );
        $this->add_color_control_pair( $wp_customize, 'ct_special_color', 'Color', 'ct_special_section', '#333333', '#D0D0D0' );

        $wp_customize->add_setting( 'ct_special_bold', array(
            'default'           => true,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_special_bold', array(
            'label'   => __( 'Bold', 'ct-custom' ),
            'section' => 'ct_special_section',
            'type'    => 'checkbox',
        ) );

        $wp_customize->add_setting( 'ct_special_italic', array(
            'default'           => false,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( 'ct_special_italic', array(
            'label'   => __( 'Italic', 'ct-custom' ),
            'section' => 'ct_special_section',
            'type'    => 'checkbox',
        ) );

        $this->add_transform_select( $wp_customize, 'ct_special_transform', 'Text Transform', 'ct_special_section', 'none' );
    }

    /* Helper: add range control */
    private function add_range_control( $wp_customize, $id, $label, $section, $default, $min, $max ) {
        $wp_customize->add_setting( $id, array(
            'default'           => $default,
            'sanitize_callback' => 'absint',
            'transport'         => 'postMessage',
        ) );

        $wp_customize->add_control( new RangeControl( $wp_customize, $id, array(
            'label'       => __( $label, 'ct-custom' ),
            'section'     => $section,
            'input_attrs' => array(
                'min'  => $min,
                'max'  => $max,
                'step' => 1,
            ),
        ) ) );
    }

    /* Helper: style checkboxes (bold + italic + uppercase) */
    private function add_style_checkboxes( $wp_customize, $prefix, $label, $section, $bold_default, $italic_default, $uppercase_default ) {
        $wp_customize->add_setting( $prefix . '_bold', array(
            'default'           => $bold_default,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( $prefix . '_bold', array(
            'label'   => sprintf( __( '%s Bold', 'ct-custom' ), $label ),
            'section' => $section,
            'type'    => 'checkbox',
        ) );

        $wp_customize->add_setting( $prefix . '_italic', array(
            'default'           => $italic_default,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( $prefix . '_italic', array(
            'label'   => sprintf( __( '%s Italic', 'ct-custom' ), $label ),
            'section' => $section,
            'type'    => 'checkbox',
        ) );

        $wp_customize->add_setting( $prefix . '_uppercase', array(
            'default'           => $uppercase_default,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( $prefix . '_uppercase', array(
            'label'   => sprintf( __( '%s Uppercase', 'ct-custom' ), $label ),
            'section' => $section,
            'type'    => 'checkbox',
        ) );
    }

    /* Helper: text transform select */
    private function add_transform_select( $wp_customize, $id, $label, $section, $default ) {
        $wp_customize->add_setting( $id, array(
            'default'           => $default,
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );

        $wp_customize->add_control( $id, array(
            'label'   => __( $label, 'ct-custom' ),
            'section' => $section,
            'type'    => 'select',
            'choices' => array(
                'none'       => __( 'None', 'ct-custom' ),
                'capitalize' => __( 'Capitalize', 'ct-custom' ),
                'uppercase'  => __( 'Uppercase', 'ct-custom' ),
                'lowercase'  => __( 'Lowercase', 'ct-custom' ),
            ),
        ) );
    }

    public function sanitize_checkbox( $value ) {
        return ( isset( $value ) && true == $value ) ? true : false;
    }

    public function sanitize_float( $value ) {
        return floatval( $value );
    }

    public function sanitize_contact_point( $value ) {
        assert( is_string( $value ), 'Contact point value must be a string' );

        $decoded = json_decode( $value, true );

        if ( ! is_array( $decoded ) ) {
            return '{}';
        }

        $sanitized = array(
            'telephone'    => isset( $decoded['telephone'] ) ? sanitize_text_field( $decoded['telephone'] ) : '',
            'fax_number'   => isset( $decoded['fax_number'] ) ? sanitize_text_field( $decoded['fax_number'] ) : '',
            'email'        => isset( $decoded['email'] ) ? sanitize_email( $decoded['email'] ) : '',
            'contact_type' => isset( $decoded['contact_type'] ) ? sanitize_text_field( $decoded['contact_type'] ) : '',
        );

        $address_fields = array( 'street_address', 'street_number', 'city', 'state', 'postal_code', 'country' );
        $address        = array();

        if ( isset( $decoded['address'] ) && is_array( $decoded['address'] ) ) {
            $max_fields = 6;
            $count      = 0;
            foreach ( $address_fields as $field ) {
                if ( $count >= $max_fields ) {
                    break;
                }
                $address[ $field ] = isset( $decoded['address'][ $field ] ) ? sanitize_text_field( $decoded['address'][ $field ] ) : '';
                $count++;
            }
        }

        $sanitized['address'] = $address;

        assert( is_array( $sanitized ), 'Sanitized result must be an array' );

        return wp_json_encode( $sanitized );
    }

    public function sanitize_social_networks( $value ) {
        assert( is_string( $value ), 'Social networks value must be a string' );

        $decoded = json_decode( $value, true );

        if ( ! is_array( $decoded ) ) {
            return '[]';
        }

        $sanitized   = array();
        $max_entries = 50;
        $count       = 0;

        foreach ( $decoded as $entry ) {
            if ( $count >= $max_entries ) {
                break;
            }

            if ( ! is_array( $entry ) ) {
                continue;
            }

            $name = isset( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : '';
            $url  = isset( $entry['url'] ) ? esc_url_raw( $entry['url'] ) : '';

            if ( '' === $name || '' === $url ) {
                continue;
            }

            $sanitized[] = array(
                'name'     => $name,
                'url'      => $url,
                'icon_id'  => isset( $entry['icon_id'] ) ? absint( $entry['icon_id'] ) : 0,
                'icon_url' => isset( $entry['icon_url'] ) ? esc_url_raw( $entry['icon_url'] ) : '',
            );

            $count++;
        }

        assert( is_array( $sanitized ), 'Sanitized result must be an array' );

        return wp_json_encode( $sanitized );
    }
}
