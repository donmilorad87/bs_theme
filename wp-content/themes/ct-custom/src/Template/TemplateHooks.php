<?php
/**
 * Template Hooks singleton.
 *
 * Consumes both TemplateTags and TemplateFunctions traits,
 * and registers all WordPress hooks from the template layer.
 *
 * @package CT_Custom
 */

namespace CTCustom\Template;

class TemplateHooks {

	use TemplateTags;
	use TemplateFunctions;

	/** @var TemplateHooks|null Singleton instance. */
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

		assert( self::$instance instanceof self, 'Instance must be TemplateHooks' );

		return self::$instance;
	}

	/**
	 * Private constructor — registers all hooks.
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register all WordPress hooks from the template layer.
	 *
	 * @return void
	 */
	private function register_hooks() {
		assert( function_exists( 'add_filter' ), 'add_filter must exist' );
		assert( function_exists( 'add_action' ), 'add_action must exist' );

		/* From CT_Template_Functions trait */
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_action( 'wp_head', array( $this, 'pingback_header' ) );
		add_action( 'wp_head', array( $this, 'breadcrumb_schema' ), 5 );
		add_action( 'wp_head', array( $this, 'contact_point_schema' ), 5 );

		/* Invalidate Organization schema cache when relevant options change */
		add_action( 'update_option_ct_custom_contact_point', array( $this, 'invalidate_schema_cache' ) );
		add_action( 'update_option_ct_custom_social_networks', array( $this, 'invalidate_schema_cache' ) );
		add_action( 'customize_save_after', array( $this, 'invalidate_schema_cache' ) );

		/* Auth form shortcodes */
		add_shortcode( 'ct_login_form', array( $this, 'login_form_shortcode' ) );
		add_shortcode( 'ct_register_form', array( $this, 'register_form_shortcode' ) );

		/* Contact shortcodes */
		add_shortcode( 'ct_contact_form', array( $this, 'contact_form_shortcode' ) );
		add_shortcode( 'ct_social_icons', array( $this, 'social_icons_shortcode' ) );

		/* Language shortcode and filter */
		add_shortcode( 'ct_language_switcher', array( $this, 'language_switcher_shortcode' ) );
		add_filter( 'language_attributes', array( $this, 'language_attributes_filter' ) );
	}

	/**
	 * Login form shortcode: [ct_login_form]
	 *
	 * @return string HTML markup for the login form.
	 */
	public function login_form_shortcode() {
		assert( function_exists( 'get_template_part' ), 'get_template_part must exist' );
		assert( function_exists( 'ob_start' ), 'ob_start must exist' );

		ob_start();
		get_template_part( 'template-parts/auth/login' );
		return ob_get_clean();
	}

	/**
	 * Register form shortcode: [ct_register_form]
	 *
	 * @return string HTML markup for the registration form.
	 */
	public function register_form_shortcode() {
		assert( function_exists( 'get_template_part' ), 'get_template_part must exist' );
		assert( function_exists( 'ob_start' ), 'ob_start must exist' );

		ob_start();
		get_template_part( 'template-parts/auth/register' );
		return ob_get_clean();
	}

	/**
	 * Contact form shortcode: [ct_contact_form]
	 *
	 * Renders the contact form template part with fresh nonces.
	 *
	 * @return string HTML markup for the contact form.
	 */
	public function contact_form_shortcode() {
		assert( function_exists( 'get_template_part' ), 'get_template_part must exist' );
		assert( function_exists( 'ob_start' ), 'ob_start must exist' );

		ob_start();
		get_template_part( 'template-parts/contact-form' );
		return ob_get_clean();
	}

	/**
	 * Social icons shortcode: [ct_social_icons]
	 *
	 * Renders social icons from current Customizer data.
	 *
	 * @return string HTML markup for social icons.
	 */
	public function social_icons_shortcode() {
		assert( class_exists( Contact::class ), 'Contact class must be loaded' );
		assert( function_exists( 'ob_start' ), 'ob_start must exist' );

		$contact  = Contact::instance();
		$networks = $contact->get_social_networks();

		ob_start();
		$contact->render_social_icons_markup( $networks );
		return ob_get_clean();
	}

	/**
	 * Language switcher shortcode: [ct_language_switcher]
	 *
	 * @return string HTML markup for the language switcher.
	 */
	public function language_switcher_shortcode() {
		assert( function_exists( 'get_template_directory' ), 'get_template_directory must exist' );

		if ( ! Language::instance()->is_multilingual() ) {
			return '';
		}

		ob_start();
		$ct_switcher_data = Language::instance()->get_language_switcher_data();
		include get_template_directory() . '/template-parts/language-switcher.php';
		return ob_get_clean();
	}

	/**
	 * Filter the HTML lang attribute to match the current page language.
	 *
	 * @param string $output language_attributes output.
	 * @return string
	 */
	public function language_attributes_filter( $output ) {
		assert( is_string( $output ), 'output must be a string' );

		$current = ct_get_current_language();

		assert( is_string( $current ), 'current language must be a string' );

		if ( '' !== $current ) {
			$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $current ) . '"', $output );
		}

		return $output;
	}
}
