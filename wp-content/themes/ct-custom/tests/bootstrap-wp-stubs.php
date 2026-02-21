<?php
/**
 * Minimal WordPress stubs for running auth tests WITHOUT WordPress.
 *
 * Provides in-memory implementations of WordPress functions/classes
 * used by the auth system. State is stored in $GLOBALS arrays and
 * reset between tests via AuthTestCase::setUp().
 *
 * @package BSCustom\Tests
 */

if ( defined( 'BS_WP_STUBS_LOADED' ) ) {
    return;
}
define( 'BS_WP_STUBS_LOADED', true );

/* ── Constants ──────────────────────────────────────────────────── */

if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', false );
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wp-stub/' );
}

if ( ! defined( 'UPLOAD_ERR_OK' ) ) {
    define( 'UPLOAD_ERR_OK', 0 );
}

/* ── Global state stores ────────────────────────────────────────── */

$GLOBALS['bs_test_transients']    = array();
$GLOBALS['bs_test_transient_ttl'] = array();
$GLOBALS['bs_test_users']         = array();
$GLOBALS['bs_test_user_meta']     = array();
$GLOBALS['bs_test_options']       = array();
$GLOBALS['bs_test_current_user']  = 0;
$GLOBALS['bs_test_error_log']     = array();
$GLOBALS['bs_test_next_user_id']  = 1;
$GLOBALS['bs_test_server']        = array();

/* ── WP_Error ───────────────────────────────────────────────────── */

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct( $code = '', $message = '', $data = '' ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

/* ── WP_REST_Request ────────────────────────────────────────────── */

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private $params  = array();
        private $headers = array();
        private $files   = array();
        private $method  = 'GET';

        public function __construct( $method = 'GET', $route = '' ) {
            $this->method = $method;
        }

        public function set_param( $key, $value ) {
            $this->params[ $key ] = $value;
        }

        public function get_param( $key ) {
            return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
        }

        public function get_params() {
            return $this->params;
        }

        public function set_header( $name, $value ) {
            $this->headers[ strtolower( $name ) ] = $value;
        }

        public function get_header( $name ) {
            $lower = strtolower( $name );
            return isset( $this->headers[ $lower ] ) ? $this->headers[ $lower ] : null;
        }

        public function get_file_params() {
            return $this->files;
        }

        public function set_file_params( $files ) {
            $this->files = $files;
        }

        public function get_method() {
            return $this->method;
        }
    }
}

/* ── WP_REST_Response ───────────────────────────────────────────── */

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        private $data;
        private $status;

        public function __construct( $data = null, $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }
    }
}

/* ── WP_REST_Server ─────────────────────────────────────────────── */

if ( ! class_exists( 'WP_REST_Server' ) ) {
    class WP_REST_Server {
        const CREATABLE = 'POST';
        const READABLE  = 'GET';
    }
}

/* ── WP_Post ────────────────────────────────────────────────────── */

if ( ! class_exists( 'WP_Post' ) ) {
    class WP_Post {
        public $ID;
        public $post_content;
        public $post_title;
        public $post_type;

        public function __construct( $data = array() ) {
            $max = 20;
            $count = 0;
            foreach ( $data as $key => $value ) {
                if ( $count >= $max ) { break; }
                $count++;
                $this->$key = $value;
            }
        }
    }
}

/* ── Transient functions ────────────────────────────────────────── */

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        if ( ! isset( $GLOBALS['bs_test_transients'][ $key ] ) ) {
            return false;
        }

        /* Check expiration */
        if ( isset( $GLOBALS['bs_test_transient_ttl'][ $key ] ) ) {
            if ( time() > $GLOBALS['bs_test_transient_ttl'][ $key ] ) {
                unset( $GLOBALS['bs_test_transients'][ $key ] );
                unset( $GLOBALS['bs_test_transient_ttl'][ $key ] );
                return false;
            }
        }

        return $GLOBALS['bs_test_transients'][ $key ];
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $ttl = 0 ) {
        $GLOBALS['bs_test_transients'][ $key ] = $value;
        if ( $ttl > 0 ) {
            $GLOBALS['bs_test_transient_ttl'][ $key ] = time() + $ttl;
            /* Also store in options for get_rate_limit_remaining() */
            $GLOBALS['bs_test_options'][ '_transient_timeout_' . $key ] = time() + $ttl;
        }
        return true;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) {
        unset( $GLOBALS['bs_test_transients'][ $key ] );
        unset( $GLOBALS['bs_test_transient_ttl'][ $key ] );
        unset( $GLOBALS['bs_test_options'][ '_transient_timeout_' . $key ] );
        return true;
    }
}

