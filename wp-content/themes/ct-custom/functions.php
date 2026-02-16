<?php
/**
 * CT Custom Theme - Functions and Definitions
 *
 * @package CT_Custom
 */

/* ── 1. Composer Autoloader (PSR-4 namespaces + PHPMailer, Firebase JWT) ─ */
$ct_autoloader = get_template_directory() . '/vendor/autoload.php';
if ( is_readable( $ct_autoloader ) ) {
    require_once $ct_autoloader;
}

use CTCustom\Theme\ThemeSettings;
use CTCustom\Cpt\ContactMessageCpt;
use CTCustom\Template\TemplateHooks;
use CTCustom\Customizer\ThemeCustomizer;
use CTCustom\Customizer\FontManager;
use CTCustom\Multilang\TranslationService;
use CTCustom\Multilang\LanguagePageManager;
use CTCustom\Multilang\HreflangService;
use CTCustom\Widgets\CompanyInfoWidget;
use CTCustom\Widgets\ContactPointWidget;
use CTCustom\Widgets\SocialIconsWidget;
use CTCustom\Widgets\MenuWidget;
use CTCustom\Widgets\WidgetLanguageFilter;
use CTCustom\Admin\PageLanguageFilter;
use CTCustom\Admin\MenuLanguageFilter;
use CTCustom\RestApi\AuthRestController;
use CTCustom\RestApi\Endpoints\ResolveTranslation;
use CTCustom\RestApi\Endpoints\GetTranslations;
use CTCustom\RestApi\Endpoints\FontDownload;
use CTCustom\Blocks\PageAccessControl;

/* ── 2. Asset Version Helper ────────────────────────────────────────── */

/**
 * Get a cached filemtime-based version string for an asset path.
 *
 * @param string $path Absolute filesystem path to the asset.
 * @return string Version string (filemtime or '1.0.0' fallback).
 */
function ct_get_asset_version( $path ) {
    static $versions = array();

    assert( is_string( $path ), 'Asset path must be a string' );

    if ( isset( $versions[ $path ] ) ) {
        return $versions[ $path ];
    }

    $version = is_readable( $path ) ? (string) filemtime( $path ) : '1.0.0';
    $versions[ $path ] = $version;

    assert( is_string( $version ), 'Version must be a string' );

    return $version;
}

/* ── 3. Boot Converted Procedural Classes ───────────────────────────── */
CTCustom\Customizer\CustomizerSetup::boot();
CTCustom\Customizer\DynamicCss::boot();
CTCustom\Sidebar\SidebarMeta::boot();
PageAccessControl::boot();

/* ── 4. Theme Bootstrap Class ───────────────────────────────────────── */

class CT_Custom_Theme {

    private $admin_page_hook = '';

    /** @var ThemeSettings */
    private $settings;

