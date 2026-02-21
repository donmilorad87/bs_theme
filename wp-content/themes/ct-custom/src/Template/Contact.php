<?php
/**
 * Contact singleton.
 *
 * Provides data-preparation and rendering for the contact page.
 *
 * @package BS_Custom
 */

namespace BSCustom\Template;

class Contact {

	/** @var Contact|null Singleton instance. */
	private static $instance = null;

	/** @var array|null Cached contact template data. */
	private $contact_template_data_cache = null;

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

		assert( self::$instance instanceof self, 'Instance must be Contact' );

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Decode and return the contact-point data stored in options.
	 *
	 * @return array{address: object|null, phone: string, fax: string, email: string}
	 */
	public function get_contact_point_data() {
		$cp_raw  = get_option( 'bs_custom_contact_point', '' );
		$cp      = json_decode( stripslashes( $cp_raw ) );
		$address = ( isset( $cp->address ) && is_object( $cp->address ) ) ? $cp->address : null;
		$company = isset( $cp->company ) ? $cp->company : '';
		$phone   = isset( $cp->telephone ) ? $cp->telephone : '';
		$fax     = isset( $cp->fax_number ) ? $cp->fax_number : '';
		$email   = isset( $cp->email ) ? $cp->email : '';

		assert( is_string( $company ), 'company must be a string' );
		assert( is_string( $phone ), 'phone must be a string' );
		assert( is_string( $email ), 'email must be a string' );

		return array(
			'address' => $address,
			'company' => $company,
			'phone'   => $phone,
			'fax'     => $fax,
			'email'   => $email,
		);
	}

	/**
	 * Decode and return the social networks stored in options.
	 *
	 * @return array List of social network arrays.
	 */
	public function get_social_networks() {
		$networks_raw = get_option( 'bs_custom_social_networks', '[]' );
		$networks     = json_decode( stripslashes( $networks_raw ), true );

		if ( ! is_array( $networks ) ) {
			$networks = array();
		}

		assert( is_array( $networks ), 'networks must be an array' );

		return $networks;
	}

	/**
	 * Gather heading and content customizer settings for the contact page.
	 *
	 * @return array{contact_heading: string, contact_content: string}
	 */
	public function get_contact_page_data() {
		$contact_heading = get_theme_mod( 'bs_contact_heading', 'Contact' );
		$contact_content = get_theme_mod(
			'bs_contact_content',
			'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam posuere ipsum nec velit mattis elementum. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Maecenas eu placerat metus, eget placerat libero.'
		);

		assert( is_string( $contact_heading ), 'contact_heading must be a string' );
		assert( is_string( $contact_content ), 'contact_content must be a string' );

		return array(
			'contact_heading' => $contact_heading,
			'contact_content' => $contact_content,
		);
	}

	/**
	 * Build address line arrays from a contact-point address object.
	 *
	 * @param object|null $address The address object from contact-point data.
	 * @return array{line1: string, line2: string}
	 */
	public function build_address_lines( $address ) {
		assert( is_object( $address ) || is_null( $address ), 'address must be an object or null' );

		if ( ! $address ) {
			return array( 'line1' => '', 'line2' => '' );
		}

		$line1_parts = array();
		if ( ! empty( $address->street_number ) ) {
			$line1_parts[] = esc_html( $address->street_number );
		}
		if ( ! empty( $address->street_address ) ) {
			$line1_parts[] = esc_html( $address->street_address );
		}

		$line2_parts = array();
		if ( ! empty( $address->postal_code ) ) {
			$line2_parts[] = esc_html( $address->postal_code );
		}
		if ( ! empty( $address->city ) ) {
			$line2_parts[] = esc_html( $address->city );
		}
		if ( ! empty( $address->state ) ) {
			$line2_parts[] = esc_html( $address->state );
		}
		if ( ! empty( $address->country ) ) {
			$line2_parts[] = esc_html( $address->country );
		}

		assert( is_array( $line1_parts ), 'line1_parts must be an array' );

		return array(
			'line1' => implode( ' ', $line1_parts ),
			'line2' => implode( ' ', $line2_parts ),
		);
	}