/* ── User functions ─────────────────────────────────────────────── */

if ( ! function_exists( 'get_user_by' ) ) {
    function get_user_by( $field, $value ) {
        $max = 1000;
        $count = 0;

        foreach ( $GLOBALS['bs_test_users'] as $user ) {
            if ( $count >= $max ) { break; }
            $count++;

            if ( $field === 'id' && $user->ID === (int) $value ) {
                return $user;
            }
            if ( $field === 'email' && $user->user_email === $value ) {
                return $user;
            }
            if ( $field === 'login' && $user->user_login === $value ) {
                return $user;
            }
        }

        return false;
    }
}

if ( ! function_exists( 'get_user_meta' ) ) {
    function get_user_meta( $user_id, $key, $single = false ) {
        if ( ! isset( $GLOBALS['bs_test_user_meta'][ $user_id ][ $key ] ) ) {
            return $single ? '' : array();
        }

        $value = $GLOBALS['bs_test_user_meta'][ $user_id ][ $key ];
        return $single ? $value : array( $value );
    }
}

if ( ! function_exists( 'update_user_meta' ) ) {
    function update_user_meta( $user_id, $key, $value ) {
        if ( ! isset( $GLOBALS['bs_test_user_meta'][ $user_id ] ) ) {
            $GLOBALS['bs_test_user_meta'][ $user_id ] = array();
        }
        $GLOBALS['bs_test_user_meta'][ $user_id ][ $key ] = $value;
        return true;
    }
}

if ( ! function_exists( 'wp_insert_user' ) ) {
    function wp_insert_user( $userdata ) {
        $id = $GLOBALS['bs_test_next_user_id']++;

        $user = new \stdClass();
        $user->ID           = $id;
        $user->user_login   = $userdata['user_login'] ?? '';
        $user->user_email   = $userdata['user_email'] ?? '';
        $user->user_pass    = password_hash( $userdata['user_pass'] ?? '', PASSWORD_BCRYPT );
        $user->first_name   = $userdata['first_name'] ?? '';
        $user->last_name    = $userdata['last_name'] ?? '';
        $user->display_name = trim( ( $userdata['first_name'] ?? '' ) . ' ' . ( $userdata['last_name'] ?? '' ) );
        $user->roles        = array( $userdata['role'] ?? 'subscriber' );

        $GLOBALS['bs_test_users'][ $id ] = $user;
        return $id;
    }
}

if ( ! function_exists( 'wp_update_user' ) ) {
    function wp_update_user( $userdata ) {
        $id = $userdata['ID'] ?? 0;

        if ( ! isset( $GLOBALS['bs_test_users'][ $id ] ) ) {
            return new \WP_Error( 'invalid_user', 'User not found.' );
        }

        $user = $GLOBALS['bs_test_users'][ $id ];

        if ( isset( $userdata['first_name'] ) ) {
            $user->first_name = $userdata['first_name'];
        }
        if ( isset( $userdata['last_name'] ) ) {
            $user->last_name = $userdata['last_name'];
        }
        if ( isset( $userdata['display_name'] ) ) {
            $user->display_name = $userdata['display_name'];
        }

        $GLOBALS['bs_test_users'][ $id ] = $user;
        return $id;
    }
}

if ( ! function_exists( 'username_exists' ) ) {
    function username_exists( $username ) {
        $max = 1000;
        $count = 0;

        foreach ( $GLOBALS['bs_test_users'] as $user ) {
            if ( $count >= $max ) { break; }
            $count++;

            if ( $user->user_login === $username ) {
                return $user->ID;
            }
        }

        return false;
    }
}

