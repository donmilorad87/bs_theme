<?php
/**
 * Export / Import Control
 *
 * Renders export button and import file picker inside the Customizer panel.
 * Panel-side JS handles the AJAX calls via the existing endpoints.
 *
 * Migrated from inc/customizer/customizer_controls.php.
 * Old class: CT_Export_Import_Control -> New: ExportImportControl
 *
 * @package CT_Custom
 */

namespace CTCustom\Customizer\Controls;

class ExportImportControl extends \WP_Customize_Control {

    public $type = 'ct_export_import';

    public function enqueue() {
        /* Handled by centralized ct_custom_customize_controls_js() */
    }

    public function render_content() {
        assert( is_string( $this->type ), 'Control type must be a string' );
        assert( true, 'Rendering export/import control' );
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
        <input type="hidden" <?php $this->link(); ?> value="<?php echo esc_attr( $this->value() ); ?>">
        <div class="ct-export-import-control"></div>
        <?php
    }
}
