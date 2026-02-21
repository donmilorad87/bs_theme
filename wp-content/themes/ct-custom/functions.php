<?php
/**
 * BS Custom Theme - Functions and Definitions
 *
 * @package BS_Custom
 */

/* ── 1. Composer Autoloader (PSR-4 namespaces + PHPMailer, Firebase JWT) ─ */
$bs_autoloader = get_template_directory() . '/vendor/autoload.php';
if ( is_readable( $bs_autoloader ) ) {
    require_once $bs_autoloader;
}

use BSCustom\Theme\ThemeSettings;
use BSCustom\Cpt\ContactMessageCpt;
use BSCustom\Cpt\ContactFormCpt;
use BSCustom\Template\TemplateHooks;
use BSCustom\Customizer\ThemeCustomizer;
use BSCustom\Customizer\FontManager;
use BSCustom\Multilang\TranslationService;
use BSCustom\Multilang\LanguagePageManager;
use BSCustom\Multilang\HreflangService;
use BSCustom\Widgets\CompanyInfoWidget;
use BSCustom\Widgets\ContactPointWidget;
use BSCustom\Widgets\SocialIconsWidget;
use BSCustom\Widgets\MenuWidget;
use BSCustom\Widgets\WidgetLanguageFilter;
use BSCustom\Admin\PageLanguageFilter;
use BSCustom\Admin\PostLanguageFilter;
use BSCustom\Admin\MenuLanguageFilter;
use BSCustom\RestApi\AuthRestController;
use BSCustom\RestApi\GlobalThrottle;
use BSCustom\RestApi\Endpoints\ResolveTranslation;
use BSCustom\RestApi\Endpoints\GetTranslations;
use BSCustom\RestApi\Endpoints\FontDownload;
use BSCustom\Blocks\PageAccessControl;
use BSCustom\Seo\SeoMeta;
use BSCustom\Seo\SeoService;
use BSCustom\Seo\SeoSettings;
use BSCustom\Seo\RedirectManager;
use BSCustom\Seo\SitemapRewrite;
use BSCustom\Seo\SitemapPages;
use BSCustom\Seo\LlmsTxt;

/* ── 2. Asset Version Helper ────────────────────────────────────────── */

/**
 * Get a cached filemtime-based version string for an asset path.
 *
 * @param string $path Absolute filesystem path to the asset.
 * @return string Version string (filemtime or '1.0.0' fallback).
 */