if ( ! function_exists( 'email_exists' ) ) {
    function email_exists( $email ) {
        $max = 1000;
        $count = 0;

        foreach ( $GLOBALS['bs_test_users'] as $user ) {
            if ( $count >= $max ) { break; }
            $count++;

            if ( $user->user_email === $email ) {
                return $user->ID;
            }
        }

        return false;
    }
}

if ( ! function_exists( 'wp_signon' ) ) {
    function wp_signon( $creds, $secure_cookie = false ) {
        $login    = $creds['user_login'] ?? '';
        $password = $creds['user_password'] ?? '';

        $user = get_user_by( 'login', $login );

        if ( ! $user ) {
            return new \WP_Error( 'invalid_username', 'Invalid username.' );
        }

        if ( ! password_verify( $password, $user->user_pass ) ) {
            return new \WP_Error( 'incorrect_password', 'Incorrect password.' );
        }

        return $user;
    }
}

if ( ! function_exists( 'wp_check_password' ) ) {
    function wp_check_password( $password, $hash, $user_id = 0 ) {
        return password_verify( $password, $hash );
    }
}

if ( ! function_exists( 'wp_set_password' ) ) {
    function wp_set_password( $password, $user_id ) {
        if ( isset( $GLOBALS['bs_test_users'][ $user_id ] ) ) {
            $GLOBALS['bs_test_users'][ $user_id ]->user_pass = password_hash( $password, PASSWORD_BCRYPT );
        }
    }
}

if ( ! function_exists( 'wp_logout' ) ) {
    function wp_logout() {
        $GLOBALS['bs_test_current_user'] = 0;
    }
}

if ( ! function_exists( 'wp_set_current_user' ) ) {
    function wp_set_current_user( $user_id ) {
        $GLOBALS['bs_test_current_user'] = (int) $user_id;
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        return (int) $GLOBALS['bs_test_current_user'];
    }
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
    function is_user_logged_in() {
        return $GLOBALS['bs_test_current_user'] > 0;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        $user_id = get_current_user_id();
        if ( ! $user_id || ! isset( $GLOBALS['bs_test_users'][ $user_id ] ) ) {
            return false;
        }
        $user = $GLOBALS['bs_test_users'][ $user_id ];
        if ( $capability === 'manage_options' ) {
            return in_array( 'administrator', $user->roles ?? array(), true );
        }
        return false;
    }
}

/* ── Nonce / rand ───────────────────────────────────────────────── */

if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = '' ) {
        return 'test_nonce_' . md5( $action );
    }
}

if ( ! function_exists( 'wp_rand' ) ) {
    function wp_rand( $min = 0, $max = 0 ) {
        return random_int( $min, $max );
    }
}

/* ── Validation / sanitization ──────────────────────────────────── */

if ( ! function_exists( 'is_email' ) ) {
    function is_email( $email ) {
        return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return is_string( $str ) ? trim( strip_tags( $str ) ) : '';
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return is_string( $email ) ? trim( strtolower( $email ) ) : '';
    }
}

if ( ! function_exists( 'sanitize_user' ) ) {
    function sanitize_user( $username ) {
        return is_string( $username ) ? trim( $username ) : '';
    }
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
    function sanitize_file_name( $name ) {
        return preg_replace( '/[^a-zA-Z0-9._-]/', '', $name );
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        return is_string( $value ) ? stripslashes( $value ) : $value;
    }
}

/* ── Options ────────────────────────────────────────────────────── */

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $key, $default = false ) {
        return isset( $GLOBALS['bs_test_options'][ $key ] )
            ? $GLOBALS['bs_test_options'][ $key ]
            : $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $key, $value ) {
        $GLOBALS['bs_test_options'][ $key ] = $value;
        return true;
    }
}

/* ── URL / site ─────────────────────────────────────────────────── */

if ( ! function_exists( 'get_site_url' ) ) {
    function get_site_url() {
        return 'https://example.com';
    }
}

