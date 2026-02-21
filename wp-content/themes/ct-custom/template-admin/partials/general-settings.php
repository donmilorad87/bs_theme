<?php
/**
 * General settings admin partial.
 *
 * @package BS_Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bs_back_to_top_enabled = get_theme_mod( 'bs_back_to_top_enabled', true );
$bs_topbar_enabled      = get_theme_mod( 'bs_topbar_enabled', true );
$bs_theme_toggle_enabled  = get_theme_mod( 'bs_theme_toggle_enabled', true );
$bs_theme_color_mode      = get_theme_mod( 'bs_theme_color_mode', 'light' );
$bs_theme_color_mode      = ( 'dark' === $bs_theme_color_mode ) ? 'dark' : 'light';
$bs_theme_toggle_position = get_theme_mod( 'bs_theme_toggle_position', 'header' );
$bs_theme_toggle_position = is_string( $bs_theme_toggle_position ) ? $bs_theme_toggle_position : 'header';
$bs_lang_switcher_position = get_theme_mod( 'bs_lang_switcher_position', 'top_header' );
$bs_lang_switcher_position = is_string( $bs_lang_switcher_position ) ? $bs_lang_switcher_position : 'top_header';
$bs_languages_enabled = get_theme_mod( 'bs_languages_enabled', true );
if ( is_string( $bs_languages_enabled ) ) {
    $normalized = strtolower( $bs_languages_enabled );
    $bs_languages_enabled = ! ( '0' === $bs_languages_enabled || 'off' === $normalized || 'false' === $normalized );
}
$bs_user_management_enabled = get_theme_mod( 'bs_user_management_enabled', true );
$bs_auth_links_position      = get_theme_mod( 'bs_auth_links_position', 'top_header' );
$bs_auth_links_position      = is_string( $bs_auth_links_position ) ? $bs_auth_links_position : 'top_header';
$bs_email_enabled = get_option( 'bs_email_enabled', 'on' );
if ( is_string( $bs_email_enabled ) ) {
    $normalized = strtolower( $bs_email_enabled );
    $bs_email_enabled = ! ( '0' === $bs_email_enabled || 'off' === $normalized || 'false' === $normalized );
}
$bs_contact_throttle_limit = absint( get_option( 'bs_contact_throttle_limit', 5 ) );
if ( $bs_contact_throttle_limit < 1 ) {
    $bs_contact_throttle_limit = 5;
}
$bs_contact_throttle_window = absint( get_option( 'bs_contact_throttle_window', 1 ) );
if ( $bs_contact_throttle_window < 1 ) {
    $bs_contact_throttle_window = 1;
}
?>

<div class="ct-admin-section ct-general-section"
     data-nonce="<?php echo esc_attr( wp_create_nonce( 'admin_save_general_settings_nonce' ) ); ?>"
     data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">

    <h2><?php esc_html_e( 'General', 'ct-custom' ); ?></h2>

    <form id="bs_general_form" class="ct-seo-form">
        <div class="ct-seo-field ct-seo-field--toggle">
            <label><?php esc_html_e( 'Enable Top Bar', 'ct-custom' ); ?></label>
            <label class="ct-seo-toggle">
                <input type="checkbox" id="bs_topbar_enabled" class="ct-seo-toggle__input" <?php checked( $bs_topbar_enabled ); ?>>
                <span class="ct-seo-toggle__slider"></span>
            </label>
        </div>
        <div class="ct-seo-field ct-seo-field--toggle">
            <label><?php esc_html_e( 'Enable Dark/Light Toggle', 'ct-custom' ); ?></label>
            <label class="ct-seo-toggle">
                <input type="checkbox" id="bs_theme_toggle_enabled" class="ct-seo-toggle__input" <?php checked( $bs_theme_toggle_enabled ); ?>>
                <span class="ct-seo-toggle__slider"></span>
            </label>
        </div>
        <div class="ct-seo-field">
            <label><?php esc_html_e( 'Theme Mode', 'ct-custom' ); ?></label>
            <div class="ct-general-theme-mode">
                <label class="ct-general-theme-mode__option">
                    <input type="radio" name="bs_theme_color_mode" value="light" <?php checked( 'light', $bs_theme_color_mode ); ?>>
                    <span><?php esc_html_e( 'Light', 'ct-custom' ); ?></span>
                </label>
                <label class="ct-general-theme-mode__option">
                    <input type="radio" name="bs_theme_color_mode" value="dark" <?php checked( 'dark', $bs_theme_color_mode ); ?>>
                    <span><?php esc_html_e( 'Dark', 'ct-custom' ); ?></span>
                </label>
            </div>
            <p class="ct-seo-field__hint"><?php esc_html_e( 'Used when the toggle is disabled.', 'ct-custom' ); ?></p>
        </div>
        <div class="ct-seo-field">
            <label for="bs_theme_toggle_position"><?php esc_html_e( 'Toggle Position', 'ct-custom' ); ?></label>
            <select id="bs_theme_toggle_position">
                <option value="top_header" <?php selected( $bs_theme_toggle_position, 'top_header' ); ?>><?php esc_html_e( 'Top Header', 'ct-custom' ); ?></option>
                <option value="header" <?php selected( $bs_theme_toggle_position, 'header' ); ?>><?php esc_html_e( 'Header', 'ct-custom' ); ?></option>
                <option value="footer" <?php selected( $bs_theme_toggle_position, 'footer' ); ?>><?php esc_html_e( 'Top Footer', 'ct-custom' ); ?></option>
                <option value="bottom_footer" <?php selected( $bs_theme_toggle_position, 'bottom_footer' ); ?>><?php esc_html_e( 'Bottom Footer', 'ct-custom' ); ?></option>
                <option value="floating_top_left" <?php selected( $bs_theme_toggle_position, 'floating_top_left' ); ?>><?php esc_html_e( 'Floating Top Left', 'ct-custom' ); ?></option>
                <option value="floating_top_right" <?php selected( $bs_theme_toggle_position, 'floating_top_right' ); ?>><?php esc_html_e( 'Floating Top Right', 'ct-custom' ); ?></option>
                <option value="floating_bottom_right" <?php selected( $bs_theme_toggle_position, 'floating_bottom_right' ); ?>><?php esc_html_e( 'Floating Bottom Right', 'ct-custom' ); ?></option>
                <option value="floating_bottom_left" <?php selected( $bs_theme_toggle_position, 'floating_bottom_left' ); ?>><?php esc_html_e( 'Floating Bottom Left', 'ct-custom' ); ?></option>
            </select>
        </div>
        <div class="ct-seo-field ct-seo-field--toggle">
            <label><?php esc_html_e( 'Enable Languages', 'ct-custom' ); ?></label>
            <label class="ct-seo-toggle">
                <input type="checkbox" id="bs_languages_enabled" class="ct-seo-toggle__input" <?php checked( $bs_languages_enabled ); ?>>
                <span class="ct-seo-toggle__slider"></span>
            </label>
        </div>
        <?php if ( function_exists( 'bs_is_multilingual' ) && bs_is_multilingual() ) : ?>
            <div class="ct-seo-field">
                <label for="bs_lang_switcher_position"><?php esc_html_e( 'Language Switcher Position', 'ct-custom' ); ?></label>
                <select id="bs_lang_switcher_position">
                    <option value="top_header" <?php selected( $bs_lang_switcher_position, 'top_header' ); ?>><?php esc_html_e( 'Top Header', 'ct-custom' ); ?></option>
                    <option value="header" <?php selected( $bs_lang_switcher_position, 'header' ); ?>><?php esc_html_e( 'Header', 'ct-custom' ); ?></option>
                    <option value="footer" <?php selected( $bs_lang_switcher_position, 'footer' ); ?>><?php esc_html_e( 'Top Footer', 'ct-custom' ); ?></option>
                    <option value="bottom_footer" <?php selected( $bs_lang_switcher_position, 'bottom_footer' ); ?>><?php esc_html_e( 'Bottom Footer', 'ct-custom' ); ?></option>
                    <option value="floating_top_left" <?php selected( $bs_lang_switcher_position, 'floating_top_left' ); ?>><?php esc_html_e( 'Floating Top Left', 'ct-custom' ); ?></option>
                    <option value="floating_top_right" <?php selected( $bs_lang_switcher_position, 'floating_top_right' ); ?>><?php esc_html_e( 'Floating Top Right', 'ct-custom' ); ?></option>
                    <option value="floating_bottom_right" <?php selected( $bs_lang_switcher_position, 'floating_bottom_right' ); ?>><?php esc_html_e( 'Floating Bottom Right', 'ct-custom' ); ?></option>
                    <option value="floating_bottom_left" <?php selected( $bs_lang_switcher_position, 'floating_bottom_left' ); ?>><?php esc_html_e( 'Floating Bottom Left', 'ct-custom' ); ?></option>
                </select>
            </div>
        <?php endif; ?>
        <div class="ct-seo-field ct-seo-field--toggle">
            <label><?php esc_html_e( 'Enable Back to Top', 'ct-custom' ); ?></label>
            <label class="ct-seo-toggle">
                <input type="checkbox" id="bs_back_to_top_enabled" class="ct-seo-toggle__input" <?php checked( $bs_back_to_top_enabled ); ?>>
                <span class="ct-seo-toggle__slider"></span>
            </label>
        </div>
        <div class="ct-seo-field ct-seo-field--toggle">
            <label><?php esc_html_e( 'Enable Login / Registration', 'ct-custom' ); ?></label>
            <label class="ct-seo-toggle">
                <input type="checkbox" id="bs_user_management_enabled" class="ct-seo-toggle__input" <?php checked( $bs_user_management_enabled ); ?>>
                <span class="ct-seo-toggle__slider"></span>
            </label>
        </div>
        <div class="ct-seo-field ct-seo-field--toggle">
            <label><?php esc_html_e( 'Enable Email', 'ct-custom' ); ?></label>
            <label class="ct-seo-toggle">
                <input type="checkbox" id="bs_email_enabled" class="ct-seo-toggle__input" <?php checked( $bs_email_enabled ); ?>>
                <span class="ct-seo-toggle__slider"></span>
            </label>
        </div>
        <div class="ct-seo-field">
            <label for="bs_contact_throttle_limit"><?php esc_html_e( 'Throttle Limit', 'ct-custom' ); ?></label>
            <input type="number" id="bs_contact_throttle_limit" class="ct-admin-input" min="1" max="200" value="<?php echo esc_attr( $bs_contact_throttle_limit ); ?>">
            <p class="ct-seo-field__hint"><?php esc_html_e( 'Max submissions per window.', 'ct-custom' ); ?></p>
        </div>
        <div class="ct-seo-field">
            <label for="bs_contact_throttle_window"><?php esc_html_e( 'Throttle Window (minutes)', 'ct-custom' ); ?></label>
            <input type="number" id="bs_contact_throttle_window" class="ct-admin-input" min="1" max="1440" value="<?php echo esc_attr( $bs_contact_throttle_window ); ?>">
            <p class="ct-seo-field__hint"><?php esc_html_e( 'Default is 1 minute.', 'ct-custom' ); ?></p>
        </div>
        <div class="ct-seo-field">
            <label for="bs_auth_links_position"><?php esc_html_e( 'Auth Links Position', 'ct-custom' ); ?></label>
            <select id="bs_auth_links_position">
                <option value="top_header" <?php selected( $bs_auth_links_position, 'top_header' ); ?>><?php esc_html_e( 'Top Header', 'ct-custom' ); ?></option>
                <option value="header" <?php selected( $bs_auth_links_position, 'header' ); ?>><?php esc_html_e( 'Header', 'ct-custom' ); ?></option>
                <option value="footer" <?php selected( $bs_auth_links_position, 'footer' ); ?>><?php esc_html_e( 'Top Footer', 'ct-custom' ); ?></option>
                <option value="bottom_footer" <?php selected( $bs_auth_links_position, 'bottom_footer' ); ?>><?php esc_html_e( 'Bottom Footer', 'ct-custom' ); ?></option>
                <option value="floating_top_left" <?php selected( $bs_auth_links_position, 'floating_top_left' ); ?>><?php esc_html_e( 'Floating Top Left', 'ct-custom' ); ?></option>
                <option value="floating_top_right" <?php selected( $bs_auth_links_position, 'floating_top_right' ); ?>><?php esc_html_e( 'Floating Top Right', 'ct-custom' ); ?></option>
                <option value="floating_bottom_right" <?php selected( $bs_auth_links_position, 'floating_bottom_right' ); ?>><?php esc_html_e( 'Floating Bottom Right', 'ct-custom' ); ?></option>
                <option value="floating_bottom_left" <?php selected( $bs_auth_links_position, 'floating_bottom_left' ); ?>><?php esc_html_e( 'Floating Bottom Left', 'ct-custom' ); ?></option>
            </select>
        </div>
        <span class="ct-seo-form__status" id="bs_general_status"></span>
    </form>

</div>
