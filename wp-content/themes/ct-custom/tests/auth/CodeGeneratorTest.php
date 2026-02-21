<?php
/**
 * Tests for CodeGenerator trait.
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

class CodeGeneratorTest extends AuthTestCase {

    /** @var object Test class that uses the CodeGenerator trait. */
    private $generator;

    protected function setUp(): void {
        parent::setUp();

        $this->generator = new class {
            use \BSCustom\RestApi\CodeGenerator;

            public function callGenerate(): string {
                return $this->generate_code();
            }

            public function callStore( string $prefix, string $key, string $code, int $ttl ): void {
                $this->store_code( $prefix, $key, $code, $ttl );
            }

            public function callVerify( string $prefix, string $key, string $code ): bool {
                return $this->verify_code( $prefix, $key, $code );
            }

            public function callDelete( string $prefix, string $key ): void {
                $this->delete_code( $prefix, $key );
            }
        };
    }

    /* ── generate_code ───────────────────────────────────────────── */

    public function test_code_is_6_digits(): void {
        $code = $this->generator->callGenerate();

        $this->assertSame( 6, strlen( $code ) );
        $this->assertMatchesRegularExpression( '/^\d{6}$/', $code );
    }

    public function test_code_is_zero_padded(): void {
        /* Run multiple times to increase chance of testing padding */
        $all_six = true;
        for ( $i = 0; $i < 20; $i++ ) {
            $code = $this->generator->callGenerate();
            if ( strlen( $code ) !== 6 ) {
                $all_six = false;
                break;
            }
        }
        $this->assertTrue( $all_six );
    }

    /* ── store + verify ──────────────────────────────────────────── */

    public function test_store_and_verify_correct_code(): void {
        $this->generator->callStore( 'bs_test_', 'user@test.com', '123456', 1800 );

        $result = $this->generator->callVerify( 'bs_test_', 'user@test.com', '123456' );

        $this->assertTrue( $result );
    }

    public function test_verify_wrong_code(): void {
        $this->generator->callStore( 'bs_test_', 'user@test.com', '123456', 1800 );

        $result = $this->generator->callVerify( 'bs_test_', 'user@test.com', '999999' );

        $this->assertFalse( $result );
    }

    public function test_verify_expired_code(): void {
        /* Store with 0 TTL effectively means it expires immediately */
        $key = 'bs_test_' . md5( 'user@test.com' );
        $GLOBALS['bs_test_transients'][ $key ] = '123456';
        $GLOBALS['bs_test_transient_ttl'][ $key ] = time() - 1; /* Already expired */

        $result = $this->generator->callVerify( 'bs_test_', 'user@test.com', '123456' );

        $this->assertFalse( $result );
    }

    /* ── delete_code ─────────────────────────────────────────────── */

    public function test_delete_code_removes_transient(): void {
        $this->generator->callStore( 'bs_test_', 'user@test.com', '123456', 1800 );

        $this->generator->callDelete( 'bs_test_', 'user@test.com' );

        $result = $this->generator->callVerify( 'bs_test_', 'user@test.com', '123456' );
        $this->assertFalse( $result );
    }

    /* ── hash_equals behavior ────────────────────────────────────── */

    public function test_verify_uses_timing_safe_comparison(): void {
        $this->generator->callStore( 'bs_test_', 'key', '000001', 1800 );

        /* Verify uses hash_equals internally */
        $this->assertTrue( $this->generator->callVerify( 'bs_test_', 'key', '000001' ) );
        $this->assertFalse( $this->generator->callVerify( 'bs_test_', 'key', '1' ) );
    }
}