if ( ! function_exists( 'get_template_directory' ) ) {
    function get_template_directory() {
        return dirname( __DIR__ );
    }
}

if ( ! function_exists( 'get_template_directory_uri' ) ) {
    function get_template_directory_uri() {
        return 'https://example.com/wp-content/themes/ct-custom';
    }
}

if ( ! function_exists( 'is_ssl' ) ) {
    function is_ssl() {
        return true;
    }
}

/* ── i18n ───────────────────────────────────────────────────────── */

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'sprintf' ) && false ) {
    /* sprintf is a PHP built-in, no stub needed */
}

/* ── Hooks ──────────────────────────────────────────────────────── */

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
        /* No-op for testing */
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
        /* No-op for testing */
    }
}

if ( ! function_exists( 'did_action' ) ) {
    function did_action( $tag ) {
        return 0;
    }
}

if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args ) {
        /* No-op for testing */
    }
}

if ( ! defined( 'REST_API_VERSION' ) ) {
    define( 'REST_API_VERSION', '2' );
}

/* ── WP functions used in various places ────────────────────────── */

if ( ! function_exists( '__return_true' ) ) {
    function __return_true() {
        return true;
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof \WP_Error;
    }
}

if ( ! function_exists( 'is_singular' ) ) {
    function is_singular() {
        return $GLOBALS['bs_test_options']['_is_singular'] ?? false;
    }
}

if ( ! function_exists( 'is_front_page' ) ) {
    function is_front_page() {
        return $GLOBALS['bs_test_options']['_is_front_page'] ?? false;
    }
}

if ( ! function_exists( 'is_page' ) ) {
    function is_page() {
        return $GLOBALS['bs_test_options']['_is_page'] ?? false;
    }
}

if ( ! function_exists( 'is_single' ) ) {
    function is_single() {
        return $GLOBALS['bs_test_options']['_is_single'] ?? false;
    }
}

if ( ! function_exists( 'is_category' ) ) {
    function is_category() {
        return $GLOBALS['bs_test_options']['_is_category'] ?? false;
    }
}

if ( ! function_exists( 'is_search' ) ) {
    function is_search() {
        return $GLOBALS['bs_test_options']['_is_search'] ?? false;
    }
}

if ( ! function_exists( 'is_404' ) ) {
    function is_404() {
        return $GLOBALS['bs_test_options']['_is_404'] ?? false;
    }
}

if ( ! function_exists( 'is_archive' ) ) {
    function is_archive() {
        return $GLOBALS['bs_test_options']['_is_archive'] ?? false;
    }
}

if ( ! function_exists( 'get_the_archive_title' ) ) {
    function get_the_archive_title() {
        return $GLOBALS['bs_test_options']['_archive_title'] ?? 'Archive';
    }
}

if ( ! function_exists( 'single_cat_title' ) ) {
    function single_cat_title( $prefix = '', $display = true ) {
        $title = $GLOBALS['bs_test_options']['_cat_title'] ?? 'Category';
        if ( $display ) {
            echo $prefix . $title;
        }
        return $prefix . $title;
    }
}

if ( ! function_exists( 'get_post_ancestors' ) ) {
    function get_post_ancestors( $post_id ) {
        return $GLOBALS['bs_test_options']['_post_ancestors'][ (int) $post_id ] ?? array();
    }
}

if ( ! function_exists( 'get_queried_object' ) ) {
    function get_queried_object() {
        return $GLOBALS['bs_test_options']['_queried_object'] ?? null;
    }
}

if ( ! function_exists( 'has_block' ) ) {
    function has_block( $block_name, $post ) {
        if ( ! $post || ! isset( $post->post_content ) ) {
            return false;
        }
        return strpos( $post->post_content, '<!-- wp:' . $block_name . ' ' ) !== false
            || strpos( $post->post_content, '<!-- wp:' . $block_name . ' -->' ) !== false;
    }
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
    function wp_safe_redirect( $url ) {
        $GLOBALS['bs_test_options']['_last_redirect'] = $url;

        /* When the flag is set, throw so that any subsequent exit is never reached. */
        if ( ! empty( $GLOBALS['bs_test_options']['_throw_on_redirect'] ) ) {
            throw new \RuntimeException( 'wp_safe_redirect:' . $url );
        }
    }
}

