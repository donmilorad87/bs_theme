<?php
/**
 * Contact Point Control
 *
 * Stores a JSON object of contact details in a hidden textarea.
 * Panel-side JS builds a fixed-shape form (no repeater).
 *
 * Migrated from inc/customizer/customizer_controls.php.
 * Old class: CT_Contact_Point_Control -> New: ContactPointControl
 *
 * @package BS_Custom
 */

namespace BSCustom\Customizer\Controls;

class ContactPointControl extends \WP_Customize_Control {

    public $type = 'ct_contact_point';

    /**
     * Contact type choices exposed to JS.
     *
     * @var array
     */
    public $contact_type_choices = array(
        'customer service'    => 'Customer Service',
        'technical support'   => 'Technical Support',
        'billing support'     => 'Billing Support',
        'sales'               => 'Sales',
        'reservations'        => 'Reservations',
        'credit card support' => 'Credit Card Support',
        'emergency'           => 'Emergency',
        'baggage tracking'    => 'Baggage Tracking',
        'roadside assistance' => 'Roadside Assistance',
        'package tracking'    => 'Package Tracking',
    );

    public function enqueue() {
        /* Handled by centralized bs_custom_customize_controls_js() */
    }

    public function to_json() {
        parent::to_json();

        $raw     = $this->value();
        $contact = json_decode( $raw, true );

        assert( is_string( $raw ), 'Value must be a string' );
        assert( is_array( $contact ) || null === $contact, 'Decoded value must be array or null' );

        $this->json['contact']              = is_array( $contact ) ? $contact : array();
        $this->json['contact_type_choices']  = $this->contact_type_choices;
    }

    public function render_content() {
        assert( ! empty( $this->label ), 'Contact point control must have a label' );
        assert( is_string( $this->type ), 'Control type must be a string' );
        ?>
        <label>
            <span class="customize-control-title">
                <?php echo esc_html( $this->label ); ?>
            </span>
            <?php if ( ! empty( $this->description ) ) : ?>
                <span class="description customize-control-description">
                    <?php echo esc_html( $this->description ); ?>
                </span>
            <?php endif; ?>
        </label>
        <textarea class="ct-contact-point-textarea" style="display:none;"
            <?php $this->link(); ?>><?php echo esc_textarea( $this->value() ); ?></textarea>
        <div class="ct-contact-point-control"></div>
        <?php
    }
}
