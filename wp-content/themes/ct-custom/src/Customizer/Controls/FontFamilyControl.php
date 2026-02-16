<?php
/**
 * Font Family Control
 *
 * Renders a text input backed by a <datalist> populated from
 * google-fonts.json. The browser provides a native autocomplete
 * dropdown — no separate search input or <select> needed.
 *
 * Each <option> carries data-variants so the font-weights module
 * can show/hide checkboxes when the family changes.
 *
 * @package CT_Custom
 */

namespace CTCustom\Customizer\Controls;

use CTCustom\Customizer\FontManager;

class FontFamilyControl extends \WP_Customize_Control {

    public $type = 'ct_font_family';

    public function enqueue() {
        /* Handled by centralized ct_custom_customize_controls_js() */
    }

    public function render_content() {
        assert( ! empty( $this->label ), 'Font family control must have a label' );
        assert( is_string( $this->type ), 'Control type must be a string' );

        $fonts = array();

        if ( class_exists( FontManager::class ) ) {
            $fonts = FontManager::get_font_catalog();
        }

        $list_id = 'ct-font-family-list-' . esc_attr( $this->id );
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
        <div class="ct-font-family-control">
            <input type="text"
                   class="ct-font-family-control__input"
                   list="<?php echo $list_id; ?>"
                   placeholder="<?php esc_attr_e( 'Type to search fonts…', 'ct-custom' ); ?>"
                   value="<?php echo esc_attr( $this->value() ); ?>"
                   autocomplete="off"
                   <?php $this->link(); ?>>
            <datalist id="<?php echo $list_id; ?>">
                <?php
                $max_fonts  = 2000;
                $font_count = 0;

                foreach ( $fonts as $font ) :
                    if ( $font_count >= $max_fonts ) {
                        break;
                    }
                    $font_count++;

                    if ( ! isset( $font['family'] ) ) {
                        continue;
                    }

                    $api_fam  = esc_attr( $font['family'] );
                    $display  = isset( $font['displayName'] ) ? esc_attr( $font['displayName'] ) : $api_fam;
                    $variants = isset( $font['variants'] ) ? $font['variants'] : array();
                    ?>
                    <option value="<?php echo $display; ?>"
                            data-api-family="<?php echo $api_fam; ?>"
                            data-variants="<?php echo esc_attr( implode( ',', $variants ) ); ?>">
                    </option>
                <?php endforeach; ?>
            </datalist>
        </div>
        <?php
    }
}
