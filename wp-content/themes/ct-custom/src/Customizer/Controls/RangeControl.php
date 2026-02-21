<?php
/**
 * Range Control
 *
 * Custom customizer control for range slider inputs.
 *
 * Migrated from inc/customizer/customizer_controls.php.
 * Old class: BS_Range_Control -> New: RangeControl
 *
 * @package BS_Custom
 */

namespace BSCustom\Customizer\Controls;

class RangeControl extends \WP_Customize_Control {

    public $type = 'bs_range';

    public function enqueue() {
        /* Handled by centralized bs_custom_customize_controls_js() */
    }

    public function render_content() {
        assert( ! empty( $this->label ), 'Range control must have a label' );
        assert( isset( $this->input_attrs['min'] ), 'Range control must have min attribute' );
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
            <div style="display:flex;align-items:center;gap:10px;">
                <input
                    type="range"
                    <?php $this->input_attrs(); ?>
                    value="<?php echo esc_attr( $this->value() ); ?>"
                    <?php $this->link(); ?>
                    style="flex:1;"
                />
                <span class="ct-range-value" style="min-width:40px;text-align:center;font-weight:600;">
                    <?php echo esc_html( $this->value() ); ?>
                </span>
            </div>
        </label>
        <?php
    }
}
