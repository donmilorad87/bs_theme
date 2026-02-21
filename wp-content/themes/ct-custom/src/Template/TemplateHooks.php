<?php
/**
 * Template Hooks singleton.
 *
 * Consumes both TemplateTags and TemplateFunctions traits,
 * and registers all WordPress hooks from the template layer.
 *
 * @package BS_Custom
 */

namespace BSCustom\Template;

use BSCustom\Cpt\ContactFormCpt;

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

		/* From BS_Template_Functions trait */
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_action( 'wp_head', array( $this, 'pingback_header' ) );

		/*
		 * breadcrumb_schema and contact_point_schema are now handled by
		 * SeoService::output_schema_graph() as a unified @graph output.
		 * Cache invalidation also moved to SeoService.
		 */

		/* Auth form shortcodes */
		add_shortcode( 'bs_login_form', array( $this, 'login_form_shortcode' ) );
		add_shortcode( 'bs_register_form', array( $this, 'register_form_shortcode' ) );

		/* Contact shortcodes */
		add_shortcode( 'bs_contact_form', array( $this, 'contact_form_shortcode' ) );
		add_shortcode( 'bs_social_icons', array( $this, 'social_icons_shortcode' ) );

		/* Language shortcode and filter */
		add_shortcode( 'bs_language_switcher', array( $this, 'language_switcher_shortcode' ) );
		add_filter( 'language_attributes', array( $this, 'language_attributes_filter' ) );
	}

	/**
	 * Login form shortcode: [bs_login_form]
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
	 * Register form shortcode: [bs_register_form]
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
	 * Contact form shortcode: [bs_contact_form id="123"]
	 *
	 * Renders the contact form template part with fresh nonces.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML markup for the contact form.
	 */
	public function contact_form_shortcode( $atts = array() ) {
		assert( function_exists( 'get_template_part' ), 'get_template_part must exist' );
		assert( function_exists( 'ob_start' ), 'ob_start must exist' );

		$atts = shortcode_atts( array(
			'id' => '',
		), $atts, 'bs_contact_form' );

		$form_id = isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
		if ( $form_id <= 0 ) {
			$form_id = ContactFormCpt::get_first_form_id();
		}

		if ( $form_id <= 0 ) {
			return '';
		}

		$form = ContactFormCpt::get_form( $form_id );
		if ( empty( $form ) ) {
			return '';
		}

		$settings = isset( $form['settings'] ) ? $form['settings'] : array();
		if ( ! empty( $settings['logged_in_only'] ) && ! is_user_logged_in() ) {
			return '';
		}

		ob_start();
		get_template_part( 'template-parts/contact-form', null, array( 'form' => $form ) );
		return ob_get_clean();
	}

	/**
	 * Social icons shortcode: [bs_social_icons]
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
	 * Language switcher shortcode: [bs_language_switcher]
	 *
	 * @return string HTML markup for the language switcher.
	 */
	public function language_switcher_shortcode() {
		assert( function_exists( 'get_template_directory' ), 'get_template_directory must exist' );

		if ( ! Language::instance()->is_multilingual() ) {
			return '';
		}

		ob_start();
		$bs_switcher_data = Language::instance()->get_language_switcher_data();
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

		$current = bs_get_current_language();

		assert( is_string( $current ), 'current language must be a string' );

		if ( '' !== $current ) {
			$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $current ) . '"', $output );
		}

		return $output;
	}
}
