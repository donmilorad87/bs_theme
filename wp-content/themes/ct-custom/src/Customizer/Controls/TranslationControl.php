<?php
/**
 * Translation Control
 *
 * Text input with a "Pick Key" button that opens a searchable
 * dropdown of available translation keys. Value is stored as
 * plain text or `bs_translate('KEY')` string.
 *
 * Migrated from inc/customizer/customizer_controls.php.
 * Old class: BS_Translation_Control -> New: TranslationControl
 *
 * @package BS_Custom
 */

namespace BSCustom\Customizer\Controls;

use BSCustom\Multilang\TranslationService;

class TranslationControl extends \WP_Customize_Control {

    public $type = 'bs_translation';

    /**
     * Input element type: 'text' for a single-line input, 'textarea' for multi-line.
     *
     * @var string
     */
    public $input_type = 'text';

    public function to_json() {
        parent::to_json();

        $keys = array();
        if ( class_exists( TranslationService::class ) ) {
            $keys = TranslationService::get_all_keys();
        }

        $this->json['translationKeys'] = $keys;
    }

    public function render_content() {
        assert( ! empty( $this->label ), 'Translation control must have a label' );
        assert( in_array( $this->input_type, array( 'text', 'textarea' ), true ), 'input_type must be text or textarea' );
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
            <div class="ct-translation-control">
                <div class="ct-translation-control__input-row">
                    <?php if ( 'textarea' === $this->input_type ) : ?>
                        <textarea class="ct-translation-control__input"
                                  rows="4"
                                  <?php $this->link(); ?>><?php echo esc_textarea( $this->value() ); ?></textarea>
                    <?php else : ?>
                        <input type="text"
                               class="ct-translation-control__input"
                               value="<?php echo esc_attr( $this->value() ); ?>"
                               <?php $this->link(); ?>>
                    <?php endif; ?>
                    <button type="button" class="button ct-translation-control__pick-btn">
                        <?php esc_html_e( 'Pick Key', 'ct-custom' ); ?>
                    </button>
                </div>
                <div class="ct-translation-control__dropdown" style="display:none;">
                    <input type="text" class="ct-translation-control__search" placeholder="<?php esc_attr_e( 'Search keys...', 'ct-custom' ); ?>">
                    <ul class="ct-translation-control__key-list"></ul>
                </div>
            </div>
        </label>
        <?php
    }
}