    public function __construct() {
        $this->settings = new ThemeSettings();

        add_action( 'after_setup_theme', array( $this, 'theme_setup' ) );
        add_action( 'after_setup_theme', array( $this, 'content_width' ), 0 );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_bloat' ), 200 );
        add_action( 'init', array( $this, 'remove_head_bloat' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_widget_scripts' ) );
        add_action( 'widgets_init', array( $this, 'register_widgets' ) );
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'init', array( $this, 'register_pattern_categories' ) );
        add_action( 'init', array( $this, 'register_patterns' ) );
        add_action( 'init', array( $this, 'register_cpts' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_translation_picker' ) );
        add_filter( 'block_categories_all', array( $this, 'register_block_categories' ), 10, 2 );

        if ( wp_doing_ajax() || is_admin() ) {
            $this->register_ajax_handlers();
        }

        add_filter( 'upload_mimes', array( $this, 'allow_svg_upload' ) );
        add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 3 );
    /*     add_filter( 'the_content', array( $this, 'add_paragraph_class' ) ); */
        add_filter( 'the_content', array( $this, 'resolve_block_content_patterns' ), 9 );
        add_filter( 'widget_text', array( $this, 'resolve_translation_patterns' ), 8 );
        add_filter( 'widget_text_content', array( $this, 'resolve_translation_patterns' ), 8 );
        $this->register_theme_mod_translation_filters();
        add_filter( 'pre_get_avatar_data', array( $this, 'override_avatar_with_local' ), 10, 2 );
        add_filter( 'rest_authentication_errors', array( $this, 'allow_ct_auth_cookie_auth' ), 100 );
    }

    public function theme_setup() {
        load_theme_textdomain( 'ct-custom', get_template_directory() . '/languages' );

        add_theme_support( 'automatic-feed-links' );
        add_theme_support( 'title-tag' );
        add_theme_support( 'post-thumbnails' );

        $ct_base_menus = array( 'main-menu', 'top-bar-menu', 'footer-copyright-menu' );
        $ct_menu_locations = array();

        /* Build per-language menu locations dynamically */
        $ct_languages = array();

        if ( function_exists( 'ct_get_language_manager' ) ) {
            $ct_lang_mgr  = ct_get_language_manager();
            $ct_languages = $ct_lang_mgr->get_enabled();
        }

        if ( empty( $ct_languages ) ) {
            $ct_languages = array( array( 'iso2' => 'en', 'native_name' => 'English' ) );
        }

        $ct_menu_count = 0;
        $ct_menu_max   = 50;

        foreach ( $ct_languages as $ct_lang ) {
            if ( $ct_menu_count >= $ct_menu_max ) { break; }
            $ct_menu_count++;

            $ct_base_count = 0;
            foreach ( $ct_base_menus as $ct_base ) {
                if ( $ct_base_count >= 3 ) { break; }
                $ct_base_count++;

                $ct_menu_locations[ $ct_base . '-' . $ct_lang['iso2'] ] = sprintf(
                    '%s (%s)',
                    ucwords( str_replace( '-', ' ', $ct_base ) ),
                    $ct_lang['native_name']
                );
            }
        }

        register_nav_menus( $ct_menu_locations );

        add_theme_support( 'html5', array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
        ) );

        add_theme_support( 'custom-logo', array(
            'height'      => 250,
            'width'       => 250,
            'flex-width'  => true,
            'flex-height' => true,
        ) );
    }

    public function content_width() {
        $GLOBALS['content_width'] = apply_filters( 'ct_custom_content_width', 1200 );
    }

    public function enqueue_frontend_scripts() {
        /* CSS — external file enables browser caching (24 KB saved on repeat visits) */
        $css_path = get_template_directory() . '/assets/frontend/css/app.css';

        wp_enqueue_style(
            'ct-custom-app-css',
            get_template_directory_uri() . '/assets/frontend/css/app.css',
            array(),
            ct_get_asset_version( $css_path )
        );

        /* JS — deferred, non-render-blocking */
        $js_path = get_template_directory() . '/assets/frontend/js/app.js';

        wp_enqueue_script(
            'ct-custom-app-js',
            get_template_directory_uri() . '/assets/frontend/js/app.js',
            array(),
            ct_get_asset_version( $js_path ),
            array( 'strategy' => 'defer', 'in_footer' => true )
        );

        /* Share with Friend — separate script, only when enabled */
        if ( get_theme_mod( 'ct_social_share_enabled', true ) ) {
            $share_js_path = get_template_directory() . '/assets/frontend/js/share.js';

            wp_enqueue_script(
                'ct-custom-share-js',
                get_template_directory_uri() . '/assets/frontend/js/share.js',
                array(),
                ct_get_asset_version( $share_js_path ),
                array( 'strategy' => 'defer', 'in_footer' => true )
            );
        }

        /* Inject current language translations for frontend JS */
        if ( function_exists( 'ct_get_translator' ) ) {
            $translator = ct_get_translator();
            wp_localize_script( 'ct-custom-app-js', 'ctTranslationData', array(
                'iso2'         => function_exists( 'ct_get_current_language' ) ? ct_get_current_language() : 'en',
                'translations' => $translator->get_all_translations(),
            ) );
        }

        /* Auth page — standalone bundle (login-register.php template) */
        if ( is_page_template( 'login-register.php' ) ) {
            $auth_css_path = get_template_directory() . '/assets/frontend-auth/css/auth-app.css';
            wp_enqueue_style(
                'ct-custom-auth-css',
                get_template_directory_uri() . '/assets/frontend-auth/css/auth-app.css',
                array(),
                ct_get_asset_version( $auth_css_path )
            );

            $auth_js_path = get_template_directory() . '/assets/frontend-auth/js/auth-app.js';
            wp_enqueue_script(
                'ct-custom-auth-js',
                get_template_directory_uri() . '/assets/frontend-auth/js/auth-app.js',
                array(),
                ct_get_asset_version( $auth_js_path ),
                array( 'strategy' => 'defer', 'in_footer' => true )
            );
        }

        /* Profile page — standalone bundle (profile.php template) */
        if ( is_page_template( 'profile.php' ) ) {
            $profile_css_path = get_template_directory() . '/assets/frontend-profile/css/profile-app.css';
            wp_enqueue_style(
                'ct-custom-profile-css',
                get_template_directory_uri() . '/assets/frontend-profile/css/profile-app.css',
                array(),
                ct_get_asset_version( $profile_css_path )
            );

            $profile_js_path = get_template_directory() . '/assets/frontend-profile/js/profile-app.js';
            wp_enqueue_script(
                'ct-custom-profile-js',
                get_template_directory_uri() . '/assets/frontend-profile/js/profile-app.js',
                array(),
                ct_get_asset_version( $profile_js_path ),
                array( 'strategy' => 'defer', 'in_footer' => true )
            );
        }
    }

    /**
     * Remove render-blocking WordPress default assets from the frontend.
     */
    public function dequeue_bloat() {
        if ( is_admin() ) {
            return;
        }

        /* Block-library CSS (~40 KB) — only needed if using Gutenberg blocks on frontend */
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );

        /* Classic theme styles — not needed */
        wp_dequeue_style( 'classic-theme-styles' );

        /* Global styles (FSE) — not used in classic themes */
        wp_dequeue_style( 'global-styles' );
    }

