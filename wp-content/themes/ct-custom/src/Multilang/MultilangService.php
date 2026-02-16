<?php
/**
 * Multilanguage Service.
 *
 * Singleton service class that consumes the CT_Multilang_Helpers trait,
 * providing a single entry point for all multilanguage operations.
 *
 * @package BS_Custom
 */

namespace BSCustom\Multilang;

class MultilangService {

    use MultilangHelpers;

    /** @var MultilangService|null Singleton instance. */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        assert( null === self::$instance || self::$instance instanceof self, 'Instance must be null or MultilangService' );

        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        assert( self::$instance instanceof self, 'Instance must be a MultilangService' );

        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct() {}
}
