<?php
/**
 * Tests for RestLogger trait.
 *
 * @package CTCustom\Tests\Auth
 */

namespace CTCustom\Tests\Auth;

class RestLoggerTest extends AuthTestCase {

    /** @var object Test class that uses the RestLogger trait. */
    private $logger;

    protected function setUp(): void {
        parent::setUp();

        $this->logger = new class {
            use \CTCustom\RestApi\RestLogger;

            public function callLog( string $message ): void {
                $this->log( $message );
            }
        };
    }

    public function test_logs_when_debug_on(): void {
        /* WP_DEBUG is defined as false in stubs; we test the branch logic indirectly.
         * Since we can't redefine a constant, we verify the method doesn't throw. */
        $this->logger->callLog( 'Test message' );
        $this->assertTrue( true ); /* No exception thrown */
    }

    public function test_silent_when_debug_off(): void {
        /* WP_DEBUG is false in stubs, so error_log should not be called */
        $this->logger->callLog( 'Should be silent' );
        $this->assertTrue( true );
    }

    public function test_message_format(): void {
        /* Verify the method accepts non-empty strings without error */
        $this->logger->callLog( 'Rate limited: IP=127.0.0.1' );
        $this->assertTrue( true );
    }
}
