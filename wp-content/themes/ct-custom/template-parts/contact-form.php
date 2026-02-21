<?php
/**
 * Template Part: Contact Form
 *
 * Renders a dynamic contact form based on CPT configuration.
 *
 * @package BS_Custom
 */

assert( function_exists( 'esc_html__' ), 'esc_html__ must exist' );
assert( function_exists( 'esc_attr__' ), 'esc_attr__ must exist' );

$form = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();

if ( empty( $form ) ) {
    return;
}

$fields   = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array();
$settings = isset( $form['settings'] ) && is_array( $form['settings'] ) ? $form['settings'] : array();

if ( empty( $fields ) ) {
    return;
}

$form_id          = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
$rest_url         = esc_url( rest_url( 'ct-auth/v1' ) );
$nonce            = wp_create_nonce( 'wp_rest' );
$captcha_enabled  = ! empty( $settings['captcha_enabled'] );
$uploads_enabled  = isset( $settings['file_uploads']['enabled'] ) && $settings['file_uploads']['enabled'];
$upload_storage   = isset( $settings['file_uploads']['storage'] ) ? $settings['file_uploads']['storage'] : 'wordpress';
$has_file_field   = false;
$captcha_token    = '';
$captcha_code     = '';

foreach ( $fields as $field ) {
    if ( isset( $field['type'] ) && 'file' === $field['type'] ) {
        $has_file_field = true;
        break;
    }
}

$captcha_payload = array();
if ( $captcha_enabled && class_exists( '\BSCustom\RestApi\Endpoints\ContactCaptcha' ) ) {
    $captcha_payload = \BSCustom\RestApi\Endpoints\ContactCaptcha::create_challenge();
    $captcha_token   = isset( $captcha_payload['token'] ) ? (string) $captcha_payload['token'] : '';
    $captcha_code    = isset( $captcha_payload['code'] ) ? (string) $captcha_payload['code'] : '';
}