	/**
	 * Gather all data needed by the contact page template.
	 *
	 * @return array
	 */
	public function get_contact_template_data() {
		if ( null !== $this->contact_template_data_cache ) {
			return $this->contact_template_data_cache;
		}

		$cp_data   = $this->get_contact_point_data();
		$page_data = $this->get_contact_page_data();

		assert( is_array( $cp_data ), 'cp_data must be an array' );
		assert( is_array( $page_data ), 'page_data must be an array' );

		$this->contact_template_data_cache = array_merge( $cp_data, $page_data, array(
			'networks'   => $this->get_social_networks(),
			'addr_lines' => $this->build_address_lines( $cp_data['address'] ),
		) );

		return $this->contact_template_data_cache;
	}

	/**
	 * Render the "Reach Us" info block.
	 *
	 * @param array $data Contact point data.
	 * @return void
	 */
	public function render_reach_us_info( $data ) {
		assert( is_array( $data ), 'data must be an array' );

		$address    = isset( $data['address'] ) ? $data['address'] : null;
		$addr_lines = isset( $data['addr_lines'] ) ? $data['addr_lines'] : array( 'line1' => '', 'line2' => '' );
		$company    = isset( $data['company'] ) ? $data['company'] : '';
		$phone      = isset( $data['phone'] ) ? $data['phone'] : '';
		$fax        = isset( $data['fax'] ) ? $data['fax'] : '';
		$email      = isset( $data['email'] ) ? $data['email'] : '';

		assert( is_string( $company ), 'company must be a string' );
		assert( is_string( $phone ), 'phone must be a string' );
		assert( is_string( $email ), 'email must be a string' );

		$has_address = ! empty( $addr_lines['line1'] ) || ! empty( $addr_lines['line2'] );
		?>
		<h2 class="section-title mb24"><?php echo esc_html( get_theme_mod( 'bs_reach_us_title', 'REACH US' ) ); ?></h2>

		<p class="reach-us__company mb8 ct-cp-company<?php echo ! empty( $company ) ? '' : ' ct-cp-hidden'; ?>">
			<?php esc_html_e( 'Company:', 'ct-custom' ); ?>
			<span class="ct-cp-company-value"><?php echo esc_html( $company ); ?></span>
		</p>

		<p class="reach-us__address mb16 ct-cp-address<?php echo $has_address ? '' : ' ct-cp-hidden'; ?>">
			<span class="ct-cp-address-line1"><?php echo $addr_lines['line1']; ?></span>
			<br>
			<span class="ct-cp-address-line2"><?php echo $addr_lines['line2']; ?></span>
		</p>

		<p class="reach-us__phone mb4 ct-cp-phone<?php echo ! empty( $phone ) ? '' : ' ct-cp-hidden'; ?>">
			<?php esc_html_e( 'Phone:', 'ct-custom' ); ?>
			<a class="ct-cp-phone-link" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">
				<span class="ct-cp-phone-value"><?php echo esc_html( $phone ); ?></span>
			</a>
		</p>

		<p class="reach-us__fax mb4 ct-cp-fax<?php echo ! empty( $fax ) ? '' : ' ct-cp-hidden'; ?>">
			<?php esc_html_e( 'Fax:', 'ct-custom' ); ?>
			<span class="ct-cp-fax-value"><?php echo esc_html( $fax ); ?></span>
		</p>

		<p class="reach-us__email mb4 ct-cp-email<?php echo ! empty( $email ) ? '' : ' ct-cp-hidden'; ?>">
			<?php esc_html_e( 'Email:', 'ct-custom' ); ?>
			<a class="ct-cp-email-link" href="mailto:<?php echo esc_attr( $email ); ?>">
				<span class="ct-cp-email-value"><?php echo esc_html( $email ); ?></span>
			</a>
		</p>
		<?php
	}