if ( ! function_exists( 'get_avatar_url' ) ) {
    function get_avatar_url( $user_id, $args = array() ) {
        $meta = get_user_meta( $user_id, 'bs_avatar_id', true );
        if ( $meta ) {
            return 'https://example.com/wp-content/uploads/avatar_' . $meta . '.jpg';
        }
        return 'https://example.com/default-avatar.png';
    }
}

if ( ! function_exists( 'media_handle_sideload' ) ) {
    function media_handle_sideload( $file_array, $post_id = 0 ) {
        static $next_attachment = 100;
        return $next_attachment++;
    }
}

/* ── Permission callback shim ───────────────────────────────────── */

if ( ! function_exists( 'bs_jwt_or_cookie_permission_check' ) ) {
    function bs_jwt_or_cookie_permission_check( $request ) {
        return \BSCustom\Services\JwtAuth::jwt_or_cookie_permission_check( $request );
    }
}

/* ── URL helpers used by PageAccessControl ──────────────────────── */

if ( ! function_exists( 'bs_custom_get_auth_page_url' ) ) {
    function bs_custom_get_auth_page_url() {
        return 'https://example.com/login-register/';
    }
}

if ( ! function_exists( 'bs_custom_get_profile_page_url' ) ) {
    function bs_custom_get_profile_page_url() {
        return 'https://example.com/profile/';
    }
}

if ( ! function_exists( 'bs_get_language_home_url' ) ) {
    function bs_get_language_home_url() {
        return 'https://example.com/';
    }
}

/* ── Error logging capture ──────────────────────────────────────── */

/* Override error_log in test context to capture messages */
if ( ! function_exists( 'bs_test_get_error_log' ) ) {
    function bs_test_get_error_log() {
        return $GLOBALS['bs_test_error_log'];
    }
}

/* ── Mail service stub ──────────────────────────────────────────── */

/* The MailService and EmailTemplate are used by endpoints but we don't
   want to actually send email in tests. We override them minimally here. */

/* ── Functions needed by EmailTemplate ─────────────────────────── */

if ( ! function_exists( 'get_theme_mod' ) ) {
    function get_theme_mod( $name, $default = false ) {
        return isset( $GLOBALS['bs_test_options'][ 'theme_mod_' . $name ] )
            ? $GLOBALS['bs_test_options'][ 'theme_mod_' . $name ]
            : $default;
    }
}

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '' ) {
        if ( 'name' === $show ) {
            return isset( $GLOBALS['bs_test_options']['blogname'] )
                ? $GLOBALS['bs_test_options']['blogname']
                : 'Test Site';
        }
        if ( 'description' === $show ) {
            return isset( $GLOBALS['bs_test_options']['blogdescription'] )
                ? $GLOBALS['bs_test_options']['blogdescription']
                : '';
        }
        return '';
    }
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
    function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail' ) {
        return false;
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return filter_var( $url, FILTER_SANITIZE_URL );
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $string, $remove_breaks = false ) {
        $string = strip_tags( $string );
        if ( $remove_breaks ) {
            $string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
        }
        return trim( $string );
    }
}

/* ── Post meta ─────────────────────────────────────────────────── */

if ( ! isset( $GLOBALS['bs_test_post_meta'] ) ) {
    $GLOBALS['bs_test_post_meta'] = array();
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key = '', $single = false ) {
        if ( '' === $key ) {
            return isset( $GLOBALS['bs_test_post_meta'][ $post_id ] )
                ? $GLOBALS['bs_test_post_meta'][ $post_id ]
                : array();
        }
        if ( ! isset( $GLOBALS['bs_test_post_meta'][ $post_id ][ $key ] ) ) {
            return $single ? '' : array();
        }
        $value = $GLOBALS['bs_test_post_meta'][ $post_id ][ $key ];
        return $single ? $value : array( $value );
    }
}

