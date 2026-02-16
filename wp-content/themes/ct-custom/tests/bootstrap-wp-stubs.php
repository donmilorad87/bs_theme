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

if ( defined( 'CT_WP_STUBS_LOADED' ) ) {
    return;
}
define( 'CT_WP_STUBS_LOADED', true );

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

$GLOBALS['ct_test_transients']    = array();
$GLOBALS['ct_test_transient_ttl'] = array();
$GLOBALS['ct_test_users']         = array();
$GLOBALS['ct_test_user_meta']     = array();
$GLOBALS['ct_test_options']       = array();
$GLOBALS['ct_test_current_user']  = 0;
$GLOBALS['ct_test_error_log']     = array();
$GLOBALS['ct_test_next_user_id']  = 1;
$GLOBALS['ct_test_server']        = array();

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
        if ( ! isset( $GLOBALS['ct_test_transients'][ $key ] ) ) {
            return false;
        }

        /* Check expiration */
        if ( isset( $GLOBALS['ct_test_transient_ttl'][ $key ] ) ) {
            if ( time() > $GLOBALS['ct_test_transient_ttl'][ $key ] ) {
                unset( $GLOBALS['ct_test_transients'][ $key ] );
                unset( $GLOBALS['ct_test_transient_ttl'][ $key ] );
                return false;
            }
        }

        return $GLOBALS['ct_test_transients'][ $key ];
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $ttl = 0 ) {
        $GLOBALS['ct_test_transients'][ $key ] = $value;
        if ( $ttl > 0 ) {
            $GLOBALS['ct_test_transient_ttl'][ $key ] = time() + $ttl;
            /* Also store in options for get_rate_limit_remaining() */
            $GLOBALS['ct_test_options'][ '_transient_timeout_' . $key ] = time() + $ttl;
        }
        return true;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) {
        unset( $GLOBALS['ct_test_transients'][ $key ] );
        unset( $GLOBALS['ct_test_transient_ttl'][ $key ] );
        unset( $GLOBALS['ct_test_options'][ '_transient_timeout_' . $key ] );
        return true;
    }
}

/* ── User functions ─────────────────────────────────────────────── */