	/**
	 * Render the Contact / Reach Us section.
	 *
	 * @return void
	 */
	public function render_contact_section() {
		$data = $this->get_contact_template_data();

		assert( is_array( $data ), 'data must be an array' );
		assert( array_key_exists( 'networks', $data ), 'networks key must exist' );

		$networks = $data['networks'];
		?>
		<!-- Contact / Reach Us Section -->
		<div class="contact-section df">

			<!-- Left: Contact Form -->
			<div class="contact-section__form">
				<h2 class="section-title mb24"><?php echo esc_html( get_theme_mod( 'bs_contact_us_title', 'CONTACT US' ) ); ?></h2>
				<?php echo do_shortcode( '[bs_contact_form]' ); ?>
			</div>

			<!-- Right: Reach Us -->
			<div class="contact-section__info ct-contact-point-block">
				<?php $this->render_reach_us_info( $data ); ?>

				<div class="ct-contact-social-icons">
					<?php $this->render_social_icons_markup( $networks ); ?>
				</div>
			</div>

		</div><!-- .contact-section -->
		<?php
	}

	/**
	 * Render social icons markup.
	 *
	 * @param array $networks Array of network entries.
	 * @return void
	 */
	public function render_social_icons_markup( $networks ) {
		assert( is_array( $networks ), 'networks must be an array' );

		$enabled = get_option( 'bs_social_icons_enabled', 'on' );
		if ( false === $enabled || 'off' === $enabled || '0' === $enabled ) {
			return;
		}

		$max_icons  = 50;
		$icon_count = 0;

		echo '<div class="social-icons df aic" role="list" aria-label="' . esc_attr__( 'Social Networks', 'ct-custom' ) . '">';

		foreach ( $networks as $network ) {
			if ( $icon_count >= $max_icons ) {
				break;
			}
			$icon_count++;

			$name     = isset( $network['name'] ) ? $network['name'] : '';
			$url      = isset( $network['url'] ) ? $network['url'] : '';
			$icon_id  = isset( $network['icon_id'] ) ? absint( $network['icon_id'] ) : 0;
			$icon_url = isset( $network['icon_url'] ) ? $network['icon_url'] : '';

			if ( empty( $url ) ) {
				continue;
			}

			echo '<a href="' . esc_url( $url ) . '"'
				. ' class="dif aic jcc"'
				. ' target="_blank"'
				. ' rel="noopener noreferrer"'
				. ' title="' . esc_attr( $name ) . '"'
				. ' role="listitem">';

			if ( $icon_id && function_exists( 'bs_custom_get_attachment_image' ) ) {
				echo bs_custom_get_attachment_image( $icon_id, 'thumbnail', array(
					'alt'     => esc_attr( $name ),
					'loading' => 'lazy',
				) );
			} elseif ( $icon_url ) {
				echo '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $name ) . '" loading="lazy">';
			}

			echo '</a>';
		}

		if ( get_theme_mod( 'bs_social_share_enabled', true ) ) {
			echo '<button'
				. ' type="button"'
				. ' class="share-button share-with-friend dif aic jcc cp p0"'
				. ' data-url="' . esc_attr( home_url( '/' ) ) . '"'
				. ' data-title="' . esc_attr( get_bloginfo( 'name' ) ) . '"'
				. ' data-text="' . esc_attr( get_bloginfo( 'description' ) ) . '"'
				. ' title="' . esc_attr__( 'Share with a friend', 'ct-custom' ) . '"'
				. ' role="listitem"'
				. ' aria-label="' . esc_attr__( 'Share with a friend', 'ct-custom' ) . '">'
				. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">'
				. '<path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>'
				. '</svg>'
				. '</button>';
		}

		echo '</div>';
	}
}
