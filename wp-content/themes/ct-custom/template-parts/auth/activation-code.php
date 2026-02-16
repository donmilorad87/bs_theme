<?php
/**
 * Template Part: Activation Code Form
 *
 * @package CT_Custom
 */

assert( function_exists( 'esc_html__' ), 'esc_html__ must exist' );
assert( function_exists( 'esc_attr__' ), 'esc_attr__ must exist' );
?>
<div class="ct-auth-form ct-auth-form--activation" role="form" aria-label="<?php esc_attr_e( 'Account activation form', 'ct-custom' ); ?>">
    <div class="ct-auth-form__header df jcsb aic">
        <h3 class="ct-auth-form__title m0"><?php esc_html_e( 'Activate Account', 'ct-custom' ); ?></h3>
    </div>

    <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>

    <p class="ct-auth-form__description"><?php esc_html_e( 'Enter the 6-digit code sent to your email.', 'ct-custom' ); ?></p>

    <div class="ct-auth-form__fields">
        <input type="hidden" name="email" value="">
        <div class="ct-auth-form__field">
            <label for="ct-activation-code" class="ct-auth-form__label fs14"><?php esc_html_e( 'Activation Code', 'ct-custom' ); ?></label>
            <input type="text" id="ct-activation-code" class="ct-auth-form__input ct-auth-form__code-input" name="code"
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required
                   data-ct-validate-required="true"
                   data-ct-validate-code="true"
                   placeholder="000000">
        </div>
    </div>

    <button type="button" class="ct-auth-form__submit df aic jcc w100 cp" data-ct-auth-action="verify-activation">
        <span class="ct-auth-form__submit-text"><?php esc_html_e( 'Verify Code', 'ct-custom' ); ?></span>
    </button>

    <div class="ct-auth-form__footer tac fs14">
        <a href="#" class="ct-auth-form__link fs14" data-ct-auth-action="resend-activation-code"><?php esc_html_e( 'Resend code', 'ct-custom' ); ?></a>
        <span class="ct-auth-form__footer-separator">&middot;</span>
        <a href="#" class="ct-auth-form__link fs14" data-ct-auth-action="show-login"><?php esc_html_e( 'Back to login', 'ct-custom' ); ?></a>
    </div>
</div>