    /**
     * Remove unnecessary items from wp_head that add bytes before first paint.
     */
    public function remove_head_bloat() {
        /* Emoji detection script + inline CSS (~20 KB) */
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );

        /* WP 6.4+ emoji loader (wp-emoji-release.min.js) */
        remove_action( 'wp_head', 'wp_emoji_loader_script', 7 );
        remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );

        /* Disable emoji DNS prefetch and static-ize filters */
        add_filter( 'emoji_svg_url', '__return_false' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

        /* RSD link (XML-RPC discovery) */
        remove_action( 'wp_head', 'rsd_link' );

        /* Windows Live Writer manifest */
        remove_action( 'wp_head', 'wlwmanifest_link' );

        /* Shortlink */
        remove_action( 'wp_head', 'wp_shortlink_wp_head' );

        /* WordPress generator meta tag */
        remove_action( 'wp_head', 'wp_generator' );

        /* REST API link in head (still accessible, just not advertised) */
        remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );

        /* oEmbed discovery links */
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );

        /* wp-embed script */
        remove_action( 'wp_head', 'wp_oembed_add_host_js' );
    }

    public function register_admin_menu() {
        $this->admin_page_hook = add_menu_page(
            __( 'CT Custom Theme', 'ct-custom' ),
            __( 'CT Custom Theme', 'ct-custom' ),
            'manage_options',
            'ct-custom-settings',
            array( $this, 'render_admin_page' ),
            'dashicons-admin-customizer',
            61
        );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        include_once get_template_directory() . '/template-admin/template-admin-main.php';
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook ) {
            return;
        }

        wp_enqueue_media();

        $css_file = get_template_directory() . '/assets/backend/css/app.css';
        $js_file  = get_template_directory() . '/assets/backend/js/admin.js';

        wp_enqueue_style(
            'ct-custom-admin-css',
            get_template_directory_uri() . '/assets/backend/css/app.css',
            array(),
            (string) filemtime( $css_file )
        );

        wp_enqueue_script(
            'ct-custom-admin-js',
            get_template_directory_uri() . '/assets/backend/js/admin.js',
            array(),
            (string) filemtime( $js_file ),
            true
        );

        wp_localize_script( 'ct-custom-admin-js', 'wpApiSettings', array(
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ) );
    }

    private function register_ajax_handlers() {
        $actions = array(
            'admin_save_contact_pointers',
            'admin_get_contact_messages_count',
            'admin_export_settings',
            'admin_import_settings',
            'admin_save_languages',
            'admin_add_language',
            'admin_remove_language',
            'admin_set_default_language',
            'admin_update_language',
            'admin_get_translation_keys',
            'admin_save_translation',
            'admin_add_translation_key',
            'admin_delete_translation_key',
            'admin_export_translations',
            'admin_import_translations',
            'admin_get_pages_by_language',
            'admin_save_page_translation',
            'admin_duplicate_page',
            'admin_save_email_config',
            'admin_save_jwt_auth',
        );

        $max_actions = 26;
        $count = 0;

        foreach ( $actions as $action ) {
            if ( $count >= $max_actions ) {
                break;
            }

            add_action( 'wp_ajax_' . $action, array( $this->settings, $action ) );
            $count++;
        }
    }

    public function allow_svg_upload( $mimes ) {
        assert( is_array( $mimes ), 'Mimes must be an array' );
        assert( function_exists( 'current_user_can' ), 'current_user_can must exist' );

        if ( ! current_user_can( 'upload_files' ) ) {
            return $mimes;
        }

        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';

        return $mimes;
    }

    /**
     * Override WordPress avatar with locally uploaded image.
     *
     * Hooks into pre_get_avatar_data so get_avatar() and get_avatar_url()
     * return the user's uploaded photo stored as ct_avatar_id user meta.
     *
     * @param array $args        Avatar data arguments.
     * @param mixed $id_or_email User ID, email, WP_User, or WP_Comment.
     * @return array Modified avatar data.
     */
    public function override_avatar_with_local( $args, $id_or_email ) {
        assert( is_array( $args ), 'Args must be an array' );

        $user_id = 0;

        if ( is_numeric( $id_or_email ) ) {
            $user_id = (int) $id_or_email;
        } elseif ( $id_or_email instanceof WP_User ) {
            $user_id = $id_or_email->ID;
        } elseif ( $id_or_email instanceof WP_Comment ) {
            $user_id = (int) $id_or_email->user_id;
        } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
            $user = get_user_by( 'email', $id_or_email );
            if ( $user ) {
                $user_id = $user->ID;
            }
        }

        if ( $user_id <= 0 ) {
            return $args;
        }

        $avatar_id = (int) get_user_meta( $user_id, 'ct_avatar_id', true );

        if ( $avatar_id <= 0 ) {
            return $args;
        }

        $url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );

        if ( $url ) {
            $args['url']          = $url;
            $args['found_avatar'] = true;
        }

        return $args;
    }

    /**
     * Allow cookie-based auth for ct-auth REST endpoints even when the
     * page nonce is stale.
     *
     * @param WP_Error|null|true $result Existing authentication result.
     * @return WP_Error|null|true
     */
    public function allow_ct_auth_cookie_auth( $result ) {
        if ( ! is_wp_error( $result ) ) {
            return $result;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $rest_prefix = rest_get_url_prefix();

        /* Only apply to our ct-auth/v1 namespace */
        if ( false === strpos( $request_uri, $rest_prefix . '/ct-auth/v1/' ) &&
             false === strpos( $request_uri, '?rest_route=/ct-auth/v1/' ) ) {
            return $result;
        }

        /* Only clear nonce errors, not other auth errors */
        if ( 'rest_cookie_invalid_nonce' !== $result->get_error_code() ) {
            return $result;
        }

        /* User was already authenticated from cookie — let them through */
        if ( get_current_user_id() > 0 ) {
            return true;
        }

        return $result;
    }

    public function add_module_type( $tag, $handle, $src ) {
        assert( is_string( $tag ), 'Tag must be a string' );
        assert( is_string( $handle ), 'Handle must be a string' );

        $module_handles = array(
            'ct-custom-admin-js',
            'ct-custom-app-js',
            'ct-custom-share-js',
            'ct-custom-auth-js',
            'ct-custom-profile-js',
            'ct-customizer-controls',
            'ct-custom-customizer',
            'ct-widget-language-filter',
        );

        if ( in_array( $handle, $module_handles, true ) ) {
            /* Remove any existing type attribute first, then add type="module" */
            $tag = preg_replace( '/\s+type\s*=\s*["\'][^"\']*["\']/', '', $tag, 1 );
            $tag = str_replace( '<script ', '<script type="module" ', $tag );
        }

        return $tag;
    }

    public function register_widgets() {
        assert( function_exists( 'register_widget' ), 'register_widget must exist' );
        assert( class_exists( CompanyInfoWidget::class ), 'CompanyInfoWidget must be loaded' );

        register_widget( CompanyInfoWidget::class );
        register_widget( ContactPointWidget::class );
        register_widget( SocialIconsWidget::class );
        register_widget( MenuWidget::class );

        $max_columns = 5;

        /* Per-language page/post sidebars and footer sidebars */
        $lang_mgr   = ct_get_language_manager();
        $languages  = $lang_mgr->get_enabled();
        $max_langs  = 50;
        $lang_count = 0;

        foreach ( $languages as $lang ) {
            if ( $lang_count >= $max_langs ) {
                break;
            }
            $lang_count++;

            $iso2 = $lang['iso2'];

            register_sidebar( array(
                'name'          => sprintf(
                    /* translators: %s: language name */
                    __( 'Left Sidebar (%s)', 'ct-custom' ),
                    $lang['native_name']
                ),
                'id'            => 'sidebar-left-' . $iso2,
                'before_widget' => '<div id="%1$s" class="sidebar-widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="sidebar-widget__title">',
                'after_title'   => '</h3>',
            ) );

            register_sidebar( array(
                'name'          => sprintf(
                    /* translators: %s: language name */
                    __( 'Right Sidebar (%s)', 'ct-custom' ),
                    $lang['native_name']
                ),
                'id'            => 'sidebar-right-' . $iso2,
                'before_widget' => '<div id="%1$s" class="sidebar-widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="sidebar-widget__title">',
                'after_title'   => '</h3>',
            ) );

            for ( $i = 1; $i <= $max_columns; $i++ ) {
                register_sidebar( array(
                    'name'          => sprintf(
                        /* translators: 1: column number, 2: language name */
                        __( 'Footer Column %1$d (%2$s)', 'ct-custom' ),
                        $i,
                        $lang['native_name']
                    ),
                    'id'            => 'footer-column-' . $i . '-' . $iso2,
                    'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
                    'after_widget'  => '</div>',
                    'before_title'  => '<h4 class="footer-widget__title">',
                    'after_title'   => '</h4>',
                ) );
            }
        }
    }

    public function enqueue_widget_scripts( $hook ) {
        assert( is_string( $hook ), 'Hook must be a string' );
        assert( is_admin(), 'Must be in admin context' );

        if ( 'widgets.php' !== $hook && 'customize.php' !== $hook ) {
            return;
        }

        wp_enqueue_media();

        $js_path = get_template_directory() . '/assets/widgets/js/company-info-upload.js';

        wp_enqueue_script(
            'ct-company-info-upload',
            get_template_directory_uri() . '/assets/widgets/js/company-info-upload.js',
            array( 'jquery' ),
            ct_get_asset_version( $js_path ),
            true
        );

        /* Translation picker for widget title/description fields */
        $picker_js_path  = get_template_directory() . '/assets/widgets/js/widget-translation-picker.js';
        $picker_css_path = get_template_directory() . '/assets/widgets/css/widget-translation-picker.css';

        wp_enqueue_script(
            'ct-widget-translation-picker',
            get_template_directory_uri() . '/assets/widgets/js/widget-translation-picker.js',
            array( 'jquery' ),
            ct_get_asset_version( $picker_js_path ),
            true
        );

        wp_enqueue_style(
            'ct-widget-translation-picker',
            get_template_directory_uri() . '/assets/widgets/css/widget-translation-picker.css',
            array(),
            ct_get_asset_version( $picker_css_path )
        );

        $picker_keys = class_exists( TranslationService::class )
            ? TranslationService::get_all_keys()
            : array();

        wp_localize_script( 'ct-widget-translation-picker', 'ctWidgetTranslationPicker', array(
            'keys' => $picker_keys,
        ) );

        /* Editor translator — needed for live translation preview in widgets */
        $translator_path = get_template_directory() . '/inc/blocks/shared/editor-translator.js';
        $translator_url  = get_template_directory_uri() . '/inc/blocks/shared/editor-translator.js';

        if ( file_exists( $translator_path ) ) {
            wp_enqueue_script(
                'ct-editor-translator',
                $translator_url,
                array(),
                filemtime( $translator_path ),
                true
            );

            $iso2         = function_exists( 'ct_get_current_language' ) ? ct_get_current_language() : 'en';
            $translations = array();

            if ( function_exists( 'ct_get_translator' ) ) {
                $translations = ct_get_translator()->get_all_translations();
            }

            wp_localize_script( 'ct-editor-translator', 'ctTranslationPreviewData', array(
                'iso2'         => $iso2,
                'translations' => $translations,
            ) );
        }
    }

    public function register_cpts() {
        $cpt = new ContactMessageCpt();
        $cpt->register();
    }

    public function register_blocks() {
        assert( function_exists( 'register_block_type' ), 'register_block_type must exist' );
        assert( is_dir( get_template_directory() . '/inc/blocks/team-members' ), 'Block directory must exist' );

        register_block_type( get_template_directory() . '/inc/blocks/team-members' );
        register_block_type( get_template_directory() . '/inc/blocks/sidebar-content' );
        register_block_type( get_template_directory() . '/inc/blocks/unprotected-page' );
        register_block_type( get_template_directory() . '/inc/blocks/protected-page' );
        register_block_type( get_template_directory() . '/inc/blocks/admin-page' );
    }

    public function enqueue_translation_picker() {
        $script_path = get_template_directory() . '/inc/blocks/shared/translation-picker.js';
        $script_url  = get_template_directory_uri() . '/inc/blocks/shared/translation-picker.js';

        assert( file_exists( $script_path ), 'Translation picker script must exist' );

        wp_enqueue_script(
            'ct-translation-picker',
            $script_url,
            array( 'wp-element', 'wp-components', 'wp-i18n' ),
            filemtime( $script_path ),
            true
        );

        $style_path = get_template_directory() . '/inc/blocks/shared/translation-picker.css';
        $style_url  = get_template_directory_uri() . '/inc/blocks/shared/translation-picker.css';

        /* Ensure chip styles (.ct-inline-tp) are available for inline input */
        $inline_css_path = get_template_directory() . '/inc/blocks/shared/inline-translation-preview.css';
        $inline_css_url  = get_template_directory_uri() . '/inc/blocks/shared/inline-translation-preview.css';

        if ( file_exists( $inline_css_path ) ) {
            wp_enqueue_style(
                'ct-inline-translation-preview',
                $inline_css_url,
                array(),
                filemtime( $inline_css_path )
            );
        }

        wp_enqueue_style(
            'ct-translation-picker',
            $style_url,
            array( 'wp-components' ),
            filemtime( $style_path )
        );

        $keys = class_exists( TranslationService::class )
            ? TranslationService::get_all_keys()
            : array();

        wp_localize_script( 'ct-translation-picker', 'ctTranslationPickerData', array(
            'keys' => $keys,
        ) );

        /*
         * Editor translator — client-side resolver for live preview.
         * No WP deps; pure JS reading from ctTranslationPreviewData.
         */
        $translator_path = get_template_directory() . '/inc/blocks/shared/editor-translator.js';
        $translator_url  = get_template_directory_uri() . '/inc/blocks/shared/editor-translator.js';

        wp_enqueue_script(
            'ct-editor-translator',
            $translator_url,
            array(),
            filemtime( $translator_path ),
            true
        );

        $iso2         = function_exists( 'ct_get_current_language' ) ? ct_get_current_language() : 'en';
        $translations = array();

        if ( function_exists( 'ct_get_translator' ) ) {
            $translations = ct_get_translator()->get_all_translations();
        }

        wp_localize_script( 'ct-editor-translator', 'ctTranslationPreviewData', array(
            'iso2'         => $iso2,
            'translations' => $translations,
        ) );

        /*
         * Sidebar settings panel + translation preview — only on the post/page editor.
         * wp-edit-post pulls in wp-editor which conflicts with
         * wp-edit-widgets on the block widget editor (WP 5.8+).
         */
        $screen = get_current_screen();
        if ( $screen && 'post' === $screen->base ) {
            $panel_path = get_template_directory() . '/inc/blocks/shared/sidebar-settings-panel.js';
            $panel_url  = get_template_directory_uri() . '/inc/blocks/shared/sidebar-settings-panel.js';
            $panel_deps = include get_template_directory() . '/inc/blocks/shared/sidebar-settings-panel.asset.php';

            if ( file_exists( $panel_path ) ) {
                wp_enqueue_script(
                    'ct-sidebar-settings-panel',
                    $panel_url,
                    $panel_deps['dependencies'],
                    filemtime( $panel_path ),
                    true
                );
            }

            /* Translation preview: sidebar panel listing all translations */
            $preview_path = get_template_directory() . '/inc/blocks/shared/translation-preview.js';
            $preview_url  = get_template_directory_uri() . '/inc/blocks/shared/translation-preview.js';

            if ( file_exists( $preview_path ) ) {
                wp_enqueue_script(
                    'ct-translation-preview',
                    $preview_url,
                    array(
                        'ct-editor-translator',
                        'wp-element',
                        'wp-data',
                        'wp-plugins',
                        'wp-edit-post',
                        'wp-i18n',
                    ),
                    filemtime( $preview_path ),
                    true
                );
            }

            $preview_css_path = get_template_directory() . '/inc/blocks/shared/translation-preview.css';
            $preview_css_url  = get_template_directory_uri() . '/inc/blocks/shared/translation-preview.css';

            if ( file_exists( $preview_css_path ) ) {
                wp_enqueue_style(
                    'ct-translation-preview',
                    $preview_css_url,
                    array(),
                    filemtime( $preview_css_path )
                );
            }

            /* Inline translation preview: DOM text replacement for non-selected blocks */
            $inline_js_path = get_template_directory() . '/inc/blocks/shared/inline-translation-preview.js';
            $inline_js_url  = get_template_directory_uri() . '/inc/blocks/shared/inline-translation-preview.js';

            if ( file_exists( $inline_js_path ) ) {
                wp_enqueue_script(
                    'ct-inline-translation-preview',
                    $inline_js_url,
                    array(
                        'ct-editor-translator',
                        'wp-data',
                        'wp-block-editor',
                    ),
                    filemtime( $inline_js_path ),
                    true
                );
            }

            $inline_css_path = get_template_directory() . '/inc/blocks/shared/inline-translation-preview.css';
            $inline_css_url  = get_template_directory_uri() . '/inc/blocks/shared/inline-translation-preview.css';

            if ( file_exists( $inline_css_path ) ) {
                wp_enqueue_style(
                    'ct-inline-translation-preview',
                    $inline_css_url,
                    array(),
                    filemtime( $inline_css_path )
                );
            }
        }
    }

    public function register_block_categories( $categories, $context ) {
        assert( is_array( $categories ), 'Categories must be an array' );
        assert( is_object( $context ), 'Context must be an object' );

        array_unshift( $categories, array(
            'slug'  => 'ct-custom',
            'title' => __( 'CT Custom', 'ct-custom' ),
        ) );

        return $categories;
    }

    public function register_pattern_categories() {
        assert( function_exists( 'register_block_pattern_category' ), 'register_block_pattern_category must exist' );

        register_block_pattern_category( 'ct-custom', array(
            'label' => __( 'CT Custom', 'ct-custom' ),
        ) );
    }

    /**
     * Register block patterns from inc/patterns/*.php files.
     *
     * Each file must have a header block with at least Title and Slug.
     * Mirrors the auto-discovery logic WordPress uses for /patterns/.
     *
     * @return void
     */
    public function register_patterns() {
        assert( function_exists( 'register_block_pattern' ), 'register_block_pattern must exist' );

        $patterns_dir = get_template_directory() . '/inc/patterns';

        if ( ! is_dir( $patterns_dir ) ) {
            return;
        }

        $files     = glob( $patterns_dir . '/*.php' );
        $max_files = 50;
        $count     = 0;

        if ( ! is_array( $files ) ) {
            return;
        }

        $default_headers = array(
            'title'         => 'Title',
            'slug'          => 'Slug',
            'categories'    => 'Categories',
            'keywords'      => 'Keywords',
            'description'   => 'Description',
            'viewportWidth' => 'Viewport Width',
            'blockTypes'    => 'Block Types',
            'inserter'      => 'Inserter',
        );

        foreach ( $files as $file ) {
            if ( $count >= $max_files ) {
                break;
            }
            $count++;

            $headers = get_file_data( $file, $default_headers );

            if ( empty( $headers['title'] ) || empty( $headers['slug'] ) ) {
                continue;
            }

            /* Skip if already registered */
            if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $headers['slug'] ) ) {
                continue;
            }

            /* Capture pattern content via output buffering */
            ob_start();
            include $file;
            $content = ob_get_clean();

            if ( empty( $content ) ) {
                continue;
            }

            $pattern_data = array(
                'title'   => $headers['title'],
                'content' => $content,
            );

            if ( ! empty( $headers['categories'] ) ) {
                $pattern_data['categories'] = array_map( 'trim', explode( ',', $headers['categories'] ) );
            }
            if ( ! empty( $headers['keywords'] ) ) {
                $pattern_data['keywords'] = array_map( 'trim', explode( ',', $headers['keywords'] ) );
            }
            if ( ! empty( $headers['description'] ) ) {
                $pattern_data['description'] = $headers['description'];
            }
            if ( ! empty( $headers['viewportWidth'] ) ) {
                $pattern_data['viewportWidth'] = (int) $headers['viewportWidth'];
            }
            if ( ! empty( $headers['blockTypes'] ) ) {
                $pattern_data['blockTypes'] = array_map( 'trim', explode( ',', $headers['blockTypes'] ) );
            }
            if ( isset( $headers['inserter'] ) && '' !== $headers['inserter'] ) {
                $pattern_data['inserter'] = ! in_array( strtolower( $headers['inserter'] ), array( 'no', 'false' ), true );
            }

            register_block_pattern( $headers['slug'], $pattern_data );
        }
    }

    /**
     * Resolve ct_translate() patterns in content.
     *
     * @param string $content Post/widget content.
     * @return string
     */
    public function resolve_translation_patterns( $content ) {
        assert( is_string( $content ), 'Content must be a string' );

        if ( empty( $content ) ) {
            return $content;
        }

        return TranslationService::resolve( $content );
    }

    /**
     * Resolve ct_translate() patterns in block editor content.
     *
     * Runs at priority 9 on the_content — same as do_blocks but registered
     * after it, so blocks are already rendered. Must run before wptexturize
     * (priority 10) which converts quotes to curly entities.
     *
     * @param string $content Post content.
     * @return string
     */
    public function resolve_block_content_patterns( $content ) {
        assert( is_string( $content ), 'Content must be a string' );

        if ( empty( $content ) ) {
            return $content;
        }

        return TranslationService::resolve_block_content( $content );
    }

    /**
     * Register theme_mod filters for text-based customizer fields.
     */
    private function register_theme_mod_translation_filters() {
        $text_mods = array(
            'ct_hero_title',
            'ct_hero_description',
            'ct_section2_title',
            'ct_section2_description',
            'ct_topbar_text1_content',
            'ct_topbar_text2_content',
            'ct_site_description',
            'ct_contact_heading',
            'ct_reach_us_title',
            'ct_contact_us_title',
        );

        $max  = 20;
        $count = 0;

        foreach ( $text_mods as $mod ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            add_filter( 'theme_mod_' . $mod, array( $this, 'resolve_theme_mod_translation' ) );
        }
    }

    /**
     * Resolve translation patterns in a theme_mod value.
     *
     * @param mixed $value Theme mod value.
     * @return mixed
     */
    public function resolve_theme_mod_translation( $value ) {
        if ( ! is_string( $value ) || '' === $value ) {
            return $value;
        }

        return TranslationService::resolve( $value );
    }

    public function add_paragraph_class( $content ) {
        assert( is_string( $content ), 'Content must be a string' );

        if ( empty( $content ) ) {
            return $content;
        }

        $max_replacements = 100;
        $count            = 0;

        /* Match every opening <p ...> tag in a single pass */
        $result = preg_replace_callback(
            '/<p(\s[^>]*)?>/i',
            function ( $matches ) use ( &$count, $max_replacements ) {
                if ( $count >= $max_replacements ) {
                    return $matches[0];
                }
                $count++;

                $attrs = isset( $matches[1] ) ? $matches[1] : '';

                /* Already has ct-p — skip */
                if ( false !== strpos( $attrs, 'ct-p' ) ) {
                    return $matches[0];
                }

                /* Skip team-member block paragraphs — they have their own typography */
                if ( false !== strpos( $attrs, 'team-member__' ) ) {
                    return $matches[0];
                }

                /* Has class="..." — append ct-p */
                if ( preg_match( '/class="([^"]*)"/', $attrs, $cls ) ) {
                    $new_attrs = preg_replace(
                        '/class="([^"]*)"/',
                        'class="ct-p $1"',
                        $attrs,
                        1
                    );
                    return '<p' . $new_attrs . '>';
                }

                /* No class attribute — add one */
                if ( '' === $attrs ) {
                    return '<p class="ct-p">';
                }

                return '<p class="ct-p"' . $attrs . '>';
            },
            $content
        );

        assert( is_string( $result ) || null === $result, 'Regex result must be string or null' );

        return ( null !== $result ) ? $result : $content;
    }
}