if ( ! function_exists( 'get_user_by' ) ) {
    function get_user_by( $field, $value ) {
        $max = 1000;
        $count = 0;

        foreach ( $GLOBALS['ct_test_users'] as $user ) {
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
        if ( ! isset( $GLOBALS['ct_test_user_meta'][ $user_id ][ $key ] ) ) {
            return $single ? '' : array();
        }

        $value = $GLOBALS['ct_test_user_meta'][ $user_id ][ $key ];
        return $single ? $value : array( $value );
    }
}

if ( ! function_exists( 'update_user_meta' ) ) {
    function update_user_meta( $user_id, $key, $value ) {
        if ( ! isset( $GLOBALS['ct_test_user_meta'][ $user_id ] ) ) {
            $GLOBALS['ct_test_user_meta'][ $user_id ] = array();
        }
        $GLOBALS['ct_test_user_meta'][ $user_id ][ $key ] = $value;
        return true;
    }
}

if ( ! function_exists( 'wp_insert_user' ) ) {
    function wp_insert_user( $userdata ) {
        $id = $GLOBALS['ct_test_next_user_id']++;

        $user = new \stdClass();
        $user->ID           = $id;
        $user->user_login   = $userdata['user_login'] ?? '';
        $user->user_email   = $userdata['user_email'] ?? '';
        $user->user_pass    = password_hash( $userdata['user_pass'] ?? '', PASSWORD_BCRYPT );
        $user->first_name   = $userdata['first_name'] ?? '';
        $user->last_name    = $userdata['last_name'] ?? '';
        $user->display_name = trim( ( $userdata['first_name'] ?? '' ) . ' ' . ( $userdata['last_name'] ?? '' ) );
        $user->roles        = array( $userdata['role'] ?? 'subscriber' );

        $GLOBALS['ct_test_users'][ $id ] = $user;
        return $id;
    }
}

if ( ! function_exists( 'wp_update_user' ) ) {
    function wp_update_user( $userdata ) {
        $id = $userdata['ID'] ?? 0;

        if ( ! isset( $GLOBALS['ct_test_users'][ $id ] ) ) {
            return new \WP_Error( 'invalid_user', 'User not found.' );
        }

        $user = $GLOBALS['ct_test_users'][ $id ];

        if ( isset( $userdata['first_name'] ) ) {
            $user->first_name = $userdata['first_name'];
        }
        if ( isset( $userdata['last_name'] ) ) {
            $user->last_name = $userdata['last_name'];
        }
        if ( isset( $userdata['display_name'] ) ) {
            $user->display_name = $userdata['display_name'];
        }

        $GLOBALS['ct_test_users'][ $id ] = $user;
        return $id;
    }
}

if ( ! function_exists( 'username_exists' ) ) {
    function username_exists( $username ) {
        $max = 1000;
        $count = 0;

        foreach ( $GLOBALS['ct_test_users'] as $user ) {
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

        foreach ( $GLOBALS['ct_test_users'] as $user ) {
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
        if ( isset( $GLOBALS['ct_test_users'][ $user_id ] ) ) {
            $GLOBALS['ct_test_users'][ $user_id ]->user_pass = password_hash( $password, PASSWORD_BCRYPT );
        }
    }
}

if ( ! function_exists( 'wp_logout' ) ) {
    function wp_logout() {
        $GLOBALS['ct_test_current_user'] = 0;
    }
}

if ( ! function_exists( 'wp_set_current_user' ) ) {
    function wp_set_current_user( $user_id ) {
        $GLOBALS['ct_test_current_user'] = (int) $user_id;
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        return (int) $GLOBALS['ct_test_current_user'];
    }
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
    function is_user_logged_in() {
        return $GLOBALS['ct_test_current_user'] > 0;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        $user_id = get_current_user_id();
        if ( ! $user_id || ! isset( $GLOBALS['ct_test_users'][ $user_id ] ) ) {
            return false;
        }
        $user = $GLOBALS['ct_test_users'][ $user_id ];
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
        return isset( $GLOBALS['ct_test_options'][ $key ] )
            ? $GLOBALS['ct_test_options'][ $key ]
            : $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $key, $value ) {
        $GLOBALS['ct_test_options'][ $key ] = $value;
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
        return $GLOBALS['ct_test_options']['_is_singular'] ?? false;
    }
}

if ( ! function_exists( 'get_queried_object' ) ) {
    function get_queried_object() {
        return $GLOBALS['ct_test_options']['_queried_object'] ?? null;
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
        $GLOBALS['ct_test_options']['_last_redirect'] = $url;

        /* When the flag is set, throw so that any subsequent exit is never reached. */
        if ( ! empty( $GLOBALS['ct_test_options']['_throw_on_redirect'] ) ) {
            throw new \RuntimeException( 'wp_safe_redirect:' . $url );
        }
    }
}

if ( ! function_exists( 'get_avatar_url' ) ) {
    function get_avatar_url( $user_id, $args = array() ) {
        $meta = get_user_meta( $user_id, 'ct_avatar_id', true );
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

if ( ! function_exists( 'ct_jwt_or_cookie_permission_check' ) ) {
    function ct_jwt_or_cookie_permission_check( $request ) {
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

if ( ! function_exists( 'ct_get_language_home_url' ) ) {
    function ct_get_language_home_url() {
        return 'https://example.com/';
    }
}

/* ── Error logging capture ──────────────────────────────────────── */

/* Override error_log in test context to capture messages */
if ( ! function_exists( 'ct_test_get_error_log' ) ) {
    function ct_test_get_error_log() {
        return $GLOBALS['ct_test_error_log'];
    }
}

/* ── Mail service stub ──────────────────────────────────────────── */

/* The MailService and EmailTemplate are used by endpoints but we don't
   want to actually send email in tests. We override them minimally here. */

/* ── Functions needed by EmailTemplate ─────────────────────────── */

if ( ! function_exists( 'get_theme_mod' ) ) {
    function get_theme_mod( $name, $default = false ) {
        return isset( $GLOBALS['ct_test_options'][ 'theme_mod_' . $name ] )
            ? $GLOBALS['ct_test_options'][ 'theme_mod_' . $name ]
            : $default;
    }
}

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '' ) {
        if ( 'name' === $show ) {
            return isset( $GLOBALS['ct_test_options']['blogname'] )
                ? $GLOBALS['ct_test_options']['blogname']
                : 'Test Site';
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
