<?php

namespace BSCustom\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class JwtService {

    const ALGORITHM       = 'HS256';
    const MAX_TOKEN_LEN   = 4096;
    const MIN_SECRET_LEN  = 16;

    /** @var array|null Cached config from options. */
    private $config_cache = null;

    /**
     * Load and cache JWT config from the bs_custom_jwt_auth option.
     *
     * @return array Config array with defaults applied.
     */
    private function get_config() {
        if ( null !== $this->config_cache ) {
            return $this->config_cache;
        }

        $raw    = get_option( 'bs_custom_jwt_auth', '{}' );
        $config = json_decode( $raw, true );

        if ( ! is_array( $config ) ) {
            $config = array();
        }

        if ( ! isset( $config['secret'] ) ) {
            $config['secret'] = '';
        }

        if ( ! isset( $config['expiration_hours'] ) ) {
            $config['expiration_hours'] = 24;
        }

        assert( is_array( $config ), 'JWT config must be an array' );

        $this->config_cache = $config;
        return $config;
    }

    /**
     * Issue a JWT for an authenticated user.
     *
     * @param int $user_id WordPress user ID.
     * @return string|false JWT string or false on failure.
     */
    public function issue( $user_id ) {
        assert( is_int( $user_id ) && $user_id > 0, 'User ID must be a positive integer' );

        $secret = $this->get_secret();

        if ( false === $secret ) {
            return false;
        }

        $expiration_hours = (int) $this->get_config()['expiration_hours'];
        $now              = time();

        $payload = array(
            'iss'     => get_site_url(),
            'iat'     => $now,
            'exp'     => $now + ( $expiration_hours * 3600 ),
            'user_id' => $user_id,
        );

        assert( is_array( $payload ), 'Payload must be an array' );

        return JWT::encode( $payload, $secret, self::ALGORITHM );
    }

    /**
     * Issue a short-lived reset token.
     *
     * @param string $email       User email.
     * @param int    $ttl_minutes Token lifetime in minutes.
     * @return string|false JWT string or false on failure.
     */
    public function issue_reset_token( $email, $ttl_minutes = 10 ) {
        assert( is_string( $email ) && strlen( $email ) > 0, 'Email must be non-empty' );
        assert( is_int( $ttl_minutes ) && $ttl_minutes > 0, 'TTL must be positive' );

        $secret = $this->get_secret();

        if ( false === $secret ) {
            return false;
        }

        $now = time();

        $payload = array(
            'iss'     => get_site_url(),
            'iat'     => $now,
            'exp'     => $now + ( $ttl_minutes * 60 ),
            'email'   => $email,
            'purpose' => 'password_reset',
        );

        return JWT::encode( $payload, $secret, self::ALGORITHM );
    }

    /**
     * Verify and decode a JWT.
     *
     * @param string $token JWT string.
     * @return object|false Decoded payload or false on failure.
     */
    public function verify( $token ) {
        assert( is_string( $token ), 'Token must be a string' );

        if ( strlen( $token ) > self::MAX_TOKEN_LEN ) {
            return false;
        }

        $secret = $this->get_secret();

        if ( false === $secret ) {
            return false;
        }

        try {
            $decoded = JWT::decode( $token, new Key( $secret, self::ALGORITHM ) );
            assert( is_object( $decoded ), 'Decoded token must be an object' );
            return $decoded;
        } catch ( ExpiredException $e ) {
            return false;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get the JWT secret from options.
     *
     * @return string|false Secret key or false if not configured.
     */
    private function get_secret() {
        $secret = $this->get_config()['secret'];

        if ( empty( $secret ) || strlen( $secret ) < self::MIN_SECRET_LEN ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[CT_JWT_Service] JWT secret is not configured or too short.' );
            }
            return false;
        }

        assert( is_string( $secret ), 'Secret must be a string' );

        return $secret;
    }
}
