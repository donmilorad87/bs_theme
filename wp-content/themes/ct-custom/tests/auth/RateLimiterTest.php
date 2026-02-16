<?php
/**
 * Tests for RateLimiter trait.
 *
 * @package CTCustom\Tests\Auth
 */

namespace CTCustom\Tests\Auth;

class RateLimiterTest extends AuthTestCase {

    /** @var object Test class that uses the RateLimiter trait. */
    private $limiter;

    protected function setUp(): void {
        parent::setUp();

        $this->limiter = new class {
            use \CTCustom\RestApi\RateLimiter;

            public function callIsRateLimitedByIp( string $prefix, string $ip, int $max ): bool {
                return $this->is_rate_limited_by_ip( $prefix, $ip, $max );
            }

            public function callIsRateLimitedByKey( string $prefix, string $key, int $max ): bool {
                return $this->is_rate_limited_by_key( $prefix, $key, $max );
            }

            public function callIncrement( string $prefix, string $id, int $window ): void {
                $this->increment_rate_limit( $prefix, $id, $window );
            }

            public function callGetClientIp(): string {
                return $this->get_client_ip();
            }

            public function callGetRemaining( string $prefix, string $id ): int {
                return $this->get_rate_limit_remaining( $prefix, $id );
            }

            public function callFormatWaitTime( int $seconds ): string {
                return $this->format_wait_time( $seconds );
            }
        };
    }

    /* ── is_rate_limited_by_ip ───────────────────────────────────── */

    public function test_not_limited_initially(): void {
        $result = $this->limiter->callIsRateLimitedByIp( 'test_', '10.0.0.1', 5 );
        $this->assertFalse( $result );
    }

    public function test_limited_after_max_attempts(): void {
        $prefix = 'test_';
        $ip     = '10.0.0.1';

        /* Increment to reach the limit */
        for ( $i = 0; $i < 5; $i++ ) {
            $this->limiter->callIncrement( $prefix, $ip, 300 );
        }

        $this->assertTrue( $this->limiter->callIsRateLimitedByIp( $prefix, $ip, 5 ) );
    }

    /* ── is_rate_limited_by_key ──────────────────────────────────── */

    public function test_limited_by_key(): void {
        $prefix = 'email_';
        $email  = 'user@example.com';

        for ( $i = 0; $i < 3; $i++ ) {
            $this->limiter->callIncrement( $prefix, $email, 3600 );
        }

        $this->assertTrue( $this->limiter->callIsRateLimitedByKey( $prefix, $email, 3 ) );
    }

    /* ── get_client_ip ───────────────────────────────────────────── */

    public function test_get_ip_from_server(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $this->assertSame( '192.168.1.1', $this->limiter->callGetClientIp() );
    }

    public function test_get_ip_fallback(): void {
        unset( $_SERVER['REMOTE_ADDR'] );
        $this->assertSame( '0.0.0.0', $this->limiter->callGetClientIp() );
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; /* restore */
    }

    /* ── format_wait_time ────────────────────────────────────────── */

    public function test_format_minutes_and_seconds(): void {
        $result = $this->limiter->callFormatWaitTime( 150 );
        $this->assertStringContainsString( '2', $result );
        $this->assertStringContainsString( '30', $result );
    }

    public function test_format_minutes_only(): void {
        $result = $this->limiter->callFormatWaitTime( 120 );
        $this->assertStringContainsString( '2', $result );
        $this->assertStringNotContainsString( 'second', $result );
    }

    public function test_format_seconds_only(): void {
        $result = $this->limiter->callFormatWaitTime( 45 );
        $this->assertStringContainsString( '45', $result );
    }

    public function test_format_zero_seconds(): void {
        $result = $this->limiter->callFormatWaitTime( 0 );
        $this->assertStringContainsString( 'few seconds', $result );
    }

    /* ── increment_rate_limit ────────────────────────────────────── */

    public function test_increment_creates_transient(): void {
        $prefix = 'inc_test_';
        $id     = '10.0.0.1';

        $this->limiter->callIncrement( $prefix, $id, 300 );

        $key   = $prefix . md5( $id );
        $value = \get_transient( $key );

        $this->assertSame( 1, (int) $value );
    }
}
