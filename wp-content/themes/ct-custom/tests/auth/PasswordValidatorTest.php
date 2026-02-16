<?php
/**
 * Tests for PasswordValidator trait.
 *
 * @package BSCustom\Tests\Auth
 */

namespace BSCustom\Tests\Auth;

class PasswordValidatorTest extends AuthTestCase {

    /** @var object Test class that uses the PasswordValidator trait. */
    private $validator;

    protected function setUp(): void {
        parent::setUp();

        /* Anonymous class to expose the private trait method for testing */
        $this->validator = new class {
            use \BSCustom\RestApi\PasswordValidator;

            public function validate( string $password ) {
                return $this->validate_password_strength( $password );
            }
        };
    }

    /* ── Happy path ──────────────────────────────────────────────── */

    public function test_valid_password_passes(): void {
        $result = $this->validator->validate( 'Abcdef1!' );
        $this->assertTrue( $result );
    }

    public function test_exactly_8_chars_passes(): void {
        $result = $this->validator->validate( 'Ab1!xxxx' );
        $this->assertTrue( $result );
    }

    public function test_all_special_chars_passes(): void {
        $result = $this->validator->validate( 'Aa1!@#$%' );
        $this->assertTrue( $result );
    }

    public function test_unicode_special_char_passes(): void {
        /* Non-ASCII character counts as special (non-alphanumeric) */
        $result = $this->validator->validate( 'Abcdef1€' );
        $this->assertTrue( $result );
    }

    /* ── Failure cases ───────────────────────────────────────────── */

    public function test_too_short_fails(): void {
        $result = $this->validator->validate( 'Ab1!xyz' );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'weak_password', $result->get_error_code() );
    }

    public function test_no_lowercase_fails(): void {
        $result = $this->validator->validate( 'ABCDEF1!' );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'weak_password', $result->get_error_code() );
    }

    public function test_no_uppercase_fails(): void {
        $result = $this->validator->validate( 'abcdef1!' );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'weak_password', $result->get_error_code() );
    }

    public function test_no_digit_fails(): void {
        $result = $this->validator->validate( 'Abcdefg!' );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'weak_password', $result->get_error_code() );
    }

    public function test_no_special_char_fails(): void {
        $result = $this->validator->validate( 'Abcdef12' );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'weak_password', $result->get_error_code() );
    }

    public function test_empty_password_fails(): void {
        $result = $this->validator->validate( '' );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'weak_password', $result->get_error_code() );
    }
}