if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $post_id, $key, $value ) {
        if ( ! isset( $GLOBALS['bs_test_post_meta'][ $post_id ] ) ) {
            $GLOBALS['bs_test_post_meta'][ $post_id ] = array();
        }
        $GLOBALS['bs_test_post_meta'][ $post_id ][ $key ] = $value;
        return true;
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) {
        return 'https://example.com' . $path;
    }
}

if ( ! function_exists( 'get_the_title' ) ) {
    function get_the_title( $post_id = 0 ) {
        if ( isset( $GLOBALS['bs_test_options']['_post_titles'][ $post_id ] ) ) {
            return $GLOBALS['bs_test_options']['_post_titles'][ $post_id ];
        }
        return 'Test Post Title';
    }
}

if ( ! function_exists( 'get_the_author_meta' ) ) {
    function get_the_author_meta( $field = '', $user_id = 0 ) {
        if ( 'display_name' === $field ) {
            return isset( $GLOBALS['bs_test_options']['_author_name'] )
                ? $GLOBALS['bs_test_options']['_author_name']
                : 'Test Author';
        }
        return '';
    }
}

if ( ! function_exists( 'get_the_category' ) ) {
    function get_the_category( $post_id = 0 ) {
        if ( isset( $GLOBALS['bs_test_options']['_post_categories'][ $post_id ] ) ) {
            return $GLOBALS['bs_test_options']['_post_categories'][ $post_id ];
        }
        return array();
    }
}

if ( ! function_exists( 'get_the_tags' ) ) {
    function get_the_tags( $post_id = 0 ) {
        if ( isset( $GLOBALS['bs_test_options']['_post_tags'][ $post_id ] ) ) {
            return $GLOBALS['bs_test_options']['_post_tags'][ $post_id ];
        }
        return false;
    }
}

if ( ! function_exists( 'get_query_var' ) ) {
    function get_query_var( $var, $default = '' ) {
        return isset( $GLOBALS['bs_test_options']['_query_vars'][ $var ] )
            ? $GLOBALS['bs_test_options']['_query_vars'][ $var ]
            : $default;
    }
}

if ( ! function_exists( 'register_post_meta' ) ) {
    function register_post_meta( $post_type, $meta_key, $args = array() ) {
        if ( ! isset( $GLOBALS['bs_test_registered_meta'] ) ) {
            $GLOBALS['bs_test_registered_meta'] = array();
        }
        $GLOBALS['bs_test_registered_meta'][ $post_type . ':' . $meta_key ] = $args;
        return true;
    }
}

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post_id ) {
        if ( isset( $GLOBALS['bs_test_posts'][ $post_id ] ) ) {
            return $GLOBALS['bs_test_posts'][ $post_id ];
        }
        return null;
    }
}

if ( ! function_exists( 'has_post_thumbnail' ) ) {
    function has_post_thumbnail( $post_id = 0 ) {
        return ! empty( $GLOBALS['bs_test_post_meta'][ $post_id ]['_thumbnail_id'] );
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $str ) {
        return is_string( $str ) ? trim( strip_tags( $str ) ) : '';
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return filter_var( $url, FILTER_SANITIZE_URL );
    }
}

/* ── Functions needed by SeoService / MetaTags ────────────────── */

if ( ! function_exists( 'get_the_ID' ) ) {
    function get_the_ID() {
        return isset( $GLOBALS['bs_test_options']['_current_post_id'] )
            ? (int) $GLOBALS['bs_test_options']['_current_post_id']
            : 0;
    }
}

if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $post_id = 0 ) {
        $post_id = (int) $post_id;

        /* Return slug-based permalink if post has post_name */
        if ( isset( $GLOBALS['bs_test_posts'][ $post_id ] ) ) {
            $post = $GLOBALS['bs_test_posts'][ $post_id ];
            if ( ! empty( $post->post_name ) ) {
                return 'https://example.com/' . $post->post_name . '/';
            }
        }

        return 'https://example.com/?p=' . $post_id;
    }
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
    function get_edit_post_link( $post_id = 0, $context = 'display' ) {
        return 'https://example.com/wp-admin/post.php?post=' . (int) $post_id . '&action=edit';
    }
}