$user       = wp_get_current_user();
$user_name  = ( $user && $user->ID > 0 ) ? trim( $user->first_name . ' ' . $user->last_name ) : '';
$user_email = ( $user && $user->ID > 0 ) ? $user->user_email : '';
?>
<form class="ct-contact-form w100"
      id="bs_contact_form_<?php echo esc_attr( $form_id ); ?>"
      data-form-id="<?php echo esc_attr( $form_id ); ?>"
      data-rest-url="<?php echo $rest_url; ?>"
      data-nonce="<?php echo esc_attr( $nonce ); ?>"
      data-captcha-enabled="<?php echo esc_attr( $captcha_enabled ? '1' : '0' ); ?>"
      data-uploads-enabled="<?php echo esc_attr( $uploads_enabled ? '1' : '0' ); ?>"
      data-upload-storage="<?php echo esc_attr( $upload_storage ); ?>"
      <?php echo $has_file_field ? 'enctype="multipart/form-data"' : ''; ?>
      novalidate
      role="form"
      aria-label="<?php esc_attr_e( 'Contact form', 'ct-custom' ); ?>">

    <input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">

    <div class="ct-contact-form__messages mb16" role="alert" aria-live="polite"></div>

    <?php foreach ( $fields as $field ) :
        if ( ! is_array( $field ) ) {
            continue;
        }

        $type        = isset( $field['type'] ) ? $field['type'] : 'text';
        $name        = isset( $field['name'] ) ? $field['name'] : '';
        $label       = isset( $field['label'] ) ? $field['label'] : $name;
        $placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
        $required    = ! empty( $field['required'] );
        $options     = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
        $conditions  = isset( $field['conditions'] ) && is_array( $field['conditions'] ) ? $field['conditions'] : array();
        $min         = isset( $field['min'] ) ? $field['min'] : '';
        $max         = isset( $field['max'] ) ? $field['max'] : '';
        $step        = isset( $field['step'] ) ? $field['step'] : '';
        $default     = isset( $field['default'] ) ? $field['default'] : '';
        $accept      = isset( $field['accept'] ) ? $field['accept'] : '';

        if ( '' === $name ) {
            continue;
        }

        if ( '' === $default ) {
            if ( 'name' === $name && '' !== $user_name ) {
                $default = $user_name;
            }
            if ( 'email' === $name && '' !== $user_email ) {
                $default = $user_email;
            }
        }

        $wrapper_attrs = array(
            'class'           => 'ct-contact-form__field mb16',
            'data-field-name' => $name,
            'data-field-type' => $type,
            'data-required'   => $required ? '1' : '0',
        );
        if ( 'file' === $type && '' !== $accept ) {
            $wrapper_attrs['data-accept'] = $accept;
        }

        if ( ! empty( $conditions['enabled'] ) && ! empty( $conditions['rules'] ) ) {
            $wrapper_attrs['data-conditions'] = wp_json_encode( $conditions );
        }

        $attr_html = '';
        foreach ( $wrapper_attrs as $attr_key => $attr_val ) {
            $attr_html .= ' ' . $attr_key . '="' . esc_attr( $attr_val ) . '"';
        }

        if ( 'hidden' === $type ) :
            ?>
            <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $default ); ?>">
            <?php
            continue;
        endif;
        ?>
        <div<?php echo $attr_html; ?>>
            <label for="ct-contact-<?php echo esc_attr( $name ); ?>" class="ct-contact-form__label db fs14 mb4">
                <?php echo esc_html( $label ); ?><?php if ( $required ) : ?> <span aria-hidden="true">*</span><?php endif; ?>
            </label>

            <?php if ( 'textarea' === $type ) : ?>
                <textarea id="ct-contact-<?php echo esc_attr( $name ); ?>"
                          name="<?php echo esc_attr( $name ); ?>"
                          class="ct-contact-form__input ct-contact-form__input--textarea db fs14"
                          rows="5"
                          <?php echo $required ? 'required aria-required="true"' : ''; ?>
                          placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $default ); ?></textarea>
            <?php elseif ( 'select' === $type ) : ?>
                <select id="ct-contact-<?php echo esc_attr( $name ); ?>"
                        name="<?php echo esc_attr( $name ); ?>"
                        class="ct-contact-form__input db fs14"
                        <?php echo $required ? 'required aria-required="true"' : ''; ?>>
                    <?php foreach ( $options as $option ) :
                        $opt_value = isset( $option['value'] ) ? $option['value'] : '';
                        $opt_label = isset( $option['label'] ) ? $option['label'] : $opt_value;
                        ?>
                        <option value="<?php echo esc_attr( $opt_value ); ?>"<?php echo ( (string) $opt_value === (string) $default ) ? ' selected' : ''; ?>>
                            <?php echo esc_html( $opt_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ( 'radio' === $type ) : ?>
                <div class="ct-contact-form__options df fdc">
                    <?php foreach ( $options as $index => $option ) :
                        $opt_value = isset( $option['value'] ) ? $option['value'] : '';
                        $opt_label = isset( $option['label'] ) ? $option['label'] : $opt_value;
                        $opt_id    = 'ct-contact-' . $name . '-' . $index;
                        ?>
                        <label for="<?php echo esc_attr( $opt_id ); ?>" class="ct-contact-form__option df aic">
                            <input type="radio"
                                   id="<?php echo esc_attr( $opt_id ); ?>"
                                   name="<?php echo esc_attr( $name ); ?>"
                                   value="<?php echo esc_attr( $opt_value ); ?>"
                                   <?php echo ( (string) $opt_value === (string) $default ) ? 'checked' : ''; ?>
                                   <?php echo $required ? 'required aria-required="true"' : ''; ?>
                            >
                            <span><?php echo esc_html( $opt_label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php elseif ( 'checkbox_group' === $type ) :
                $defaults = is_array( $default ) ? $default : array_filter( array_map( 'trim', explode( ',', (string) $default ) ) );
                ?>
                <div class="ct-contact-form__options df fdc">
                    <?php foreach ( $options as $index => $option ) :
                        $opt_value = isset( $option['value'] ) ? $option['value'] : '';
                        $opt_label = isset( $option['label'] ) ? $option['label'] : $opt_value;
                        $opt_id    = 'ct-contact-' . $name . '-' . $index;
                        $is_checked = in_array( (string) $opt_value, $defaults, true );
                        ?>
                        <label for="<?php echo esc_attr( $opt_id ); ?>" class="ct-contact-form__option df aic">
                            <input type="checkbox"
                                   id="<?php echo esc_attr( $opt_id ); ?>"
                                   name="<?php echo esc_attr( $name ); ?>[]"
                                   value="<?php echo esc_attr( $opt_value ); ?>"
                                   <?php echo $is_checked ? 'checked' : ''; ?>
                            >
                            <span><?php echo esc_html( $opt_label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php elseif ( 'checkbox' === $type ) : ?>
                <label class="ct-contact-form__option df aic">
                    <input type="checkbox"
                           id="ct-contact-<?php echo esc_attr( $name ); ?>"
                           name="<?php echo esc_attr( $name ); ?>"
                           value="1"
                           <?php echo ( '1' === (string) $default || 'true' === (string) $default ) ? 'checked' : ''; ?>
                           <?php echo $required ? 'required aria-required="true"' : ''; ?>>
                    <span><?php echo esc_html( $placeholder ? $placeholder : $label ); ?></span>
                </label>
            <?php elseif ( 'range' === $type ) : ?>
                <input type="range"
                       id="ct-contact-<?php echo esc_attr( $name ); ?>"
                       name="<?php echo esc_attr( $name ); ?>"
                       class="ct-contact-form__input ct-contact-form__input--range db fs14"
                       value="<?php echo esc_attr( $default ); ?>"
                       <?php echo '' !== $min ? 'min="' . esc_attr( $min ) . '"' : ''; ?>
                       <?php echo '' !== $max ? 'max="' . esc_attr( $max ) . '"' : ''; ?>
                       <?php echo '' !== $step ? 'step="' . esc_attr( $step ) . '"' : ''; ?>
                       <?php echo $required ? 'required aria-required="true"' : ''; ?>>
            <?php elseif ( 'file' === $type ) : ?>
                <input type="file"
                       id="ct-contact-<?php echo esc_attr( $name ); ?>"
                       name="<?php echo esc_attr( $name ); ?>"
                       class="ct-contact-form__input db fs14"
                       <?php echo '' !== $accept ? 'accept="' . esc_attr( $accept ) . '"' : ''; ?>
                       <?php echo $required ? 'required aria-required="true"' : ''; ?>>
                <div class="ct-contact-form__file-preview df fdc" data-preview-for="<?php echo esc_attr( $name ); ?>" aria-live="polite">
                    <div class="ct-contact-form__file-preview-placeholder fs12"><?php esc_html_e( 'Preview will appear here.', 'ct-custom' ); ?></div>
                    <img class="ct-contact-form__file-preview-image dn" alt="" loading="lazy">
                    <video class="ct-contact-form__file-preview-video dn" controls playsinline></video>
                    <span class="ct-contact-form__file-preview-name fs12 dn"></span>
                </div>
            <?php else : ?>
                <input type="<?php echo esc_attr( $type ); ?>"
                       id="ct-contact-<?php echo esc_attr( $name ); ?>"
                       name="<?php echo esc_attr( $name ); ?>"
                       class="ct-contact-form__input db fs14"
                       value="<?php echo esc_attr( $default ); ?>"
                       <?php echo '' !== $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
                       <?php echo '' !== $min ? 'min="' . esc_attr( $min ) . '"' : ''; ?>
                       <?php echo '' !== $max ? 'max="' . esc_attr( $max ) . '"' : ''; ?>
                       <?php echo '' !== $step ? 'step="' . esc_attr( $step ) . '"' : ''; ?>
                       <?php echo $required ? 'required aria-required="true"' : ''; ?>>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ( $captcha_enabled ) : ?>
        <div class="ct-contact-form__captcha mb16">
            <label class="ct-contact-form__label db fs14 mb4"><?php esc_html_e( 'Captcha', 'ct-custom' ); ?> <span aria-hidden="true">*</span></label>
            <div class="ct-contact-form__captcha-row df aic mb8">
                <canvas class="ct-contact-form__captcha-canvas"
                        width="320"
                        height="100"
                        aria-label="<?php esc_attr_e( 'Captcha challenge', 'ct-custom' ); ?>"
                        <?php echo '' !== $captcha_code ? 'data-captcha-code="' . esc_attr( $captcha_code ) . '"' : ''; ?>></canvas>
                <button type="button" class="ct-contact-form__captcha-refresh fs16" aria-label="<?php esc_attr_e( 'Refresh captcha', 'ct-custom' ); ?>">↻</button>
            </div>
            <input type="text"
                   name="captcha_value"
                   class="ct-contact-form__input db fs14"
                   placeholder="<?php esc_attr_e( 'Enter the code', 'ct-custom' ); ?>"
                   required
                   aria-required="true">
            <input type="hidden" name="captcha_token" value="<?php echo esc_attr( $captcha_token ); ?>">
        </div>
    <?php endif; ?>

    <button type="submit" class="ct-contact-form__submit dif aic jcc cp fs14">
        <span class="ct-contact-form__submit-text"><?php esc_html_e( 'Send Message', 'ct-custom' ); ?></span>
    </button>
</form>