function bs_get_asset_version( $path ) {
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
BSCustom\Customizer\CustomizerSetup::boot();
BSCustom\Customizer\DynamicCss::boot();
BSCustom\Sidebar\SidebarMeta::boot();
PageAccessControl::boot();
SeoMeta::boot();
RedirectManager::boot();
SitemapRewrite::boot();
SitemapPages::boot();
LlmsTxt::boot();

/* ── 4. Theme Bootstrap Class ───────────────────────────────────────── */

class BS_Custom_Theme {

    private $admin_page_hook = '';

    /** @var ThemeSettings */
    private $settings;

    /** @var array|null Cached language slug maps for posts (category/tag). */
    private $language_slug_cache = null;

    public function __construct() {
        $this->settings = new ThemeSettings();

        add_action( 'after_setup_theme', array( $this, 'theme_setup' ) );
        add_action( 'after_setup_theme', array( $this, 'content_width' ), 0 );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_bloat' ), 200 );
        add_action( 'init', array( $this, 'set_default_theme_cookie' ), 1 );
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
        add_filter( 'nav_menu_args', array( $this, 'add_menu_utility_classes' ) );
        add_filter( 'nav_menu_link_attributes', array( $this, 'add_menu_link_utility_classes' ), 10, 4 );
        add_filter( 'nav_menu_submenu_css_class', array( $this, 'add_submenu_utility_classes' ), 10, 3 );
        add_filter( 'nav_menu_css_class', array( $this, 'add_menu_item_utility_classes' ), 10, 4 );
    /*     add_filter( 'the_content', array( $this, 'add_paragraph_class' ) ); */
        add_filter( 'the_content', array( $this, 'resolve_block_content_patterns' ), 9 );
        add_filter( 'the_content', array( $this, 'append_author_list_for_language' ), 12 );
        add_filter( 'widget_text', array( $this, 'resolve_translation_patterns' ), 8 );
        add_filter( 'widget_text_content', array( $this, 'resolve_translation_patterns' ), 8 );
        $this->register_theme_mod_translation_filters();
        add_filter( 'pre_get_avatar_data', array( $this, 'override_avatar_with_local' ), 10, 2 );
        add_filter( 'rest_authentication_errors', array( $this, 'allow_ct_auth_cookie_auth' ), 100 );
        add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_for_subscribers' ) );
        add_action( 'init', array( $this, 'register_author_language_rewrites' ) );
        add_action( 'admin_init', array( $this, 'ensure_language_author_pages' ) );
        add_action( 'admin_init', array( $this, 'ensure_language_blog_pages' ) );
        add_filter( 'query_vars', array( $this, 'register_author_language_query_vars' ) );
        add_action( 'pre_get_posts', array( $this, 'filter_author_archive_by_language' ) );
        add_filter( 'author_link', array( $this, 'filter_author_link_language_prefix' ), 10, 3 );
        add_filter( 'page_link', array( $this, 'filter_author_page_link_language_prefix' ), 10, 3 );
        add_action( 'init', array( $this, 'register_post_language_rewrites' ) );
        add_filter( 'post_link', array( $this, 'filter_post_link_language_prefix' ), 10, 3 );
        add_action( 'pre_get_posts', array( $this, 'filter_single_post_by_language_prefix' ) );
        add_filter( 'parse_request', array( $this, 'maybe_route_language_prefixed_post' ), 9, 1 );
    }

    public function theme_setup() {
        load_theme_textdomain( 'ct-custom', get_template_directory() . '/languages' );

        add_theme_support( 'automatic-feed-links' );
        add_theme_support( 'title-tag' );
        add_theme_support( 'post-thumbnails' );

        $bs_base_menus = array( 'main-menu', 'top-bar-menu', 'footer-copyright-menu' );
        $bs_menu_locations = array();

        /* Build per-language menu locations dynamically */
        $bs_languages = array();

        if ( function_exists( 'bs_get_language_manager' ) ) {
            $bs_lang_mgr  = bs_get_language_manager();
            $bs_languages = $bs_lang_mgr->get_enabled();
        }

        if ( empty( $bs_languages ) ) {
            $bs_languages = array( array( 'iso2' => 'en', 'native_name' => 'English' ) );
        }

        $bs_menu_count = 0;
        $bs_menu_max   = 50;

        foreach ( $bs_languages as $bs_lang ) {
            if ( $bs_menu_count >= $bs_menu_max ) { break; }
            $bs_menu_count++;

            $bs_base_count = 0;
            foreach ( $bs_base_menus as $bs_base ) {
                if ( $bs_base_count >= 3 ) { break; }
                $bs_base_count++;

                $bs_menu_locations[ $bs_base . '-' . $bs_lang['iso2'] ] = sprintf(
                    '%s (%s)',
                    ucwords( str_replace( '-', ' ', $bs_base ) ),
                    $bs_lang['native_name']
                );
            }
        }

        register_nav_menus( $bs_menu_locations );

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
        $GLOBALS['content_width'] = apply_filters( 'bs_custom_content_width', 1200 );
    }

    public function enqueue_frontend_scripts() {
        /* CSS — external file enables browser caching (24 KB saved on repeat visits) */
        $css_path = get_template_directory() . '/assets/frontend/css/app.css';

        wp_enqueue_style(
            'ct-custom-app-css',
            get_template_directory_uri() . '/assets/frontend/css/app.css',
            array(),
            bs_get_asset_version( $css_path )
        );

        /* JS — deferred, non-render-blocking */
        $js_path = get_template_directory() . '/assets/frontend/js/app.js';

        wp_enqueue_script(
            'ct-custom-app-js',
            get_template_directory_uri() . '/assets/frontend/js/app.js',
            array(),
            bs_get_asset_version( $js_path ),
            array( 'strategy' => 'defer', 'in_footer' => true )
        );

        /* Share with Friend — separate script, only when enabled */
        if ( get_theme_mod( 'bs_social_share_enabled', true ) ) {
            $share_js_path = get_template_directory() . '/assets/frontend/js/share.js';

            wp_enqueue_script(
                'ct-custom-share-js',
                get_template_directory_uri() . '/assets/frontend/js/share.js',
                array(),
                bs_get_asset_version( $share_js_path ),
                array( 'strategy' => 'defer', 'in_footer' => true )
            );
        }

        /* Inject current language translations for frontend JS */
        if ( function_exists( 'bs_get_translator' ) ) {
            $translator = bs_get_translator();
            wp_localize_script( 'ct-custom-app-js', 'ctTranslationData', array(
                'iso2'         => function_exists( 'bs_get_current_language' ) ? bs_get_current_language() : 'en',
                'translations' => $translator->get_all_translations(),
            ) );
        }

        /* Auth page — standalone bundle (login-register.php template) */
        $user_management_enabled = true;
        if ( function_exists( 'bs_user_management_enabled' ) ) {
            $user_management_enabled = bs_user_management_enabled();
        }

        if ( $user_management_enabled && is_page_template( 'login-register.php' ) ) {
            $auth_css_path = get_template_directory() . '/assets/frontend-auth/css/auth-app.css';
            wp_enqueue_style(
                'ct-custom-auth-css',
                get_template_directory_uri() . '/assets/frontend-auth/css/auth-app.css',
                array( 'ct-custom-app-css' ),
                bs_get_asset_version( $auth_css_path )
            );

            $auth_js_path = get_template_directory() . '/assets/frontend-auth/js/auth-app.js';
            wp_enqueue_script(
                'ct-custom-auth-js',
                get_template_directory_uri() . '/assets/frontend-auth/js/auth-app.js',
                array( 'ct-custom-app-js' ),
                bs_get_asset_version( $auth_js_path ),
                array( 'strategy' => 'defer', 'in_footer' => true )
            );
        }

        /* Profile page — standalone bundle (profile.php template) */
        if ( $user_management_enabled && is_page_template( 'profile.php' ) ) {
            $profile_css_path = get_template_directory() . '/assets/frontend-profile/css/profile-app.css';
            wp_enqueue_style(
                'ct-custom-profile-css',
                get_template_directory_uri() . '/assets/frontend-profile/css/profile-app.css',
                array( 'ct-custom-app-css' ),
                bs_get_asset_version( $profile_css_path )
            );

            $profile_js_path = get_template_directory() . '/assets/frontend-profile/js/profile-app.js';
            wp_enqueue_script(
                'ct-custom-profile-js',
                get_template_directory_uri() . '/assets/frontend-profile/js/profile-app.js',
                array( 'ct-custom-app-js' ),
                bs_get_asset_version( $profile_js_path ),
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
     * Set the bs_theme cookie to 'light' if it does not exist yet.
     *
     * Runs on 'init' (priority 1) so the cookie is sent before any output.
     * The body data-theme attribute is always set server-side via Header.php.
     */
    public function set_default_theme_cookie() {
        assert( ! headers_sent(), 'Headers must not be sent yet' );
        assert( function_exists( 'sanitize_text_field' ), 'WordPress must be loaded' );

        if ( isset( $_COOKIE['bs_theme'] ) ) {
            $value = sanitize_text_field( wp_unslash( $_COOKIE['bs_theme'] ) );
            if ( 'dark' === $value || 'light' === $value ) {
                return;
            }
        }

        setcookie( 'bs_theme', 'light', time() + 31536000, '/', '', is_ssl(), false );
        $_COOKIE['bs_theme'] = 'light';
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
            __( 'BS Custom Theme', 'ct-custom' ),
            __( 'BS Custom Theme', 'ct-custom' ),
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
        $extra_css_file = get_template_directory() . '/assets/backend/css/admin-extra.css';
        $extra_js_file  = get_template_directory() . '/assets/backend/js/admin-extra.js';

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

        if ( file_exists( $extra_css_file ) ) {
            wp_enqueue_style(
                'ct-custom-admin-extra-css',
                get_template_directory_uri() . '/assets/backend/css/admin-extra.css',
                array( 'ct-custom-admin-css' ),
                (string) filemtime( $extra_css_file )
            );
        }

        if ( file_exists( $extra_js_file ) ) {
            wp_enqueue_script(
                'ct-custom-admin-extra-js',
                get_template_directory_uri() . '/assets/backend/js/admin-extra.js',
                array( 'ct-custom-admin-js' ),
                (string) filemtime( $extra_js_file ),
                true
            );
        }

        wp_localize_script( 'ct-custom-admin-js', 'wpApiSettings', array(
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ) );
    }

    private function register_ajax_handlers() {
        $actions = array(
            'admin_save_general_settings',
            'admin_get_contact_messages_count',
            'admin_get_contact_forms',
            'admin_get_contact_form',
            'admin_save_contact_form',
            'admin_delete_contact_form',
            'admin_export_settings',
            'admin_import_settings',
            'admin_save_email_config',
        );

        $languages_enabled = get_theme_mod( 'bs_languages_enabled', true );
        if ( is_string( $languages_enabled ) ) {
            $normalized = strtolower( $languages_enabled );
            $languages_enabled = ! ( '0' === $languages_enabled || 'off' === $normalized || 'false' === $normalized );
        }

        if ( $languages_enabled ) {
            $language_actions = array(
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
            );
            $actions = array_merge( $actions, $language_actions );
        }

        $user_management_enabled = get_theme_mod( 'bs_user_management_enabled', true );
        if ( is_string( $user_management_enabled ) ) {
            $normalized = strtolower( $user_management_enabled );
            $user_management_enabled = ! ( '0' === $user_management_enabled || 'off' === $normalized || 'false' === $normalized );
        }
        if ( $user_management_enabled ) {
            $actions[] = 'admin_save_jwt_auth';
        }

        $max_actions = 35;
        $count = 0;

        foreach ( $actions as $action ) {
            if ( $count >= $max_actions ) {
                break;
            }

            add_action( 'wp_ajax_' . $action, array( $this->settings, $action ) );
            $count++;
        }

        /* SEO AJAX handlers — separate SeoSettings instance */
        $seo_settings = new SeoSettings();
        $seo_actions  = array(
            'admin_save_seo_global',
            'admin_load_seo_global',
            'admin_save_seo_social',
            'admin_save_seo_social_icons',
            'admin_save_seo_contact_point',
            'admin_save_seo_sitemap',
            'admin_save_seo_llms',
            'admin_save_seo_redirect',
            'admin_load_seo_redirects',
            'admin_save_seo_breadcrumbs',
            'admin_get_seo_dashboard',
            'admin_bulk_analyze_seo',
            'admin_ping_search_engines',
            'admin_get_sitemap_priorities',
            'admin_save_sitemap_priorities',
            'admin_get_sitemap_tree_types',
            'admin_get_sitemap_tree_items',
            'admin_get_sitemap_lang_counts',
            'admin_save_sitemap_item',
            'admin_regenerate_sitemap',
            'admin_save_sitemap_order',
        );

        $seo_max = 23;
        $seo_count = 0;

        foreach ( $seo_actions as $seo_action ) {
            if ( $seo_count >= $seo_max ) {
                break;
            }

            add_action( 'wp_ajax_' . $seo_action, array( $seo_settings, $seo_action ) );
            $seo_count++;
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
     * Append spacing utilities to nav menu UL classes.
     *
     * @param array $args Nav menu arguments.
     * @return array
     */
    public function add_menu_utility_classes( $args ) {
        if ( ! is_array( $args ) ) {
            return $args;
        }

        $menu_class = isset( $args['menu_class'] ) ? (string) $args['menu_class'] : '';
        $parts      = array_filter( preg_split( '/\s+/', trim( $menu_class ) ) );
        $parts[]    = 'm0';
        $parts[]    = 'p0';

        $args['menu_class'] = trim( implode( ' ', array_values( array_unique( $parts ) ) ) );

        return $args;
    }

    /**
     * Add display utilities to main menu anchors.
     *
     * @param array    $atts Link attributes.
     * @param \WP_Post $item Menu item.
     * @param object   $args Menu args.
     * @param int      $depth Menu depth.
     * @return array
     */
    public function add_menu_link_utility_classes( $atts, $item, $args, $depth ) {
        if ( ! is_object( $args ) || ! property_exists( $args, 'menu_id' ) || 'primary-menu' !== $args->menu_id ) {
            return $atts;
        }

        $class_str = isset( $atts['class'] ) ? (string) $atts['class'] : '';
        $classes   = array_filter( preg_split( '/\s+/', trim( $class_str ) ) );

        $classes[] = ( 0 === (int) $depth ) ? 'dib' : 'db';

        $atts['class'] = trim( implode( ' ', array_values( array_unique( $classes ) ) ) );

        return $atts;
    }

    /**
     * Append spacing utilities to submenu UL classes.
     *
     * @param array  $classes Existing submenu classes.
     * @param object $args Menu args.
     * @param int    $depth Submenu depth.
     * @return array
     */
    public function add_submenu_utility_classes( $classes, $args, $depth ) {
        if ( ! is_array( $classes ) ) {
            return $classes;
        }

        $classes[] = 'm0';
        $classes[] = 'p0';

        return array_values( array_unique( $classes ) );
    }

    /**
     * Add spacing utilities to widget menu list items.
     *
     * @param array    $classes Existing menu item classes.
     * @param \WP_Post $item Menu item.
     * @param object   $args Menu args.
     * @param int      $depth Menu depth.
     * @return array
     */
    public function add_menu_item_utility_classes( $classes, $item, $args, $depth ) {
        if ( ! is_array( $classes ) ) {
            return $classes;
        }

        if ( isset( $args->container_class ) && 'widget-menu' === $args->container_class ) {
            $classes[] = 'mb4';
        }

        return array_values( array_unique( $classes ) );
    }

    /**
     * Override WordPress avatar with locally uploaded image.
     *
     * Hooks into pre_get_avatar_data so get_avatar() and get_avatar_url()
     * return the user's uploaded photo stored as bs_avatar_id user meta.
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

        $avatar_id = (int) get_user_meta( $user_id, 'bs_avatar_id', true );

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

    /**
     * Prevent the admin bar from rendering for subscribers.
     *
     * @param bool $show Current show_admin_bar value.
     * @return bool
     */
    public function hide_admin_bar_for_subscribers( $show ) {
        assert( is_bool( $show ), 'show must be a boolean' );

        $user = wp_get_current_user();

        assert( $user instanceof \WP_User, 'Current user must be a WP_User' );

        if ( $user->exists() && in_array( 'subscriber', (array) $user->roles, true ) ) {
            return false;
        }

        return $show;
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
        $lang_mgr   = bs_get_language_manager();
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
                'before_widget' => '<div id="%1$s" class="sidebar-widget mb24 %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="sidebar-widget__title fs16 mb16">',
                'after_title'   => '</h3>',
            ) );

            register_sidebar( array(
                'name'          => sprintf(
                    /* translators: %s: language name */
                    __( 'Right Sidebar (%s)', 'ct-custom' ),
                    $lang['native_name']
                ),
                'id'            => 'sidebar-right-' . $iso2,
                'before_widget' => '<div id="%1$s" class="sidebar-widget mb24 %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="sidebar-widget__title fs16 mb16">',
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
                'before_widget' => '<div id="%1$s" class="footer-widget mb16 %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h4 class="footer-widget__title mb16">',
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
            bs_get_asset_version( $js_path ),
            true
        );

        /* Translation picker for widget title/description fields */
        $picker_js_path  = get_template_directory() . '/assets/widgets/js/widget-translation-picker.js';
        $picker_css_path = get_template_directory() . '/assets/widgets/css/widget-translation-picker.css';

        wp_enqueue_script(
            'ct-widget-translation-picker',
            get_template_directory_uri() . '/assets/widgets/js/widget-translation-picker.js',
            array( 'jquery' ),
            bs_get_asset_version( $picker_js_path ),
            true
        );

        wp_enqueue_style(
            'ct-widget-translation-picker',
            get_template_directory_uri() . '/assets/widgets/css/widget-translation-picker.css',
            array(),
            bs_get_asset_version( $picker_css_path )
        );

        $picker_keys = class_exists( TranslationService::class )
            ? TranslationService::get_all_keys()
            : array();

        wp_localize_script( 'ct-widget-translation-picker', 'ctWidgetTranslationPicker', array(
            'keys' => $picker_keys,
        ) );

        /* Editor translator — needed for live translation preview in widgets */
        $translator_path = get_template_directory() . '/src/Blocks/shared/editor-translator.js';
        $translator_url  = get_template_directory_uri() . '/src/Blocks/shared/editor-translator.js';

        if ( file_exists( $translator_path ) ) {
            wp_enqueue_script(
                'ct-editor-translator',
                $translator_url,
                array(),
                filemtime( $translator_path ),
                true
            );

            $iso2         = function_exists( 'bs_get_current_language' ) ? bs_get_current_language() : 'en';
            $translations = array();

            if ( function_exists( 'bs_get_translator' ) ) {
                $translations = bs_get_translator()->get_all_translations();
            }

            wp_localize_script( 'ct-editor-translator', 'ctTranslationPreviewData', array(
                'iso2'         => $iso2,
                'translations' => $translations,
            ) );
        }
    }

    public function register_cpts() {
        $message_cpt = new ContactMessageCpt();
        $message_cpt->register();

        $form_cpt = new ContactFormCpt();
        $form_cpt->register();
    }

    public function register_blocks() {
        assert( function_exists( 'register_block_type' ), 'register_block_type must exist' );
        assert( is_dir( get_template_directory() . '/src/Blocks/team-members' ), 'Block directory must exist' );

        register_block_type( get_template_directory() . '/src/Blocks/team-members' );
        register_block_type( get_template_directory() . '/src/Blocks/sidebar-content' );
        register_block_type( get_template_directory() . '/src/Blocks/unprotected-page' );
        register_block_type( get_template_directory() . '/src/Blocks/protected-page' );
        register_block_type( get_template_directory() . '/src/Blocks/admin-page' );
    }

    public function enqueue_translation_picker() {
        $script_path = get_template_directory() . '/src/Blocks/shared/translation-picker.js';
        $script_url  = get_template_directory_uri() . '/src/Blocks/shared/translation-picker.js';

        assert( file_exists( $script_path ), 'Translation picker script must exist' );

        wp_enqueue_script(
            'ct-translation-picker',
            $script_url,
            array( 'wp-element', 'wp-components', 'wp-i18n' ),
            filemtime( $script_path ),
            true
        );

        $style_path = get_template_directory() . '/src/Blocks/shared/translation-picker.css';
        $style_url  = get_template_directory_uri() . '/src/Blocks/shared/translation-picker.css';

        /* Ensure chip styles (.ct-inline-tp) are available for inline input */
        $inline_css_path = get_template_directory() . '/src/Blocks/shared/inline-translation-preview.css';
        $inline_css_url  = get_template_directory_uri() . '/src/Blocks/shared/inline-translation-preview.css';

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
        $translator_path = get_template_directory() . '/src/Blocks/shared/editor-translator.js';
        $translator_url  = get_template_directory_uri() . '/src/Blocks/shared/editor-translator.js';

        wp_enqueue_script(
            'ct-editor-translator',
            $translator_url,
            array(),
            filemtime( $translator_path ),
            true
        );

        $iso2         = function_exists( 'bs_get_current_language' ) ? bs_get_current_language() : 'en';
        $translations = array();

        if ( function_exists( 'bs_get_translator' ) ) {
            $translations = bs_get_translator()->get_all_translations();
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
            $panel_path = get_template_directory() . '/src/Blocks/shared/sidebar-settings-panel.js';
            $panel_url  = get_template_directory_uri() . '/src/Blocks/shared/sidebar-settings-panel.js';
            $panel_deps = include get_template_directory() . '/src/Blocks/shared/sidebar-settings-panel.asset.php';

            if ( file_exists( $panel_path ) ) {
                wp_enqueue_script(
                    'ct-sidebar-settings-panel',
                    $panel_url,
                    $panel_deps['dependencies'],
                    filemtime( $panel_path ),
                    true
                );
            }

            /* SEO sidebar panel: analysis engine + sidebar plugin + styles */
            $seo_engine_path = get_template_directory() . '/src/Blocks/shared/seo-analysis-engine.js';
            $seo_engine_url  = get_template_directory_uri() . '/src/Blocks/shared/seo-analysis-engine.js';

            if ( file_exists( $seo_engine_path ) ) {
                wp_enqueue_script(
                    'ct-seo-analysis-engine',
                    $seo_engine_url,
                    array(),
                    filemtime( $seo_engine_path ),
                    true
                );
            }

            $seo_panel_path = get_template_directory() . '/src/Blocks/shared/seo-sidebar-panel.js';
            $seo_panel_url  = get_template_directory_uri() . '/src/Blocks/shared/seo-sidebar-panel.js';
            $seo_panel_deps = include get_template_directory() . '/src/Blocks/shared/seo-sidebar-panel.asset.php';

            if ( file_exists( $seo_panel_path ) ) {
                wp_enqueue_script(
                    'ct-seo-sidebar-panel',
                    $seo_panel_url,
                    array_merge( $seo_panel_deps['dependencies'], array( 'ct-seo-analysis-engine' ) ),
                    filemtime( $seo_panel_path ),
                    true
                );
            }

            $seo_panel_css_path = get_template_directory() . '/src/Blocks/shared/seo-sidebar-panel.css';
            $seo_panel_css_url  = get_template_directory_uri() . '/src/Blocks/shared/seo-sidebar-panel.css';

            if ( file_exists( $seo_panel_css_path ) ) {
                wp_enqueue_style(
                    'ct-seo-sidebar-panel',
                    $seo_panel_css_url,
                    array( 'wp-components' ),
                    filemtime( $seo_panel_css_path )
                );
            }

            /* Translation preview: sidebar panel listing all translations */
            $preview_path = get_template_directory() . '/src/Blocks/shared/translation-preview.js';
            $preview_url  = get_template_directory_uri() . '/src/Blocks/shared/translation-preview.js';

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

            $preview_css_path = get_template_directory() . '/src/Blocks/shared/translation-preview.css';
            $preview_css_url  = get_template_directory_uri() . '/src/Blocks/shared/translation-preview.css';

            if ( file_exists( $preview_css_path ) ) {
                wp_enqueue_style(
                    'ct-translation-preview',
                    $preview_css_url,
                    array(),
                    filemtime( $preview_css_path )
                );
            }

            /* Inline translation preview: DOM text replacement for non-selected blocks */
            $inline_js_path = get_template_directory() . '/src/Blocks/shared/inline-translation-preview.js';
            $inline_js_url  = get_template_directory_uri() . '/src/Blocks/shared/inline-translation-preview.js';

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

            $inline_css_path = get_template_directory() . '/src/Blocks/shared/inline-translation-preview.css';
            $inline_css_url  = get_template_directory_uri() . '/src/Blocks/shared/inline-translation-preview.css';

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
            'title' => __( 'BS Custom', 'ct-custom' ),
        ) );

        return $categories;
    }

    public function register_pattern_categories() {
        assert( function_exists( 'register_block_pattern_category' ), 'register_block_pattern_category must exist' );

        register_block_pattern_category( 'ct-custom', array(
            'label' => __( 'BS Custom', 'ct-custom' ),
        ) );
    }

    /**
     * Register block patterns from src/Patterns/*.php files.
     *
     * Each file must have a header block with at least Title and Slug.
     * Mirrors the auto-discovery logic WordPress uses for /patterns/.
     *
     * @return void
     */
    public function register_patterns() {
        assert( function_exists( 'register_block_pattern' ), 'register_block_pattern must exist' );

        $patterns_dir = get_template_directory() . '/src/Patterns';

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
     * Resolve bs_translate() patterns in content.
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
     * Resolve bs_translate() patterns in block editor content.
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
            'bs_hero_title',
            'bs_hero_description',
            'bs_section2_title',
            'bs_section2_description',
            'bs_topbar_text1_content',
            'bs_topbar_text2_content',
            'bs_site_description',
            'bs_contact_heading',
            'bs_reach_us_title',
            'bs_contact_us_title',
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

    /**
     * Filter author archives by language markers (category/tag).
     *
     * @param \WP_Query $query Main query object.
     * @return void
     */
    public function filter_author_archive_by_language( $query ) {
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_author() ) {
            return;
        }

        $lang = $this->resolve_current_language_iso2( $query );

        if ( '' === $lang ) {
            return;
        }

        $tax_query = $this->build_language_marker_tax_query( $lang );

        if ( empty( $tax_query ) ) {
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        $existing_tax = $query->get( 'tax_query' );

        if ( ! empty( $existing_tax ) ) {
            $combined = array( 'relation' => 'AND' );
            $combined[] = $existing_tax;
            $combined[] = $tax_query;
            $query->set( 'tax_query', $combined );
            return;
        }

        $query->set( 'tax_query', $tax_query );
    }

    /**
     * Register language-prefixed author rewrite rules.
     *
     * @return void
     */
    public function register_author_language_rewrites() {
        $langs = $this->get_enabled_language_iso2();

        if ( empty( $langs ) ) {
            return;
        }

        foreach ( $langs as $iso2 ) {
            $safe = preg_quote( $iso2, '/' );

            add_rewrite_rule(
                '^' . $safe . '/author/([^/]+)/page/([0-9]+)/?$',
                'index.php?author_name=$matches[1]&paged=$matches[2]&bs_lang=' . $iso2,
                'top'
            );

            add_rewrite_rule(
                '^' . $safe . '/author/([^/]+)/?$',
                'index.php?author_name=$matches[1]&bs_lang=' . $iso2,
                'top'
            );
        }
    }

    /**
     * Register language-prefixed post rewrite rules.
     *
     * @return void
     */
    public function register_post_language_rewrites() {
        $langs = $this->get_enabled_language_iso2();

        if ( empty( $langs ) ) {
            return;
        }

        foreach ( $langs as $iso2 ) {
            $safe = preg_quote( $iso2, '/' );

            add_rewrite_rule(
                '^' . $safe . '/([^/]+)/?$',
                'index.php?name=$matches[1]&post_type=post&bs_lang=' . $iso2,
                'top'
            );
        }
    }

    /**
     * Prefer a page route when a language-prefixed path matches a page.
     *
     * @param \WP $wp WordPress request object.
     * @return \WP
     */
    public function maybe_route_language_prefixed_post( $wp ) {
        if ( ! $wp instanceof \WP || is_admin() ) {
            return $wp;
        }

        $vars = isset( $wp->query_vars ) && is_array( $wp->query_vars ) ? $wp->query_vars : array();
        $lang = isset( $vars['bs_lang'] ) ? sanitize_key( (string) $vars['bs_lang'] ) : '';
        $name = isset( $vars['name'] ) ? sanitize_title( (string) $vars['name'] ) : '';
        $type = isset( $vars['post_type'] ) ? $vars['post_type'] : '';

        if ( '' === $lang || '' === $name || ( '' !== $type && 'post' !== $type ) ) {
            return $wp;
        }

        $enabled = $this->get_enabled_language_iso2();
        if ( ! in_array( $lang, $enabled, true ) ) {
            return $wp;
        }

        $path = $lang . '/' . $name;
        $page = get_page_by_path( $path, OBJECT, 'page' );

        if ( ! $page || is_wp_error( $page ) ) {
            return $wp;
        }

        unset( $wp->query_vars['name'], $wp->query_vars['post_type'] );
        $wp->query_vars['pagename'] = $path;
        $wp->query_vars['bs_lang']  = $lang;

        return $wp;
    }

    /**
     * Ensure per-language author pages exist under each language homepage.
     *
     * @return void
     */
    public function ensure_language_author_pages() {
        if ( ! function_exists( 'bs_get_language_manager' ) ) {
            return;
        }

        $mgr       = bs_get_language_manager();
        $languages = $mgr->get_enabled();

        if ( empty( $languages ) ) {
            return;
        }

        $source_page = get_page_by_path( 'author', OBJECT, 'page' );
        $template    = '';

        if ( $source_page && ! is_wp_error( $source_page ) ) {
            $template = (string) get_post_meta( $source_page->ID, '_wp_page_template', true );
        }

        $translation_group = '';

        foreach ( $languages as $lang ) {
            if ( empty( $lang['iso2'] ) ) {
                continue;
            }

            $iso2 = sanitize_key( $lang['iso2'] );
            $page = get_page_by_path( $iso2 . '/author', OBJECT, 'page' );

            if ( $page && ! is_wp_error( $page ) ) {
                $group = get_post_meta( $page->ID, 'bs_translation_group', true );
                if ( is_string( $group ) && '' !== $group ) {
                    $translation_group = $group;
                    break;
                }
            }
        }

        if ( '' === $translation_group && $source_page && ! is_wp_error( $source_page ) ) {
            $group = get_post_meta( $source_page->ID, 'bs_translation_group', true );
            if ( is_string( $group ) && '' !== $group ) {
                $translation_group = $group;
            }
        }

        if ( '' === $translation_group ) {
            $translation_group = wp_generate_uuid4();
        }

        foreach ( $languages as $lang ) {
            if ( empty( $lang['iso2'] ) ) {
                continue;
            }

            $iso2 = sanitize_key( $lang['iso2'] );
            $parent = get_page_by_path( $iso2, OBJECT, 'page' );

            if ( ! $parent || is_wp_error( $parent ) ) {
                continue;
            }

            $author_page = get_page_by_path( $iso2 . '/author', OBJECT, 'page' );

            if ( $author_page && ! is_wp_error( $author_page ) ) {
                if ( (int) $author_page->post_parent !== (int) $parent->ID ) {
                    wp_update_post( array(
                        'ID'          => $author_page->ID,
                        'post_parent' => (int) $parent->ID,
                    ) );
                }

                $existing_lang = get_post_meta( $author_page->ID, 'bs_language', true );
                if ( $existing_lang !== $iso2 ) {
                    update_post_meta( $author_page->ID, 'bs_language', $iso2 );
                }

                $existing_group = get_post_meta( $author_page->ID, 'bs_translation_group', true );
                if ( ! is_string( $existing_group ) || '' === $existing_group ) {
                    update_post_meta( $author_page->ID, 'bs_translation_group', $translation_group );
                }

                if ( '' !== $template ) {
                    update_post_meta( $author_page->ID, '_wp_page_template', $template );
                }

                continue;
            }

            $new_post = array(
                'post_title'   => $source_page && ! is_wp_error( $source_page ) ? $source_page->post_title : 'Author',
                'post_content' => $source_page && ! is_wp_error( $source_page ) ? $source_page->post_content : '',
                'post_excerpt' => $source_page && ! is_wp_error( $source_page ) ? $source_page->post_excerpt : '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => (int) $parent->ID,
                'post_name'    => 'author',
            );

            if ( $source_page && ! is_wp_error( $source_page ) ) {
                $new_post['menu_order'] = $source_page->menu_order;
                $new_post['post_author'] = $source_page->post_author;
            }

            $new_id = wp_insert_post( $new_post, true );

            if ( is_wp_error( $new_id ) || $new_id <= 0 ) {
                continue;
            }

            update_post_meta( $new_id, 'bs_language', $iso2 );
            update_post_meta( $new_id, 'bs_translation_group', $translation_group );

            if ( '' !== $template ) {
                update_post_meta( $new_id, '_wp_page_template', $template );
            }
        }
    }

    /**
     * Ensure per-language blog pages exist under each language homepage.
     *
     * @return void
     */
    public function ensure_language_blog_pages() {
        if ( ! function_exists( 'bs_get_language_manager' ) ) {
            return;
        }

        $mgr       = bs_get_language_manager();
        $languages = $mgr->get_enabled();

        if ( empty( $languages ) ) {
            return;
        }

        $source_page = get_page_by_path( 'blog', OBJECT, 'page' );
        $template    = '';

        if ( $source_page && ! is_wp_error( $source_page ) ) {
            $template = (string) get_post_meta( $source_page->ID, '_wp_page_template', true );
        }

        $translation_group = '';

        foreach ( $languages as $lang ) {
            if ( empty( $lang['iso2'] ) ) {
                continue;
            }

            $iso2 = sanitize_key( $lang['iso2'] );
            $page = get_page_by_path( $iso2 . '/blog', OBJECT, 'page' );

            if ( $page && ! is_wp_error( $page ) ) {
                $group = get_post_meta( $page->ID, 'bs_translation_group', true );
                if ( is_string( $group ) && '' !== $group ) {
                    $translation_group = $group;
                    break;
                }
            }
        }

        if ( '' === $translation_group && $source_page && ! is_wp_error( $source_page ) ) {
            $group = get_post_meta( $source_page->ID, 'bs_translation_group', true );
            if ( is_string( $group ) && '' !== $group ) {
                $translation_group = $group;
            }
        }

        if ( '' === $translation_group ) {
            $translation_group = wp_generate_uuid4();
        }

        foreach ( $languages as $lang ) {
            if ( empty( $lang['iso2'] ) ) {
                continue;
            }

            $iso2   = sanitize_key( $lang['iso2'] );
            $parent = get_page_by_path( $iso2, OBJECT, 'page' );

            if ( ! $parent || is_wp_error( $parent ) ) {
                continue;
            }

            $blog_page = get_page_by_path( $iso2 . '/blog', OBJECT, 'page' );

            if ( $blog_page && ! is_wp_error( $blog_page ) ) {
                if ( (int) $blog_page->post_parent !== (int) $parent->ID ) {
                    wp_update_post( array(
                        'ID'          => $blog_page->ID,
                        'post_parent' => (int) $parent->ID,
                    ) );
                }

                $existing_lang = get_post_meta( $blog_page->ID, 'bs_language', true );
                if ( $existing_lang !== $iso2 ) {
                    update_post_meta( $blog_page->ID, 'bs_language', $iso2 );
                }

                $existing_group = get_post_meta( $blog_page->ID, 'bs_translation_group', true );
                if ( ! is_string( $existing_group ) || '' === $existing_group ) {
                    update_post_meta( $blog_page->ID, 'bs_translation_group', $translation_group );
                }

                if ( '' !== $template ) {
                    update_post_meta( $blog_page->ID, '_wp_page_template', $template );
                }

                continue;
            }

            $new_post = array(
                'post_title'   => $source_page && ! is_wp_error( $source_page ) ? $source_page->post_title : 'Blog',
                'post_content' => $source_page && ! is_wp_error( $source_page ) ? $source_page->post_content : '',
                'post_excerpt' => $source_page && ! is_wp_error( $source_page ) ? $source_page->post_excerpt : '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => (int) $parent->ID,
                'post_name'    => 'blog',
            );

            if ( $source_page && ! is_wp_error( $source_page ) ) {
                $new_post['menu_order']  = $source_page->menu_order;
                $new_post['post_author'] = $source_page->post_author;
            }

            $new_id = wp_insert_post( $new_post, true );

            if ( is_wp_error( $new_id ) || $new_id <= 0 ) {
                continue;
            }

            update_post_meta( $new_id, 'bs_language', $iso2 );
            update_post_meta( $new_id, 'bs_translation_group', $translation_group );

            if ( '' !== $template ) {
                update_post_meta( $new_id, '_wp_page_template', $template );
            }
        }
    }

    /**
     * Register query vars used by language-prefixed author archives.
     *
     * @param array $vars Query vars.
     * @return array
     */
    public function register_author_language_query_vars( $vars ) {
        if ( is_array( $vars ) && ! in_array( 'bs_lang', $vars, true ) ) {
            $vars[] = 'bs_lang';
        }

        return $vars;
    }

    /**
     * Prefix author links with the active language when bs_lang is set.
     *
     * @param string $link Author URL.
     * @param int    $author_id Author ID.
     * @param string $author_nicename Author nicename.
     * @return string
     */
    public function filter_author_link_language_prefix( $link, $author_id, $author_nicename ) {
        $lang = $this->resolve_current_language_iso2( $GLOBALS['wp_query'] ?? null );

        if ( '' === $lang ) {
            return $link;
        }

        $enabled = $this->get_enabled_language_iso2();

        if ( ! in_array( $lang, $enabled, true ) ) {
            return $link;
        }

        return $this->prefix_url_with_language( $link, $lang );
    }

    /**
     * Prefix the "author" page link with the active language when bs_lang is set.
     *
     * @param string $link Page URL.
     * @param int    $post_id Page ID.
     * @param bool   $sample Sample link flag.
     * @return string
     */
    public function filter_author_page_link_language_prefix( $link, $post_id, $sample ) {
        $lang = $this->resolve_current_language_iso2( $GLOBALS['wp_query'] ?? null );

        if ( '' === $lang ) {
            return $link;
        }

        $enabled = $this->get_enabled_language_iso2();

        if ( ! in_array( $lang, $enabled, true ) ) {
            return $link;
        }

        $post = get_post( $post_id );

        if ( ! $post || 'page' !== $post->post_type || 'author' !== $post->post_name ) {
            return $link;
        }

        return $this->prefix_url_with_language( $link, $lang );
    }

    /**
     * Prefix post links with the post's language.
     *
     * @param string  $link  Post URL.
     * @param \WP_Post $post Post object.
     * @param bool    $leavename Leave name flag.
     * @return string
     */
    public function filter_post_link_language_prefix( $link, $post, $leavename ) {
        if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
            return $link;
        }

        $lang = $this->get_post_language_iso2( $post->ID );

        if ( '' === $lang ) {
            return $link;
        }

        $enabled = $this->get_enabled_language_iso2();

        if ( ! in_array( $lang, $enabled, true ) ) {
            return $link;
        }

        return $this->prefix_url_with_language( $link, $lang );
    }

    /**
     * Filter single post queries by the language prefix.
     *
     * @param \WP_Query $query The query being modified.
     * @return void
     */
    public function filter_single_post_by_language_prefix( $query ) {
        assert( $query instanceof \WP_Query, 'Query must be a WP_Query instance' );

        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( ! $query->is_single() ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        if ( is_array( $post_type ) ) {
            if ( ! in_array( 'post', $post_type, true ) ) {
                return;
            }
        } elseif ( '' !== $post_type && 'post' !== $post_type ) {
            return;
        }

        $lang = sanitize_key( (string) $query->get( 'bs_lang' ) );

        if ( '' === $lang ) {
            return;
        }

        $enabled = $this->get_enabled_language_iso2();
        if ( ! in_array( $lang, $enabled, true ) ) {
            return;
        }

        $tax_query = $this->build_language_marker_tax_query( $lang );

        if ( empty( $tax_query ) ) {
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        $existing_tax = $query->get( 'tax_query' );

        if ( ! empty( $existing_tax ) ) {
            $combined = array( 'relation' => 'AND' );
            $combined[] = $existing_tax;
            $combined[] = $tax_query;
            $query->set( 'tax_query', $combined );
            return;
        }

        $query->set( 'tax_query', $tax_query );
    }

    /**
     * Append a language-filtered author list to the "author" page content.
     *
     * @param string $content Page content.
     * @return string
     */
    public function append_author_list_for_language( $content ) {
        if ( is_admin() || ! is_page() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post = get_post();

        if ( ! $post || 'page' !== $post->post_type || 'author' !== $post->post_name ) {
            return $content;
        }

        $lang = '';

        if ( function_exists( 'get_query_var' ) ) {
            $query_lang = get_query_var( 'bs_lang' );
            if ( is_string( $query_lang ) && '' !== $query_lang ) {
                $lang = sanitize_key( $query_lang );
            }
        }

        if ( '' === $lang && isset( $_GET['lang'] ) ) {
            $lang = sanitize_key( wp_unslash( $_GET['lang'] ) );
        }

        $authors = $this->get_author_list_for_language( $lang, 500 );

        if ( empty( $authors ) ) {
            return $content;
        }

        $html = '<div class="ct-author-list">';
        $html .= '<ul class="ct-author-list__items">';

        foreach ( $authors as $author ) {
            $name = isset( $author['name'] ) ? $author['name'] : '';
            $url  = isset( $author['url'] ) ? $author['url'] : '';

            if ( '' === $name || '' === $url ) {
                continue;
            }

            $html .= '<li class="ct-author-list__item">';
            $html .= '<a class="ct-author-list__link" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $content . $html;
    }

    /**
     * Resolve current language ISO2 code for archive filtering.
     *
     * @param \WP_Query|null $query Optional query object.
     * @return string
     */
    private function resolve_current_language_iso2( $query = null ) {
        $lang = '';

        if ( $query instanceof \WP_Query ) {
            $lang = sanitize_key( (string) $query->get( 'bs_lang' ) );
        }

        if ( '' === $lang && isset( $_GET['lang'] ) ) {
            $lang = sanitize_key( wp_unslash( $_GET['lang'] ) );
        }

        if ( '' === $lang && $query instanceof \WP_Query && $query->is_page() ) {
            $page_id = (int) $query->get( 'page_id' );

            if ( $page_id <= 0 && function_exists( 'get_queried_object_id' ) ) {
                $page_id = (int) get_queried_object_id();
            }

            if ( $page_id > 0 ) {
                $page_lang = get_post_meta( $page_id, 'bs_language', true );
                if ( is_string( $page_lang ) && '' !== $page_lang ) {
                    $lang = sanitize_key( $page_lang );
                }
            }
        }

        if ( '' === $lang && function_exists( 'bs_get_current_language' ) ) {
            $lang = sanitize_key( bs_get_current_language() );
        }

        if ( '' === $lang ) {
            return '';
        }

        $enabled = $this->get_enabled_language_iso2();

        return in_array( $lang, $enabled, true ) ? $lang : '';
    }

    /**
     * Prefix a URL path with /{lang}/ when not already present.
     *
     * @param string $url  URL to prefix.
     * @param string $lang ISO2 language code.
     * @return string
     */
    private function prefix_url_with_language( $url, $lang ) {
        if ( '' === $url || '' === $lang ) {
            return $url;
        }

        $lang = sanitize_key( $lang );

        if ( '' === $lang ) {
            return $url;
        }

        $home = home_url( '/' );

        if ( '' === $home ) {
            return $url;
        }

        $home = trailingslashit( $home );

        if ( 0 !== strpos( $url, $home ) ) {
            return $url;
        }

        $relative = ltrim( substr( $url, strlen( $home ) ), '/' );

        if ( '' === $relative ) {
            return $url;
        }

        $prefix = $lang . '/';

        if ( 0 === strpos( $relative, $prefix ) ) {
            return $url;
        }

        return $home . $prefix . $relative;
    }

    /**
     * Resolve a post's language ISO2 from its language category/tag.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    private function get_post_language_iso2( $post_id ) {
        $post_id = (int) $post_id;

        if ( $post_id <= 0 ) {
            return '';
        }

        $maps = $this->get_language_slug_maps();

        $cat_slugs = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'slugs' ) );
        if ( is_array( $cat_slugs ) ) {
            foreach ( $cat_slugs as $slug ) {
                if ( isset( $maps['category'][ $slug ] ) ) {
                    return $maps['category'][ $slug ];
                }
            }
        }

        $tag_slugs = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'slugs' ) );
        if ( is_array( $tag_slugs ) ) {
            foreach ( $tag_slugs as $slug ) {
                if ( isset( $maps['tag'][ $slug ] ) ) {
                    return $maps['tag'][ $slug ];
                }
            }
        }

        return '';
    }

    /**
     * Build cached maps of language slugs to ISO2 codes.
     *
     * @return array{category: array<string, string>, tag: array<string, string>}
     */
    private function get_language_slug_maps() {
        if ( is_array( $this->language_slug_cache ) ) {
            return $this->language_slug_cache;
        }

        $langs = $this->get_enabled_language_iso2();
        $cat   = array();
        $tag   = array();

        foreach ( $langs as $iso2 ) {
            $native = $this->get_language_native_name( $iso2 );
            $cat_slug = sanitize_title( $native );

            if ( '' !== $cat_slug ) {
                $cat[ $cat_slug ] = $iso2;
            }

            if ( '' !== $iso2 ) {
                $cat[ $iso2 ] = $iso2;
                $tag[ sanitize_key( $iso2 ) ] = $iso2;
            }
        }

        $this->language_slug_cache = array(
            'category' => $cat,
            'tag'      => $tag,
        );

        return $this->language_slug_cache;
    }

    /**
     * Build author data list for a language (based on category/tag markers).
     *
     * @param string $lang  ISO2 language code.
     * @param int    $limit Max authors.
     * @return array<int, array{id:int,name:string,url:string}>
     */
    private function get_author_list_for_language( $lang, $limit ) {
        $author_ids = $this->get_author_ids_for_language_markers( $lang, $limit );

        if ( empty( $author_ids ) ) {
            return array();
        }

        $items = array();

        foreach ( $author_ids as $author_id ) {
            $user = get_userdata( $author_id );
            if ( ! $user ) {
                continue;
            }

            $name = (string) $user->display_name;
            $url  = get_author_posts_url( $author_id );

            if ( '' === $name || '' === $url ) {
                continue;
            }

            if ( '' !== $lang ) {
                $url = $this->prefix_url_with_language( $url, $lang );
            }

            $items[] = array(
                'id'   => (int) $author_id,
                'name' => $name,
                'url'  => $url,
            );
        }

        usort( $items, function ( $a, $b ) {
            return strcasecmp( $a['name'], $b['name'] );
        } );

        return $items;
    }

    /**
     * Get author IDs for a language based on category/tag markers.
     *
     * @param string $lang  ISO2 language code.
     * @param int    $limit Max author IDs to return.
     * @return array<int, int>
     */
    private function get_author_ids_for_language_markers( $lang, $limit ) {
        global $wpdb;

        $limit = max( 1, min( 500, (int) $limit ) );

        if ( ! isset( $wpdb ) ) {
            return array();
        }

        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 5000,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'has_password'   => false,
        );

        if ( '' !== $lang ) {
            $tax_query = $this->build_language_marker_tax_query( $lang );

            if ( empty( $tax_query ) ) {
                return array();
            }

            $args['tax_query'] = $tax_query;
        }

        $post_ids = get_posts( $args );

        if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
        $sql = $wpdb->prepare(
            "SELECT DISTINCT post_author
             FROM {$wpdb->posts}
             WHERE ID IN ({$placeholders})
               AND post_author > 0",
            $post_ids
        );

        $ids = $wpdb->get_col( $sql );

        if ( ! is_array( $ids ) ) {
            return array();
        }

        $ids = array_map( 'intval', $ids );

        return array_slice( $ids, 0, $limit );
    }

    /**
     * Get enabled language ISO2 codes.
     *
     * @return array<int, string>
     */
    private function get_enabled_language_iso2() {
        if ( function_exists( 'bs_get_language_manager' ) ) {
            $mgr   = bs_get_language_manager();
            $langs = $mgr->get_enabled();
            $iso2  = array();
            $max   = 50;
            $i     = 0;

            foreach ( $langs as $lang ) {
                if ( $i >= $max ) {
                    break;
                }
                $i++;
                if ( ! empty( $lang['iso2'] ) ) {
                    $iso2[] = sanitize_key( $lang['iso2'] );
                }
            }

            $iso2 = array_values( array_unique( array_filter( $iso2 ) ) );
            if ( ! empty( $iso2 ) ) {
                return $iso2;
            }
        }

        return array( 'en' );
    }

    /**
     * Get native name for a language ISO2 code.
     *
     * @param string $lang ISO2 language code.
     * @return string
     */
    private function get_language_native_name( $lang ) {
        if ( function_exists( 'bs_get_language_manager' ) ) {
            $mgr   = bs_get_language_manager();
            $langs = $mgr->get_enabled();
            $max   = 50;
            $i     = 0;

            foreach ( $langs as $item ) {
                if ( $i >= $max ) {
                    break;
                }
                $i++;
                if ( isset( $item['iso2'] ) && $item['iso2'] === $lang && ! empty( $item['native_name'] ) ) {
                    return (string) $item['native_name'];
                }
            }
        }

        return 'en' === $lang ? 'English' : $lang;
    }

    /**
     * Build tax_query for language markers (category/tag).
     *
     * @param string $lang ISO2 language code.
     * @return array
     */
    private function build_language_marker_tax_query( $lang ) {
        $clauses = array();
        $cat_ids = $this->get_language_category_ids( $lang );
        $tag_id  = $this->get_language_tag_id( $lang );

        if ( ! empty( $cat_ids ) ) {
            $clauses[] = array(
                'taxonomy'         => 'category',
                'field'            => 'term_id',
                'terms'            => $cat_ids,
                'include_children' => false,
            );
        }

        if ( $tag_id > 0 ) {
            $clauses[] = array(
                'taxonomy'         => 'post_tag',
                'field'            => 'term_id',
                'terms'            => array( $tag_id ),
                'include_children' => false,
            );
        }

        if ( empty( $clauses ) ) {
            return array();
        }

        if ( count( $clauses ) === 1 ) {
            return array( $clauses[0] );
        }

        $tax_query = array( 'relation' => 'OR' );
        foreach ( $clauses as $clause ) {
            $tax_query[] = $clause;
        }

        return $tax_query;
    }

    /**
     * Get category term IDs representing a language (including children).
     *
     * @param string $lang ISO2 language code.
     * @return array<int, int>
     */
    private function get_language_category_ids( $lang ) {
        if ( '' === $lang || ! taxonomy_exists( 'category' ) ) {
            return array();
        }

        $native = $this->get_language_native_name( $lang );

        $slugs = array( $lang, sanitize_title( $native ) );
        $slugs = array_values( array_unique( array_filter( $slugs ) ) );

        $term = null;
        foreach ( $slugs as $slug ) {
            $term = get_term_by( 'slug', $slug, 'category' );
            if ( $term && ! is_wp_error( $term ) ) {
                break;
            }
        }

        if ( ! $term ) {
            $names = array( $native, strtoupper( $lang ), ucfirst( $lang ) );
            $names = array_values( array_unique( array_filter( $names ) ) );
            foreach ( $names as $name ) {
                $term = get_term_by( 'name', $name, 'category' );
                if ( $term && ! is_wp_error( $term ) ) {
                    break;
                }
            }
        }

        if ( ! $term || is_wp_error( $term ) ) {
            return array();
        }

        $parent_id = (int) $term->term_id;
        $ids       = array( $parent_id );

        $children = get_terms( array(
            'taxonomy'   => 'category',
            'child_of'   => $parent_id,
            'hide_empty' => false,
            'fields'     => 'ids',
            'number'     => 500,
        ) );

        if ( is_array( $children ) ) {
            foreach ( $children as $cid ) {
                $ids[] = (int) $cid;
            }
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /**
     * Get tag term ID representing a language.
     *
     * @param string $lang ISO2 language code.
     * @return int
     */
    private function get_language_tag_id( $lang ) {
        if ( '' === $lang || ! taxonomy_exists( 'post_tag' ) ) {
            return 0;
        }

        $native = $this->get_language_native_name( $lang );

        $slugs = array( $lang, sanitize_title( $native ) );
        $slugs = array_values( array_unique( array_filter( $slugs ) ) );

        $term = null;
        foreach ( $slugs as $slug ) {
            $term = get_term_by( 'slug', $slug, 'post_tag' );
            if ( $term && ! is_wp_error( $term ) ) {
                break;
            }
        }

        if ( ! $term ) {
            $names = array( strtoupper( $lang ), ucfirst( $lang ), $lang, $native );
            $names = array_values( array_unique( array_filter( $names ) ) );
            foreach ( $names as $name ) {
                $term = get_term_by( 'name', $name, 'post_tag' );
                if ( $term && ! is_wp_error( $term ) ) {
                    break;
                }
            }
        }

        return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
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
new BS_Custom_Theme();

/* Template hooks singleton */
TemplateHooks::instance();

/* SEO service singleton */
SeoService::instance();

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
$bs_font_manager = new FontManager();
assert( $bs_font_manager instanceof FontManager, 'Font manager must be instantiated' );
$bs_font_manager->init();

/* Language filter widget */
new WidgetLanguageFilter();

/* Language filter tabs on Pages list screen */
new PageLanguageFilter();

/* Language filter tabs on Posts list screen */
new PostLanguageFilter();

/* Language filter tabs on Manage Locations screen */
new MenuLanguageFilter();

/* Hreflang tags */
$bs_hreflang = new HreflangService();
$bs_hreflang->init();

/* ── 6. Global Wrapper Function Files (src/, cannot be autoloaded) ─── */
require get_template_directory() . '/src/Template/functions-template-tags.php';
require get_template_directory() . '/src/Template/functions-template.php';
require get_template_directory() . '/src/Template/functions-header.php';
require get_template_directory() . '/src/Template/functions-footer.php';
require get_template_directory() . '/src/Template/functions-homepage.php';
require get_template_directory() . '/src/Template/functions-contact.php';
require get_template_directory() . '/src/Template/functions-language.php';
require get_template_directory() . '/src/Template/functions-auth-forms.php';
require get_template_directory() . '/src/Multilang/functions-multilang.php';
require get_template_directory() . '/src/Sidebar/functions-sidebar.php';
require get_template_directory() . '/src/Seo/functions-seo.php';

/* ── 7. Admin Initialization ────────────────────────────────────────── */

/* Auto-migrate existing pages and duplicate for seeded languages on first admin load */
add_action( 'admin_init', function () {
    $mgr     = bs_get_language_manager();
    $default = $mgr->get_default();

    if ( null === $default ) {
        return;
    }

    /* Step 1: Assign default language to pages that have no bs_language meta */
    if ( ! get_option( 'bs_custom_language_migration_done' ) ) {
        BSCustom\Multilang\LanguagePageManager::migrate_existing_pages( $default['iso2'] );
    }

    /* Step 1b: Rename homepage slugs to iso2 codes (e.g. "homepage" → "en") */
    if ( ! get_option( 'bs_custom_homepage_slug_migration_done' ) ) {
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

        $option_key = 'bs_custom_pages_duplicated_' . $lang['iso2'];

        if ( get_option( $option_key ) ) {
            continue;
        }

        $existing = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'meta_key'       => 'bs_language',
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

        if ( get_option( 'bs_custom_widgets_cloned_' . $lang['iso2'] ) ) {
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
$global_throttle = new GlobalThrottle();
$global_throttle->register();

add_action( 'rest_api_init', function () {
    $translation_endpoint = new ResolveTranslation();
    $translation_endpoint->register();

    $translations_dict = new GetTranslations();
    $translations_dict->register();

    $font_download = new FontDownload();
    $font_download->register();
} );
