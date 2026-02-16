<?php
/**
 * Template Part: User Profile Form
 *
 * @package BS_Custom
 */

assert( function_exists( 'esc_html__' ), 'esc_html__ must exist' );
assert( function_exists( 'esc_attr__' ), 'esc_attr__ must exist' );

$user       = wp_get_current_user();
$avatar_url = $user ? get_avatar_url( $user->ID, array( 'size' => 150 ) ) : '';
$first_name = $user ? esc_attr( $user->first_name ) : '';
$last_name  = $user ? esc_attr( $user->last_name ) : '';
$user_email = $user ? esc_attr( $user->user_email ) : '';
$user_login = $user ? esc_attr( $user->user_login ) : '';
?>
<div class="ct-auth-form ct-auth-form--profile" role="form" aria-label="<?php esc_attr_e( 'User profile', 'ct-custom' ); ?>">
    <div class="ct-auth-form__header df jcsb aic">
        <h3 class="ct-auth-form__title m0"><?php esc_html_e( 'My Account', 'ct-custom' ); ?></h3>
    </div>

    <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>

    <!-- Tab navigation -->
    <div class="ct-auth-form__tabs df" role="tablist">
        <button type="button" class="ct-auth-form__tab ct-auth-form__tab--active fs14" data-ct-profile-tab="profile" role="tab" aria-selected="true">
            <?php esc_html_e( 'Profile', 'ct-custom' ); ?>
        </button>
        <button type="button" class="ct-auth-form__tab fs14" data-ct-profile-tab="messages" role="tab" aria-selected="false">
            <?php esc_html_e( 'Messages', 'ct-custom' ); ?>
        </button>
    </div>

    <!-- Profile tab panel -->
    <div class="ct-auth-form__tab-panel ct-auth-form__tab-panel--active" data-ct-profile-panel="profile" role="tabpanel">

        <!-- Avatar Section -->
        <div class="ct-auth-form__avatar-section df aic">
            <div class="ct-auth-form__avatar-img df aic jcc" style="<?php echo $avatar_url ? 'background-image:url(' . esc_url( $avatar_url ) . ')' : ''; ?>">
                <?php if ( ! $avatar_url ) : ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?php endif; ?>
            </div>
            <button type="button" class="ct-auth-form__avatar-upload-btn" data-ct-auth-action="upload-avatar">
                <?php esc_html_e( 'Change Photo', 'ct-custom' ); ?>
            </button>
            <input type="file" class="ct-auth-form__avatar-file-input" accept="image/jpeg,image/png,image/gif,image/webp" aria-hidden="true" style="display:none">
        </div>

        <!-- Profile Info -->
        <div class="ct-auth-form__fields">
            <div class="ct-auth-form__field">
                <label class="ct-auth-form__label fs14"><?php esc_html_e( 'Email', 'ct-custom' ); ?></label>
                <input type="email" class="ct-auth-form__input ct-auth-form__input--readonly fs14" value="<?php echo $user_email; ?>" readonly>
            </div>
            <div class="ct-auth-form__field">
                <label class="ct-auth-form__label fs14"><?php esc_html_e( 'Username', 'ct-custom' ); ?></label>
                <input type="text" class="ct-auth-form__input ct-auth-form__input--readonly fs14" value="<?php echo $user_login; ?>" readonly>
            </div>
            <div class="ct-auth-form__name-row df">
                <div class="ct-auth-form__field">
                    <label for="ct-profile-first" class="ct-auth-form__label fs14"><?php esc_html_e( 'First Name', 'ct-custom' ); ?></label>
                    <input type="text" id="ct-profile-first" class="ct-auth-form__input fs14" name="first_name" value="<?php echo $first_name; ?>"
                           data-ct-validate-required="true">
                </div>
                <div class="ct-auth-form__field">
                    <label for="ct-profile-last" class="ct-auth-form__label fs14"><?php esc_html_e( 'Last Name', 'ct-custom' ); ?></label>
                    <input type="text" id="ct-profile-last" class="ct-auth-form__input fs14" name="last_name" value="<?php echo $last_name; ?>"
                           data-ct-validate-required="true">
                </div>
            </div>
        </div>

        <button type="button" class="ct-auth-form__submit df aic jcc w100 cp" data-ct-auth-action="save-profile">
            <span class="ct-auth-form__submit-text"><?php esc_html_e( 'Save Profile', 'ct-custom' ); ?></span>
        </button>

        <!-- Change Password Section -->
        <hr class="ct-auth-form__divider">
        <div class="ct-auth-form__change-password-section" data-ct-password-section>
        <h4 class="ct-auth-form__section-title"><?php esc_html_e( 'Change Password', 'ct-custom' ); ?></h4>

        <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>

        <div class="ct-auth-form__fields">
            <div class="ct-auth-form__field">
                <label for="ct-profile-current-pass" class="ct-auth-form__label fs14"><?php esc_html_e( 'Current Password', 'ct-custom' ); ?></label>
                <div class="ct-auth-form__password-wrap">
                    <input type="password" id="ct-profile-current-pass" class="ct-auth-form__input fs14" name="current_password"
                           autocomplete="current-password"
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
                <label for="ct-profile-new-pass" class="ct-auth-form__label fs14"><?php esc_html_e( 'New Password', 'ct-custom' ); ?></label>
                <div class="ct-auth-form__password-wrap">
                    <input type="password" id="ct-profile-new-pass" class="ct-auth-form__input fs14" name="new_password"
                           autocomplete="new-password"
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
                    <div class="ct-auth-form__rule df aic" data-rule="different" data-rule-compare="current_password">
                        <span class="ct-auth-form__rule-default"><?php esc_html_e( 'Must be different from current password', 'ct-custom' ); ?></span>
                        <span class="ct-auth-form__rule-info"><?php esc_html_e( 'This is your current password — no need to change it', 'ct-custom' ); ?></span>
                    </div>
                </div>
            </div>
            <div class="ct-auth-form__field">
                <label for="ct-profile-confirm-pass" class="ct-auth-form__label fs14"><?php esc_html_e( 'Confirm New Password', 'ct-custom' ); ?></label>
                <div class="ct-auth-form__password-wrap">
                    <input type="password" id="ct-profile-confirm-pass" class="ct-auth-form__input fs14" name="new_password_confirm"
                           autocomplete="new-password"
                           data-ct-validate-match="new_password">
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

        <button type="button" class="ct-auth-form__submit df aic jcc w100 cp ct-auth-form__submit--secondary ct-auth-form__submit--disabled" data-ct-auth-action="change-password" disabled>
            <span class="ct-auth-form__submit-text"><?php esc_html_e( 'Change Password', 'ct-custom' ); ?></span>
        </button>
        </div>

    </div>

    <!-- Messages tab panel -->
    <div class="ct-auth-form__tab-panel" data-ct-profile-panel="messages" role="tabpanel" style="display:none;">
        <div class="ct-auth-form__messages-history" id="ct_profile_messages">
            <p class="ct-auth-form__loading fs14"><?php esc_html_e( 'Loading messages...', 'ct-custom' ); ?></p>
        </div>
    </div>
</div>
