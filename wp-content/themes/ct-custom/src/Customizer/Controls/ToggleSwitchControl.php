<?php
/**
 * Toggle Switch Control
 *
 * Renders a styled on/off toggle switch instead of a plain checkbox.
 * Stores value as boolean (1 or empty).
 *
 * @package BS_Custom
 */

namespace BSCustom\Customizer\Controls;

class ToggleSwitchControl extends \WP_Customize_Control {

    public $type = 'bs_toggle_switch';

    public function enqueue() {
        /* Styles handled by ct-customizer-controls CSS */
    }

    public function render_content() {
        assert( ! empty( $this->label ), 'Toggle control must have a label' );
        assert( is_string( $this->type ), 'Control type must be a string' );

        $checked = $this->value() ? 'checked' : '';
        ?>
        <div class="ct-toggle-switch-control">
            <span class="customize-control-title">
                <?php echo esc_html( $this->label ); ?>
            </span>
            <?php if ( ! empty( $this->description ) ) : ?>
                <span class="description customize-control-description">
                    <?php echo esc_html( $this->description ); ?>
                </span>
            <?php endif; ?>
            <label class="ct-toggle-switch">
                <input type="checkbox"
                       class="ct-toggle-switch__input"
                       value="1"
                       <?php echo $checked; ?>
                       <?php $this->link(); ?>>
                <span class="ct-toggle-switch__slider"></span>
            </label>
        </div>
        <?php
    }
}
