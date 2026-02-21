<?php
/**
 * Template Part: Registration Form
 *
 * @package BS_Custom
 */

assert( function_exists( 'esc_html__' ), 'esc_html__ must exist' );
assert( function_exists( 'esc_attr__' ), 'esc_attr__ must exist' );
?>
<div class="ct-auth-form ct-auth-form--register" role="form" aria-label="<?php esc_attr_e( 'Registration form', 'ct-custom' ); ?>">
    <div class="ct-auth-form__header df jcsb aic">
        <h3 class="ct-auth-form__title m0"><?php esc_html_e( 'Create Account', 'ct-custom' ); ?></h3>
    </div>

    <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>

    <div class="ct-auth-form__fields">
        <div class="ct-auth-form__field">
            <label for="ct-reg-user" class="ct-auth-form__label fs14"><?php esc_html_e( 'Username', 'ct-custom' ); ?><span class="ct-auth-form__required" aria-hidden="true">*</span></label>
            <input type="text" id="ct-reg-user" class="ct-auth-form__input fs14" name="username"
                   autocomplete="username" required
                   data-ct-validate-required="true"
                   data-ct-validate-username="true">
            <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                <div class="ct-auth-form__rule df aic" data-rule="username-min"><?php esc_html_e( 'At least 4 characters', 'ct-custom' ); ?></div>
                <div class="ct-auth-form__rule df aic" data-rule="username-chars"><?php esc_html_e( 'Only letters, numbers, -, _, .', 'ct-custom' ); ?></div>
                <div class="ct-auth-form__rule df aic" data-rule="username-special"><?php esc_html_e( 'Max 2 special characters (-, _, .)', 'ct-custom' ); ?></div>
            </div>
        </div>
        <div class="ct-auth-form__field">
            <label for="ct-reg-email" class="ct-auth-form__label fs14"><?php esc_html_e( 'Email', 'ct-custom' ); ?><span class="ct-auth-form__required" aria-hidden="true">*</span></label>
            <input type="email" id="ct-reg-email" class="ct-auth-form__input fs14" name="email"
                   autocomplete="email" required
                   data-ct-validate-required="true"
                   data-ct-validate-email="true">
            <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                <div class="ct-auth-form__rule df aic" data-rule="email-format"><?php esc_html_e( 'Please enter a valid email address', 'ct-custom' ); ?></div>
            </div>
        </div>
        <div class="ct-auth-form__name-row df">
            <div class="ct-auth-form__field">
                <label for="ct-reg-first" class="ct-auth-form__label fs14"><?php esc_html_e( 'First Name', 'ct-custom' ); ?><span class="ct-auth-form__required" aria-hidden="true">*</span></label>
                <input type="text" id="ct-reg-first" class="ct-auth-form__input fs14" name="first_name"
                       autocomplete="given-name" required
                       data-ct-validate-required="true">
                <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                    <div class="ct-auth-form__rule df aic" data-rule="required"><?php esc_html_e( 'This field is required', 'ct-custom' ); ?></div>
                </div>
            </div>
            <div class="ct-auth-form__field">
                <label for="ct-reg-last" class="ct-auth-form__label fs14"><?php esc_html_e( 'Last Name', 'ct-custom' ); ?><span class="ct-auth-form__required" aria-hidden="true">*</span></label>
                <input type="text" id="ct-reg-last" class="ct-auth-form__input fs14" name="last_name"
                       autocomplete="family-name" required
                       data-ct-validate-required="true">
                <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                    <div class="ct-auth-form__rule df aic" data-rule="required"><?php esc_html_e( 'This field is required', 'ct-custom' ); ?></div>
                </div>
            </div>
        </div>
        <div class="ct-auth-form__field">
            <label for="ct-reg-pass" class="ct-auth-form__label fs14"><?php esc_html_e( 'Password', 'ct-custom' ); ?><span class="ct-auth-form__required" aria-hidden="true">*</span></label>
            <div class="ct-auth-form__password-wrap">
                <input type="password" id="ct-reg-pass" class="ct-auth-form__input fs14" name="password"
                       autocomplete="new-password" required
                       data-ct-validate-required="true"
                       data-ct-validate-password="true">
                <button type="button" class="ct-auth-form__password-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'ct-custom' ); ?>">
                    <svg class="ct-auth-form__eye-show" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="ct-auth-form__eye-hide" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
            <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                <div class="ct-auth-form__rule df aic" data-rule="min-length"><?php esc_html_e( 'At least 8 characters', 'ct-custom' ); ?></div>
                <div class="ct-auth-form__rule df aic" data-rule="lowercase"><?php esc_html_e( 'One lowercase letter', 'ct-custom' ); ?></div>
                <div class="ct-auth-form__rule df aic" data-rule="uppercase"><?php esc_html_e( 'One uppercase letter', 'ct-custom' ); ?></div>
                <div class="ct-auth-form__rule df aic" data-rule="digit"><?php esc_html_e( 'One digit', 'ct-custom' ); ?></div>
                <div class="ct-auth-form__rule df aic" data-rule="special"><?php esc_html_e( 'One special character', 'ct-custom' ); ?></div>
            </div>
        </div>
        <div class="ct-auth-form__field">
            <label for="ct-reg-pass-confirm" class="ct-auth-form__label fs14"><?php esc_html_e( 'Confirm Password', 'ct-custom' ); ?><span class="ct-auth-form__required" aria-hidden="true">*</span></label>
            <div class="ct-auth-form__password-wrap">
                <input type="password" id="ct-reg-pass-confirm" class="ct-auth-form__input fs14" name="password_confirm"
                       autocomplete="new-password" required
                       data-ct-validate-required="true"
                       data-ct-validate-match="password">
                <button type="button" class="ct-auth-form__password-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'ct-custom' ); ?>">
                    <svg class="ct-auth-form__eye-show" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="ct-auth-form__eye-hide" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
            <div class="ct-auth-form__match-hint ct-auth-form__match-hint--hidden" aria-live="polite">
                <div class="ct-auth-form__rule ct-auth-form__match-hint-pass df aic"><?php esc_html_e( 'Passwords match', 'ct-custom' ); ?></div>
                <div class="ct-auth-form__rule ct-auth-form__match-hint-fail df aic"><?php esc_html_e( 'Passwords do not match', 'ct-custom' ); ?></div>
            </div>
        </div>
    </div>

    <div class="ct-auth-form__agree">
        <label class="ct-auth-form__agree-label df aic fs14">
            <input type="checkbox" class="ct-auth-form__agree-checkbox"
                   data-ct-validate-required="true"
                   data-ct-validate-agree="true">
            <span><?php
                printf(
                    /* translators: %s: opening and closing <a> tag for terms link */
                    esc_html__( 'I agree to the %1$sTerms and Conditions%2$s', 'ct-custom' ),
                    '<a href="' . esc_url( bs_get_page_url_by_slug( 'terms-and-conditions' ) ) . '" target="_blank" class="ct-auth-form__link">',
                    '</a>'
                );
            ?></span>
        </label>
        <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
            <div class="ct-auth-form__rule df aic" data-rule="agree-required"><?php esc_html_e( 'You must agree to the Terms and Conditions', 'ct-custom' ); ?></div>
        </div>
    </div>

    <button type="button" class="ct-auth-form__submit df aic jcc w100 cp ct-auth-form__submit--disabled" data-ct-auth-action="register" disabled>
        <span class="ct-auth-form__submit-text"><?php esc_html_e( 'Create Account', 'ct-custom' ); ?></span>
    </button>

</div>
