<?php
/**
 * REST Logger Trait
 *
 * Provides a shared log() method for REST API endpoints.
 * Outputs to PHP error_log (stderr -> docker logs) when WP_DEBUG is ON.
 *
 * @package BSCustom\RestApi
 */

namespace BSCustom\RestApi;

trait RestLogger {

    /**
     * Log a message with auto-prefixed class name.
     *
     * Output format: [BS_REST_ClassName] message
     * Only logs when WP_DEBUG is enabled.
     *
     * @param string $message The message to log.
     */
    private function log( $message ) {
        assert( is_string( $message ), 'Log message must be a string' );
        assert( strlen( $message ) > 0, 'Log message must not be empty' );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $class = ( new \ReflectionClass( $this ) )->getShortName();
            error_log( '[BS_REST_' . $class . '] ' . $message );
        }
    }
}
