<?php
/**
 * Global REST Throttle
 *
 * Applies a per-user throttle to frontend REST routes.
 *
 * @package BSCustom\RestApi
 */

namespace BSCustom\RestApi;

class GlobalThrottle {

    use RateLimiter;

    const PREFIX = 'bs_global_rest_';

    /**
     * Register the throttle filter.
     */
    public function register() {
        assert( function_exists( 'add_filter' ), 'add_filter must exist' );

        add_filter( 'rest_pre_dispatch', array( $this, 'maybe_throttle' ), 10, 3 );
    }

    /**
     * Throttle frontend REST requests globally.
     *
     * @param mixed            $result  Response to replace, or null to continue.
     * @param \WP_REST_Server  $server  Server instance.
     * @param \WP_REST_Request $request Request object.
     * @return mixed
     */
    public function maybe_throttle( $result, $server, $request ) {
        if ( null !== $result ) {
            return $result;
        }

        if ( ! ( $request instanceof \WP_REST_Request ) ) {
            return $result;
        }

        if ( 'OPTIONS' === $request->get_method() ) {
            return $result;
        }

        $route = $request->get_route();
        if ( ! is_string( $route ) || ! $this->is_frontend_route( $route ) ) {
            return $result;
        }

        if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
            return $result;
        }

        $limit      = $this->get_global_throttle_limit();
        $window_sec = $this->get_global_throttle_window();
        $identifier = $this->get_rate_identifier();
        $prefix     = self::PREFIX . $window_sec . '_' . $limit . '_';

        if ( $this->is_rate_limited_by_key( $prefix, $identifier, $limit ) ) {
            $remaining = $this->get_rate_limit_remaining( $prefix, $identifier );
            $wait_text = $this->format_wait_time( $remaining );

            return new \WP_REST_Response( array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: wait time */
                    __( 'Too many submissions. Please try again in %s.', 'ct-custom' ),
                    $wait_text
                ),
            ), 429 );
        }

        $this->increment_rate_limit( $prefix, $identifier, $window_sec );

        return $result;
    }

    /**
     * Check if a route is a frontend REST route.
     *
     * @param string $route Route path.
     * @return bool
     */
    private function is_frontend_route( string $route ): bool {
        return 0 === strpos( $route, '/ct-auth/v1/' ) || 0 === strpos( $route, '/ct-custom/v1/' );
    }
}
