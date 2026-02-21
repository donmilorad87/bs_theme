<?php
/**
 * Rate Limiter Trait
 *
 * Provides shared rate-limiting methods for REST API endpoints.
 * Uses WordPress transients for storage.
 *
 * @package BSCustom\RestApi
 */

namespace BSCustom\RestApi;

trait RateLimiter {

    /**
     * Get the client IP address.
     *
     * @return string IP address.
     */
    private function get_client_ip() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : '0.0.0.0';

        assert( is_string( $ip ), 'IP must be a string' );
        assert( strlen( $ip ) > 0, 'IP must not be empty' );

        return $ip;
    }

    /**
     * Check if an IP is rate-limited.
     *
     * @param string $prefix     Transient prefix.
     * @param string $ip         Client IP.
     * @param int    $max_attempts Maximum allowed attempts.
     * @return bool True if rate-limited.
     */
    private function is_rate_limited_by_ip( $prefix, $ip, $max_attempts ) {
        assert( is_string( $prefix ), 'Prefix must be a string' );
        assert( is_int( $max_attempts ) && $max_attempts > 0, 'Max attempts must be positive' );

        $key      = $prefix . md5( $ip );
        $attempts = (int) get_transient( $key );

        return $attempts >= $max_attempts;
    }

    /**
     * Check if a key (email, username, etc.) is rate-limited.
     *
     * @param string $prefix       Transient prefix.
     * @param string $key_value    The key to check.
     * @param int    $max_attempts Maximum allowed attempts.
     * @return bool True if rate-limited.
     */
    private function is_rate_limited_by_key( $prefix, $key_value, $max_attempts ) {
        assert( is_string( $prefix ), 'Prefix must be a string' );
        assert( is_string( $key_value ), 'Key value must be a string' );

        $key      = $prefix . md5( $key_value );
        $attempts = (int) get_transient( $key );

        return $attempts >= $max_attempts;
    }

    /**
     * Increment the rate limit counter.
     *
     * @param string $prefix     Transient prefix.
     * @param string $identifier IP or key to track.
     * @param int    $window_sec Time window in seconds.
     */
    private function increment_rate_limit( $prefix, $identifier, $window_sec ) {
        assert( is_string( $prefix ), 'Prefix must be a string' );
        assert( is_int( $window_sec ) && $window_sec > 0, 'Window must be positive' );

        $key      = $prefix . md5( $identifier );
        $attempts = (int) get_transient( $key );

        set_transient( $key, $attempts + 1, $window_sec );
    }

    /**
     * Get the remaining seconds until a rate limit expires.
     *
     * @param string $prefix     Transient prefix.
     * @param string $identifier IP or key that is limited.
     * @return int Remaining seconds (0 if not found or already expired).
     */
    private function get_rate_limit_remaining( $prefix, $identifier ) {
        assert( is_string( $prefix ), 'Prefix must be a string' );
        assert( is_string( $identifier ), 'Identifier must be a string' );

        $key     = $prefix . md5( $identifier );
        $timeout = (int) get_option( '_transient_timeout_' . $key, 0 );

        if ( 0 === $timeout ) {
            return 0;
        }

        $remaining = $timeout - time();

        return $remaining > 0 ? $remaining : 0;
    }

    /**
     * Format remaining seconds into a human-readable string.
     *
     * @param int $seconds Remaining seconds.
     * @return string Formatted string (e.g. "4 minutes", "2 minutes and 30 seconds").
     */
    private function format_wait_time( $seconds ) {
        assert( is_int( $seconds ) && $seconds >= 0, 'Seconds must be non-negative' );

        if ( $seconds <= 0 ) {
            return __( 'a few seconds', 'ct-custom' );
        }

        $minutes = (int) floor( $seconds / 60 );
        $secs    = $seconds % 60;

        if ( $minutes > 0 && $secs > 0 ) {
            return sprintf(
                /* translators: 1: minutes, 2: seconds */
                __( '%1$d minute(s) and %2$d second(s)', 'ct-custom' ),
                $minutes,
                $secs
            );
        }

        if ( $minutes > 0 ) {
            return sprintf(
                /* translators: %d: minutes */
                __( '%d minute(s)', 'ct-custom' ),
                $minutes
            );
        }

        return sprintf(
            /* translators: %d: seconds */
            __( '%d second(s)', 'ct-custom' ),
            $secs
        );
    }

    /**
     * Get a per-user identifier for throttling.
     *
     * Logged-in users use their user ID. Guests use a session/cookie token.
     *
     * @return string
     */
    protected function get_rate_identifier(): string {
        if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        }

        $session_id = '';
        if ( function_exists( 'session_id' ) ) {
            if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) {
                @session_start();
            }
            $session_id = session_id();
        }

        $token = '';
        if ( is_string( $session_id ) && '' !== $session_id ) {
            $token = $session_id;
        }

        $cookie_name = 'bs_rest_throttle';
        if ( '' === $token && isset( $_COOKIE[ $cookie_name ] ) && is_string( $_COOKIE[ $cookie_name ] ) ) {
            $cookie_val = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
            if ( '' !== $cookie_val ) {
                $token = $cookie_val;
            }
        }

        if ( '' === $token ) {
            $token = wp_generate_uuid4();
            if ( ! headers_sent() ) {
                $expires = time() + ( 30 * DAY_IN_SECONDS );
                $path = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
                $domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
                $secure = function_exists( 'is_ssl' ) ? is_ssl() : false;
                setcookie( $cookie_name, $token, $expires, $path, $domain, $secure, true );
                $_COOKIE[ $cookie_name ] = $token;
            }
        }

        $ua = '';
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $ua = substr( md5( $_SERVER['HTTP_USER_AGENT'] ), 0, 8 );
        }

        return 'guest_' . $ua . '_' . $token;
    }

    /**
     * Get global throttle limit (max submissions).
     *
     * @return int
     */
    protected function get_global_throttle_limit(): int {
        $limit = get_option( 'bs_contact_throttle_limit', 5 );
        $limit = absint( $limit );
        if ( $limit < 1 ) {
            $limit = 5;
        }
        if ( $limit > 200 ) {
            $limit = 200;
        }
        return $limit;
    }

    /**
     * Get global throttle window in seconds.
     *
     * @return int
     */
    protected function get_global_throttle_window(): int {
        $minutes = get_option( 'bs_contact_throttle_window', 1 );
        $minutes = absint( $minutes );
        if ( $minutes < 1 ) {
            $minutes = 1;
        }
        if ( $minutes > 1440 ) {
            $minutes = 1440;
        }
        return $minutes * 60;
    }
}
