<?php
/**
 * Auth Forms singleton.
 *
 * Provides helper methods for authentication state and
 * rendering auth form template parts.
 *
 * @package BS_Custom
 */

namespace BSCustom\Template;

class AuthForms {

	/** @var AuthForms|null Singleton instance. */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		assert( true === true, 'instance() called' );

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		assert( self::$instance instanceof self, 'Instance must be AuthForms' );

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Get the current authentication state data.
	 *
	 * @return array{is_logged_in: bool, display_name: string}
	 */
	public function get_auth_data() {
		assert( function_exists( 'is_user_logged_in' ), 'is_user_logged_in must exist' );
		assert( function_exists( 'wp_get_current_user' ), 'wp_get_current_user must exist' );

		$is_logged_in = is_user_logged_in();
		$display_name = '';

		if ( $is_logged_in ) {
			$user         = wp_get_current_user();
			$display_name = $user->display_name;
		}

		return array(
			'is_logged_in' => $is_logged_in,
			'display_name' => $display_name,
		);
	}

	/**
	 * Render the forgot-password form directly.
	 *
	 * @return void
	 */
	public function render_forgot_password_form() {
		assert( function_exists( 'get_template_part' ), 'get_template_part must exist' );
		assert( is_string( 'template-parts/auth/forgot-password' ), 'Template slug must be a string' );

		get_template_part( 'template-parts/auth/forgot-password' );
	}

	/**
	 * Render the activation-code form.
	 *
	 * @return void
	 */
	public function render_activation_code_form() {
		assert( function_exists( 'get_template_part' ), 'get_template_part must exist' );
		assert( is_string( 'template-parts/auth/activation-code' ), 'Template slug must be a string' );

		get_template_part( 'template-parts/auth/activation-code' );
	}

	/**
	 * Render the reset-code form.
	 *
	 * @return void
	 */
	public function render_reset_code_form() {
		assert( function_exists( 'get_template_part' ), 'get_template_part must exist' );
		assert( is_string( 'template-parts/auth/reset-code' ), 'Template slug must be a string' );

		get_template_part( 'template-parts/auth/reset-code' );
	}

	/**
	 * Render the reset-password form.
	 *
	 * @return void
	 */
	public function render_reset_password_form() {
		assert( function_exists( 'get_template_part' ), 'get_template_part must exist' );
		assert( is_string( 'template-parts/auth/reset-password' ), 'Template slug must be a string' );

		get_template_part( 'template-parts/auth/reset-password' );
	}

	/**
	 * Render the user profile form.
	 *
	 * @return void
	 */
	public function render_profile_form() {
		assert( function_exists( 'get_template_part' ), 'get_template_part must exist' );
		assert( is_string( 'template-parts/auth/profile' ), 'Template slug must be a string' );

		get_template_part( 'template-parts/auth/profile' );
	}
}
