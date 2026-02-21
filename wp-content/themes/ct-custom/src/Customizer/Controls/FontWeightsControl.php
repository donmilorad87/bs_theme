<?php
/**
 * Font Weights Multi-Checkbox Control
 *
 * Shows available weights for the selected font.
 * Stored as comma-separated string like "400,400i,700,700i".
 *
 * Renders all 18 weight checkboxes but hides unavailable ones
 * based on the current font's variants from google-fonts.json.
 * JS handles dynamic show/hide when the font family changes.
 *
 * Migrated from inc/customizer/customizer_controls.php.
 * Old class: BS_Font_Weights_Control -> New: FontWeightsControl
 *
 * @package BS_Custom
 */

namespace BSCustom\Customizer\Controls;

use BSCustom\Customizer\FontManager;

class FontWeightsControl extends \WP_Customize_Control {

    public $type = 'bs_font_weights';

    public function enqueue() {
        /* Handled by centralized bs_custom_customize_controls_js() */
    }

    public function render_content() {
        assert( ! empty( $this->label ), 'Font weights control must have a label' );
        assert( is_string( $this->type ), 'Control type must be a string' );

        $all_weights = array(
            '100'  => 'Thin (100)',
            '200'  => 'Extra Light (200)',
            '300'  => 'Light (300)',
            '400'  => 'Regular (400)',
            '500'  => 'Medium (500)',
            '600'  => 'Semi Bold (600)',
            '700'  => 'Bold (700)',
            '800'  => 'Extra Bold (800)',
            '900'  => 'Black (900)',
            '100i' => 'Thin Italic (100i)',
            '200i' => 'Extra Light Italic (200i)',
            '300i' => 'Light Italic (300i)',
            '400i' => 'Regular Italic (400i)',
            '500i' => 'Medium Italic (500i)',
            '600i' => 'Semi Bold Italic (600i)',
            '700i' => 'Bold Italic (700i)',
            '800i' => 'Extra Bold Italic (800i)',
            '900i' => 'Black Italic (900i)',
        );

        /* Get the current font's available variants */
        $current_family   = get_theme_mod( 'bs_font_family', '' );
        $available_weights = $this->get_available_weights( $current_family );

        $current = $this->value();
        $selected_weights = array_filter( array_map( 'trim', explode( ',', $current ) ) );
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
        <input type="hidden" class="ct-font-weights-value" <?php $this->link(); ?> value="<?php echo esc_attr( $current ); ?>">
        <div class="ct-font-weights-control">
            <?php
            $weight_count = 0;
            $max_display  = 18;

            foreach ( $all_weights as $val => $label ) :
                if ( $weight_count >= $max_display ) {
                    break;
                }
                $weight_count++;

                $is_available = in_array( $val, $available_weights, true );
                $checked      = in_array( $val, $selected_weights, true ) ? 'checked' : '';
                $display      = $is_available ? '' : 'none';
                ?>
                <label class="ct-font-weight-item" style="<?php echo $display ? "display:{$display}" : ''; ?>">
                    <input type="checkbox"
                           class="ct-font-weight-checkbox"
                           value="<?php echo esc_attr( $val ); ?>"
                           <?php echo $checked; ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Map Google Fonts variant names to our weight format
     * and return only available weights for the given font.
     *
     * @param string $family Font family name.
     * @return array Array of available weight strings like "400", "700", "400i".
     */
    private function get_available_weights( $family ) {
        if ( '' === $family ) {
            return array();
        }

        $variants = FontManager::get_font_variants( $family );

        if ( empty( $variants ) ) {
            return array();
        }

        /* Map Google variant names to our weight format */
        $variant_map = array(
            'regular'    => '400',
            'italic'     => '400i',
            '100'        => '100',
            '100italic'  => '100i',
            '200'        => '200',
            '200italic'  => '200i',
            '300'        => '300',
            '300italic'  => '300i',
            '500'        => '500',
            '500italic'  => '500i',
            '600'        => '600',
            '600italic'  => '600i',
            '700'        => '700',
            '700italic'  => '700i',
            '800'        => '800',
            '800italic'  => '800i',
            '900'        => '900',
            '900italic'  => '900i',
        );

        $available = array();
        $count     = 0;
        $max       = 18;

        foreach ( $variants as $variant ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            if ( isset( $variant_map[ $variant ] ) ) {
                $available[] = $variant_map[ $variant ];
            }
        }

        return $available;
    }
}