/* ── 5. Instantiate Theme + Singleton Components ────────────────────── */
new CT_Custom_Theme();

/* Template hooks singleton */
TemplateHooks::instance();

/*
 * Customizer registration.
 * The constructor only hooks customize_register which fires solely in the
 * Customizer context (admin frame AND preview iframe).  Removing the
 * is_admin() guard ensures panels also register in the preview iframe,
 * preventing WordPress core JS from deactivating them when it syncs
 * active-state data from the preview.
 */
new ThemeCustomizer();

/* Font manager — downloads Google Fonts on customizer save */
$ct_font_manager = new FontManager();
assert( $ct_font_manager instanceof FontManager, 'Font manager must be instantiated' );
$ct_font_manager->init();

/* Language filter widget */
new WidgetLanguageFilter();

/* Language filter tabs on Pages list screen */
new PageLanguageFilter();

/* Language filter tabs on Manage Locations screen */
new MenuLanguageFilter();

/* Hreflang tags */
$ct_hreflang = new HreflangService();
$ct_hreflang->init();

/* ── 6. Global Wrapper Function Files (cannot be autoloaded) ────────── */
require get_template_directory() . '/inc/template/template-tags.php';
require get_template_directory() . '/inc/template/template-functions.php';
require get_template_directory() . '/inc/template/header.php';
require get_template_directory() . '/inc/template/footer.php';
require get_template_directory() . '/inc/template/homepage.php';
require get_template_directory() . '/inc/template/contact.php';
require get_template_directory() . '/inc/template/language.php';
require get_template_directory() . '/inc/template/auth-forms.php';
require get_template_directory() . '/inc/multilang/functions-multilang.php';
require get_template_directory() . '/inc/sidebar/functions-sidebar.php';

