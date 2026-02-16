<?php
/**
 * Template Part: Contact Form
 *
 * Custom contact form that submits to the REST API.
 * Replaces the Contact Form 7 dependency.
 *
 * @package BS_Custom
 */

assert( function_exists( 'esc_html__' ), 'esc_html__ must exist' );
assert( function_exists( 'esc_attr__' ), 'esc_attr__ must exist' );

$user       = wp_get_current_user();
$user_name  = ( $user && $user->ID > 0 ) ? esc_attr( trim( $user->first_name . ' ' . $user->last_name ) ) : '';
$user_email = ( $user && $user->ID > 0 ) ? esc_attr( $user->user_email ) : '';
$rest_url   = esc_url( rest_url( 'ct-auth/v1' ) );
$nonce      = wp_create_nonce( 'wp_rest' );
?>
<form class="ct-contact-form w100"
      id="ct_contact_form"
      data-pointer="contact_us"
      data-rest-url="<?php echo $rest_url; ?>"
      data-nonce="<?php echo esc_attr( $nonce ); ?>"
      novalidate
      role="form"
      aria-label="<?php esc_attr_e( 'Contact form', 'ct-custom' ); ?>">

    <div class="ct-contact-form__messages" role="alert" aria-live="polite"></div>

    <div class="ct-contact-form__field">
        <label for="ct-contact-name" class="ct-contact-form__label fs14">
            <?php esc_html_e( 'Name', 'ct-custom' ); ?> <span aria-hidden="true">*</span>
        </label>
        <input type="text"
               id="ct-contact-name"
               name="name"
               class="ct-contact-form__input fs14"
               value="<?php echo $user_name; ?>"
               required
               autocomplete="name"
               aria-required="true">
    </div>

    <div class="ct-contact-form__field">
        <label for="ct-contact-email" class="ct-contact-form__label fs14">
            <?php esc_html_e( 'Email', 'ct-custom' ); ?> <span aria-hidden="true">*</span>
        </label>
        <input type="email"
               id="ct-contact-email"
               name="email"
               class="ct-contact-form__input fs14"
               value="<?php echo $user_email; ?>"
               required
               autocomplete="email"
               aria-required="true">
    </div>

    <div class="ct-contact-form__field">
        <label for="ct-contact-phone" class="ct-contact-form__label fs14">
            <?php esc_html_e( 'Phone', 'ct-custom' ); ?>
        </label>
        <input type="tel"
               id="ct-contact-phone"
               name="phone"
               class="ct-contact-form__input fs14"
               autocomplete="tel">
    </div>

    <div class="ct-contact-form__field">
        <label for="ct-contact-message" class="ct-contact-form__label fs14">
            <?php esc_html_e( 'Message', 'ct-custom' ); ?> <span aria-hidden="true">*</span>
        </label>
        <textarea id="ct-contact-message"
                  name="message"
                  class="ct-contact-form__input ct-contact-form__input--textarea fs14"
                  rows="5"
                  required
                  aria-required="true"></textarea>
    </div>

    <button type="submit" class="ct-contact-form__submit dif aic jcc cp fs14">
        <span class="ct-contact-form__submit-text"><?php esc_html_e( 'Send Message', 'ct-custom' ); ?></span>
    </button>
</form>

<form class="ct-contact-form w100"
      id="ct_contact_form_2"
      data-pointer="contact_us_2"
      data-rest-url="<?php echo $rest_url; ?>"
      data-nonce="<?php echo esc_attr( $nonce ); ?>"
      novalidate
      role="form"
      aria-label="<?php esc_attr_e( 'Contact form', 'ct-custom' ); ?>">

    <div class="ct-contact-form__messages" role="alert" aria-live="polite"></div>

    <div class="ct-contact-form__field">
        <label for="ct-contact-name-2" class="ct-contact-form__label fs14">
            <?php esc_html_e( 'Name', 'ct-custom' ); ?> <span aria-hidden="true">*</span>
        </label>
        <input type="text"
               id="ct-contact-name-2"
               name="name"
               class="ct-contact-form__input fs14"
               value="<?php echo $user_name; ?>"
               required
               autocomplete="name"
               aria-required="true">
    </div>

    <div class="ct-contact-form__field">
        <label for="ct-contact-email-2" class="ct-contact-form__label fs14">
            <?php esc_html_e( 'Email', 'ct-custom' ); ?> <span aria-hidden="true">*</span>
        </label>
        <input type="email"
               id="ct-contact-email-2"
               name="email"
               class="ct-contact-form__input fs14"
               value="<?php echo $user_email; ?>"
               required
               autocomplete="email"
               aria-required="true">
    </div>

    <div class="ct-contact-form__field">
        <label for="ct-contact-phone-2" class="ct-contact-form__label fs14">
            <?php esc_html_e( 'Phone', 'ct-custom' ); ?>
        </label>
        <input type="tel"
               id="ct-contact-phone-2"
               name="phone"
               class="ct-contact-form__input fs14"
               autocomplete="tel">
    </div>

    <div class="ct-contact-form__field">
        <label for="ct-contact-message-2" class="ct-contact-form__label fs14">
            <?php esc_html_e( 'Message', 'ct-custom' ); ?> <span aria-hidden="true">*</span>
        </label>
        <textarea id="ct-contact-message-2"
                  name="message"
                  class="ct-contact-form__input ct-contact-form__input--textarea fs14"
                  rows="5"
                  required
                  aria-required="true"></textarea>
    </div>

    <button type="submit" class="ct-contact-form__submit dif aic jcc cp fs14">
        <span class="ct-contact-form__submit-text"><?php esc_html_e( 'Send Message', 'ct-custom' ); ?></span>
    </button>
</form>