if ( ! function_exists( 'get_locale' ) ) {
    function get_locale() {
        return 'en_US';
    }
}

if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
    function get_the_post_thumbnail_url( $post_id = 0, $size = 'thumbnail' ) {
        if ( has_post_thumbnail( $post_id ) ) {
            return 'https://example.com/wp-content/uploads/thumb_' . $post_id . '.jpg';
        }
        return false;
    }
}

if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
    function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail' ) {
        return array( 'https://example.com/img.jpg', 1200, 630 );
    }
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
    function wp_get_attachment_url( $attachment_id ) {
        return 'https://example.com/wp-content/uploads/attachment_' . $attachment_id . '.jpg';
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) {
        return json_encode( $data, $options, $depth );
    }
}

/* ── Functions needed by RedirectManager ──────────────────────── */

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url, $component = -1 ) {
        if ( -1 === $component ) {
            return parse_url( $url );
        }
        return parse_url( $url, $component );
    }
}

if ( ! function_exists( 'wp_redirect' ) ) {
    function wp_redirect( $location, $status = 302 ) {
        $GLOBALS['bs_test_options']['_last_wp_redirect']        = $location;
        $GLOBALS['bs_test_options']['_last_wp_redirect_status'] = $status;

        if ( ! empty( $GLOBALS['bs_test_options']['_throw_on_redirect'] ) ) {
            throw new \RuntimeException( 'wp_redirect:' . $status . ':' . $location );
        }
    }
}

if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() {
        return ! empty( $GLOBALS['bs_test_options']['_is_admin'] );
    }
}

if ( ! function_exists( 'status_header' ) ) {
    function status_header( $code ) {
        $GLOBALS['bs_test_options']['_last_status_header'] = $code;
    }
}

/* ── Functions needed by LlmsTxt ─────────────────────────────── */

if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( $args = array() ) {
        $type   = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
        $limit  = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 10;
        $result = array();
        $count  = 0;
        $max    = 1000;
        $idx    = 0;

        foreach ( $GLOBALS['bs_test_posts'] as $post ) {
            if ( $idx >= $max ) { break; }
            $idx++;

            if ( isset( $post->post_type ) && $post->post_type === $type ) {
                if ( isset( $post->post_status ) && 'publish' !== $post->post_status ) {
                    continue;
                }
                $result[] = $post;
                $count++;
                if ( $count >= $limit ) { break; }
            }
        }

        return $result;
    }
}

if ( ! function_exists( 'get_categories' ) ) {
    function get_categories( $args = array() ) {
        return isset( $GLOBALS['bs_test_options']['_categories'] )
            ? $GLOBALS['bs_test_options']['_categories']
            : array();
    }
}

if ( ! function_exists( 'get_tags' ) ) {
    function get_tags( $args = array() ) {
        return isset( $GLOBALS['bs_test_options']['_tags'] )
            ? $GLOBALS['bs_test_options']['_tags']
            : array();
    }
}

if ( ! function_exists( 'get_category_link' ) ) {
    function get_category_link( $term_id ) {
        return 'https://example.com/category/' . $term_id;
    }
}

if ( ! function_exists( 'get_tag_link' ) ) {
    function get_tag_link( $term_id ) {
        return 'https://example.com/tag/' . $term_id;
    }
}

/* ── Functions needed by SeoOgImage ──────────────────────────── */

if ( ! function_exists( 'wp_upload_dir' ) ) {
    function wp_upload_dir() {
        return array(
            'basedir' => '/tmp/wp-stub-uploads',
            'baseurl' => 'https://example.com/wp-content/uploads',
        );
    }
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
    function wp_mkdir_p( $target ) {
        if ( ! is_dir( $target ) ) {
            return mkdir( $target, 0755, true );
        }
        return true;
    }
}

if ( ! function_exists( 'add_shortcode' ) ) {
    function add_shortcode( $tag, $callback ) {
        /* No-op for testing */
    }
}
