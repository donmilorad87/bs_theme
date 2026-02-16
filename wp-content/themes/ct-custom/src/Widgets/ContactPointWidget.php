<?php

namespace CTCustom\Widgets;

use WP_Widget;
use CTCustom\Multilang\TranslationService;

class ContactPointWidget extends WP_Widget {

    const MAX_FIELDS = 10;

    public function __construct() {
        parent::__construct(
            'ct_contact_point',
            __( 'CT Custom: Contact Point', 'ct-custom' ),
            array(
                'description' => __( 'Displays contact point data from admin settings.', 'ct-custom' ),
            )
        );
    }

    public function widget( $args, $instance ) {
        assert( is_array( $args ), 'Widget args must be an array' );
        assert( is_array( $instance ), 'Widget instance must be an array' );

        $raw     = get_option( 'ct_custom_contact_point', '' );
        $contact = ! empty( $raw ) ? json_decode( $raw, true ) : array();

        if ( ! is_array( $contact ) ) {
            $contact = array();
        }

        $telephone = isset( $contact['telephone'] ) ? $contact['telephone'] : '';
        $fax       = isset( $contact['fax_number'] ) ? $contact['fax_number'] : '';
        $email     = isset( $contact['email'] ) ? $contact['email'] : '';
        $address   = isset( $contact['address'] ) && is_array( $contact['address'] ) ? $contact['address'] : array();

        $street  = isset( $address['street_address'] ) ? $address['street_address'] : '';
        $number  = isset( $address['street_number'] ) ? $address['street_number'] : '';
        $city    = isset( $address['city'] ) ? $address['city'] : '';
        $state   = isset( $address['state'] ) ? $address['state'] : '';
        $postal  = isset( $address['postal_code'] ) ? $address['postal_code'] : '';
        $country = isset( $address['country'] ) ? $address['country'] : '';

        $line1       = trim( $number . ' ' . $street );
        $line2_parts = array_filter( array( $postal, $city ) );
        $has_address = $line1 || $line2_parts || $state || $country;

        echo $args['before_widget'];

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . esc_html( TranslationService::resolve_raw( $instance['title'] ) ) . $args['after_title'];
        }

        echo '<div class="widget-contact-point ct-contact-point-block">';

        // Address — always rendered, hidden when empty
        echo '<address class="widget-contact-point__address ct-cp-address' . ( $has_address ? '' : ' ct-cp-hidden' ) . '">';
        echo '<span class="widget-contact-point__street ct-cp-street' . ( $line1 ? '' : ' ct-cp-hidden' ) . '">' . esc_html( $line1 ) . '</span>';
        echo '<br>';
        echo '<span class="widget-contact-point__city ct-cp-city' . ( $line2_parts ? '' : ' ct-cp-hidden' ) . '">' . esc_html( implode( ' ', $line2_parts ) ) . '</span>';
        echo '<br>';
        echo '<span class="widget-contact-point__state ct-cp-state' . ( $state ? '' : ' ct-cp-hidden' ) . '">' . esc_html( $state ) . '</span>';
        echo '<br>';
        echo '<span class="widget-contact-point__country ct-cp-country' . ( $country ? '' : ' ct-cp-hidden' ) . '">' . esc_html( $country ) . '</span>';
        echo '</address>';

        // Contact list — always rendered
        echo '<ul class="widget-contact-point__list">';

        // Phone
        echo '<li class="widget-contact-point__item ct-cp-phone' . ( $telephone ? '' : ' ct-cp-hidden' ) . '">';
        echo '<span class="widget-contact-point__label">' . esc_html__( 'Phone:', 'ct-custom' ) . '</span> ';
        echo '<a class="ct-cp-phone-link" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $telephone ) ) . '"><span class="ct-cp-phone-value">' . esc_html( $telephone ) . '</span></a>';
        echo '</li>';

        // Fax
        echo '<li class="widget-contact-point__item ct-cp-fax' . ( $fax ? '' : ' ct-cp-hidden' ) . '">';
        echo '<span class="widget-contact-point__label">' . esc_html__( 'Fax:', 'ct-custom' ) . '</span> ';
        echo '<span class="ct-cp-fax-value">' . esc_html( $fax ) . '</span>';
        echo '</li>';

        // Email
        echo '<li class="widget-contact-point__item ct-cp-email' . ( $email ? '' : ' ct-cp-hidden' ) . '">';
        echo '<span class="widget-contact-point__label">' . esc_html__( 'Email:', 'ct-custom' ) . '</span> ';
        echo '<a class="ct-cp-email-link" href="mailto:' . esc_attr( $email ) . '"><span class="ct-cp-email-value">' . esc_html( $email ) . '</span></a>';
        echo '</li>';

        echo '</ul>';

        echo '</div>';

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        assert( is_array( $instance ) || empty( $instance ), 'Instance must be an array or empty' );
        assert( is_object( $this ), 'Widget must be initialized' );

        $title = isset( $instance['title'] ) ? $instance['title'] : '';
        ?>
        <div class="ct-wtp-field">
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Title:', 'ct-custom' ); ?>
            </label>
            <div class="ct-wtp">
                <input
                    class="widefat ct-wtp__target"
                    id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                    type="text"
                    value="<?php echo esc_attr( $title ); ?>"
                >
                <button type="button" class="button ct-wtp__pick-btn"><?php esc_html_e( 'Pick Key', 'ct-custom' ); ?></button>
                <div class="ct-wtp__dropdown" style="display:none;">
                    <input type="text" class="ct-wtp__search widefat" placeholder="<?php esc_attr_e( 'Search keys...', 'ct-custom' ); ?>">
                    <ul class="ct-wtp__key-list"></ul>
                </div>
            </div>
        </div>
        <p class="description">
            <?php esc_html_e( 'Contact data is read from CT Custom Theme > Contact Point settings.', 'ct-custom' ); ?>
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        assert( is_array( $new_instance ), 'New instance must be an array' );
        assert( is_array( $old_instance ), 'Old instance must be an array' );

        $instance          = array();
        $instance['title'] = sanitize_text_field( $new_instance['title'] );

        return $instance;
    }
}