/* ── 7. Admin Initialization ────────────────────────────────────────── */

/* Auto-migrate existing pages and duplicate for seeded languages on first admin load */
add_action( 'admin_init', function () {
    $mgr     = ct_get_language_manager();
    $default = $mgr->get_default();

    if ( null === $default ) {
        return;
    }

    /* Step 1: Assign default language to pages that have no ct_language meta */
    if ( ! get_option( 'ct_custom_language_migration_done' ) ) {
        CTCustom\Multilang\LanguagePageManager::migrate_existing_pages( $default['iso2'] );
    }

    /* Step 1b: Rename homepage slugs to iso2 codes (e.g. "homepage" → "en") */
    if ( ! get_option( 'ct_custom_homepage_slug_migration_done' ) ) {
        LanguagePageManager::migrate_homepage_slugs();
    }

    /* Step 2: Duplicate pages for non-default languages that have zero pages */
    $languages = $mgr->get_enabled();
    $page_mgr  = new LanguagePageManager();
    $max_langs = 50;
    $count     = 0;

    foreach ( $languages as $lang ) {
        if ( $count >= $max_langs ) {
            break;
        }
        $count++;

        if ( $lang['iso2'] === $default['iso2'] ) {
            continue;
        }

        $option_key = 'ct_custom_pages_duplicated_' . $lang['iso2'];

        if ( get_option( $option_key ) ) {
            continue;
        }

        $existing = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'meta_key'       => 'ct_language',
            'meta_value'     => $lang['iso2'],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );

        if ( empty( $existing ) ) {
            $page_mgr->duplicate_pages_for_language( $lang['iso2'], $default['iso2'] );
        }

        update_option( $option_key, true );
    }

    /* Step 3: Clone shared widget instances for languages that still share them */
    $widget_count = 0;

    foreach ( $languages as $lang ) {
        if ( $widget_count >= $max_langs ) {
            break;
        }
        $widget_count++;

        if ( $lang['iso2'] === $default['iso2'] ) {
            continue;
        }

        if ( get_option( 'ct_custom_widgets_cloned_' . $lang['iso2'] ) ) {
            continue;
        }

        LanguagePageManager::migrate_shared_widgets( $lang['iso2'], $default['iso2'] );
    }
}, 20 );

/* Migrate email/JWT settings from Customizer to options (one-time) */
add_action( 'admin_init', function () {
    $settings = new ThemeSettings();
    $settings->maybe_migrate_email_jwt_to_options();
}, 10 );

/* Migrate email template option to theme_mods (one-time) */
add_action( 'admin_init', function () {
    $settings = new ThemeSettings();
    $settings->maybe_migrate_email_template_to_theme_mods();
}, 11 );

/* ── 8. REST API ────────────────────────────────────────────────────── */
new AuthRestController();

add_action( 'rest_api_init', function () {
    $translation_endpoint = new ResolveTranslation();
    $translation_endpoint->register();

    $translations_dict = new GetTranslations();
    $translations_dict->register();

    $font_download = new FontDownload();
    $font_download->register();
} );
