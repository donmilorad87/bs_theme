<?php
/**
 * Social Networks Repeater Control
 *
 * Stores a JSON array of networks in a hidden textarea.
 * Panel-side JS builds the add/edit/remove UI.
 *
 * Migrated from inc/customizer/customizer_controls.php.
 * Old class: BS_Social_Networks_Control -> New: SocialNetworksControl
 *
 * @package BS_Custom
 */

namespace BSCustom\Customizer\Controls;

class SocialNetworksControl extends \WP_Customize_Control {

    public $type = 'bs_social_networks';

    public function enqueue() {
        /* Handled by centralized bs_custom_customize_controls_js() */
    }

    public function to_json() {
        parent::to_json();

        $raw      = $this->value();
        $networks = json_decode( $raw, true );

        assert( is_string( $raw ), 'Value must be a string' );
        assert( is_array( $networks ) || null === $networks, 'Decoded value must be array or null' );

        $this->json['networks'] = is_array( $networks ) ? $networks : array();
    }

    public function render_content() {
        assert( ! empty( $this->label ), 'Social networks control must have a label' );
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
        <textarea class="ct-social-networks-textarea" style="display:none;"
            <?php $this->link(); ?>><?php echo esc_textarea( $this->value() ); ?></textarea>
        <div class="ct-social-networks-control"></div>
        <?php
    }
}
