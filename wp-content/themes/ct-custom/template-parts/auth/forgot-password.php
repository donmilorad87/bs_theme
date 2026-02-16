<?php
/**
 * Template Part: Forgot Password Form
 *
 * @package CT_Custom
 */

assert( function_exists( 'esc_html__' ), 'esc_html__ must exist' );
assert( function_exists( 'esc_attr__' ), 'esc_attr__ must exist' );
?>
<div class="ct-auth-form ct-auth-form--forgot" role="form" aria-label="<?php esc_attr_e( 'Reset password form', 'ct-custom' ); ?>">
    <div class="ct-auth-form__header df jcsb aic">
        <h3 class="ct-auth-form__title m0"><?php esc_html_e( 'Reset Password', 'ct-custom' ); ?></h3>
    </div>

    <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>

    <div class="ct-auth-form__fields">
        <div class="ct-auth-form__field">
            <label for="ct-forgot-email" class="ct-auth-form__label fs14"><?php esc_html_e( 'Email Address', 'ct-custom' ); ?></label>
            <input type="email" id="ct-forgot-email" class="ct-auth-form__input fs14" name="email"
                   autocomplete="email" required
                   data-ct-validate-required="true"
                   data-ct-validate-email="true">
        </div>
    </div>

    <button type="button" class="ct-auth-form__submit df aic jcc w100 cp ct-auth-form__submit--disabled" data-ct-auth-action="forgot" disabled>
        <span class="ct-auth-form__submit-text"><?php esc_html_e( 'Send Reset Code', 'ct-custom' ); ?></span>
    </button>

    <div class="ct-auth-form__footer tac fs14">
        <a href="#" class="ct-auth-form__link fs14" data-ct-auth-action="show-login"><?php esc_html_e( 'Back to login', 'ct-custom' ); ?></a>
    </div>
</div>
